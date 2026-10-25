<?php

declare(strict_types=1);

namespace App\Modules\Academique\Services;

use PDO;

/**
 * Rotation des jetons de vérification des bulletins : de l'ancien format (version 1) vers le jeton HMAC officiel
 * (version 2, voir BulletinTokenService). OUTIL MANUEL, jamais déclenché automatiquement.
 *
 * Quatre opérations :
 *   check()     lecture seule : état des jetons (comptages uniquement, aucun jeton dans le résultat) ;
 *   plan()      lecture seule : calcule la rotation complète et une somme de contrôle du plan ;
 *   apply()     applique le plan — exige la somme de contrôle de la simulation ET le nom de la personne qui autorise,
 *               écrit d'abord une sauvegarde vérifiable, puis agit en UNE transaction avec contrôles de sortie ;
 *   restore()   remet l'état d'avant depuis la sauvegarde (vérifiée par somme de contrôle).
 *
 * Garanties :
 *   - les anciens jetons ne sont conservés que sous forme d'empreinte SHA-256 (bulletin_tokens_retired), jamais acceptés ;
 *   - un jeton n'est remplacé que si la ligne est encore dans l'état prévu (comparaison-et-échange) ;
 *   - `updated_at` est préservé ; seuls verification_token, token_version et data_json (clés verification_token et
 *     qr_code_url) changent ;
 *   - idempotent : une seconde application ne trouve plus rien à faire ;
 *   - aucun jeton, aucune clé n'est écrit dans un message, un journal ou le résultat des méthodes.
 */
final class BulletinTokenRotator
{
    /** Littéral historique : sert UNIQUEMENT à reconnaître d'anciens jetons faibles, jamais à en produire. */
    public const LEGACY_LITERAL = 'ecole_app_v2';

    public const MOTIF_FAIBLE     = 'legacy_weak';       // jeton calculable avec le littéral public
    public const MOTIF_ROTATION   = 'rotation_v2';       // jeton hérité remplacé
    public const MOTIF_JSON       = 'json_divergent';    // ancien jeton figé dans le JSON, différent de la colonne

