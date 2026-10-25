<?php

namespace App\Modules\Academique\Repositories;

use Core\Database;
use PDO;

class EvaluationRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ─── Lecture ─────────────────────────────────────────────────────────────

    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT ev.id)
             FROM `evaluations` ev
             JOIN `periodes_scolaires` ps ON ps.id = ev.periode_scolaire_id
             JOIN `types_evaluations` te  ON te.id = ev.type_evaluation_id
             JOIN `matieres` m            ON m.id  = ev.matiere_id
             JOIN `classes` cl            ON cl.id = ev.classe_id
             {$where}"
        );
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt   = $this->pdo->prepare(
            "SELECT ev.*,
                    ps.nom          AS periode_nom,
                    ps.annee_scolaire,
                    te.nom          AS type_nom,
                    te.couleur      AS type_couleur,
                    te.icone        AS type_icone,
                    te.code         AS type_code,
                    m.nom           AS matiere_nom,
                    m.est_conduite  AS matiere_est_conduite,
                    m.coefficient   AS matiere_coef,
                    cl.nom          AS classe_nom,
                    cl.niveau       AS classe_niveau,
                    CONCAT(pr.prenom, ' ', pr.nom) AS enseignant_nom
             FROM `evaluations` ev
             JOIN `periodes_scolaires` ps ON ps.id = ev.periode_scolaire_id
             JOIN `types_evaluations` te  ON te.id = ev.type_evaluation_id
             JOIN `matieres` m            ON m.id  = ev.matiere_id
             JOIN `classes` cl            ON cl.id = ev.classe_id
             LEFT JOIN `professeurs` pr   ON pr.id = ev.enseignant_id
             {$where}
             ORDER BY ev.date_evaluation DESC, " . \App\Models\ClasseModel::ordreNiveauSql('cl.niveau') . " ASC, m.nom ASC, ev.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return [
            'data'         => $stmt->fetchAll(PDO::FETCH_OBJ),
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $total > 0 ? (int)ceil($total / $perPage) : 1,
        ];
    }

    public function findWithDetails(int $id): ?\stdClass
    {
        $stmt = $this->pdo->prepare(
            "SELECT ev.*,
                    ps.nom          AS periode_nom,
                    ps.annee_scolaire,
                    ps.statut       AS periode_statut,
                    ps.notes_saisie_ouverte AS periode_saisie_ouverte,
                    te.nom          AS type_nom,
                    te.code         AS type_code,
                    te.couleur      AS type_couleur,
                    te.icone        AS type_icone,
                    te.est_eliminatoire AS type_eliminatoire,
                    m.nom           AS matiere_nom,
                    m.est_conduite  AS matiere_est_conduite,
                    m.coefficient   AS matiere_coef,
                    cl.nom          AS classe_nom,
                    cl.niveau       AS classe_niveau,
                    CONCAT(pr.prenom, ' ', pr.nom) AS enseignant_nom,
                    pr.specialite   AS enseignant_specialite
             FROM `evaluations` ev
             JOIN `periodes_scolaires` ps ON ps.id = ev.periode_scolaire_id
             JOIN `types_evaluations` te  ON te.id = ev.type_evaluation_id
             JOIN `matieres` m            ON m.id  = ev.matiere_id
             JOIN `classes` cl            ON cl.id = ev.classe_id
             LEFT JOIN `professeurs` pr   ON pr.id = ev.enseignant_id
             WHERE ev.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$row) return null;

        // Comptage notes V2 (table créée en Phase 2.4)
        $row->nb_notes = $this->countNotes($id);

        return $row;
    }

    public function countStats(): array
    {
        $row = $this->pdo->query(
            "SELECT
                COUNT(*)                           AS total,
                SUM(statut = 'brouillon')          AS brouillons,
                SUM(statut = 'publiee')            AS publiees,
                SUM(statut = 'verrouillee')        AS verrouillees,
                SUM(statut = 'archivee')           AS archivees,
                SUM(notes_saisie_ouverte = 1)      AS saisie_ouverte
             FROM `evaluations`"
        )->fetch(PDO::FETCH_OBJ);

        return [
            'total'         => (int)($row->total          ?? 0),
            'brouillons'    => (int)($row->brouillons     ?? 0),
            'publiees'      => (int)($row->publiees       ?? 0),
            'verrouillees'  => (int)($row->verrouillees   ?? 0),
            'archivees'     => (int)($row->archivees      ?? 0),
            'saisie_ouverte'=> (int)($row->saisie_ouverte ?? 0),
        ];
    }

    /**
     * Vérifie si des notes V2 ont été saisies pour cette évaluation.
     * La table notes_v2 sera créée en Phase 2.4 — try/catch pour compatibilité anticipée.
     */
    public function hasNotes(int $evaluationId): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM `notes_v2` WHERE evaluation_id = ?"
            );
            $stmt->execute([$evaluationId]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ─── Listes pour les sélecteurs (formulaires) ────────────────────────────

    public function listPeriodesForSelect(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, nom, annee_scolaire, statut, is_active
             FROM `periodes_scolaires`
             WHERE statut != 'archivee'
             ORDER BY annee_scolaire DESC, ordre ASC, numero ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function listTypesForSelect(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, code, nom, coefficient_defaut, note_max_defaut, couleur, icone
             FROM `types_evaluations`
             WHERE actif = 1 AND est_archive = 0
             ORDER BY ordre ASC, nom ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * @param bool $inclureConduite false pour exclure la matière « Conduite »
     *             du select (formulaire d'évaluation ouvert à un enseignant —
     *             circuit réservé au Directeur/Admin, voir EvaluationPolicy).
     */
    public function listMatieresForSelect(bool $inclureConduite = true): array
    {
        $sql = "SELECT id, nom, coefficient, est_conduite FROM `matieres`";
        if (!$inclureConduite) {
            $sql .= " WHERE est_conduite IS NULL";
        }
        $sql .= " ORDER BY nom ASC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_OBJ);
    }

    /** true / false selon le marqueur `est_conduite` de la matière ; null si la matière n'existe pas. */
    public function matiereEstConduite(int $matiereId): ?bool
    {
        $stmt = $this->pdo->prepare("SELECT est_conduite FROM `matieres` WHERE id = ?");
        $stmt->execute([$matiereId]);
        $v = $stmt->fetch(PDO::FETCH_NUM);
        return $v === false ? null : ((int)$v[0] === 1);
    }

    /**
     * Existe-t-il déjà, pour cette classe et cette période, une évaluation de Conduite non archivée
     * (brouillon compris) ? $exceptId exclut l'évaluation en cours de modification.
     */
    public function conduiteExisteDeja(int $classeId, int $periodeId, ?int $exceptId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM `evaluations` ev JOIN `matieres` m ON m.id = ev.matiere_id
                 WHERE m.est_conduite = 1 AND ev.classe_id = ? AND ev.periode_scolaire_id = ? AND ev.statut <> 'archivee'";
        $params = [$classeId, $periodeId];
        if ($exceptId !== null) { $sql .= " AND ev.id <> ?"; $params[] = $exceptId; }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function listClassesForSelect(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, nom, niveau
             FROM `classes`
             ORDER BY " . \App\Models\ClasseModel::ordreNiveauSql() . " ASC, nom ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function listEnseignantsForSelect(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id,
                    CONCAT(prenom, ' ', nom,
                        IF(specialite IS NOT NULL AND specialite != '', CONCAT(' (', specialite, ')'), '')) AS label
             FROM `professeurs`
             WHERE actif = 1
             ORDER BY nom ASC, prenom ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    // ─── Helpers privés ──────────────────────────────────────────────────────

    private function countNotes(int $evaluationId): int
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `notes_v2` WHERE evaluation_id = ?");
            $stmt->execute([$evaluationId]);
            return (int)$stmt->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function buildWhere(array $filters): array
    {
        $conditions = [];
        $params     = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(ev.libelle LIKE ? OR m.nom LIKE ? OR cl.nom LIKE ?)';
            $like         = '%' . $filters['search'] . '%';
            $params[]     = $like;
            $params[]     = $like;
            $params[]     = $like;
        }
        if (!empty($filters['statut'])) {
            $conditions[] = 'ev.statut = ?';
            $params[]     = $filters['statut'];
        }
        if (!empty($filters['periode_scolaire_id'])) {
            $conditions[] = 'ev.periode_scolaire_id = ?';
            $params[]     = (int)$filters['periode_scolaire_id'];
        }
        if (!empty($filters['classe_id'])) {
            $conditions[] = 'ev.classe_id = ?';
            $params[]     = (int)$filters['classe_id'];
        }
        if (!empty($filters['matiere_id'])) {
            $conditions[] = 'ev.matiere_id = ?';
            $params[]     = (int)$filters['matiere_id'];
        }
        if (!empty($filters['type_evaluation_id'])) {
            $conditions[] = 'ev.type_evaluation_id = ?';
            $params[]     = (int)$filters['type_evaluation_id'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$where, $params];
    }
}
