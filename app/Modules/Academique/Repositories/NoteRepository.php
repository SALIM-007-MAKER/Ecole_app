<?php

namespace App\Modules\Academique\Repositories;

use Core\Database;

class NoteRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ─── Lecture ─────────────────────────────────────────────────────────────

    public function findWithDetails(int $noteId): ?object
    {
        $sql = "SELECT n.*,
                       e.nom AS eleve_nom, e.prenom AS eleve_prenom, e.matricule,
                       ev.libelle AS eval_libelle, ev.note_max, ev.coefficient,
                       ev.statut AS eval_statut, ev.notes_saisie_ouverte,
                       ev.classe_id, ev.matiere_id, ev.periode_scolaire_id,
                       te.nom AS type_nom, te.couleur AS type_couleur, te.icone AS type_icone,
                       m.nom AS matiere_nom,
                       c.nom AS classe_nom
                FROM notes_v2 n
                JOIN eleves e     ON e.id = n.eleve_id
                JOIN evaluations ev ON ev.id = n.evaluation_id
                LEFT JOIN types_evaluations te ON te.id = ev.type_evaluation_id
                LEFT JOIN matieres m  ON m.id = ev.matiere_id
                LEFT JOIN classes  c  ON c.id = ev.classe_id
                WHERE n.id = ?
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$noteId]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function listElevesAvecNotes(int $evaluationId): array
    {
        $sql = "SELECT e.id AS eleve_id, e.nom, e.prenom, e.matricule,
                       n.id AS note_id, n.valeur, n.est_absent, n.commentaire,
                       n.statut, n.updated_at, n.created_by
                FROM evaluations ev
                JOIN eleves e ON e.classe_id = ev.classe_id
                LEFT JOIN notes_v2 n
                    ON n.eleve_id = e.id AND n.evaluation_id = ev.id
                WHERE ev.id = :eval_id
                ORDER BY e.nom, e.prenom";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':eval_id' => $evaluationId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function paginate(array $filters, int $page = 1, int $perPage = 50): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $countSql = "SELECT COUNT(*) FROM notes_v2 n {$where}";
        $total    = (int)$this->pdo->prepare($countSql)->execute($params) ? null : 0;

        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql    = "SELECT n.*,
                          e.nom AS eleve_nom, e.prenom AS eleve_prenom, e.matricule,
                          ev.libelle AS eval_libelle, ev.note_max
                   FROM notes_v2 n
                   JOIN eleves e       ON e.id = n.eleve_id
                   JOIN evaluations ev ON ev.id = n.evaluation_id
                   {$where}
                   ORDER BY e.nom, e.prenom
                   LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return [
            'data'         => $stmt->fetchAll(\PDO::FETCH_OBJ),
            'total'        => $total,
            'current_page' => $page,
            'last_page'    => max(1, (int)ceil($total / $perPage)),
            'per_page'     => $perPage,
        ];
    }

    public function countStats(int $evaluationId): array
    {
        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(statut = 'saisie')      AS saisies,
                    SUM(statut = 'publiee')     AS publiees,
                    SUM(statut = 'verrouillee') AS verrouillees,
                    SUM(est_absent = 1)         AS absents,
                    SUM(est_absent = 0 AND valeur IS NOT NULL) AS avec_note,
                    MIN(valeur) AS note_min,
                    MAX(valeur) AS note_max_val,
                    AVG(valeur) AS note_moyenne
                FROM notes_v2
                WHERE evaluation_id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$evaluationId]);
        return (array)($stmt->fetch(\PDO::FETCH_OBJ) ?: new \stdClass());
    }

    public function countElevesInClasse(int $evaluationId): int
    {
        $sql = "SELECT COUNT(e.id)
                FROM evaluations ev
                JOIN eleves e ON e.classe_id = ev.classe_id
                WHERE ev.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$evaluationId]);
        return (int)$stmt->fetchColumn();
    }

    // ─── Historique ──────────────────────────────────────────────────────────

    public function insertHistorique(
        int    $noteId,
        ?float $valeurAvant,
        ?float $valeurApres,
        int    $absentAvant,
        int    $absentApres,
        ?string $commentaire,
        int    $modifiePar
    ): void {
        $sql = "INSERT INTO notes_historique
                    (note_id, valeur_avant, valeur_apres, absent_avant, absent_apres,
                     commentaire, modifie_par)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $this->pdo->prepare($sql)->execute([
            $noteId, $valeurAvant, $valeurApres,
            $absentAvant, $absentApres,
            $commentaire, $modifiePar,
        ]);
    }

    public function getHistorique(int $noteId): array
    {
        $sql = "SELECT h.*, CONCAT(COALESCE(u.prenom,''), ' ', COALESCE(u.nom,'')) AS modifie_par_nom
            FROM notes_historique h
            LEFT JOIN users u ON u.id = h.modifie_par
            WHERE h.note_id = ?
            ORDER BY h.modifie_le DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$noteId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    // ─── Privé ───────────────────────────────────────────────────────────────

    private function buildWhere(array $f): array
    {
        $conds  = [];
        $params = [];

        if (!empty($f['evaluation_id'])) {
            $conds[]  = 'n.evaluation_id = ?';
            $params[] = (int)$f['evaluation_id'];
        }
        if (!empty($f['eleve_id'])) {
            $conds[]  = 'n.eleve_id = ?';
            $params[] = (int)$f['eleve_id'];
        }
        if (!empty($f['statut'])) {
            $conds[]  = 'n.statut = ?';
            $params[] = $f['statut'];
        }
        if (($f['presence'] ?? 'all') === 'absent') {
            $conds[] = 'n.est_absent = 1';
        } elseif (($f['presence'] ?? 'all') === 'present') {
            $conds[] = 'n.est_absent = 0';
        }

        $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
        return [$where, $params];
    }
}
