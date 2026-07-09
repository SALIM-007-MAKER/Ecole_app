<?php

namespace App\Modules\Scolarite\Repositories;

use Core\Database;
use PDO;

class FamilleRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ─── Listing ──────────────────────────────────────────────────────────────

    public function paginate(
        string $q       = '',
        string $actif   = '1',
        int    $page    = 1,
        int    $perPage = 20
    ): array {
        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[]  = '(f.nom LIKE ? OR f.telephone LIKE ? OR f.email LIKE ? OR f.ville LIKE ?)';
            $like     = '%' . $q . '%';
            $params   = array_merge($params, [$like, $like, $like, $like]);
        }

        if ($actif !== '') {
            $where[]  = 'f.actif = ?';
            $params[] = (int)$actif;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset   = ($page - 1) * $perPage;

        $total = (int)$this->pdo->prepare(
            "SELECT COUNT(*) FROM `familles` f $whereSql"
        )->execute($params) ? $this->pdo->query(
            "SELECT COUNT(*) FROM `familles` f $whereSql"
        )->fetchColumn() : 0;

        // Recompute total properly
        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM `familles` f $whereSql");
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $stmtData = $this->pdo->prepare(
            "SELECT f.*,
                    COUNT(DISTINCT fe.eleve_id) AS nb_eleves
             FROM `familles` f
             LEFT JOIN `familles_eleves` fe ON fe.famille_id = f.id
             $whereSql
             GROUP BY f.id
             ORDER BY f.nom ASC
             LIMIT $perPage OFFSET $offset"
        );
        $stmtData->execute($params);
        $data = $stmtData->fetchAll(PDO::FETCH_OBJ);

        return [
            'data'        => $data,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $total > 0 ? (int)ceil($total / $perPage) : 1,
        ];
    }

    // ─── Détail ───────────────────────────────────────────────────────────────

    public function findWithDetails(int $id): ?\stdClass
    {
        $stmt = $this->pdo->prepare(
            "SELECT f.*,
                    u.prenom AS created_by_prenom, u.nom AS created_by_nom
             FROM `familles` f
             LEFT JOIN `users` u ON u.id = f.created_by
             WHERE f.id = ?"
        );
        $stmt->execute([$id]);
        $famille = $stmt->fetch(PDO::FETCH_OBJ) ?: null;

        if (!$famille) {
            return null;
        }

        // Charger les élèves rattachés avec les détails du lien
        $stmt2 = $this->pdo->prepare(
            "SELECT e.id, e.nom, e.prenom, e.matricule, e.photo,
                    c.nom AS classe_nom,
                    fe.lien_parente, fe.est_responsable_legal,
                    fe.est_contact_principal, fe.est_contact_urgence, fe.ordre,
                    fe.id AS lien_id
             FROM `familles_eleves` fe
             JOIN `eleves` e ON e.id = fe.eleve_id
             LEFT JOIN `classes` c ON c.id = e.classe_id
             WHERE fe.famille_id = ?
             ORDER BY fe.ordre ASC, e.nom ASC, e.prenom ASC"
        );
        $stmt2->execute([$id]);
        $famille->eleves = $stmt2->fetchAll(PDO::FETCH_OBJ);

        return $famille;
    }

    // ─── Par élève ────────────────────────────────────────────────────────────

    /** Tous les responsables (familles) d'un élève. */
    public function findByEleve(int $eleveId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT f.*,
                    fe.lien_parente, fe.est_responsable_legal,
                    fe.est_contact_principal, fe.est_contact_urgence, fe.ordre,
                    fe.id AS lien_id
             FROM `familles_eleves` fe
             JOIN `familles` f ON f.id = fe.famille_id
             WHERE fe.eleve_id = ?
             ORDER BY fe.ordre ASC, f.nom ASC"
        );
        $stmt->execute([$eleveId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    // ─── Élèves disponibles ───────────────────────────────────────────────────

    /** Élèves non encore rattachés à cette famille. */
    public function findElevesDisponibles(int $familleId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.id, e.nom, e.prenom, e.matricule,
                    c.nom AS classe_nom
             FROM `eleves` e
             LEFT JOIN `classes` c ON c.id = e.classe_id
             WHERE e.id NOT IN (
                 SELECT eleve_id FROM `familles_eleves` WHERE famille_id = ?
             )
             AND e.actif = 1
             ORDER BY e.nom ASC, e.prenom ASC"
        );
        $stmt->execute([$familleId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    // ─── Statistiques ─────────────────────────────────────────────────────────

    public function countStats(): array
    {
        $row = $this->pdo->query(
            "SELECT
                COUNT(*) AS total,
                SUM(actif = 1) AS actives,
                SUM(actif = 0) AS archivees
             FROM `familles`"
        )->fetch(PDO::FETCH_OBJ);

        $avecEleves = (int)$this->pdo->query(
            "SELECT COUNT(DISTINCT famille_id) FROM `familles_eleves`"
        )->fetchColumn();

        return [
            'total'      => (int)($row->total ?? 0),
            'actives'    => (int)($row->actives ?? 0),
            'archivees'  => (int)($row->archivees ?? 0),
            'avec_eleves'=> $avecEleves,
        ];
    }

    // ─── Recherche ────────────────────────────────────────────────────────────

    /** Autocomplete : retourne id + label pour les selects. */
    public function search(string $q, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, nom, telephone, ville
             FROM `familles`
             WHERE actif = 1 AND (nom LIKE ? OR telephone LIKE ?)
             ORDER BY nom ASC
             LIMIT $limit"
        );
        $like = '%' . $q . '%';
        $stmt->execute([$like, $like]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    // ─── Vérification unicité lien ────────────────────────────────────────────

    public function lienExiste(int $familleId, int $eleveId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM `familles_eleves` WHERE famille_id = ? AND eleve_id = ?"
        );
        $stmt->execute([$familleId, $eleveId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // ─── Fratries ─────────────────────────────────────────────────────────────

    /** Élèves dans la même famille (fratrie) — exclut l'élève donné. */
    public function getFratrie(int $eleveId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.id, e.nom, e.prenom, e.matricule,
                    c.nom AS classe_nom, fe2.lien_parente
             FROM `familles_eleves` fe1
             JOIN `familles_eleves` fe2 ON fe2.famille_id = fe1.famille_id
                                        AND fe2.eleve_id != fe1.eleve_id
             JOIN `eleves` e ON e.id = fe2.eleve_id
             LEFT JOIN `classes` c ON c.id = e.classe_id
             WHERE fe1.eleve_id = ?
             ORDER BY e.nom ASC, e.prenom ASC"
        );
        $stmt->execute([$eleveId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
