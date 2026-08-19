<?php

namespace App\Modules\Finance\Repositories;

use Core\Database;
use PDO;

class FraisRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ─── Listing paginé des types de frais ───────────────────────────────────

    public function paginate(
        string $q              = '',
        string $statut         = 'actif',
        string $periodicite    = '',
        string $estObligatoire = '',
        int    $categorieId    = 0,
        string $anneeScolaire  = '',
        string $niveau         = '',
        int    $page           = 1,
        int    $perPage        = 25
    ): array {
        $where  = [];
        $params = [];

        if ($q !== '') {
            $where[]  = '(fft.nom LIKE ? OR fft.code LIKE ? OR fft.description LIKE ?)';
            $like     = '%' . $q . '%';
            $params   = array_merge($params, [$like, $like, $like]);
        }
        if ($statut !== '') {
            $where[]  = 'fft.statut = ?';
            $params[] = $statut;
        }
        if ($periodicite !== '') {
            $where[]  = 'fft.periodicite = ?';
            $params[] = $periodicite;
        }
        if ($estObligatoire !== '') {
            $where[]  = 'fft.est_obligatoire = ?';
            $params[] = (int)$estObligatoire;
        }
        if ($categorieId > 0) {
            $where[]  = 'fft.categorie_id = ?';
            $params[] = $categorieId;
        }
        if ($anneeScolaire !== '') {
            $where[]  = '(fft.annee_scolaire IS NULL OR fft.annee_scolaire = ?)';
            $params[] = $anneeScolaire;
        }
        if ($niveau !== '') {
            $where[]  = "JSON_CONTAINS(fft.niveaux_cibles, JSON_QUOTE(?)) OR fft.niveaux_cibles IS NULL";
            $params[] = $niveau;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset   = ($page - 1) * $perPage;

        $stmtCount = $this->pdo->prepare(
            "SELECT COUNT(*) FROM `finance_frais_types` fft $whereSql"
        );
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $stmtData = $this->pdo->prepare(
            "SELECT fft.*,
                    fcf.nom     AS categorie_nom,
                    fcf.couleur AS categorie_couleur,
                    fcf.icone   AS categorie_icone,
                    (SELECT COUNT(*) FROM `finance_tarifs` ft WHERE ft.frais_type_id = fft.id) AS nb_tarifs
             FROM `finance_frais_types` fft
             LEFT JOIN `finance_categories_frais` fcf ON fcf.id = fft.categorie_id
             $whereSql
             ORDER BY fft.nom ASC
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

    // ─── Détail d'un type de frais ────────────────────────────────────────────

    public function findWithDetails(int $id): ?\stdClass
    {
        $stmt = $this->pdo->prepare(
            "SELECT fft.*,
                    fcf.nom     AS categorie_nom,
                    fcf.couleur AS categorie_couleur,
                    fcf.code    AS categorie_code
             FROM `finance_frais_types` fft
             LEFT JOIN `finance_categories_frais` fcf ON fcf.id = fft.categorie_id
             WHERE fft.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    // ─── Tarifs d'un type de frais ────────────────────────────────────────────

    public function getTarifs(int $fraisTypeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT ft.*, c.nom AS classe_nom, c.niveau AS classe_niveau
             FROM `finance_tarifs` ft
             LEFT JOIN `classes` c ON c.id = ft.classe_id
             WHERE ft.frais_type_id = ?
             ORDER BY ft.annee_scolaire DESC, " . \App\Models\ClasseModel::ordreNiveauSql('ft.niveau') . ", c.nom"
        );
        $stmt->execute([$fraisTypeId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    // ─── Historique d'un type de frais ────────────────────────────────────────

    public function getHistorique(int $fraisTypeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT fh.*, u.nom AS user_nom, u.prenom AS user_prenom
             FROM `finance_historique` fh
             LEFT JOIN `users` u ON u.id = fh.user_id
             WHERE fh.entite_type = 'frais_type' AND fh.entite_id = ?
             ORDER BY fh.created_at DESC
             LIMIT 50"
        );
        $stmt->execute([$fraisTypeId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    // ─── Contrainte unicité (année + catégorie + niveaux) ─────────────────────

    /**
     * Vérifie qu'il n'existe pas déjà un frais avec les mêmes
     * catégorie_id, annee_scolaire et niveaux_cibles (règle métier #1).
     */
    public function existeDoublon(
        ?int    $categorieId,
        ?string $anneeScolaire,
        array   $niveauxCibles,
        int     $excludeId = 0
    ): bool {
        $whereAnnee   = $anneeScolaire !== null
            ? "AND fft.annee_scolaire = ?"
            : "AND fft.annee_scolaire IS NULL";
        $whereCat     = $categorieId !== null
            ? "AND fft.categorie_id = ?"
            : "AND fft.categorie_id IS NULL";

        $sql = "SELECT id FROM `finance_frais_types` fft
                WHERE fft.id != ? $whereCat $whereAnnee
                AND fft.statut != 'archive'";

        $params = [$excludeId];
        if ($categorieId !== null)   $params[] = $categorieId;
        if ($anneeScolaire !== null) $params[] = $anneeScolaire;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $candidats = $stmt->fetchAll(PDO::FETCH_OBJ);

        if (empty($candidats) || empty($niveauxCibles)) {
            return !empty($candidats) && empty($niveauxCibles);
        }

        // Vérifier le chevauchement de niveaux en PHP (JSON comparison)
        foreach ($candidats as $c) {
            if ($c->niveaux_cibles === null) {
                return true; // candidat couvre tous niveaux
            }
            $niveauxExist = json_decode($c->niveaux_cibles, true) ?? [];
            if (!empty(array_intersect($niveauxCibles, $niveauxExist))) {
                return true;
            }
        }

        return false;
    }

    // ─── Vérifier si un frais est utilisé dans une facture ───────────────────

    /**
     * Un frais utilisé dans une facture ne peut pas être supprimé (règle métier #2).
     * La table finance_lignes_facture est créée en Phase 3.1.3.
     */
    public function countUsageInFactures(int $fraisTypeId): int
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM `finance_lignes_facture` WHERE frais_type_id = ?"
            );
            $stmt->execute([$fraisTypeId]);
            return (int)$stmt->fetchColumn();
        } catch (\PDOException) {
            // Table pas encore créée (avant Phase 3.1.3) → 0 usage
            return 0;
        }
    }

    // ─── Stats globales ───────────────────────────────────────────────────────

    public function countStats(): array
    {
        $row = $this->pdo->query(
            "SELECT
                COUNT(*)                          AS total,
                SUM(statut = 'actif')             AS actifs,
                SUM(statut = 'inactif')           AS inactifs,
                SUM(statut = 'archive')           AS archives,
                SUM(est_obligatoire = 1)          AS obligatoires,
                SUM(est_obligatoire = 0)          AS optionnels
             FROM `finance_frais_types`"
        )->fetch(PDO::FETCH_OBJ);

        $nbCats = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM `finance_categories_frais` WHERE actif = 1"
        )->fetchColumn();

        return [
            'total'         => (int)($row->total ?? 0),
            'actifs'        => (int)($row->actifs ?? 0),
            'inactifs'      => (int)($row->inactifs ?? 0),
            'archives'      => (int)($row->archives ?? 0),
            'obligatoires'  => (int)($row->obligatoires ?? 0),
            'optionnels'    => (int)($row->optionnels ?? 0),
            'nb_categories' => $nbCats,
        ];
    }

    // ─── Catégories ───────────────────────────────────────────────────────────

    public function paginateCategories(int $page = 1, int $perPage = 25): array
    {
        $offset = ($page - 1) * $perPage;
        $total  = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM `finance_categories_frais`"
        )->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT fcf.*,
                    COUNT(fft.id) AS nb_frais
             FROM `finance_categories_frais` fcf
             LEFT JOIN `finance_frais_types` fft ON fft.categorie_id = fcf.id
             GROUP BY fcf.id
             ORDER BY fcf.nom
             LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute();

        return [
            'data'        => $stmt->fetchAll(PDO::FETCH_OBJ),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $total > 0 ? (int)ceil($total / $perPage) : 1,
        ];
    }

    // ─── Tarifs — upsert ─────────────────────────────────────────────────────

    public function findTarif(int $fraisTypeId, string $anneeScolaire, ?string $niveau, ?int $classeId): ?\stdClass
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `finance_tarifs`
             WHERE frais_type_id = ?
               AND annee_scolaire = ?
               AND (niveau  <=> ?)
               AND (classe_id <=> ?)"
        );
        $stmt->execute([$fraisTypeId, $anneeScolaire, $niveau, $classeId]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    public function updateTarif(int $tarifId, float $montant, string $devise): void
    {
        $this->pdo->prepare(
            "UPDATE `finance_tarifs` SET montant = ?, devise = ? WHERE id = ?"
        )->execute([$montant, $devise, $tarifId]);
    }

    public function insertTarif(
        int     $fraisTypeId,
        string  $anneeScolaire,
        ?string $niveau,
        ?int    $classeId,
        float   $montant,
        string  $devise
    ): void {
        $this->pdo->prepare(
            "INSERT INTO `finance_tarifs`
                (frais_type_id, annee_scolaire, niveau, classe_id, montant, devise)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([$fraisTypeId, $anneeScolaire, $niveau, $classeId, $montant, $devise]);
    }

    // ─── Enregistrer l'historique Finance ─────────────────────────────────────

    public function logHistorique(
        string $entiteType,
        int    $entiteId,
        string $action,
        ?array $avant,
        ?array $apres,
        ?int   $userId
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO `finance_historique`
                (entite_type, entite_id, action, avant, apres, user_id)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $entiteType,
            $entiteId,
            $action,
            $avant !== null ? json_encode($avant, JSON_UNESCAPED_UNICODE) : null,
            $apres !== null ? json_encode($apres, JSON_UNESCAPED_UNICODE) : null,
            $userId,
        ]);
    }
}
