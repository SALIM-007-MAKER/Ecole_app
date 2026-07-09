<?php

namespace App\Modules\Academique\Repositories;

use Core\Database;
use PDO;

class PeriodeScolaireRepository
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
            "SELECT COUNT(*) FROM `periodes_scolaires` ps {$where}"
        );
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt   = $this->pdo->prepare(
            "SELECT ps.*
             FROM `periodes_scolaires` ps
             {$where}
             ORDER BY ps.annee_scolaire DESC, ps.ordre ASC, ps.numero ASC
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

    public function findWithStats(int $id): ?\stdClass
    {
        $stmt = $this->pdo->prepare(
            "SELECT ps.*,
                    (SELECT COUNT(*) FROM `controles` c WHERE c.periode_id = ps.id) AS nb_controles_v1,
                    (SELECT COUNT(*) FROM `notes`     n
                       JOIN `controles` c ON c.id = n.controle_id AND c.periode_id = ps.id
                    ) AS nb_notes_v1
             FROM `periodes_scolaires` ps
             WHERE ps.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    public function findAllWithStats(string $annee = ''): array
    {
        $where  = $annee !== '' ? "WHERE ps.annee_scolaire = ?" : '';
        $params = $annee !== '' ? [$annee] : [];

        $stmt = $this->pdo->prepare(
            "SELECT ps.*,
                    (SELECT COUNT(*) FROM `controles` c WHERE c.periode_id = ps.id) AS nb_controles_v1
             FROM `periodes_scolaires` ps
             {$where}
             ORDER BY ps.annee_scolaire DESC, ps.ordre ASC, ps.numero ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function findActive(?string $annee = null): ?\stdClass
    {
        if ($annee !== null) {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM `periodes_scolaires` WHERE is_active = 1 AND annee_scolaire = ? LIMIT 1"
            );
            $stmt->execute([$annee]);
        } else {
            $stmt = $this->pdo->query(
                "SELECT * FROM `periodes_scolaires` WHERE is_active = 1 ORDER BY annee_scolaire DESC LIMIT 1"
            );
        }
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    public function findForSelect(?string $annee = null): array
    {
        if ($annee !== null) {
            $stmt = $this->pdo->prepare(
                "SELECT id, nom, statut, is_active, annee_scolaire
                 FROM `periodes_scolaires`
                 WHERE annee_scolaire = ? AND statut != 'archivee'
                 ORDER BY ordre ASC, numero ASC"
            );
            $stmt->execute([$annee]);
        } else {
            $stmt = $this->pdo->query(
                "SELECT id, nom, statut, is_active, annee_scolaire
                 FROM `periodes_scolaires`
                 WHERE statut != 'archivee'
                 ORDER BY annee_scolaire DESC, ordre ASC, numero ASC"
            );
        }
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function listAnneesScolaires(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT annee_scolaire FROM `periodes_scolaires` ORDER BY annee_scolaire DESC"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);
        return array_column($rows, 'annee_scolaire');
    }

    public function existsForAnneeTypeNumero(
        string $annee,
        string $type,
        int    $numero,
        int    $excludeId = 0
    ): bool {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM `periodes_scolaires`
             WHERE annee_scolaire = ? AND type_periode = ? AND numero = ? AND id != ?"
        );
        $stmt->execute([$annee, $type, $numero, $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function nomExists(string $nom, string $annee, int $excludeId = 0): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM `periodes_scolaires`
             WHERE nom = ? AND annee_scolaire = ? AND id != ?"
        );
        $stmt->execute([$nom, $annee, $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function countStats(): array
    {
        $row = $this->pdo->query(
            "SELECT
                COUNT(*)                              AS total,
                SUM(statut = 'ouverte')               AS ouvertes,
                SUM(statut = 'fermee')                AS fermees,
                SUM(statut = 'verrouillee')           AS verrouillees,
                SUM(statut = 'archivee')              AS archivees,
                SUM(is_active = 1)                    AS actives,
                COUNT(DISTINCT annee_scolaire)        AS nb_annees
             FROM `periodes_scolaires`"
        )->fetch(PDO::FETCH_OBJ);

        return [
            'total'        => (int)($row->total        ?? 0),
            'ouvertes'     => (int)($row->ouvertes      ?? 0),
            'fermees'      => (int)($row->fermees       ?? 0),
            'verrouillees' => (int)($row->verrouillees  ?? 0),
            'archivees'    => (int)($row->archivees      ?? 0),
            'actives'      => (int)($row->actives        ?? 0),
            'nb_annees'    => (int)($row->nb_annees      ?? 0),
        ];
    }

    // ─── Helpers privés ──────────────────────────────────────────────────────

    private function buildWhere(array $filters): array
    {
        $conditions = [];
        $params     = [];

        if (!empty($filters['annee_scolaire'])) {
            $conditions[] = 'ps.annee_scolaire = ?';
            $params[]     = $filters['annee_scolaire'];
        }
        if (!empty($filters['statut'])) {
            $conditions[] = 'ps.statut = ?';
            $params[]     = $filters['statut'];
        }
        if (!empty($filters['type_periode'])) {
            $conditions[] = 'ps.type_periode = ?';
            $params[]     = $filters['type_periode'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$where, $params];
    }
}
