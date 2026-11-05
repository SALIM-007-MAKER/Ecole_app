<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use Core\Tenant\TenantContext;
use PDO;

/** Saisie invalide, classe ou matière introuvable (dans l'établissement courant), règle non respectée. */
class ProgrammeException extends \InvalidArgumentException {}

/** Le programme de la classe est en lecture seule : des bulletins publiés ou archivés existent pour elle. */
final class ProgrammeVerrouilleException extends ProgrammeException {}

/** Des notes existent déjà : la modification est possible mais doit être confirmée explicitement. */
final class ProgrammeConfirmationException extends ProgrammeException {}

/**
 * Programme pédagogique d'une classe (table classe_matieres) : matières, coefficient et volume horaire PROPRES À LA CLASSE.
 *
 * Règles (validées) :
 *  - le programme est INDÉPENDANT des enseignements : une matière peut y figurer sans enseignant ;
 *  - sans ligne de programme, rien ne change : matieres.coefficient / volume_horaire restent les valeurs par défaut ;
 *  - coefficient de 0 à 20 (une décimale, 0 = suivie mais non comptée), volume horaire entier de 0 à 40 h/semaine ;
 *  - la matière de Conduite (marqueur est_conduite) n'entre pas dans un programme : ses règles propres sont inchangées ;
 *  - VERROU : dès qu'un bulletin publié ou archivé existe pour la classe, le programme est en lecture seule (les classements et analyses
 *    sont calculés en direct : les modifier ferait diverger l'affichage des bulletins figés) ;
 *  - CONFIRMATION : modifier ou retirer une matière dont des notes existent déjà exige une confirmation explicite ;
 *  - AUCUN recalcul : rien ici ne régénère un bulletin ni ne touche une note ou une moyenne ;
 *  - isolation : classe et matière doivent appartenir à l'établissement courant (jamais d'accès à un autre établissement) ;
 *  - chaque changement est journalisé (avant / après).
 *
 * Avant la migration T075 (table absente), {@see disponible()} est faux et la fonctionnalité est inerte.
 */
final class ProgrammeClasseService
{
    public const COEFFICIENT_MAX = 20.0;
    public const VOLUME_MAX = 40;

