<?php

declare(strict_types=1);

namespace App\Modules\RH\Organisation\Repositories;

use PDO;
use App\Core\Database;
use App\Modules\RH\Organisation\DTO\OrganizationFiltersDTO;

class OrganizationRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ── Départements ──────────────────────────────────────────────────────────

    public function findAllDepartements(OrganizationFiltersDTO $f): array
    {
        [$where, $params] = $this->deptWhere($f);
        $sql = "SELECT d.*,
                       e.prenom AS resp_prenom, e.nom AS resp_nom,
                       p.nom AS parent_nom,
                       (SELECT COUNT(*) FROM rh_employes em WHERE em.departement_id = d.id AND em.deleted_at IS NULL) AS nb_employes,
                       (SELECT COUNT(*) FROM rh_services  s  WHERE s.departement_id = d.id AND s.deleted_at IS NULL) AS nb_services
                FROM rh_departements d
                LEFT JOIN rh_employes   e ON e.id = d.responsable_id AND e.deleted_at IS NULL
                LEFT JOIN rh_departements p ON p.id = d.parent_id
                WHERE $where
                ORDER BY d.ordre_affichage ASC, d.nom ASC
                LIMIT :limit OFFSET :offset";
        $params[':limit']  = $f->perPage;
        $params[':offset'] = $f->offset();
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countDepartements(OrganizationFiltersDTO $f): int
    {
        [$where, $params] = $this->deptWhere($f);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM rh_departements d WHERE $where");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function findDepartementById(int $id, bool $withArchived = false): ?array
    {
        $cond = $withArchived ? '' : 'AND d.deleted_at IS NULL';
        $sql = "SELECT d.*,
                       e.prenom AS resp_prenom, e.nom AS resp_nom,
                       p.nom AS parent_nom
                FROM rh_departements d
                LEFT JOIN rh_employes   e ON e.id = d.responsable_id
                LEFT JOIN rh_departements p ON p.id = d.parent_id
                WHERE d.id = :id $cond";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function codeExistsDept(string $code, int $excludeId = 0): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM rh_departements WHERE code = :code AND id != :ex"
        );
        $stmt->execute([':code' => $code, ':ex' => $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function insertDepartement(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO rh_departements
             (nom, code, description, responsable_id, parent_id, budget_centre, ordre_affichage, actif)
             VALUES (:nom, :code, :description, :responsable_id, :parent_id, :budget_centre, :ordre_affichage, :actif)"
        );
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateDepartement(int $id, array $data): void
    {
        $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
        $data['id'] = $id;
        $this->pdo->prepare("UPDATE rh_departements SET $sets, updated_at = NOW() WHERE id = :id")
                  ->execute($data);
    }

    public function softDeleteDepartement(int $id): void
    {
        $this->pdo->prepare("UPDATE rh_departements SET deleted_at = NOW(), actif = 0 WHERE id = :id")
                  ->execute([':id' => $id]);
    }

    public function restoreDepartement(int $id): void
    {
        $this->pdo->prepare("UPDATE rh_departements SET deleted_at = NULL, actif = 1 WHERE id = :id")
                  ->execute([':id' => $id]);
    }

    /** Arbre hiérarchique complet pour l'organigramme */
    public function findDepartementsTree(): array
    {
        $stmt = $this->pdo->query(
            "SELECT d.id, d.nom, d.code, d.description, d.parent_id, d.ordre_affichage, d.actif,
                    e.prenom AS resp_prenom, e.nom AS resp_nom,
                    (SELECT COUNT(*) FROM rh_employes em WHERE em.departement_id = d.id AND em.deleted_at IS NULL) AS nb_employes
             FROM rh_departements d
             LEFT JOIN rh_employes e ON e.id = d.responsable_id AND e.deleted_at IS NULL
             WHERE d.deleted_at IS NULL
             ORDER BY d.ordre_affichage ASC, d.parent_id ASC NULLS FIRST, d.nom ASC"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->buildTree($rows);
    }

    /** Liste plate des départements actifs (pour selects) */
    public function findDepartementsActifs(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, nom, code FROM rh_departements WHERE deleted_at IS NULL AND actif = 1 ORDER BY ordre_affichage ASC, nom ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Services ──────────────────────────────────────────────────────────────

    public function findServicesByDepartement(int $deptId, bool $withArchived = false): array
    {
        $cond = $withArchived ? '' : 'AND s.deleted_at IS NULL';
        $sql = "SELECT s.*,
                       e.prenom AS resp_prenom, e.nom AS resp_nom,
                       (SELECT COUNT(*) FROM rh_employes em WHERE em.departement_id = s.departement_id AND em.deleted_at IS NULL) AS nb_employes
                FROM rh_services s
                LEFT JOIN rh_employes e ON e.id = s.responsable_id AND e.deleted_at IS NULL
                WHERE s.departement_id = :deptId $cond
                ORDER BY s.ordre_affichage ASC, s.nom ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':deptId' => $deptId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findServiceById(int $id, bool $withArchived = false): ?array
    {
        $cond = $withArchived ? '' : 'AND s.deleted_at IS NULL';
        $sql = "SELECT s.*, d.nom AS departement_nom, d.code AS departement_code,
                       e.prenom AS resp_prenom, e.nom AS resp_nom
                FROM rh_services s
                JOIN rh_departements d ON d.id = s.departement_id
                LEFT JOIN rh_employes e ON e.id = s.responsable_id
                WHERE s.id = :id $cond";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function codeExistsService(string $code, int $excludeId = 0): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM rh_services WHERE code = :code AND id != :ex"
        );
        $stmt->execute([':code' => $code, ':ex' => $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function insertService(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO rh_services
             (departement_id, nom, code, description, responsable_id, ordre_affichage, actif)
             VALUES (:departement_id, :nom, :code, :description, :responsable_id, :ordre_affichage, :actif)"
        );
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateService(int $id, array $data): void
    {
        $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
        $data['id'] = $id;
        $this->pdo->prepare("UPDATE rh_services SET $sets, updated_at = NOW() WHERE id = :id")
                  ->execute($data);
    }

    public function softDeleteService(int $id): void
    {
        $this->pdo->prepare("UPDATE rh_services SET deleted_at = NOW(), actif = 0 WHERE id = :id")
                  ->execute([':id' => $id]);
    }

    public function restoreService(int $id): void
    {
        $this->pdo->prepare("UPDATE rh_services SET deleted_at = NULL, actif = 1 WHERE id = :id")
                  ->execute([':id' => $id]);
    }

    public function findServicesActifs(?int $deptId = null): array
    {
        $where = 'deleted_at IS NULL AND actif = 1';
        $params = [];
        if ($deptId !== null) {
            $where .= ' AND departement_id = :deptId';
            $params[':deptId'] = $deptId;
        }
        $stmt = $this->pdo->prepare("SELECT id, nom, code, departement_id FROM rh_services WHERE $where ORDER BY nom ASC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Postes ────────────────────────────────────────────────────────────────

    public function findAllPostes(OrganizationFiltersDTO $f): array
    {
        [$where, $params] = $this->posteWhere($f);
        $sql = "SELECT p.*, d.nom AS departement_nom, s.nom AS service_nom,
                       (SELECT COUNT(*) FROM rh_employes em WHERE em.poste_id = p.id AND em.deleted_at IS NULL) AS nb_occupants
                FROM rh_postes p
                LEFT JOIN rh_departements d ON d.id = p.departement_id
                LEFT JOIN rh_services     s ON s.id = p.service_id
                WHERE $where
                ORDER BY p.categorie ASC, p.niveau DESC, p.intitule ASC
                LIMIT :limit OFFSET :offset";
        $params[':limit']  = $f->perPage;
        $params[':offset'] = $f->offset();
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countPostes(OrganizationFiltersDTO $f): int
    {
        [$where, $params] = $this->posteWhere($f);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM rh_postes p LEFT JOIN rh_departements d ON d.id = p.departement_id WHERE $where");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function findPosteById(int $id, bool $withArchived = false): ?array
    {
        $cond = $withArchived ? '' : 'AND p.deleted_at IS NULL';
        $sql = "SELECT p.*, d.nom AS departement_nom, s.nom AS service_nom
                FROM rh_postes p
                LEFT JOIN rh_departements d ON d.id = p.departement_id
                LEFT JOIN rh_services     s ON s.id = p.service_id
                WHERE p.id = :id $cond";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function codeExistsPoste(string $code, int $excludeId = 0): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM rh_postes WHERE code = :code AND id != :ex"
        );
        $stmt->execute([':code' => $code, ':ex' => $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function insertPoste(array $data): int
    {
        $cols = implode(', ', array_keys($data));
        $plh  = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
        $stmt = $this->pdo->prepare("INSERT INTO rh_postes ($cols) VALUES ($plh)");
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function updatePoste(int $id, array $data): void
    {
        $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
        $data['id'] = $id;
        $this->pdo->prepare("UPDATE rh_postes SET $sets, updated_at = NOW() WHERE id = :id")
                  ->execute($data);
    }

    public function softDeletePoste(int $id): void
    {
        // Bloquer si des employés occupent actuellement le poste
        $this->pdo->prepare("UPDATE rh_postes SET deleted_at = NOW(), actif = 0 WHERE id = :id")
                  ->execute([':id' => $id]);
    }

    public function restorePoste(int $id): void
    {
        $this->pdo->prepare("UPDATE rh_postes SET deleted_at = NULL, actif = 1 WHERE id = :id")
                  ->execute([':id' => $id]);
    }

    public function countEmployesOnPoste(int $posteId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM rh_employes WHERE poste_id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([':id' => $posteId]);
        return (int)$stmt->fetchColumn();
    }

    // ── Fonctions ─────────────────────────────────────────────────────────────

    public function findAllFonctions(bool $withArchived = false): array
    {
        $cond = $withArchived ? '' : 'WHERE deleted_at IS NULL';
        $stmt = $this->pdo->query(
            "SELECT f.*,
                    (SELECT COUNT(*) FROM rh_employe_fonctions ef WHERE ef.fonction_id = f.id) AS nb_affectations
             FROM rh_fonctions f $cond ORDER BY f.niveau DESC, f.nom ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findFonctionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM rh_fonctions WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function insertFonction(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO rh_fonctions (nom, code, description, niveau, perimetre, actif)
             VALUES (:nom, :code, :description, :niveau, :perimetre, :actif)"
        );
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateFonction(int $id, array $data): void
    {
        $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
        $data['id'] = $id;
        $this->pdo->prepare("UPDATE rh_fonctions SET $sets, updated_at = NOW() WHERE id = :id")
                  ->execute($data);
    }

    public function softDeleteFonction(int $id): void
    {
        $this->pdo->prepare("UPDATE rh_fonctions SET deleted_at = NOW(), actif = 0 WHERE id = :id")
                  ->execute([':id' => $id]);
    }

    public function codeExistsFonction(string $code, int $excludeId = 0): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM rh_fonctions WHERE code = :code AND id != :ex"
        );
        $stmt->execute([':code' => $code, ':ex' => $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function statistiques(): array
    {
        return [
            'nb_departements'  => (int)$this->pdo->query("SELECT COUNT(*) FROM rh_departements WHERE deleted_at IS NULL")->fetchColumn(),
            'nb_services'      => (int)$this->pdo->query("SELECT COUNT(*) FROM rh_services WHERE deleted_at IS NULL")->fetchColumn(),
            'nb_postes'        => (int)$this->pdo->query("SELECT COUNT(*) FROM rh_postes WHERE deleted_at IS NULL")->fetchColumn(),
            'nb_fonctions'     => (int)$this->pdo->query("SELECT COUNT(*) FROM rh_fonctions WHERE deleted_at IS NULL")->fetchColumn(),
            'nb_postes_occupes'=> (int)$this->pdo->query("SELECT COUNT(DISTINCT poste_id) FROM rh_employes WHERE poste_id IS NOT NULL AND deleted_at IS NULL")->fetchColumn(),
            'par_categorie'    => $this->pdo->query(
                "SELECT categorie, COUNT(*) AS nb FROM rh_postes WHERE deleted_at IS NULL GROUP BY categorie ORDER BY nb DESC"
            )->fetchAll(PDO::FETCH_ASSOC),
            'par_departement'  => $this->pdo->query(
                "SELECT d.nom, COUNT(e.id) AS nb_employes
                 FROM rh_departements d
                 LEFT JOIN rh_employes e ON e.departement_id = d.id AND e.deleted_at IS NULL
                 WHERE d.deleted_at IS NULL GROUP BY d.id ORDER BY nb_employes DESC LIMIT 10"
            )->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    /** Insert in rh_historique_org */
    public function logHistorique(string $typeEntite, int $entiteId, string $action, ?array $ancien, ?array $nouveau, int $userId, string $userName): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO rh_historique_org
             (type_entite, entite_id, action, ancienne_valeur, nouvelle_valeur, modifie_par_id, modifie_par_nom)
             VALUES (:te, :ei, :ac, :av, :nv, :uid, :unom)"
        );
        $stmt->execute([
            ':te'   => $typeEntite,
            ':ei'   => $entiteId,
            ':ac'   => $action,
            ':av'   => $ancien ? json_encode($ancien, JSON_UNESCAPED_UNICODE) : null,
            ':nv'   => $nouveau ? json_encode($nouveau, JSON_UNESCAPED_UNICODE) : null,
            ':uid'  => $userId,
            ':unom' => $userName,
        ]);
    }

    public function findHistoriqueByEntite(string $typeEntite, int $entiteId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM rh_historique_org WHERE type_entite = :te AND entite_id = :ei ORDER BY created_at DESC LIMIT :lim"
        );
        $stmt->bindValue(':te',  $typeEntite);
        $stmt->bindValue(':ei',  $entiteId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit,    PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    private function deptWhere(OrganizationFiltersDTO $f): array
    {
        $where  = 'TRUE';
        $params = [];
        if (!$f->includeArch) {
            $where .= ' AND d.deleted_at IS NULL';
        }
        if ($f->q !== '') {
            $where .= ' AND (d.nom LIKE :q OR d.code LIKE :q)';
            $params[':q'] = '%' . $f->q . '%';
        }
        return [$where, $params];
    }

    private function posteWhere(OrganizationFiltersDTO $f): array
    {
        $where  = 'TRUE';
        $params = [];
        if (!$f->includeArch) {
            $where .= ' AND p.deleted_at IS NULL';
        }
        if ($f->q !== '') {
            $where .= ' AND (p.intitule LIKE :q OR p.code LIKE :q)';
            $params[':q'] = '%' . $f->q . '%';
        }
        if ($f->categorie !== '') {
            $where .= ' AND p.categorie = :cat';
            $params[':cat'] = $f->categorie;
        }
        if ($f->departementId !== null) {
            $where .= ' AND p.departement_id = :deptId';
            $params[':deptId'] = $f->departementId;
        }
        return [$where, $params];
    }

    /** Construit un arbre depuis une liste plate (parent_id self-référentiel) */
    private function buildTree(array $rows, ?int $parentId = null): array
    {
        $tree = [];
        foreach ($rows as $row) {
            $rowParent = $row['parent_id'] === null ? null : (int)$row['parent_id'];
            if ($rowParent === $parentId) {
                $row['children'] = $this->buildTree($rows, (int)$row['id']);
                $tree[] = $row;
            }
        }
        return $tree;
    }
}
