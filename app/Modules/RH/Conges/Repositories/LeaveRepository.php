<?php

declare(strict_types=1);

namespace App\Modules\RH\Conges\Repositories;

use App\Modules\RH\Conges\DTO\LeaveFiltersDTO;
use Core\Database;

class LeaveRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ── Lecture congés ────────────────────────────────────────────────────────

    public function findAll(LeaveFiltersDTO $f): array
    {
        [$where, $params] = $this->buildWhere($f);
        $offset = ($f->page - 1) * $f->perPage;

        $sql = "SELECT c.*,
                       e.nom AS employe_nom, e.prenom AS employe_prenom,
                       CONCAT(e.prenom, ' ', e.nom) AS employe_nom_complet,
                       e.matricule AS employe_matricule,
                       d.nom AS departement_nom,
                       tc.code AS type_code, tc.libelle AS type_libelle, tc.is_paye, tc.debit_solde
                FROM rh_conges c
                JOIN rh_employes e ON e.id = c.employe_id
                LEFT JOIN rh_affectations aff ON aff.id = c.affectation_id
                LEFT JOIN rh_departements d ON d.id = aff.departement_id
                JOIN rh_types_conges tc ON tc.id = c.type_conge_id
                {$where}
                ORDER BY c.date_debut DESC, c.created_at DESC
                LIMIT :limit OFFSET :offset";

        $params[':limit']  = $f->perPage;
        $params[':offset'] = $offset;

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function count(LeaveFiltersDTO $f): int
    {
        [$where, $params] = $this->buildWhere($f);

        $sql = "SELECT COUNT(*) FROM rh_conges c
                JOIN rh_employes e ON e.id = c.employe_id
                JOIN rh_types_conges tc ON tc.id = c.type_conge_id
                {$where}";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    private function buildWhere(LeaveFiltersDTO $f): array
    {
        $conditions = [];
        $params     = [];

        if (!$f->includeArch) {
            $conditions[] = 'c.deleted_at IS NULL';
        }
        if ($f->q !== '') {
            $conditions[] = "(e.nom LIKE :q OR e.prenom LIKE :q OR e.matricule LIKE :q)";
            $params[':q'] = '%' . $f->q . '%';
        }
        if ($f->statut !== '') {
            $conditions[] = 'c.statut = :statut';
            $params[':statut'] = $f->statut;
        }
        if ($f->typeCode !== '') {
            $conditions[] = 'tc.code = :type_code';
            $params[':type_code'] = $f->typeCode;
        }
        if ($f->employeId !== null) {
            $conditions[] = 'c.employe_id = :eid';
            $params[':eid'] = $f->employeId;
        }
        if ($f->departementId !== null) {
            $conditions[] = 'aff.departement_id = :did';
            $params[':did'] = $f->departementId;
        }
        if ($f->dateDebut !== '') {
            $conditions[] = 'c.date_fin >= :dd';
            $params[':dd'] = $f->dateDebut;
        }
        if ($f->dateFin !== '') {
            $conditions[] = 'c.date_debut <= :df';
            $params[':df'] = $f->dateFin;
        }
        if ($f->annee !== '') {
            $conditions[] = 'YEAR(c.date_debut) = :annee';
            $params[':annee'] = (int)$f->annee;
        }

        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$where, $params];
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT c.*,
                       e.nom AS employe_nom, e.prenom AS employe_prenom,
                       CONCAT(e.prenom, ' ', e.nom) AS employe_nom_complet,
                       e.matricule AS employe_matricule,
                       d.nom AS departement_nom,
                       tc.code AS type_code, tc.libelle AS type_libelle,
                       tc.is_paye, tc.debit_solde, tc.necessite_justificatif,
                       tc.necessite_approbation, tc.duree_max_jours
                FROM rh_conges c
                JOIN rh_employes e ON e.id = c.employe_id
                LEFT JOIN rh_affectations aff ON aff.id = c.affectation_id
                LEFT JOIN rh_departements d ON d.id = aff.departement_id
                JOIN rh_types_conges tc ON tc.id = c.type_conge_id
                WHERE c.id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function findByEmploye(int $employeId, ?string $annee = null): array
    {
        $sql = "SELECT c.*, tc.code AS type_code, tc.libelle AS type_libelle
                FROM rh_conges c
                JOIN rh_types_conges tc ON tc.id = c.type_conge_id
                WHERE c.employe_id = :eid AND c.deleted_at IS NULL";
        $params = [':eid' => $employeId];

        if ($annee !== null) {
            $sql .= ' AND YEAR(c.date_debut) = :annee';
            $params[':annee'] = (int)$annee;
        }
        $sql .= ' ORDER BY c.date_debut DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findEnAttente(): array
    {
        $sql = "SELECT c.*,
                       CONCAT(e.prenom, ' ', e.nom) AS employe_nom_complet,
                       e.matricule AS employe_matricule,
                       d.nom AS departement_nom,
                       tc.code AS type_code, tc.libelle AS type_libelle
                FROM rh_conges c
                JOIN rh_employes e ON e.id = c.employe_id
                LEFT JOIN rh_affectations aff ON aff.id = c.affectation_id
                LEFT JOIN rh_departements d ON d.id = aff.departement_id
                JOIN rh_types_conges tc ON tc.id = c.type_conge_id
                WHERE c.statut = 'soumis' AND c.deleted_at IS NULL
                ORDER BY c.created_at ASC";

        return $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Chevauchements ────────────────────────────────────────────────────────

    public function findChevauchements(int $employeId, string $dateDebut, string $dateFin, int $excludeId = 0): int
    {
        $sql = "SELECT COUNT(*) FROM rh_conges
                WHERE employe_id = :eid
                  AND id != :exclude
                  AND deleted_at IS NULL
                  AND statut NOT IN ('rejete', 'annule')
                  AND date_debut <= :date_fin
                  AND date_fin   >= :date_debut";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':eid'        => $employeId,
            ':exclude'    => $excludeId,
            ':date_debut' => $dateDebut,
            ':date_fin'   => $dateFin,
        ]);
        return (int)$stmt->fetchColumn();
    }

    // ── CRUD congés ───────────────────────────────────────────────────────────

    public function insert(array $data): int
    {
        $cols = implode(', ', array_keys($data));
        $vals = implode(', ', array_map(fn($k) => ':' . $k, array_keys($data)));

        $stmt = $this->pdo->prepare("INSERT INTO rh_conges ({$cols}) VALUES ({$vals})");
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($data)));
        $data['id'] = $id;
        $this->pdo->prepare("UPDATE rh_conges SET {$sets} WHERE id = :id")->execute($data);
    }

    public function softDelete(int $id): void
    {
        $this->pdo->prepare("UPDATE rh_conges SET deleted_at = NOW() WHERE id = :id")->execute([':id' => $id]);
    }

    // ── Historique ────────────────────────────────────────────────────────────

    public function insertHistorique(array $data): void
    {
        $cols = implode(', ', array_keys($data));
        $vals = implode(', ', array_map(fn($k) => ':' . $k, array_keys($data)));
        $this->pdo->prepare("INSERT INTO rh_conges_historique ({$cols}) VALUES ({$vals})")->execute($data);
    }

    public function findHistorique(int $congeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM rh_conges_historique WHERE conge_id = :cid ORDER BY created_at ASC"
        );
        $stmt->execute([':cid' => $congeId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Soldes ────────────────────────────────────────────────────────────────

    public function findSoldes(?int $employeId = null, ?int $annee = null): array
    {
        $sql = "SELECT s.*,
                       CONCAT(e.prenom, ' ', e.nom) AS employe_nom_complet,
                       e.matricule,
                       tc.code AS type_code, tc.libelle AS type_libelle
                FROM rh_soldes_conges s
                JOIN rh_employes e ON e.id = s.employe_id
                JOIN rh_types_conges tc ON tc.id = s.type_conge_id
                WHERE 1=1";
        $params = [];

        if ($employeId !== null) {
            $sql .= ' AND s.employe_id = :eid';
            $params[':eid'] = $employeId;
        }
        if ($annee !== null) {
            $sql .= ' AND s.annee = :annee';
            $params[':annee'] = $annee;
        }
        $sql .= ' ORDER BY e.nom, e.prenom, tc.ordre';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findSolde(int $employeId, int $typeCongeId, int $annee): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM rh_soldes_conges WHERE employe_id = :eid AND type_conge_id = :tid AND annee = :annee"
        );
        $stmt->execute([':eid' => $employeId, ':tid' => $typeCongeId, ':annee' => $annee]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function upsertSolde(int $employeId, int $typeCongeId, int $annee, float $soldeInitial): void
    {
        $sql = "INSERT INTO rh_soldes_conges (employe_id, type_conge_id, annee, solde_initial)
                VALUES (:eid, :tid, :annee, :si)
                ON DUPLICATE KEY UPDATE solde_initial = :si2";

        $this->pdo->prepare($sql)->execute([
            ':eid'   => $employeId,
            ':tid'   => $typeCongeId,
            ':annee' => $annee,
            ':si'    => $soldeInitial,
            ':si2'   => $soldeInitial,
        ]);
    }

    public function incrementSoldeEnAttente(int $employeId, int $typeCongeId, int $annee, float $delta): void
    {
        $sql = "INSERT INTO rh_soldes_conges (employe_id, type_conge_id, annee, solde_en_attente)
                VALUES (:eid, :tid, :annee, :delta)
                ON DUPLICATE KEY UPDATE solde_en_attente = solde_en_attente + :delta2";

        $this->pdo->prepare($sql)->execute([
            ':eid'    => $employeId,
            ':tid'    => $typeCongeId,
            ':annee'  => $annee,
            ':delta'  => $delta,
            ':delta2' => $delta,
        ]);
    }

    public function decrementSoldeEnAttente(int $employeId, int $typeCongeId, int $annee, float $delta): void
    {
        $sql = "UPDATE rh_soldes_conges
                SET solde_en_attente = GREATEST(0.0, solde_en_attente - :delta)
                WHERE employe_id = :eid AND type_conge_id = :tid AND annee = :annee";

        $this->pdo->prepare($sql)->execute([
            ':delta' => $delta,
            ':eid'   => $employeId,
            ':tid'   => $typeCongeId,
            ':annee' => $annee,
        ]);
    }

    public function transfertSoldeEnAttendePris(int $employeId, int $typeCongeId, int $annee, float $delta): void
    {
        $sql = "UPDATE rh_soldes_conges
                SET solde_en_attente = GREATEST(0.0, solde_en_attente - :delta),
                    solde_pris       = solde_pris + :delta2
                WHERE employe_id = :eid AND type_conge_id = :tid AND annee = :annee";

        $this->pdo->prepare($sql)->execute([
            ':delta'  => $delta,
            ':delta2' => $delta,
            ':eid'    => $employeId,
            ':tid'    => $typeCongeId,
            ':annee'  => $annee,
        ]);
    }

    // ── Référentiels ──────────────────────────────────────────────────────────

    public function findTypesConges(bool $actifOnly = true): array
    {
        $sql  = "SELECT * FROM rh_types_conges";
        $sql .= $actifOnly ? " WHERE actif = 1" : '';
        $sql .= " ORDER BY ordre, libelle";
        return $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findTypeById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM rh_types_conges WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function findEmployes(): array
    {
        $sql = "SELECT id, CONCAT(prenom, ' ', nom) AS nom_complet, matricule
                FROM rh_employes
                WHERE statut = 'actif' AND deleted_at IS NULL
                ORDER BY nom, prenom";
        return $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findContratsActifs(int $employeId): array
    {
        $sql = "SELECT id, type_contrat, date_debut, date_fin
                FROM rh_contrats
                WHERE employe_id = :eid AND statut = 'actif' AND deleted_at IS NULL
                ORDER BY date_debut DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':eid' => $employeId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findAffectationsActives(int $employeId): array
    {
        $sql = "SELECT a.id, p.intitule AS poste_intitule, d.nom AS departement_nom, a.type
                FROM rh_affectations a
                LEFT JOIN rh_postes p ON p.id = a.poste_id
                LEFT JOIN rh_departements d ON d.id = a.departement_id
                WHERE a.employe_id = :eid AND a.statut = 'active' AND a.deleted_at IS NULL
                ORDER BY a.est_principale DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':eid' => $employeId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findDepartements(): array
    {
        return $this->pdo->query(
            "SELECT id, nom FROM rh_departements WHERE actif = 1 AND deleted_at IS NULL ORDER BY nom"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function statistiques(): array
    {
        $mois  = date('Y-m');
        $annee = date('Y');

        $totalEnAttente = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM rh_conges WHERE statut = 'soumis' AND deleted_at IS NULL"
        )->fetchColumn();

        $enCours = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM rh_conges WHERE statut = 'en_cours' AND deleted_at IS NULL"
        )->fetchColumn();

        $approuvesMois = (int)$this->pdo->prepare(
            "SELECT COUNT(*) FROM rh_conges
             WHERE statut IN ('approuve','en_cours','termine')
               AND date_format(date_debut,'%Y-%m') = :mois
               AND deleted_at IS NULL"
        )->execute([':mois' => $mois]) ? (int)$this->pdo->prepare(
            "SELECT COUNT(*) FROM rh_conges
             WHERE statut IN ('approuve','en_cours','termine')
               AND DATE_FORMAT(date_debut,'%Y-%m') = :mois
               AND deleted_at IS NULL"
        )->execute([':mois' => $mois]) : 0;

        // Simple approach pour les stats
        $stmt = $this->pdo->query(
            "SELECT
                SUM(CASE WHEN statut = 'soumis'   AND deleted_at IS NULL THEN 1 ELSE 0 END) AS en_attente,
                SUM(CASE WHEN statut = 'approuve' AND deleted_at IS NULL THEN 1 ELSE 0 END) AS approuves,
                SUM(CASE WHEN statut = 'en_cours' AND deleted_at IS NULL THEN 1 ELSE 0 END) AS en_cours,
                SUM(CASE WHEN statut = 'rejete'   AND deleted_at IS NULL AND YEAR(created_at) = YEAR(NOW()) THEN 1 ELSE 0 END) AS rejetes_annee,
                SUM(CASE WHEN statut = 'termine'  AND deleted_at IS NULL AND YEAR(date_debut) = YEAR(NOW()) THEN 1 ELSE 0 END) AS termines_annee,
                SUM(CASE WHEN statut NOT IN ('brouillon','rejete','annule') AND deleted_at IS NULL AND YEAR(date_debut) = YEAR(NOW()) THEN duree_jours ELSE 0 END) AS total_jours_annee
             FROM rh_conges"
        );
        $global = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Par type (année en cours)
        $stmt2 = $this->pdo->query(
            "SELECT tc.libelle, COUNT(*) AS nb, SUM(c.duree_jours) AS total_jours
             FROM rh_conges c
             JOIN rh_types_conges tc ON tc.id = c.type_conge_id
             WHERE c.deleted_at IS NULL
               AND c.statut NOT IN ('brouillon','rejete','annule')
               AND YEAR(c.date_debut) = YEAR(NOW())
             GROUP BY tc.id, tc.libelle
             ORDER BY nb DESC
             LIMIT 9"
        );
        $parType = $stmt2->fetchAll(\PDO::FETCH_ASSOC);

        // Top absents injustifiés (année)
        $stmt3 = $this->pdo->query(
            "SELECT CONCAT(e.prenom,' ',e.nom) AS nom_complet,
                    COUNT(*) AS nb_absences,
                    SUM(c.duree_jours) AS total_jours
             FROM rh_conges c
             JOIN rh_employes e ON e.id = c.employe_id
             JOIN rh_types_conges tc ON tc.id = c.type_conge_id
             WHERE tc.code = 'absence_injustifiee'
               AND c.deleted_at IS NULL
               AND YEAR(c.date_debut) = YEAR(NOW())
             GROUP BY c.employe_id
             ORDER BY nb_absences DESC
             LIMIT 10"
        );
        $topAbsents = $stmt3->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'global'      => $global,
            'par_type'    => $parType,
            'top_absents' => $topAbsents,
        ];
    }
}
