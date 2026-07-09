<?php

declare(strict_types=1);

namespace App\Modules\RH\Evaluations\Repositories;

use Core\Database;
use App\Modules\RH\Evaluations\DTO\EvaluationFiltersDTO;
use PDO;

class EvaluationRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ── Campagnes ─────────────────────────────────────────────────────────────

    public function findAllCampagnes(int $annee = 0, string $statut = ''): array
    {
        $where = ['c.deleted_at IS NULL'];
        $params = [];
        if ($annee  > 0) { $where[] = 'c.annee = :annee';   $params[':annee']  = $annee; }
        if ($statut !== '') { $where[] = 'c.statut = :statut'; $params[':statut'] = $statut; }
        $sql = 'SELECT c.*,
                    (SELECT COUNT(*) FROM rh_evaluations e WHERE e.campagne_id = c.id AND e.deleted_at IS NULL) AS nb_evaluations,
                    (SELECT COUNT(*) FROM rh_evaluations e WHERE e.campagne_id = c.id AND e.statut = \'publiee\' AND e.deleted_at IS NULL) AS nb_publiees
                FROM rh_campagnes_evaluation c
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY c.annee DESC, c.date_debut DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findCampagneById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM rh_campagnes_evaluation WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findCampagneCriteres(int $campagneId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT cc.*, cr.code, cr.libelle, cr.categorie, cr.type_employe,
                    cr.note_min, cr.note_max, cr.description,
                    COALESCE(cc.poids_override, cr.poids) AS poids_effectif
             FROM rh_campagne_criteres cc
             JOIN rh_criteres_evaluation cr ON cr.id = cc.critere_id
             WHERE cc.campagne_id = :cid
             ORDER BY cc.ordre, cr.ordre'
        );
        $stmt->execute([':cid' => $campagneId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertCampagne(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rh_campagnes_evaluation
             (code, libelle, description, annee, periode, date_debut, date_fin,
              date_limite_auto_eval, date_limite_eval, statut, created_by)
             VALUES (:code,:libelle,:description,:annee,:periode,:date_debut,:date_fin,
                     :dlae,:dle,:statut,:created_by)'
        );
        $stmt->execute([
            ':code'        => $data['code'],
            ':libelle'     => $data['libelle'],
            ':description' => $data['description'],
            ':annee'       => $data['annee'],
            ':periode'     => $data['periode'],
            ':date_debut'  => $data['date_debut'],
            ':date_fin'    => $data['date_fin'],
            ':dlae'        => $data['date_limite_auto_eval'],
            ':dle'         => $data['date_limite_eval'],
            ':statut'      => 'brouillon',
            ':created_by'  => $data['created_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateCampagneStatut(int $id, string $statut): void
    {
        $this->pdo->prepare(
            'UPDATE rh_campagnes_evaluation SET statut = :s WHERE id = :id'
        )->execute([':s' => $statut, ':id' => $id]);
    }

    public function insertCampagneCritere(int $campagneId, int $critereId, ?float $poidsOverride, int $obligatoire, int $ordre): void
    {
        $this->pdo->prepare(
            'INSERT INTO rh_campagne_criteres (campagne_id, critere_id, poids_override, obligatoire, ordre)
             VALUES (:c, :cr, :p, :o, :ord)
             ON DUPLICATE KEY UPDATE poids_override = :p2, obligatoire = :o2'
        )->execute([
            ':c'   => $campagneId,
            ':cr'  => $critereId,
            ':p'   => $poidsOverride,
            ':o'   => $obligatoire,
            ':ord' => $ordre,
            ':p2'  => $poidsOverride,
            ':o2'  => $obligatoire,
        ]);
    }

    // ── Critères ──────────────────────────────────────────────────────────────

    public function findAllCriteres(string $typeEmploye = '', bool $actifOnly = true): array
    {
        $where = [];
        $params = [];
        if ($actifOnly) $where[] = 'actif = 1';
        if ($typeEmploye !== '') {
            $where[] = "(type_employe = 'tous' OR type_employe = :te)";
            $params[':te'] = $typeEmploye;
        }
        $sql = 'SELECT * FROM rh_criteres_evaluation'
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY ordre, id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Évaluations ───────────────────────────────────────────────────────────

    public function count(EvaluationFiltersDTO $f): int
    {
        [$where, $params] = $this->buildWhere($f);
        $sql = 'SELECT COUNT(*) FROM rh_evaluations e
                JOIN rh_employes emp ON emp.id = e.employe_id
                JOIN rh_campagnes_evaluation c ON c.id = e.campagne_id
                WHERE ' . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findAll(EvaluationFiltersDTO $f): array
    {
        [$where, $params] = $this->buildWhere($f);
        $offset = ($f->page - 1) * $f->perPage;
        $sql = 'SELECT e.*,
                    CONCAT(emp.prenom, \' \', emp.nom) AS employe_nom_complet,
                    emp.matricule AS employe_matricule,
                    d.nom AS departement_nom,
                    c.libelle AS campagne_libelle, c.code AS campagne_code,
                    c.annee AS campagne_annee
                FROM rh_evaluations e
                JOIN rh_employes emp ON emp.id = e.employe_id
                JOIN rh_campagnes_evaluation c ON c.id = e.campagne_id
                LEFT JOIN rh_affectations aff ON aff.id = e.affectation_id
                LEFT JOIN rh_departements d   ON d.id = aff.departement_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY c.annee DESC, e.updated_at DESC
                LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit',  $f->perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,     PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildWhere(EvaluationFiltersDTO $f): array
    {
        $where  = ['e.deleted_at IS NULL'];
        $params = [];
        if (!$f->includeArch) $where[] = "e.statut != 'archivee'";
        if ($f->q !== '') {
            $where[] = "(CONCAT(emp.prenom,' ',emp.nom) LIKE :q OR emp.matricule LIKE :q2 OR c.code LIKE :q3)";
            $params[':q'] = $params[':q2'] = $params[':q3'] = '%' . $f->q . '%';
        }
        if ($f->statut     !== '') { $where[] = 'e.statut = :statut';         $params[':statut']      = $f->statut; }
        if ($f->campagneId  > 0)  { $where[] = 'e.campagne_id = :cid';        $params[':cid']         = $f->campagneId; }
        if ($f->employeId   > 0)  { $where[] = 'e.employe_id = :eid';         $params[':eid']         = $f->employeId; }
        if ($f->annee       > 0)  { $where[] = 'c.annee = :annee';            $params[':annee']       = $f->annee; }
        if ($f->mention    !== '') { $where[] = 'e.mention = :mention';        $params[':mention']     = $f->mention; }
        return [$where, $params];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.*,
                 CONCAT(emp.prenom,\' \',emp.nom) AS employe_nom_complet,
                 emp.matricule AS employe_matricule,
                 d.nom AS departement_nom,
                 c.libelle AS campagne_libelle, c.code AS campagne_code,
                 c.annee AS campagne_annee, c.statut AS campagne_statut
             FROM rh_evaluations e
             JOIN rh_employes emp ON emp.id = e.employe_id
             JOIN rh_campagnes_evaluation c ON c.id = e.campagne_id
             LEFT JOIN rh_affectations aff ON aff.id = e.affectation_id
             LEFT JOIN rh_departements d   ON d.id = aff.departement_id
             WHERE e.id = :id AND e.deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findCritereScores(int $evaluationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ec.*, cr.code, cr.libelle, cr.categorie, cr.type_employe,
                    cr.note_min, cr.note_max, cr.description,
                    COALESCE(cc.poids_override, cr.poids) AS poids_effectif
             FROM rh_evaluation_criteres ec
             JOIN rh_criteres_evaluation cr ON cr.id = ec.critere_id
             JOIN rh_evaluations e ON e.id = ec.evaluation_id
             JOIN rh_campagne_criteres cc ON cc.campagne_id = e.campagne_id AND cc.critere_id = ec.critere_id
             WHERE ec.evaluation_id = :id
             ORDER BY cc.ordre, cr.ordre'
        );
        $stmt->execute([':id' => $evaluationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findHistorique(int $evaluationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM rh_evaluation_historique
             WHERE evaluation_id = :id ORDER BY created_at ASC'
        );
        $stmt->execute([':id' => $evaluationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findPlans(int $evaluationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM rh_plans_developpement WHERE evaluation_id = :id ORDER BY created_at ASC'
        );
        $stmt->execute([':id' => $evaluationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertEvaluation(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rh_evaluations
             (campagne_id, employe_id, affectation_id, evaluateur_id, evaluateur_nom,
              statut, created_by, updated_by)
             VALUES (:cid,:eid,:aid,:evid,:evnom,:statut,:cb,:ub)'
        );
        $stmt->execute([
            ':cid'    => $data['campagne_id'],
            ':eid'    => $data['employe_id'],
            ':aid'    => $data['affectation_id'],
            ':evid'   => $data['evaluateur_id'],
            ':evnom'  => $data['evaluateur_nom'],
            ':statut' => 'brouillon',
            ':cb'     => $data['created_by'],
            ':ub'     => $data['created_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateStatut(int $id, string $statut, array $extra, int $userId): void
    {
        $sets   = ['statut = :statut', 'updated_by = :ub', 'updated_at = NOW()'];
        $params = [':statut' => $statut, ':ub' => $userId, ':id' => $id];
        foreach ($extra as $col => $val) {
            $sets[]        = "$col = :$col";
            $params[":$col"] = $val;
        }
        $this->pdo->prepare(
            'UPDATE rh_evaluations SET ' . implode(', ', $sets) . ' WHERE id = :id'
        )->execute($params);
    }

    public function updateScoreAutoEval(int $id, float $score, string $commentaire, int $userId): void
    {
        $this->pdo->prepare(
            'UPDATE rh_evaluations
             SET score_auto_eval = :s, commentaire_auto_eval = :c,
                 date_auto_eval = NOW(), updated_by = :ub, updated_at = NOW()
             WHERE id = :id'
        )->execute([':s' => $score, ':c' => $commentaire, ':ub' => $userId, ':id' => $id]);
    }

    public function updateScoreEvaluateur(int $id, float $score, string $commentaire, int $userId): void
    {
        $this->pdo->prepare(
            'UPDATE rh_evaluations
             SET score_evaluateur = :s, commentaire_evaluateur = :c,
                 date_evaluation = NOW(), updated_by = :ub, updated_at = NOW()
             WHERE id = :id'
        )->execute([':s' => $score, ':c' => $commentaire, ':ub' => $userId, ':id' => $id]);
    }

    public function upsertCritereScore(int $evalId, int $critereId, string $type, float $note, ?string $commentaire): void
    {
        if ($type === 'auto_eval') {
            $this->pdo->prepare(
                'INSERT INTO rh_evaluation_criteres (evaluation_id, critere_id, note_auto_eval, commentaire_auto_eval)
                 VALUES (:e, :c, :n, :com)
                 ON DUPLICATE KEY UPDATE note_auto_eval = :n2, commentaire_auto_eval = :com2'
            )->execute([':e'=>$evalId,':c'=>$critereId,':n'=>$note,':com'=>$commentaire,':n2'=>$note,':com2'=>$commentaire]);
        } else {
            $this->pdo->prepare(
                'INSERT INTO rh_evaluation_criteres (evaluation_id, critere_id, note_evaluateur, commentaire_evaluateur)
                 VALUES (:e, :c, :n, :com)
                 ON DUPLICATE KEY UPDATE note_evaluateur = :n2, commentaire_evaluateur = :com2'
            )->execute([':e'=>$evalId,':c'=>$critereId,':n'=>$note,':com'=>$commentaire,':n2'=>$note,':com2'=>$commentaire]);
        }
    }

    public function insertHistorique(int $evalId, ?string $avant, string $apres, string $action, ?string $commentaire, int $userId, string $userName): void
    {
        $this->pdo->prepare(
            'INSERT INTO rh_evaluation_historique
             (evaluation_id, statut_avant, statut_apres, action, commentaire, effectue_par, effectue_par_nom)
             VALUES (:eid,:av,:ap,:act,:com,:uid,:unom)'
        )->execute([
            ':eid'  => $evalId,
            ':av'   => $avant,
            ':ap'   => $apres,
            ':act'  => $action,
            ':com'  => $commentaire,
            ':uid'  => $userId,
            ':unom' => $userName,
        ]);
    }

    public function insertPlan(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rh_plans_developpement
             (evaluation_id, employe_id, objectif, actions, ressources, echeance, created_by)
             VALUES (:eid,:empid,:obj,:act,:res,:ech,:cb)'
        );
        $stmt->execute([
            ':eid'   => $data['evaluation_id'],
            ':empid' => $data['employe_id'],
            ':obj'   => $data['objectif'],
            ':act'   => $data['actions'],
            ':res'   => $data['ressources'],
            ':ech'   => $data['echeance'],
            ':cb'    => $data['created_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function statistiques(): array
    {
        $annee = (int)date('Y');
        $global = $this->pdo->prepare(
            'SELECT
               (SELECT COUNT(*) FROM rh_evaluations e
                JOIN rh_campagnes_evaluation c ON c.id = e.campagne_id
                WHERE c.annee = :y AND e.deleted_at IS NULL AND e.statut = \'soumise\') AS en_attente,
               (SELECT COUNT(*) FROM rh_evaluations e
                JOIN rh_campagnes_evaluation c ON c.id = e.campagne_id
                WHERE c.annee = :y2 AND e.deleted_at IS NULL AND e.statut = \'validee\') AS validees,
               (SELECT COUNT(*) FROM rh_evaluations e
                JOIN rh_campagnes_evaluation c ON c.id = e.campagne_id
                WHERE c.annee = :y3 AND e.deleted_at IS NULL AND e.statut = \'publiee\') AS publiees,
               (SELECT COUNT(*) FROM rh_evaluations e
                JOIN rh_campagnes_evaluation c ON c.id = e.campagne_id
                WHERE c.annee = :y4 AND e.deleted_at IS NULL) AS total,
               (SELECT ROUND(AVG(score_final),2) FROM rh_evaluations e
                JOIN rh_campagnes_evaluation c ON c.id = e.campagne_id
                WHERE c.annee = :y5 AND e.deleted_at IS NULL AND e.score_final IS NOT NULL) AS score_moyen,
               (SELECT COUNT(*) FROM rh_campagnes_evaluation
                WHERE annee = :y6 AND statut = \'active\' AND deleted_at IS NULL) AS campagnes_actives'
        );
        $global->execute([':y'=>$annee,':y2'=>$annee,':y3'=>$annee,':y4'=>$annee,':y5'=>$annee,':y6'=>$annee]);
        $stats = $global->fetch(PDO::FETCH_ASSOC);

        $parMention = $this->pdo->prepare(
            'SELECT mention, COUNT(*) AS nb
             FROM rh_evaluations e
             JOIN rh_campagnes_evaluation c ON c.id = e.campagne_id
             WHERE c.annee = :y AND e.deleted_at IS NULL AND mention IS NOT NULL
             GROUP BY mention ORDER BY nb DESC'
        );
        $parMention->execute([':y' => $annee]);
        $stats['par_mention'] = $parMention->fetchAll(PDO::FETCH_ASSOC);

        $parStatut = $this->pdo->prepare(
            'SELECT statut, COUNT(*) AS nb
             FROM rh_evaluations e
             JOIN rh_campagnes_evaluation c ON c.id = e.campagne_id
             WHERE c.annee = :y AND e.deleted_at IS NULL
             GROUP BY statut'
        );
        $parStatut->execute([':y' => $annee]);
        $stats['par_statut'] = $parStatut->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    }
}
