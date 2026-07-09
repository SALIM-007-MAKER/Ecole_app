<?php

namespace App\Modules\RH\Employes\Repositories;

use App\Modules\RH\Employes\DTO\EmployeeFiltersDTO;
use Core\Database;
use PDO;

class EmployeeRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.*,
                    d.nom  AS departement_nom,
                    p.intitule AS poste_intitule,
                    p.categorie AS poste_categorie,
                    CONCAT(u.prenom, ' ', u.nom) AS user_nom,
                    u.role AS user_role
             FROM rh_employes e
             LEFT JOIN rh_departements d ON d.id = e.departement_id
             LEFT JOIN rh_postes       p ON p.id = e.poste_id
             LEFT JOIN users           u ON u.id = e.user_id
             WHERE e.id = :id AND e.deleted_at IS NULL"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByIdIncludingArchived(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.*,
                    d.nom  AS departement_nom,
                    p.intitule AS poste_intitule
             FROM rh_employes e
             LEFT JOIN rh_departements d ON d.id = e.departement_id
             LEFT JOIN rh_postes       p ON p.id = e.poste_id
             WHERE e.id = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAll(EmployeeFiltersDTO $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $offset = ($filters->page - 1) * $filters->perPage;

        $sql = "SELECT e.*,
                       d.nom      AS departement_nom,
                       p.intitule AS poste_intitule
                FROM rh_employes e
                LEFT JOIN rh_departements d ON d.id = e.departement_id
                LEFT JOIN rh_postes       p ON p.id = e.poste_id
                WHERE {$where}
                ORDER BY e.nom ASC, e.prenom ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $filters->perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,           PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAll(EmployeeFiltersDTO $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM rh_employes e WHERE {$where}"
        );
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findLastMatricule(string $annee): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT matricule FROM rh_employes
             WHERE matricule LIKE :prefix
             ORDER BY matricule DESC
             LIMIT 1"
        );
        $stmt->execute([':prefix' => $annee . '-%']);
        $row = $stmt->fetchColumn();
        return $row ?: null;
    }

    public function emailProExists(string $email, int $excludeId = 0): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM rh_employes
             WHERE email_pro = :email AND id != :excl AND deleted_at IS NULL"
        );
        $stmt->execute([':email' => $email, ':excl' => $excludeId]);
        return $stmt->fetchColumn() !== false;
    }

    // ── Écriture ──────────────────────────────────────────────────────────────

    public function insert(array $data): int
    {
        $cols = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($data)));
        $phs  = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));

        $stmt = $this->pdo->prepare("INSERT INTO rh_employes ({$cols}) VALUES ({$phs})");
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets = implode(', ', array_map(fn($k) => "`{$k}` = :{$k}", array_keys($data)));
        $stmt = $this->pdo->prepare(
            "UPDATE rh_employes SET {$sets}, updated_at = NOW() WHERE id = :_id"
        );
        $data['_id'] = $id;
        $stmt->execute($data);
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE rh_employes SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
    }

    public function restore(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE rh_employes SET deleted_at = NULL, statut = 'actif', updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
    }

    // ── Contacts d'urgence ────────────────────────────────────────────────────

    public function findContactsUrgence(int $employeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM rh_contacts_urgence WHERE employe_id = :eid ORDER BY principal DESC, id ASC"
        );
        $stmt->execute([':eid' => $employeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteContactsUrgence(int $employeId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM rh_contacts_urgence WHERE employe_id = :eid");
        $stmt->execute([':eid' => $employeId]);
    }

    public function insertContactUrgence(array $data): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO rh_contacts_urgence
                 (employe_id, nom_complet, lien, telephone, telephone2, email, principal)
             VALUES
                 (:employe_id, :nom_complet, :lien, :telephone, :telephone2, :email, :principal)"
        );
        $stmt->execute($data);
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function countByStatut(): array
    {
        $stmt = $this->pdo->query(
            "SELECT statut, COUNT(*) AS total
             FROM rh_employes
             WHERE deleted_at IS NULL
             GROUP BY statut"
        );
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function countByType(): array
    {
        $stmt = $this->pdo->query(
            "SELECT type_personnel, COUNT(*) AS total
             FROM rh_employes
             WHERE deleted_at IS NULL AND statut != 'inactif'
             GROUP BY type_personnel"
        );
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function countByDepartement(): array
    {
        $stmt = $this->pdo->query(
            "SELECT COALESCE(d.nom, 'Non affecté') AS departement, COUNT(e.id) AS total
             FROM rh_employes e
             LEFT JOIN rh_departements d ON d.id = e.departement_id
             WHERE e.deleted_at IS NULL AND e.statut = 'actif'
             GROUP BY e.departement_id, d.nom
             ORDER BY total DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countArchives(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM rh_employes WHERE deleted_at IS NOT NULL"
        );
        return (int)$stmt->fetchColumn();
    }

    // ── Référentiels ─────────────────────────────────────────────────────────

    public function findDepartements(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, nom, code FROM rh_departements WHERE actif = 1 ORDER BY nom"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findPostes(?string $categorie = null): array
    {
        if ($categorie !== null) {
            $stmt = $this->pdo->prepare(
                "SELECT id, intitule, code, categorie FROM rh_postes
                 WHERE actif = 1 AND categorie = :cat ORDER BY niveau DESC, intitule"
            );
            $stmt->execute([':cat' => $categorie]);
        } else {
            $stmt = $this->pdo->query(
                "SELECT id, intitule, code, categorie FROM rh_postes
                 WHERE actif = 1 ORDER BY categorie, niveau DESC, intitule"
            );
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findUsers(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, CONCAT(prenom, ' ', nom, ' (', email, ')') AS label, role
             FROM users WHERE actif = 1 ORDER BY nom, prenom"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Privé ─────────────────────────────────────────────────────────────────

    private function buildWhere(EmployeeFiltersDTO $f): array
    {
        $conditions = [];
        $params     = [];

        if ($f->includeArch !== '1') {
            $conditions[] = 'e.deleted_at IS NULL';
        }

        if ($f->q !== '') {
            $conditions[] = '(e.nom LIKE :q OR e.prenom LIKE :q OR e.matricule LIKE :q OR e.email_pro LIKE :q)';
            $params[':q'] = '%' . $f->q . '%';
        }

        if ($f->statut !== '') {
            $conditions[] = 'e.statut = :statut';
            $params[':statut'] = $f->statut;
        }

        if ($f->type !== '') {
            $conditions[] = 'e.type_personnel = :type';
            $params[':type'] = $f->type;
        }

        if ($f->departId > 0) {
            $conditions[] = 'e.departement_id = :depart_id';
            $params[':depart_id'] = $f->departId;
        }

        return [
            $conditions ? implode(' AND ', $conditions) : '1=1',
            $params,
        ];
    }
}