    private static ?bool $disponible = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly int $etablissementId,
        private readonly ?int $userId = null,
    ) {}

    /** Service de l'établissement courant (même résolution que les modèles : contexte tenant, sinon établissement par défaut). */
    public static function pour(?int $userId = null, ?int $etablissementId = null): self
    {
        $id = $etablissementId
            ?? TenantContext::current()
            ?? (int)((require ROOT_PATH . '/config/tenant.php')['default_id'] ?? 1);
        return new self(Database::getInstance()->getConnection(), $id, $userId);
    }

    /** La table classe_matieres existe-t-elle (migration T075 appliquée) ? */
    public static function disponible(): bool
    {
        if (self::$disponible === null) {
            $pdo = Database::getInstance()->getConnection();
            $s = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classe_matieres'");
            self::$disponible = (int)$s->fetchColumn() > 0;
        }
        return self::$disponible;
    }

    /** Pour les tests : oublie la détection mémorisée (changement de base). */
    public static function oublierDetection(): void
    {
        self::$disponible = null;
    }

    // ─── Lecture ─────────────────────────────────────────────────────────────────────────────────────────────────────────

    /** Classe de l'établissement courant, ou null (inexistante ou d'un autre établissement). @return array<string,mixed>|null */
    public function classe(int $classeId): ?array
    {
        $serie = \App\Services\SeriesLyceeService::disponible() ? ', serie' : ', NULL AS serie';
        $s = $this->pdo->prepare("SELECT id, etablissement_id, nom, niveau{$serie}, annee_scolaire FROM classes WHERE id = ? AND etablissement_id = ?");
        $s->execute([$classeId, $this->etablissementId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Programme de la classe : une ligne par matière, avec les valeurs par défaut de la matière pour comparaison.
     *
     * @return list<array<string,mixed>>
     */
    public function programme(int $classeId): array
    {
        $this->classeOuErreur($classeId);
        $actifSel = ProgrammeSql::colonneMatiere('actif') ? 'm.actif' : '1';
        $s = $this->pdo->prepare(
            "SELECT cm.matiere_id, m.nom, " . ProgrammeSql::nom('m') . " AS nom_affiche, " . $actifSel . " AS matiere_active, m.categorie, cm.coefficient, cm.volume_horaire, cm.obligatoire, cm.ordre,
                    m.coefficient AS coefficient_defaut, m.volume_horaire AS volume_defaut,
                    (SELECT COUNT(*) FROM enseignements en WHERE en.classe_id = cm.classe_id AND en.matiere_id = cm.matiere_id) AS nb_enseignements
             FROM classe_matieres cm
             JOIN matieres m ON m.id = cm.matiere_id AND m.etablissement_id = cm.etablissement_id
             WHERE cm.classe_id = ? AND cm.etablissement_id = ?
             ORDER BY cm.ordre, nom_affiche"
        );
        $s->execute([$classeId, $this->etablissementId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Matières de l'établissement pouvant être ajoutées au programme (hors Conduite et hors matières déjà présentes). @return list<array<string,mixed>> */
    public function ajoutables(int $classeId): array
    {
        $this->classeOuErreur($classeId);
        $s = $this->pdo->prepare(
            "SELECT m.id, m.nom, " . ProgrammeSql::nom('m') . " AS nom_affiche, m.coefficient, m.volume_horaire
             FROM matieres m
             WHERE m.etablissement_id = ? AND m.est_conduite IS NULL" . $this->filtreActives() . "
               AND NOT EXISTS (SELECT 1 FROM classe_matieres cm WHERE cm.classe_id = ? AND cm.matiere_id = m.id)
             ORDER BY nom_affiche"
        );
        $s->execute([$this->etablissementId, $classeId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Raison du verrouillage (bulletin publié ou archivé pour la classe), ou null si le programme est modifiable. */
    public function verrou(int $classeId): ?string
    {
        $this->classeOuErreur($classeId);
        $s = $this->pdo->prepare("SELECT COUNT(*) FROM bulletins_v2 WHERE classe_id = ? AND statut IN ('publie', 'archive')");
        $s->execute([$classeId]);
        $n = (int)$s->fetchColumn();
        return $n > 0
            ? "Programme en lecture seule : {$n} bulletin(s) publié(s) ou archivé(s) existent pour cette classe. Le modifier ferait diverger les classements des bulletins figés."
            : null;
    }

    /** Des notes existent-elles pour cette classe (et cette matière si précisée) ? */
    public function notesExistent(int $classeId, ?int $matiereId = null): bool
    {
        $this->classeOuErreur($classeId);
        $sql = "SELECT 1 FROM evaluations e JOIN notes_v2 n ON n.evaluation_id = e.id WHERE e.classe_id = ?";
        $params = [$classeId];
        if ($matiereId !== null) {
            $sql .= ' AND e.matiere_id = ?';
            $params[] = $matiereId;
        }
        $s = $this->pdo->prepare($sql . ' LIMIT 1');
        $s->execute($params);
        return $s->fetchColumn() !== false;
    }

    /**
     * Classes parallèles : même établissement, même niveau, même série (NULL = sans série) et même année scolaire.
     *
     * @return list<array<string,mixed>> id, nom, nb_lignes, verrou
     */
    public function paralleles(int $classeId): array
    {
        $c = $this->classeOuErreur($classeId);
        $eg = \App\Services\SeriesLyceeService::disponible() ? ' AND c.serie <=> ?' : '';
        $params = [$this->etablissementId, $c['niveau'], $c['annee_scolaire'], $classeId];
        if ($eg !== '') { array_splice($params, 3, 0, [$c['serie']]); }
        $s = $this->pdo->prepare(
            "SELECT c.id, c.nom,
                    (SELECT COUNT(*) FROM classe_matieres cm WHERE cm.classe_id = c.id) AS nb_lignes,
                    (SELECT COUNT(*) FROM bulletins_v2 b WHERE b.classe_id = c.id AND b.statut IN ('publie', 'archive')) AS nb_bulletins_figes
             FROM classes c
             WHERE c.etablissement_id = ? AND c.niveau = ? AND c.annee_scolaire = ?{$eg} AND c.id <> ?
             ORDER BY c.nom"
        );
        $s->execute($params);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Écriture ────────────────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Ajoute une matière au programme. Coefficient et volume : valeurs par défaut de la matière si non précisés.
     *
     * @param mixed $coefficient null = défaut de la matière
     * @param mixed $volume      null = défaut de la matière
     */
    public function ajouter(int $classeId, int $matiereId, mixed $coefficient = null, mixed $volume = null, int $ordre = 0, bool $obligatoire = true): void
    {
        $this->modifiable($classeId);
        $m = $this->matiereOuErreur($matiereId, true);
        if ($this->ligne($classeId, $matiereId) !== null) {
            throw new ProgrammeException('Cette matière est déjà au programme de la classe.');
        }
        $coef = $coefficient === null || $coefficient === '' ? (float)$m['coefficient'] : self::coefficientValide($coefficient);
        $vol  = $volume === null || $volume === '' ? (int)$m['volume_horaire'] : self::volumeValide($volume);
        $coef = self::coefficientValide($coef);
        $vol  = self::volumeValide($vol);
        $ordre = max(0, min(65535, $ordre));

        $this->transaction(function () use ($classeId, $matiereId, $coef, $vol, $obligatoire, $ordre, $m): void {
            $this->pdo->prepare(
                'INSERT INTO classe_matieres (etablissement_id, classe_id, matiere_id, coefficient, volume_horaire, obligatoire, ordre) VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$this->etablissementId, $classeId, $matiereId, $coef, $vol, $obligatoire ? 1 : 0, $ordre]);
            $this->journaliser('create', (int)$this->pdo->lastInsertId(), null, ['classe_id' => $classeId, 'matiere' => $m['nom'], 'coefficient' => $coef, 'volume_horaire' => $vol, 'obligatoire' => $obligatoire ? 1 : 0, 'ordre' => $ordre]);
        });

    }

    /**
     * Enregistre les valeurs de plusieurs lignes existantes en une fois (tout ou rien). Seules les lignes réellement modifiées sont écrites.
     *
     * @param array<int|string, array{coefficient?:mixed, volume_horaire?:mixed, ordre?:mixed}> $lignes indexées par matiere_id
     * @return int nombre de lignes modifiées
     * @throws ProgrammeConfirmationException des notes existent pour une matière modifiée et $confirmer est faux
     */
    public function enregistrer(int $classeId, array $lignes, bool $confirmer = false): int
    {
        $this->modifiable($classeId);
        $aEcrire = [];
        foreach ($lignes as $matiereId => $v) {
            $matiereId = (int)$matiereId;
            $courante = $this->ligne($classeId, $matiereId);
            if ($courante === null) {
                throw new ProgrammeException('Matière absente du programme de cette classe.');
            }
            $coef = array_key_exists('coefficient', $v) ? self::coefficientValide($v['coefficient']) : (float)$courante['coefficient'];
            $vol  = array_key_exists('volume_horaire', $v) ? self::volumeValide($v['volume_horaire']) : (int)$courante['volume_horaire'];
            $ordre = array_key_exists('ordre', $v) ? max(0, min(65535, (int)$v['ordre'])) : (int)$courante['ordre'];
            $oblig = array_key_exists('obligatoire', $v) ? (self::booleen($v['obligatoire']) ? 1 : 0) : (int)$courante['obligatoire'];
            if (abs($coef - (float)$courante['coefficient']) < 0.0001 && $vol === (int)$courante['volume_horaire'] && $ordre === (int)$courante['ordre'] && $oblig === (int)$courante['obligatoire']) {
                continue;
            }
            $aEcrire[$matiereId] = [$courante, $coef, $vol, $ordre, $oblig];
        }

        if (!$confirmer) {
            $avecNotes = [];
            foreach ($aEcrire as $matiereId => [$courante, $coef, $vol, $ordre, $oblig]) {
                // le coefficient et le caractère obligatoire pèsent sur les moyennes générales et les classements
                $compte = abs($coef - (float)$courante['coefficient']) >= 0.0001 || $oblig !== (int)$courante['obligatoire'];
                if ($compte && $this->notesExistent($classeId, $matiereId)) {
                    $avecNotes[] = $this->nomMatiere($matiereId);
                }
            }
            if ($avecNotes !== []) {
                throw new ProgrammeConfirmationException('Des notes existent déjà pour : ' . implode(', ', $avecNotes) . '. Modifier leur coefficient ou leur caractère obligatoire change les moyennes et classements calculés ; confirmez explicitement pour continuer.');
            }
        }

        $propre = !$this->pdo->inTransaction();
        if ($propre) { $this->pdo->beginTransaction(); }
        try {
            foreach ($aEcrire as $matiereId => [$courante, $coef, $vol, $ordre, $oblig]) {
                $this->pdo->prepare('UPDATE classe_matieres SET coefficient = ?, volume_horaire = ?, obligatoire = ?, ordre = ? WHERE id = ? AND etablissement_id = ?')
                    ->execute([$coef, $vol, $oblig, $ordre, $courante['id'], $this->etablissementId]);
                $this->journaliser('update', (int)$courante['id'],
                    ['classe_id' => $classeId, 'matiere' => $this->nomMatiere($matiereId), 'coefficient' => (float)$courante['coefficient'], 'volume_horaire' => (int)$courante['volume_horaire'], 'obligatoire' => (int)$courante['obligatoire'], 'ordre' => (int)$courante['ordre']],
                    ['classe_id' => $classeId, 'matiere' => $this->nomMatiere($matiereId), 'coefficient' => $coef, 'volume_horaire' => $vol, 'obligatoire' => $oblig, 'ordre' => $ordre, 'notes_confirmees' => $confirmer]);
            }
            if ($propre) { $this->pdo->commit(); }
        } catch (\Throwable $e) {
            if ($propre && $this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $e;
        }
        return count($aEcrire);
    }

    /** Retire une matière du programme (sans effet sur la matière, les enseignements ni les notes). */
    public function retirer(int $classeId, int $matiereId, bool $confirmer = false): void
    {
        $this->modifiable($classeId);
        $courante = $this->ligne($classeId, $matiereId);
        if ($courante === null) {
            throw new ProgrammeException('Matière absente du programme de cette classe.');
        }
        if (!$confirmer && $this->notesExistent($classeId, $matiereId)) {
            throw new ProgrammeConfirmationException('Des notes existent déjà pour ' . $this->nomMatiere($matiereId) . ' dans cette classe. La retirer du programme rend aux calculs les valeurs par défaut de la matière ; confirmez explicitement pour continuer.');
        }
        $this->transaction(function () use ($courante, $classeId, $matiereId): void {
            $this->pdo->prepare('DELETE FROM classe_matieres WHERE id = ? AND etablissement_id = ?')->execute([$courante['id'], $this->etablissementId]);
            $this->journaliser('delete', (int)$courante['id'],
                ['classe_id' => $classeId, 'matiere' => $this->nomMatiere($matiereId), 'coefficient' => (float)$courante['coefficient'], 'volume_horaire' => (int)$courante['volume_horaire'], 'obligatoire' => (int)$courante['obligatoire']], null);
        });

    }

    /**
     * Crée le programme à partir des enseignements actuels de la classe, avec les valeurs par défaut des matières. Action EXPLICITE :
     * n'écrase rien (les matières déjà au programme sont ignorées) et exclut la Conduite.
     *
     * @return int nombre de matières ajoutées
     */
    public function initialiserDepuisEnseignements(int $classeId): int
    {
        $this->modifiable($classeId);
        $s = $this->pdo->prepare(
            "SELECT DISTINCT m.id, m.nom, m.coefficient, m.volume_horaire
             FROM enseignements en
             JOIN matieres m ON m.id = en.matiere_id AND m.etablissement_id = ?
             WHERE en.classe_id = ? AND en.etablissement_id = ? AND m.est_conduite IS NULL" . $this->filtreActives() . "
               AND NOT EXISTS (SELECT 1 FROM classe_matieres cm WHERE cm.classe_id = en.classe_id AND cm.matiere_id = m.id)
             ORDER BY m.nom"
        );
        $s->execute([$this->etablissementId, $classeId, $this->etablissementId]);
        $matieres = $s->fetchAll(PDO::FETCH_ASSOC);
        if ($matieres === []) {
            return 0;
        }
        $propre = !$this->pdo->inTransaction();
        if ($propre) { $this->pdo->beginTransaction(); }
        try {
            $ins = $this->pdo->prepare('INSERT INTO classe_matieres (etablissement_id, classe_id, matiere_id, coefficient, volume_horaire, ordre) VALUES (?, ?, ?, ?, ?, 0)');
            foreach ($matieres as $m) {
                $ins->execute([$this->etablissementId, $classeId, $m['id'], $m['coefficient'], $m['volume_horaire']]);
            }
            $this->journaliser('create', null, null, ['classe_id' => $classeId, 'initialise_depuis_enseignements' => array_column($matieres, 'nom')]);
            if ($propre) { $this->pdo->commit(); }
        } catch (\Throwable $e) {
            if ($propre && $this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $e;
        }
        return count($matieres);
    }

    /**
     * Reproduit le programme d'une classe dans des classes PARALLÈLES (même établissement, niveau, série et année), jamais ailleurs.
     *
     * Mode « completer » (défaut) : ajoute les matières manquantes, ne touche à aucune ligne existante de la cible.
     * Mode « ecraser » : en plus, aligne les lignes existantes sur la source (nécessite $confirmerEcrasement) ; n'ajoute ni ne retire de matière
     * en dehors de celles de la source. Une cible verrouillée (bulletins figés), ou dont une modification touche des notes sans confirmation,
     * est IGNORÉE avec sa raison, sans bloquer les autres.
     *
     * @param list<int> $cibleIds
     * @return list<array{classe_id:int, classe:string, ajoutees:int, mises_a_jour:int, ignoree:?string}>
     */
    public function reproduire(int $sourceId, array $cibleIds, string $mode = 'completer', bool $confirmerEcrasement = false, bool $confirmerNotes = false): array
    {
        $source = $this->classeOuErreur($sourceId);
        if (!in_array($mode, ['completer', 'ecraser'], true)) {
            throw new ProgrammeException('Mode de reproduction inconnu.');
        }
        if ($mode === 'ecraser' && !$confirmerEcrasement) {
            throw new ProgrammeConfirmationException('Aligner des lignes déjà personnalisées sur le programme source demande une confirmation explicite.');
        }
        $lignesSource = $this->programme($sourceId);
        if ($lignesSource === []) {
            throw new ProgrammeException('Le programme de la classe source est vide : il n\'y a rien à reproduire.');
        }
        $autorisees = array_column($this->paralleles($sourceId), null, 'id');
        $cibleIds = array_values(array_unique(array_map('intval', $cibleIds)));
        if ($cibleIds === []) {
            throw new ProgrammeException('Sélectionnez au moins une classe parallèle.');
        }
        foreach ($cibleIds as $id) {
            if (!isset($autorisees[$id])) {
                throw new ProgrammeException('Une classe sélectionnée n\'est pas parallèle à la classe source (même niveau, série et année requis).');
            }
        }

        $resultat = [];
        foreach ($cibleIds as $cibleId) {
            $nom = (string)$autorisees[$cibleId]['nom'];
            $r = ['classe_id' => $cibleId, 'classe' => $nom, 'ajoutees' => 0, 'mises_a_jour' => 0, 'ignoree' => null];
            if (($verrou = $this->verrou($cibleId)) !== null) {
                $r['ignoree'] = 'programme verrouillé (bulletins publiés ou archivés)';
                $resultat[] = $r;
                continue;
            }
            $existantes = array_column($this->programme($cibleId), null, 'matiere_id');
            $nouvelles = [];
            $aMettreAJour = [];
            foreach ($lignesSource as $l) {
                $mid = (int)$l['matiere_id'];
                if (!isset($existantes[$mid])) {
                    if ((int)$l['matiere_active'] === 1) {   // une matière désactivée n'est plus reprise dans une NOUVELLE affectation
                        $nouvelles[] = $l;
                    }
                } elseif ($mode === 'ecraser' && (abs((float)$existantes[$mid]['coefficient'] - (float)$l['coefficient']) >= 0.0001
                        || (int)$existantes[$mid]['volume_horaire'] !== (int)$l['volume_horaire'] || (int)$existantes[$mid]['ordre'] !== (int)$l['ordre']
                        || (int)$existantes[$mid]['obligatoire'] !== (int)$l['obligatoire'])) {
                    $aMettreAJour[] = $l;
                }
            }
            if (!$confirmerNotes) {
                foreach ($aMettreAJour as $l) {
                    $mid = (int)$l['matiere_id'];
                    $compteDifferemment = abs((float)$existantes[$mid]['coefficient'] - (float)$l['coefficient']) >= 0.0001 || (int)$existantes[$mid]['obligatoire'] !== (int)$l['obligatoire'];
                    if ($compteDifferemment && $this->notesExistent($cibleId, $mid)) {
                        $r['ignoree'] = 'des notes existent pour ' . $l['nom'] . ' (confirmation requise)';
                        break;
                    }
                }
                if ($r['ignoree'] !== null) {
                    $resultat[] = $r;
                    continue;
                }
            }

            $propre = !$this->pdo->inTransaction();
            if ($propre) { $this->pdo->beginTransaction(); }
            try {
                $ins = $this->pdo->prepare('INSERT INTO classe_matieres (etablissement_id, classe_id, matiere_id, coefficient, volume_horaire, obligatoire, ordre) VALUES (?, ?, ?, ?, ?, ?, ?)');
                foreach ($nouvelles as $l) {
                    $ins->execute([$this->etablissementId, $cibleId, $l['matiere_id'], $l['coefficient'], $l['volume_horaire'], $l['obligatoire'], $l['ordre']]);
                    $r['ajoutees']++;
                }
                $maj = $this->pdo->prepare('UPDATE classe_matieres SET coefficient = ?, volume_horaire = ?, obligatoire = ?, ordre = ? WHERE classe_id = ? AND matiere_id = ? AND etablissement_id = ?');
                foreach ($aMettreAJour as $l) {
                    $maj->execute([$l['coefficient'], $l['volume_horaire'], $l['obligatoire'], $l['ordre'], $cibleId, $l['matiere_id'], $this->etablissementId]);
                    $r['mises_a_jour']++;
                }
                if ($r['ajoutees'] + $r['mises_a_jour'] > 0) {
                    $this->journaliser('update', null, null, ['classe_id' => $cibleId, 'reproduction_depuis_classe' => $sourceId, 'classe_cible' => $cibleId, 'mode' => $mode, 'ajoutees' => $r['ajoutees'], 'mises_a_jour' => $r['mises_a_jour']]);
                }
                if ($propre) { $this->pdo->commit(); }
            } catch (\Throwable $e) {
                if ($propre && $this->pdo->inTransaction()) { $this->pdo->rollBack(); }
                throw $e;
            }
            $resultat[] = $r;
        }
        return $resultat;
    }

    // ─── Reprise à la création d'une classe parallèle ────────────────────────────────────────────────────────────────────

    /**
     * Classes de l'établissement ayant un programme, proposées comme source à la création d'une classe.
     *
     * @return list<array<string,mixed>>
     */
    public function sourcesReprise(): array
    {
        $serie = \App\Services\SeriesLyceeService::disponible() ? 'c.serie' : 'NULL';
        $s = $this->pdo->prepare(
            "SELECT c.id, c.nom, c.niveau, {$serie} AS serie, c.annee_scolaire, COUNT(cm.id) AS nb_lignes
             FROM classes c
             JOIN classe_matieres cm ON cm.classe_id = c.id AND cm.etablissement_id = c.etablissement_id
             WHERE c.etablissement_id = ?
             GROUP BY c.id, c.nom, c.niveau, c.annee_scolaire" . (\App\Services\SeriesLyceeService::disponible() ? ', c.serie' : '') . "
             ORDER BY c.annee_scolaire DESC, c.niveau, c.nom"
        );
        $s->execute([$this->etablissementId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie qu'une classe peut servir de source à une NOUVELLE classe du niveau et de la série donnés : même établissement (garanti par la
     * lecture), même niveau, même série, programme non vide. Lève ProgrammeException sinon (rien n'est écrit).
     *
     * @return array<string,mixed> la classe source
     */
    public function verifierSourceReprise(string $niveau, ?string $serie, int $sourceId): array
    {
        $source = $this->classeOuErreur($sourceId);
        if ((string)$source['niveau'] !== $niveau) {
            throw new ProgrammeException('Le programme ne peut être repris que d\'une classe du même niveau (« ' . $source['niveau'] . ' » ≠ « ' . $niveau . ' »).');
        }
        $a = ($source['serie'] ?? '') === '' ? null : (string)$source['serie'];
        $b = ($serie ?? '') === '' ? null : $serie;
        if ($a !== $b) {
            throw new ProgrammeException('Le programme ne peut être repris que d\'une classe de la même série (« ' . ($a ?? 'sans série') . ' » ≠ « ' . ($b ?? 'sans série') . ' »).');
        }
        if ($this->programme($sourceId) === []) {
            throw new ProgrammeException('La classe choisie n\'a pas de programme à reprendre.');
        }
        return $source;
    }

    /**
     * Copie le programme de $sourceId dans la classe $cibleId (coefficients, volumes, caractère obligatoire, ordre). N'écrase JAMAIS rien :
     * la cible doit être sans programme. Les matières désactivées ne sont pas reprises. Aucune note ni bulletin n'est concerné.
     *
     * @return int nombre de matières reprises
     */
    public function reprendreDepuis(int $sourceId, int $cibleId): int
    {
        $source = $this->classeOuErreur($sourceId);
        $cible  = $this->classeOuErreur($cibleId);
        $this->verifierSourceReprise((string)$cible['niveau'], $cible['serie'] === null || $cible['serie'] === '' ? null : (string)$cible['serie'], $sourceId);
        $this->modifiable($cibleId);
        if ($this->programme($cibleId) !== []) {
            throw new ProgrammeException('La classe cible a déjà un programme : il n\'est pas écrasé (utilisez « Reproduire » depuis la classe source).');
        }
        $lignes = array_values(array_filter($this->programme($sourceId), fn($l) => (int)$l['matiere_active'] === 1));
        if ($lignes === []) {
            return 0;
        }
        $propre = !$this->pdo->inTransaction();
        if ($propre) { $this->pdo->beginTransaction(); }
        try {
            $ins = $this->pdo->prepare('INSERT INTO classe_matieres (etablissement_id, classe_id, matiere_id, coefficient, volume_horaire, obligatoire, ordre) VALUES (?, ?, ?, ?, ?, ?, ?)');
            foreach ($lignes as $l) {
                $ins->execute([$this->etablissementId, $cibleId, $l['matiere_id'], $l['coefficient'], $l['volume_horaire'], $l['obligatoire'], $l['ordre']]);
            }
            $this->journaliser('create', null, null, ['classe_id' => $cibleId, 'repris_depuis_classe' => $sourceId, 'source' => $source['niveau'] . ' ' . $source['nom'], 'matieres' => array_column($lignes, 'nom')]);
            if ($propre) { $this->pdo->commit(); }
        } catch (\Throwable $e) {
            if ($propre && $this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $e;
        }
        return count($lignes);
    }

    // ─── Validation ──────────────────────────────────────────────────────────────────────────────────────────────────────

    /** Coefficient normalisé (virgule acceptée), de 0 à 20, au plus une décimale. */
    public static function coefficientValide(mixed $valeur): float
    {
        $v = is_string($valeur) ? str_replace(',', '.', trim($valeur)) : $valeur;
        // Notation décimale simple uniquement (pas d'exposant, d'espace ni de signe) : « 3 », « 2,5 », « 0 ».
        if ($v === '' || (is_string($v) && preg_match('/^\d+(?:\.\d+)?$/', $v) !== 1) || (!is_string($v) && !is_int($v) && !is_float($v))) {
            throw new ProgrammeException('Coefficient invalide : un nombre de 0 à ' . (int)self::COEFFICIENT_MAX . ' est attendu.');
        }
        $f = (float)$v;
        if ($f < 0 || $f > self::COEFFICIENT_MAX) {
            throw new ProgrammeException('Coefficient hors limites : de 0 à ' . (int)self::COEFFICIENT_MAX . ' (0 = matière suivie mais non comptée).');
        }
        if (abs($f * 10 - round($f * 10)) > 0.0001) {
            throw new ProgrammeException('Coefficient invalide : au plus une décimale (ex. 2,5).');
        }
        return round($f, 1);
    }

    /** Volume horaire hebdomadaire : entier de 0 à 40. */
    public static function volumeValide(mixed $valeur): int
    {
        $v = is_string($valeur) ? trim($valeur) : $valeur;
        if ($v === '' || (!is_int($v) && !(is_string($v) && ctype_digit($v)) && !(is_float($v) && floor($v) === $v))) {
            throw new ProgrammeException('Volume horaire invalide : un nombre entier d\'heures par semaine (0 à ' . self::VOLUME_MAX . ') est attendu.');
        }
        $i = (int)$v;
        if ($i < 0 || $i > self::VOLUME_MAX) {
            throw new ProgrammeException('Volume horaire hors limites : de 0 à ' . self::VOLUME_MAX . ' heures par semaine.');
        }
        return $i;
    }

    // ─── Interne ─────────────────────────────────────────────────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function classeOuErreur(int $classeId): array
    {
        $c = $this->classe($classeId);
        if ($c === null) {
            throw new ProgrammeException('Classe introuvable.');
        }
        return $c;
    }

    /** Classe valide ET programme modifiable (pas de bulletin publié ou archivé). */
    private function modifiable(int $classeId): void
    {
        if (($raison = $this->verrou($classeId)) !== null) {
            throw new ProgrammeVerrouilleException($raison);
        }
    }

    /** Matière ordinaire de l'établissement courant (la Conduite est exclue). @return array<string,mixed> */
    private function matiereOuErreur(int $matiereId, bool $exigerActive = false): array
    {
        $actif = ProgrammeSql::colonneMatiere('actif') ? 'actif' : '1 AS actif';
        $s = $this->pdo->prepare("SELECT id, nom, coefficient, volume_horaire, est_conduite, {$actif} FROM matieres WHERE id = ? AND etablissement_id = ?");
        $s->execute([$matiereId, $this->etablissementId]);
        $m = $s->fetch(PDO::FETCH_ASSOC);
        if (!$m) {
            throw new ProgrammeException('Matière introuvable.');
        }
        if ($m['est_conduite'] !== null) {
            throw new ProgrammeException('La Conduite n\'est pas une matière de programme : elle suit ses propres règles.');
        }
        if ($exigerActive && (int)$m['actif'] !== 1) {
            throw new ProgrammeException('La matière « ' . $m['nom'] . ' » est désactivée : réactivez-la avant de l\'ajouter à un programme.');
        }
        return $m;
    }

    /** Filtre SQL « matière active » (alias m), vide tant que la migration T076 n'est pas appliquée. */
    private function filtreActives(): string
    {
        return ProgrammeSql::colonneMatiere('actif') ? ' AND m.actif = 1' : '';
    }

    /** Booléen de formulaire : '1', 'on', 'true', 1, true → vrai ; '0', '', null, false → faux. */
    public static function booleen(mixed $v): bool
    {
        return in_array($v, [true, 1, '1', 'on', 'true', 'oui'], true);
    }

    private function nomMatiere(int $matiereId): string
    {
        $s = $this->pdo->prepare('SELECT nom FROM matieres WHERE id = ? AND etablissement_id = ?');
        $s->execute([$matiereId, $this->etablissementId]);
        return (string)($s->fetchColumn() ?: ('#' . $matiereId));
    }

    /** @return array<string,mixed>|null */
    private function ligne(int $classeId, int $matiereId): ?array
    {
        $s = $this->pdo->prepare('SELECT id, coefficient, volume_horaire, obligatoire, ordre FROM classe_matieres WHERE classe_id = ? AND matiere_id = ? AND etablissement_id = ?');
        $s->execute([$classeId, $matiereId, $this->etablissementId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Écrit la ligne d'audit DANS la transaction de l'opération, sans repli : si le journal est indisponible, l'opération entière est annulée
     * (jamais de modification de coefficient sans trace).
     */
    private function journaliser(string $action, ?int $id, ?array $avant, ?array $apres): void
    {
        try {
            (new AuditService())->logStrict($this->pdo, $this->userId, $action, 'classes', 'programme_classe', $id, $avant, $apres);
        } catch (AuditIndisponibleException $e) {
            throw new ProgrammeException('Le journal d\'audit est indisponible : la modification a été annulée, rien n\'a été écrit.', 0, $e);
        }
    }

    /** Exécute $f dans une transaction (ou dans celle déjà ouverte) : l'écriture et son audit réussissent ou échouent ensemble. */
    private function transaction(callable $f): mixed
    {
        $propre = !$this->pdo->inTransaction();
        if ($propre) { $this->pdo->beginTransaction(); }
        try {
            $r = $f();
            if ($propre) { $this->pdo->commit(); }
            return $r;
        } catch (\Throwable $e) {
            if ($propre && $this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $e;
        }
    }

    /**
     * Historique des modifications du programme d'une classe (le plus récent d'abord), lu dans le journal d'audit existant :
     * date, utilisateur, action, matière, ancienne et nouvelle valeur. L'isolation entre établissements tient à la classe (vérifiée plus haut
     * comme appartenant à l'établissement courant, identifiants uniques) : le filtre porte sur l'identifiant de classe consigné dans chaque ligne,
     * pas sur audit_logs.etablissement_id (NULL pour une écriture hors contexte d'établissement).
     *
     * @return list<array{date:string, acteur:string, action:string, matiere:string, avant:array<string,mixed>, apres:array<string,mixed>}>
     */
    public function historique(int $classeId, int $limite = 100): array
    {
        $this->classeOuErreur($classeId);
        $s = $this->pdo->prepare(
            "SELECT al.created_at, al.actor_nom, al.actor_role, al.action, al.avant, al.apres
             FROM audit_logs al
             WHERE al.module = 'classes' AND al.entite = 'programme_classe'
               AND (JSON_UNQUOTE(JSON_EXTRACT(al.avant, '$.classe_id')) = ? OR JSON_UNQUOTE(JSON_EXTRACT(al.apres, '$.classe_id')) = ?)
             ORDER BY al.id DESC LIMIT " . max(1, min(500, $limite))
        );
        $s->execute([(string)$classeId, (string)$classeId]);
        $out = [];
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $avant = json_decode((string)($r['avant'] ?? ''), true) ?: [];
            $apres = json_decode((string)($r['apres'] ?? ''), true) ?: [];
            $out[] = [
                'date'    => (string)$r['created_at'],
                'acteur'  => trim((string)($r['actor_nom'] ?? '') . ($r['actor_role'] ? ' (' . $r['actor_role'] . ')' : '')) ?: 'Système',
                'action'  => (string)$r['action'],
                'matiere' => (string)($apres['matiere'] ?? $avant['matiere'] ?? ''),
                'avant'   => $avant,
                'apres'   => $apres,
            ];
        }
        return $out;
    }
}