    /** @var callable():int */
    private $clock;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ?BulletinTokenService $tokens,
        private readonly string $backupDir,
        private readonly string $dbName = '',
        private readonly string $appEnv = 'development',
        ?callable $clock = null,
    ) {
        $this->clock = $clock ?? static fn(): int => time();
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════
    //  Lecture seule
    // ═══════════════════════════════════════════════════════════════════════════════════════

    public function schemaReady(): bool
    {
        $col = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'bulletins_v2' AND column_name = 'token_version'"
        )->fetchColumn();
        $tab = $this->pdo->query("SHOW TABLES LIKE 'bulletin_tokens_retired'")->fetch(PDO::FETCH_NUM);
        return $col === 1 && $tab !== false;
    }

    /** État des jetons — comptages uniquement (aucun jeton n'est retourné). */
    public function check(): array
    {
        $out = ['schema_pret' => $this->schemaReady(), 'secret_configure' => $this->tokens !== null];
        $has = $out['schema_pret'];
        $sel = $has ? 'b.token_version' : '1 AS token_version';
        $rows = $this->pdo->query(
            "SELECT b.id, b.eleve_id, b.periode_id, b.verification_token, $sel, b.statut, b.data_json, e.etablissement_id
               FROM bulletins_v2 b LEFT JOIN eleves e ON e.id = b.eleve_id ORDER BY b.id"
        )->fetchAll(PDO::FETCH_ASSOC);

        $out += ['bulletins' => count($rows), 'par_statut' => [], 'par_version' => [], 'jetons_faibles' => 0, 'colonne_differente_du_json' => 0,
                 'json_invalide' => 0, 'qr_relatifs' => 0, 'qr_absolus' => 0, 'qr_vides' => 0, 'sans_eleve' => 0, 'v2_non_concordants' => 0];
        foreach ($rows as $r) {
            $out['par_statut'][$r['statut']] = ($out['par_statut'][$r['statut']] ?? 0) + 1;
            $out['par_version'][$r['token_version']] = ($out['par_version'][$r['token_version']] ?? 0) + 1;
            if (self::estFaible($r)) { $out['jetons_faibles']++; }
            $j = json_decode((string)$r['data_json'], true);
            if (!is_array($j)) { $out['json_invalide']++; continue; }
            if (($j['verification_token'] ?? null) !== $r['verification_token']) { $out['colonne_differente_du_json']++; }
            $qr = (string)($j['qr_code_url'] ?? '');
            if ($qr === '') { $out['qr_vides']++; } elseif (str_starts_with($qr, '/')) { $out['qr_relatifs']++; } else { $out['qr_absolus']++; }
            if ($r['etablissement_id'] === null) { $out['sans_eleve']++; }
            if ((int)$r['token_version'] === BulletinTokenService::VERSION && $this->tokens !== null && $r['etablissement_id'] !== null
                && !$this->tokens->matches($r['verification_token'], (int)$r['etablissement_id'], (int)$r['eleve_id'], (int)$r['periode_id'])) {
                $out['v2_non_concordants']++;
            }
        }
        ksort($out['par_statut']); ksort($out['par_version']);
        $out['jetons_retires'] = $has ? (int)$this->pdo->query("SELECT COUNT(*) FROM bulletin_tokens_retired")->fetchColumn() : 0;
        return $out;
    }

    /**
     * Calcule la rotation SANS rien écrire.
     *
     * @return array{rows:list<array<string,mixed>>, retired:list<array{sha:string,bulletin_id:int,motif:string}>, anomalies:list<string>, summary:array<string,mixed>, checksum:string}
     */
    public function plan(): array
    {
        if ($this->tokens === null) {
            throw BulletinTokenException::missing();
        }
        $has = $this->schemaReady();
        $sel = $has ? 'b.token_version' : '1 AS token_version';
        $rows = $this->pdo->query(
            "SELECT b.id, b.eleve_id, b.periode_id, b.verification_token, $sel, b.statut, b.data_json, b.updated_at, e.etablissement_id
               FROM bulletins_v2 b LEFT JOIN eleves e ON e.id = b.eleve_id ORDER BY b.id"
        )->fetchAll(PDO::FETCH_ASSOC);
        $existants = array_column($rows, 'verification_token');

        $plan = []; $retired = []; $anomalies = []; $dejaOk = 0; $nouveaux = [];
        foreach ($rows as $r) {
            $id = (int)$r['id'];
            if ($r['etablissement_id'] === null) { $anomalies[] = "bulletin $id : élève introuvable (établissement inconnu)"; continue; }
            $etab = (int)$r['etablissement_id'];
            if ((int)$r['token_version'] === BulletinTokenService::VERSION) {
                if ($this->tokens->matches($r['verification_token'], $etab, (int)$r['eleve_id'], (int)$r['periode_id'])) { $dejaOk++; }
                else { $anomalies[] = "bulletin $id : jeton de version 2 non concordant avec le secret courant (rotation de secret : procédure distincte)"; }
                continue;
            }
            $json = json_decode((string)$r['data_json'], true);
            if (!is_array($json)) { $anomalies[] = "bulletin $id : data_json illisible"; continue; }

            $nouveau = $this->tokens->issue($etab, (int)$r['eleve_id'], (int)$r['periode_id']);
            if (in_array($nouveau, $existants, true) || isset($nouveaux[$nouveau])) { $anomalies[] = "bulletin $id : collision du nouveau jeton"; continue; }
            $nouveaux[$nouveau] = true;

            $ancienJson = (string)($json['verification_token'] ?? '');
            $faible = self::estFaible($r);
            $retired[] = ['sha' => BulletinTokenService::retiredDigest($r['verification_token']), 'bulletin_id' => $id, 'motif' => $faible ? self::MOTIF_FAIBLE : self::MOTIF_ROTATION];
            if ($ancienJson !== '' && $ancienJson !== $r['verification_token']) {
                $retired[] = ['sha' => BulletinTokenService::retiredDigest($ancienJson), 'bulletin_id' => $id, 'motif' => self::MOTIF_JSON];
            }
            $nouveauJson = $json;
            $nouveauJson['verification_token'] = $nouveau;
            $nouveauJson['qr_code_url'] = '';        // l'URL du QR est construite à l'impression, seulement pour un bulletin publié

            $plan[] = [
                'id' => $id, 'eleve_id' => (int)$r['eleve_id'], 'periode_id' => (int)$r['periode_id'], 'etablissement_id' => $etab,
                'statut' => $r['statut'], 'ancien_jeton' => $r['verification_token'], 'ancienne_version' => (int)$r['token_version'],
                'ancien_json' => (string)$r['data_json'], 'updated_at' => $r['updated_at'], 'faible' => $faible,
                'nouveau_jeton' => $nouveau, 'nouveau_json' => json_encode($nouveauJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
        }

        // Les empreintes retirées peuvent déjà exister (rotation interrompue puis relancée) : on les dédoublonne
        $vu = []; $retiredUnique = [];
        foreach ($retired as $x) { if (!isset($vu[$x['sha']])) { $vu[$x['sha']] = true; $retiredUnique[] = $x; } }

        $summary = [
            'bulletins_total'       => count($rows),
            'a_roter'               => count($plan),
            'deja_a_jour'           => $dejaOk,
            'dont_jetons_faibles'   => count(array_filter($plan, fn($p) => $p['faible'])),
            'dont_brouillons'       => count(array_filter($plan, fn($p) => $p['statut'] === 'brouillon')),
            'dont_publies_ou_archives' => count(array_filter($plan, fn($p) => $p['statut'] !== 'brouillon')),
            'empreintes_a_retirer'  => count($retiredUnique),
            'anomalies'             => count($anomalies),
        ];
        return ['rows' => $plan, 'retired' => $retiredUnique, 'anomalies' => $anomalies, 'summary' => $summary, 'checksum' => $this->checksum($plan)];
    }

    /** Résultat présentable d'une simulation : AUCUN jeton, uniquement des comptages, les anomalies et la somme de contrôle. */
    public function simulate(): array
    {
        $p = $this->plan();
        return ['summary' => $p['summary'], 'anomalies' => $p['anomalies'], 'checksum' => $p['checksum'],
                'lignes' => array_map(fn($r) => ['id' => $r['id'], 'statut' => $r['statut'], 'faible' => $r['faible'], 'ancienne_version' => $r['ancienne_version']], $p['rows'])];
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════
    //  Application
    // ═══════════════════════════════════════════════════════════════════════════════════════

    /**
     * @param string $confirm       somme de contrôle fournie par simulate() — prouve que l'état n'a pas changé depuis
     * @param string $authorizedBy  nom de la personne qui autorise (consigné dans l'audit)
     * @return array<string,mixed>  résumé (sans aucun jeton)
     */
    public function apply(string $confirm, string $authorizedBy, bool $allowProduction = false, bool $allowPublished = false, bool $recordMigration = true): array
    {
        $debut = microtime(true);
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Schéma absent : appliquez d\'abord la migration T046.');
        }
        $plan = $this->plan();                      // exige aussi un secret valide
        if ($plan['anomalies'] !== []) {
            throw new \RuntimeException('Anomalies bloquantes : ' . implode(' ; ', $plan['anomalies']));
        }
        if ($plan['rows'] === []) {
            return ['rotated' => 0, 'message' => 'Rien à faire : aucun jeton hérité (opération idempotente).'] + $plan['summary'];
        }
        if (strtolower($this->appEnv) === 'production' && !$allowProduction) {
            throw new \RuntimeException('Environnement de production : relancer avec l\'autorisation explicite de production.');
        }
        if (mb_strlen(trim($authorizedBy)) < 3) {
            throw new \RuntimeException('Le nom de la personne qui autorise la rotation est obligatoire.');
        }
        if (!hash_equals($plan['checksum'], $confirm)) {
            throw new \RuntimeException('Somme de contrôle différente de la simulation : l\'état de la base ou le secret a changé. Relancez la simulation.');
        }
        if ($plan['summary']['dont_publies_ou_archives'] > 0 && !$allowPublished) {
            throw new \RuntimeException('Des bulletins publiés ou archivés sont concernés : leurs QR imprimés deviendraient inutilisables. Autorisation explicite requise.');
        }

        // 1 — Sauvegarde vérifiable AVANT toute écriture
        $dejaRetires = $this->empreintesExistantes(array_column($plan['retired'], 'sha'));
        $aInserer = array_values(array_filter($plan['retired'], fn($x) => !isset($dejaRetires[$x['sha']])));
        $backup = $this->ecrireSauvegarde($plan, $aInserer, $authorizedBy);

        // 2 — Transaction unique, comparaison-et-échange, contrôles de sortie
        $this->pdo->beginTransaction();
        try {
            $upd = $this->pdo->prepare(
                "UPDATE bulletins_v2
                    SET verification_token = :nv, token_version = :v, data_json = :dj, updated_at = updated_at
                  WHERE id = :id AND verification_token = :ov AND token_version = :ovv AND data_json = :odj"
            );
            foreach ($plan['rows'] as $r) {
                $upd->execute([':nv' => $r['nouveau_jeton'], ':v' => BulletinTokenService::VERSION, ':dj' => $r['nouveau_json'],
                               ':id' => $r['id'], ':ov' => $r['ancien_jeton'], ':ovv' => $r['ancienne_version'], ':odj' => $r['ancien_json']]);
                if ($upd->rowCount() !== 1) {
                    throw new \RuntimeException('Le bulletin ' . $r['id'] . ' a changé pendant la rotation : opération annulée.');
                }
            }
            $ins = $this->pdo->prepare("INSERT INTO bulletin_tokens_retired (token_sha256, bulletin_id, motif) VALUES (:s, :b, :m)");
            foreach ($aInserer as $x) { $ins->execute([':s' => $x['sha'], ':b' => $x['bulletin_id'], ':m' => $x['motif']]); }

            $this->verifierEtatFinal($plan, count($aInserer));

            $this->journaliser('rotation_jetons', [
                'bulletins' => count($plan['rows']), 'empreintes_retirees' => count($aInserer), 'sauvegarde' => basename($backup['file']),
                'somme_plan' => substr($plan['checksum'], 0, 16), 'autorise_par' => trim($authorizedBy),
            ]);
            if ($recordMigration) { $this->enregistrerMigration((int)round((microtime(true) - $debut) * 1000)); }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $e;
        }

        return ['rotated' => count($plan['rows']), 'empreintes_retirees' => count($aInserer), 'sauvegarde' => $backup['file'], 'sauvegarde_sha256' => $backup['sha256']]
             + $plan['summary'];
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════
    //  Restauration
    // ═══════════════════════════════════════════════════════════════════════════════════════

    /**
     * Remet les bulletins dans l'état d'avant la rotation (jeton, version, JSON, `updated_at` intacts) et retire les
     * empreintes ajoutées par cette rotation. Refuse si la sauvegarde est altérée, si une ligne est dans un état
     * inattendu, ou si un bulletin concerné a été publié depuis (son QR officiel serait invalidé) sauf autorisation.
     *
     * @return array<string,mixed>
     */
    public function restore(string $file, string $authorizedBy, bool $forcePublished = false): array
    {
        if (mb_strlen(trim($authorizedBy)) < 3) {
            throw new \RuntimeException('Le nom de la personne qui autorise la restauration est obligatoire.');
        }
        $data = $this->lireSauvegarde($file);
        if (($data['database'] ?? '') !== $this->dbName) {
            throw new \RuntimeException('Cette sauvegarde provient d\'une autre base : restauration refusée.');
        }

        $aRestaurer = []; $dejaRestaures = 0;
        $sel = $this->pdo->prepare('SELECT verification_token, token_version, data_json, statut FROM bulletins_v2 WHERE id = ?');
        foreach ($data['rows'] as $b) {
            $sel->execute([$b['id']]);
            $now = $sel->fetch(PDO::FETCH_ASSOC);
            if ($now === false) { throw new \RuntimeException('Bulletin ' . $b['id'] . ' introuvable : état inattendu, restauration refusée.'); }
            if ($now['verification_token'] === $b['verification_token'] && (int)$now['token_version'] === (int)$b['token_version'] && $now['data_json'] === $b['data_json']) {
                $dejaRestaures++; continue;
            }
            if (!(BulletinTokenService::retiredDigest($now['verification_token']) === $b['new_token_sha256'] && (int)$now['token_version'] === BulletinTokenService::VERSION)) {
                throw new \RuntimeException('Bulletin ' . $b['id'] . ' dans un état inattendu (ni avant, ni après la rotation) : restauration refusée.');
            }
            if ($now['statut'] !== $b['statut'] && !$forcePublished) {
                throw new \RuntimeException('Le bulletin ' . $b['id'] . ' a changé de statut depuis la rotation (publié ?) : sa restauration invaliderait un QR officiel. Autorisation explicite requise.');
            }
            $aRestaurer[] = $b;
        }

        $this->pdo->beginTransaction();
        try {
            $upd = $this->pdo->prepare(
                'UPDATE bulletins_v2 SET verification_token = :t, token_version = :v, data_json = :j, updated_at = :u WHERE id = :id'
            );
            foreach ($aRestaurer as $b) {
                $upd->execute([':t' => $b['verification_token'], ':v' => $b['token_version'], ':j' => $b['data_json'], ':u' => $b['updated_at'], ':id' => $b['id']]);
            }
            if ($data['retired_inserted'] !== []) {
                $ph = implode(',', array_fill(0, count($data['retired_inserted']), '?'));
                $this->pdo->prepare("DELETE FROM bulletin_tokens_retired WHERE token_sha256 IN ($ph)")->execute($data['retired_inserted']);
            }
            // Contrôle de sortie : chaque ligne sauvegardée est identique à la sauvegarde
            $chk = $this->pdo->prepare('SELECT verification_token, token_version, data_json, updated_at FROM bulletins_v2 WHERE id = ?');
            foreach ($data['rows'] as $b) {
                $chk->execute([$b['id']]);
                $r = $chk->fetch(PDO::FETCH_ASSOC);
                if ($r === false || $r['verification_token'] !== $b['verification_token'] || (int)$r['token_version'] !== (int)$b['token_version']
                    || $r['data_json'] !== $b['data_json'] || (string)$r['updated_at'] !== (string)$b['updated_at']) {
                    throw new \RuntimeException('Contrôle de sortie : le bulletin ' . $b['id'] . ' diffère de la sauvegarde — restauration annulée.');
                }
            }
            $this->journaliser('restauration_jetons', ['bulletins' => count($aRestaurer), 'deja_restaures' => $dejaRestaures,
                                'sauvegarde' => basename($file), 'autorise_par' => trim($authorizedBy)]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $e;
        }
        return ['restaures' => count($aRestaurer), 'deja_restaures' => $dejaRestaures, 'empreintes_supprimees' => count($data['retired_inserted'])];
    }

    // ═══════════════════════════════════════════════════════════════════════════════════════
    //  Internes
    // ═══════════════════════════════════════════════════════════════════════════════════════

    /** Jeton calculable avec le littéral public de l'ancien schéma (donc à retirer en priorité). */
    private static function estFaible(array $r): bool
    {
        return hash_equals(hash('sha256', "bulletin:{$r['eleve_id']}:{$r['periode_id']}:" . self::LEGACY_LITERAL), (string)$r['verification_token']);
    }

    /** Somme de contrôle du plan : liée aux lignes, à leur état actuel ET au secret (par une empreinte), sans révéler de jeton. */
    private function checksum(array $plan): string
    {
        $lignes = array_map(fn($r) => [
            $r['id'], $r['statut'], $r['ancienne_version'],
            hash('sha256', $r['ancien_jeton']), hash('sha256', $r['ancien_json']), hash('sha256', $r['nouveau_jeton']),
        ], $plan);
        $liaisonSecret = BulletinTokenService::fingerprint($this->tokens->issue(1, 1, 1));
        return hash('sha256', json_encode(['v' => 1, 'secret' => $liaisonSecret, 'lignes' => $lignes]));
    }

    /** @param list<string> $shas @return array<string,true> */
    private function empreintesExistantes(array $shas): array
    {
        if ($shas === []) { return []; }
        $ph = implode(',', array_fill(0, count($shas), '?'));
        $st = $this->pdo->prepare("SELECT token_sha256 FROM bulletin_tokens_retired WHERE token_sha256 IN ($ph)");
        $st->execute($shas);
        return array_fill_keys($st->fetchAll(PDO::FETCH_COLUMN), true);
    }

    /** Contrôles de sortie, DANS la transaction : tout écart annule la rotation. */
    private function verifierEtatFinal(array $plan, int $empreintesInserees): void
    {
        $total = (int)$this->pdo->query('SELECT COUNT(*) FROM bulletins_v2')->fetchColumn();
        if ($total !== $plan['summary']['bulletins_total']) { throw new \RuntimeException('Contrôle de sortie : le nombre de bulletins a changé.'); }
        $distincts = (int)$this->pdo->query('SELECT COUNT(DISTINCT verification_token) FROM bulletins_v2')->fetchColumn();
        if ($distincts !== $total) { throw new \RuntimeException('Contrôle de sortie : jetons non uniques.'); }

        $sel = $this->pdo->prepare('SELECT verification_token, token_version, data_json, statut FROM bulletins_v2 WHERE id = ?');
        foreach ($plan['rows'] as $r) {
            $sel->execute([$r['id']]);
            $n = $sel->fetch(PDO::FETCH_ASSOC);
            $j = json_decode((string)$n['data_json'], true);
            if ($n['verification_token'] !== $r['nouveau_jeton'] || (int)$n['token_version'] !== BulletinTokenService::VERSION
                || !$this->tokens->matches($n['verification_token'], $r['etablissement_id'], $r['eleve_id'], $r['periode_id'])
                || ($j['verification_token'] ?? null) !== $n['verification_token'] || ($j['qr_code_url'] ?? 'x') !== ''
                || $n['statut'] !== $r['statut']) {
                throw new \RuntimeException('Contrôle de sortie : le bulletin ' . $r['id'] . ' est incohérent (jeton, JSON, version ou statut).');
            }
            // Seules les deux clés prévues ont changé dans le JSON
            $avant = json_decode($r['ancien_json'], true);
            $avant['verification_token'] = $j['verification_token']; $avant['qr_code_url'] = '';
            if ($avant != $j) { throw new \RuntimeException('Contrôle de sortie : le JSON du bulletin ' . $r['id'] . ' a changé au-delà des deux clés prévues.'); }
        }
        $retires = (int)$this->pdo->query('SELECT COUNT(*) FROM bulletin_tokens_retired')->fetchColumn();
        $faibles = 0;
        foreach ($this->pdo->query('SELECT eleve_id, periode_id, verification_token FROM bulletins_v2')->fetchAll(PDO::FETCH_ASSOC) as $x) {
            if (self::estFaible($x)) { $faibles++; }
        }
        if ($faibles !== 0) { throw new \RuntimeException('Contrôle de sortie : il reste des jetons faibles.'); }
        if ($retires < $empreintesInserees) { throw new \RuntimeException('Contrôle de sortie : empreintes retirées manquantes.'); }
    }

    /** @return array{file:string, sha256:string} */
    private function ecrireSauvegarde(array $plan, array $retiredInserted, string $authorizedBy): array
    {
        if (!is_dir($this->backupDir) && !@mkdir($this->backupDir, 0770, true) && !is_dir($this->backupDir)) {
            throw new \RuntimeException('Dossier de sauvegarde inutilisable.');
        }
        $rows = array_map(fn($r) => [
            'id' => $r['id'], 'statut' => $r['statut'], 'verification_token' => $r['ancien_jeton'], 'token_version' => $r['ancienne_version'],
            'data_json' => $r['ancien_json'], 'updated_at' => $r['updated_at'], 'new_token_sha256' => BulletinTokenService::retiredDigest($r['nouveau_jeton']),
        ], $plan['rows']);
        $payload = [
            'format' => 1, 'cree_le' => gmdate('c', ($this->clock)()), 'database' => $this->dbName, 'autorise_par' => trim($authorizedBy),
            'somme_plan' => $plan['checksum'], 'rows' => $rows, 'retired_inserted' => array_column($retiredInserted, 'sha'),
            'avertissement' => 'Ce fichier contient d\'anciens jetons en clair : le conserver hors dépôt, à accès restreint.',
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $file = rtrim($this->backupDir, '/\\') . DIRECTORY_SEPARATOR . 'bulletin_tokens_' . gmdate('Ymd_His', ($this->clock)()) . '_' . preg_replace('/[^A-Za-z0-9_]/', '', $this->dbName) . '.json';
        if (file_put_contents($file, $json) === false) { throw new \RuntimeException('Écriture de la sauvegarde impossible.'); }
        @chmod($file, 0600);
        $sha = hash('sha256', $json);
        file_put_contents($file . '.sha256', $sha . '  ' . basename($file) . "\n");
        // Relecture : la sauvegarde doit être lisible et identique avant toute modification
        $this->lireSauvegarde($file);
        return ['file' => $file, 'sha256' => $sha];
    }

    private function lireSauvegarde(string $file): array
    {
        if (!is_file($file) || !is_file($file . '.sha256')) { throw new \RuntimeException('Sauvegarde ou somme de contrôle introuvable.'); }
        $json = (string)file_get_contents($file);
        $attendu = strtok((string)file_get_contents($file . '.sha256'), " \t\r\n");
        if (!hash_equals((string)$attendu, hash('sha256', $json))) { throw new \RuntimeException('Sauvegarde altérée : somme de contrôle invalide.'); }
        $data = json_decode($json, true);
        if (!is_array($data) || ($data['format'] ?? null) !== 1 || !isset($data['rows'], $data['retired_inserted'])) { throw new \RuntimeException('Format de sauvegarde inconnu.'); }
        return $data;
    }

    private function journaliser(string $action, array $details): void
    {
        try {
            $this->pdo->prepare("INSERT INTO audit_logs (user_id, action, module, entite, entite_id, avant, apres, ip, user_agent) VALUES (NULL, ?, 'academique', 'bulletin_tokens', NULL, NULL, ?, 'cli', 'bulletin_tokens')")
                ->execute([$action, json_encode($details, JSON_UNESCAPED_UNICODE)]);
        } catch (\Throwable) {
            // Table d'audit absente : la rotation reste valide, la trace est dans la sauvegarde.
        }
    }

    private function enregistrerMigration(int $ms): void
    {
        try {
            $existe = $this->pdo->query("SELECT COUNT(*) FROM migrations_log WHERE migration = 'T047'")->fetchColumn();
            if ((int)$existe === 0) {
                $this->pdo->prepare("INSERT INTO migrations_log (migration, name, duration_ms, status) VALUES ('T047', 'Rotation des jetons de bulletins (manuelle)', ?, 'success')")->execute([$ms]);
            }
        } catch (\Throwable) {
        }
    }
}
