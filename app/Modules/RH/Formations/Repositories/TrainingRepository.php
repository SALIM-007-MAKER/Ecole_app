<?php

declare(strict_types=1);

namespace App\Modules\RH\Formations\Repositories;

use Core\Database;
use App\Modules\RH\Formations\DTO\TrainingFiltersDTO;
use PDO;

class TrainingRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ── Catalogue ─────────────────────────────────────────────────────────────

    public function findAllFormations(string $type = '', bool $actifOnly = true): array
    {
        $where = ['f.deleted_at IS NULL'];
        $params = [];
        if ($actifOnly) $where[] = 'f.actif = 1';
        if ($type !== '') { $where[] = 'f.type = :type'; $params[':type'] = $type; }
        $stmt = $this->pdo->prepare(
            'SELECT f.*, o.nom AS organisme_nom,
                    (SELECT COUNT(*) FROM rh_formations_sessions s WHERE s.formation_id = f.id AND s.deleted_at IS NULL) AS nb_sessions
             FROM rh_formations_catalogue f
             LEFT JOIN rh_formations_organismes o ON o.id = f.organisme_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY f.titre'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findFormationById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT f.*, o.nom AS organisme_nom
             FROM rh_formations_catalogue f
             LEFT JOIN rh_formations_organismes o ON o.id = f.organisme_id
             WHERE f.id = :id AND f.deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insertFormation(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rh_formations_catalogue
             (code, titre, description, type, duree_heures, niveau, modalite,
              organisme_id, formateur_principal, cout_unitaire, max_participants,
              prerequis, objectifs, created_by)
             VALUES (:code,:titre,:desc,:type,:dh,:niv,:mod,:org,:fp,:cu,:mp,:pre,:obj,:cb)'
        );
        $stmt->execute([
            ':code'  => $data['code'],  ':titre' => $data['titre'],
            ':desc'  => $data['description'], ':type' => $data['type'],
            ':dh'    => $data['duree_heures'], ':niv'  => $data['niveau'],
            ':mod'   => $data['modalite'],     ':org'  => $data['organisme_id'],
            ':fp'    => $data['formateur_principal'], ':cu' => $data['cout_unitaire'],
            ':mp'    => $data['max_participants'],    ':pre'=> $data['prerequis'],
            ':obj'   => $data['objectifs'],    ':cb'   => $data['created_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function insertFormationCompetence(int $formationId, int $competenceId): void
    {
        $this->pdo->prepare(
            'INSERT IGNORE INTO rh_formation_competences (formation_id, competence_id) VALUES (:f, :c)'
        )->execute([':f' => $formationId, ':c' => $competenceId]);
    }

    public function findFormationCompetences(int $formationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.* FROM rh_competences c
             JOIN rh_formation_competences fc ON fc.competence_id = c.id
             WHERE fc.formation_id = :f ORDER BY c.ordre'
        );
        $stmt->execute([':f' => $formationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Organismes ────────────────────────────────────────────────────────────

    public function findAllOrganismes(): array
    {
        return $this->pdo->query(
            'SELECT * FROM rh_formations_organismes WHERE actif = 1 ORDER BY nom'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Sessions ──────────────────────────────────────────────────────────────

    public function countSessions(TrainingFiltersDTO $f): int
    {
        [$where, $params] = $this->buildSessionWhere($f);
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM rh_formations_sessions s
             JOIN rh_formations_catalogue f ON f.id = s.formation_id
             WHERE ' . implode(' AND ', $where)
        );
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findSessions(TrainingFiltersDTO $f): array
    {
        [$where, $params] = $this->buildSessionWhere($f);
        $offset = ($f->page - 1) * $f->perPage;
        $stmt = $this->pdo->prepare(
            'SELECT s.*, f.titre AS formation_titre, f.type AS formation_type,
                    f.duree_heures, f.niveau, o.nom AS organisme_nom
             FROM rh_formations_sessions s
             JOIN rh_formations_catalogue f ON f.id = s.formation_id
             LEFT JOIN rh_formations_organismes o ON o.id = f.organisme_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY s.date_debut DESC
             LIMIT :lim OFFSET :off'
        );
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':lim', $f->perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,     PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildSessionWhere(TrainingFiltersDTO $f): array
    {
        $where  = ['s.deleted_at IS NULL'];
        $params = [];
        if (!$f->includeArch) $where[] = "s.statut != 'annulee'";
        if ($f->q !== '') {
            $where[] = "(f.titre LIKE :q OR s.code_session LIKE :q2)";
            $params[':q'] = $params[':q2'] = '%' . $f->q . '%';
        }
        if ($f->statut     !== '') { $where[] = 's.statut = :statut';       $params[':statut']      = $f->statut; }
        if ($f->type       !== '') { $where[] = 'f.type = :type';            $params[':type']        = $f->type; }
        if ($f->formationId > 0)  { $where[] = 's.formation_id = :fid';     $params[':fid']         = $f->formationId; }
        if ($f->annee      > 0)   { $where[] = 'YEAR(s.date_debut) = :y';   $params[':y']           = $f->annee; }
        return [$where, $params];
    }

    public function findSessionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, f.titre AS formation_titre, f.type AS formation_type,
                    f.duree_heures, f.niveau, f.modalite, f.cout_unitaire,
                    f.prerequis, f.objectifs, f.description AS formation_description,
                    o.nom AS organisme_nom
             FROM rh_formations_sessions s
             JOIN rh_formations_catalogue f ON f.id = s.formation_id
             LEFT JOIN rh_formations_organismes o ON o.id = f.organisme_id
             WHERE s.id = :id AND s.deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insertSession(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rh_formations_sessions
             (formation_id, code_session, lieu, date_debut, date_fin, heure_debut, heure_fin,
              statut, max_participants, formateur_nom, cout_total, financeur, commentaire, created_by)
             VALUES (:fid,:cs,:lieu,:dd,:df,:hd,:hf,:statut,:mp,:fn,:ct,:fin,:com,:cb)'
        );
        $stmt->execute([
            ':fid'   => $data['formation_id'], ':cs'  => $data['code_session'],
            ':lieu'  => $data['lieu'],         ':dd'  => $data['date_debut'],
            ':df'    => $data['date_fin'],      ':hd'  => $data['heure_debut'],
            ':hf'    => $data['heure_fin'],     ':statut' => 'planifiee',
            ':mp'    => $data['max_participants'], ':fn' => $data['formateur_nom'],
            ':ct'    => $data['cout_total'],    ':fin' => $data['financeur'],
            ':com'   => $data['commentaire'],   ':cb'  => $data['created_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateSessionStatut(int $id, string $statut): void
    {
        $this->pdo->prepare(
            'UPDATE rh_formations_sessions SET statut = :s, updated_at = NOW() WHERE id = :id'
        )->execute([':s' => $statut, ':id' => $id]);
    }

    public function incrementNbInscrits(int $sessionId, int $delta = 1): void
    {
        $this->pdo->prepare(
            'UPDATE rh_formations_sessions SET nb_inscrits = nb_inscrits + :d WHERE id = :id'
        )->execute([':d' => $delta, ':id' => $sessionId]);
    }

    // ── Inscriptions ──────────────────────────────────────────────────────────

    public function findInscriptionsBySession(int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.*, CONCAT(e.prenom,\' \',e.nom) AS employe_nom_complet,
                    e.matricule AS employe_matricule
             FROM rh_formations_inscriptions i
             JOIN rh_employes e ON e.id = i.employe_id
             WHERE i.session_id = :sid ORDER BY e.nom, e.prenom'
        );
        $stmt->execute([':sid' => $sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findInscriptionsByEmploye(int $employeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.*, s.code_session, s.date_debut, s.date_fin, s.statut AS session_statut,
                    f.titre AS formation_titre, f.type AS formation_type
             FROM rh_formations_inscriptions i
             JOIN rh_formations_sessions s ON s.id = i.session_id
             JOIN rh_formations_catalogue f ON f.id = s.formation_id
             WHERE i.employe_id = :eid ORDER BY s.date_debut DESC'
        );
        $stmt->execute([':eid' => $employeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findInscription(int $sessionId, int $employeId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM rh_formations_inscriptions WHERE session_id = :s AND employe_id = :e'
        );
        $stmt->execute([':s' => $sessionId, ':e' => $employeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findInscriptionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rh_formations_inscriptions WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insertInscription(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rh_formations_inscriptions
             (session_id, employe_id, statut, created_by, updated_by)
             VALUES (:s,:e,\'inscrit\',:cb,:ub)'
        );
        $stmt->execute([':s'=>$data['session_id'],':e'=>$data['employe_id'],':cb'=>$data['created_by'],':ub'=>$data['created_by']]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateInscriptionStatut(int $id, string $statut, array $extra = [], int $userId = 0): void
    {
        $sets   = ['statut = :statut', 'updated_by = :ub', 'updated_at = NOW()'];
        $params = [':statut' => $statut, ':ub' => $userId, ':id' => $id];
        foreach ($extra as $col => $val) {
            $sets[]          = "$col = :$col";
            $params[":$col"] = $val;
        }
        $this->pdo->prepare(
            'UPDATE rh_formations_inscriptions SET ' . implode(', ', $sets) . ' WHERE id = :id'
        )->execute($params);
    }

    // ── Certifications ────────────────────────────────────────────────────────

    public function findAllCertificationsCatalogue(): array
    {
        return $this->pdo->query(
            'SELECT c.*, o.nom AS organisme_nom FROM rh_certifications c
             LEFT JOIN rh_formations_organismes o ON o.id = c.organisme_id
             WHERE c.actif = 1 ORDER BY c.libelle'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findCertificationsByEmploye(int $employeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ec.*, c.libelle AS cert_libelle, c.code AS cert_code,
                    c.duree_validite_mois, c.renouvelable
             FROM rh_employe_certifications ec
             JOIN rh_certifications c ON c.id = ec.certification_id
             WHERE ec.employe_id = :eid ORDER BY ec.date_obtention DESC'
        );
        $stmt->execute([':eid' => $employeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findExpiringCertifications(int $daysBefore = 30): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ec.*, c.libelle AS cert_libelle, c.code AS cert_code,
                    CONCAT(e.prenom,\' \',e.nom) AS employe_nom_complet, e.matricule
             FROM rh_employe_certifications ec
             JOIN rh_certifications c ON c.id = ec.certification_id
             JOIN rh_employes e ON e.id = ec.employe_id
             WHERE ec.statut = \'valide\'
               AND ec.date_expiration IS NOT NULL
               AND ec.date_expiration <= DATE_ADD(CURDATE(), INTERVAL :d DAY)
             ORDER BY ec.date_expiration ASC'
        );
        $stmt->execute([':d' => $daysBefore]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertEmployeCertification(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rh_employe_certifications
             (employe_id, certification_id, date_obtention, date_expiration, statut,
              session_id, reference_certificat, notes, created_by)
             VALUES (:e,:c,:do,:de,\'valide\',:s,:ref,:notes,:cb)'
        );
        $stmt->execute([
            ':e'    => $data['employe_id'],       ':c'   => $data['certification_id'],
            ':do'   => $data['date_obtention'],   ':de'  => $data['date_expiration'],
            ':s'    => $data['session_id'],        ':ref' => $data['reference'],
            ':notes'=> $data['notes'],             ':cb'  => $data['created_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateCertificationStatut(int $id, string $statut): void
    {
        $this->pdo->prepare(
            'UPDATE rh_employe_certifications SET statut = :s, updated_at = NOW() WHERE id = :id'
        )->execute([':s' => $statut, ':id' => $id]);
    }

    // ── Compétences ───────────────────────────────────────────────────────────

    public function findAllCompetences(string $categorie = ''): array
    {
        $where  = ['actif = 1'];
        $params = [];
        if ($categorie !== '') { $where[] = 'categorie = :cat'; $params[':cat'] = $categorie; }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM rh_competences WHERE ' . implode(' AND ', $where) . ' ORDER BY ordre, libelle'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findCompetencesByEmploye(int $employeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ec.*, c.libelle AS competence_libelle, c.code AS competence_code, c.categorie
             FROM rh_employe_competences ec
             JOIN rh_competences c ON c.id = ec.competence_id
             WHERE ec.employe_id = :eid ORDER BY c.categorie, c.libelle'
        );
        $stmt->execute([':eid' => $employeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function upsertEmployeCompetence(int $employeId, int $competenceId, string $niveau, ?int $sessionId, int $validePar, string $valideParNom, ?string $notes): void
    {
        $this->pdo->prepare(
            'INSERT INTO rh_employe_competences
             (employe_id, competence_id, niveau, date_acquisition, session_id, valide_par, valide_par_nom, notes)
             VALUES (:e,:c,:n,CURDATE(),:s,:vp,:vpn,:notes)
             ON DUPLICATE KEY UPDATE
               niveau = :n2, date_acquisition = CURDATE(), session_id = :s2,
               valide_par = :vp2, valide_par_nom = :vpn2, notes = :notes2, updated_at = NOW()'
        )->execute([
            ':e'=>$employeId,':c'=>$competenceId,':n'=>$niveau,':s'=>$sessionId,
            ':vp'=>$validePar,':vpn'=>$valideParNom,':notes'=>$notes,
            ':n2'=>$niveau,':s2'=>$sessionId,':vp2'=>$validePar,':vpn2'=>$valideParNom,':notes2'=>$notes,
        ]);
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function statistiques(): array
    {
        $annee = (int)date('Y');
        $global = $this->pdo->prepare(
            'SELECT
               (SELECT COUNT(*) FROM rh_formations_sessions WHERE statut = \'ouverte\' AND deleted_at IS NULL) AS sessions_ouvertes,
               (SELECT COUNT(*) FROM rh_formations_sessions WHERE statut = \'en_cours\' AND deleted_at IS NULL) AS sessions_en_cours,
               (SELECT COUNT(*) FROM rh_formations_sessions WHERE statut = \'terminee\' AND YEAR(date_fin) = :y AND deleted_at IS NULL) AS sessions_terminees,
               (SELECT COUNT(*) FROM rh_formations_inscriptions WHERE statut = \'valide\' AND YEAR(created_at) = :y2) AS inscriptions_validees,
               (SELECT COUNT(*) FROM rh_employe_certifications WHERE statut = \'valide\') AS certifications_actives,
               (SELECT COUNT(*) FROM rh_employe_certifications WHERE statut = \'expiree\') AS certifications_expirees,
               (SELECT COUNT(*) FROM rh_formations_catalogue WHERE actif = 1 AND deleted_at IS NULL) AS nb_formations'
        );
        $global->execute([':y'=>$annee,':y2'=>$annee]);
        $stats = $global->fetch(PDO::FETCH_ASSOC);

        $parType = $this->pdo->query(
            'SELECT f.type, COUNT(*) AS nb FROM rh_formations_sessions s
             JOIN rh_formations_catalogue f ON f.id = s.formation_id
             WHERE s.deleted_at IS NULL GROUP BY f.type'
        )->fetchAll(PDO::FETCH_ASSOC);
        $stats['par_type'] = $parType;

        return $stats;
    }
}
