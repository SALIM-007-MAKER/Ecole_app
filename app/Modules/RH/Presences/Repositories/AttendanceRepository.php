<?php

declare(strict_types=1);

namespace App\Modules\RH\Presences\Repositories;

use App\Modules\RH\Presences\DTO\AttendanceFiltersDTO;
use Core\Database;

class AttendanceRepository
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    public function findAll(AttendanceFiltersDTO $f): array
    {
        [$where, $params] = $this->buildWhere($f);

        $offset = ($f->page - 1) * $f->perPage;
        $sql = "
            SELECT
                p.*,
                CONCAT(e.prenom, ' ', e.nom)    AS employe_nom,
                e.matricule                       AS employe_matricule,
                e.photo                           AS employe_photo,
                po.intitule                       AS poste_intitule,
                d.nom                             AS departement_nom
            FROM rh_presences p
            JOIN rh_employes e ON p.employe_id = e.id
            LEFT JOIN rh_affectations a  ON p.affectation_id = a.id
            LEFT JOIN rh_postes       po ON a.poste_id       = po.id
            LEFT JOIN rh_departements d  ON a.departement_id = d.id
            {$where}
            ORDER BY p.date_presence DESC, employe_nom ASC
            LIMIT {$f->perPage} OFFSET {$offset}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function count(AttendanceFiltersDTO $f): int
    {
        [$where, $params] = $this->buildWhere($f);
        $sql = "SELECT COUNT(*) FROM rh_presences p
                JOIN rh_employes e ON p.employe_id = e.id
                LEFT JOIN rh_affectations a ON p.affectation_id = a.id
                LEFT JOIN rh_departements d ON a.departement_id = d.id
                {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $sql = "
            SELECT
                p.*,
                CONCAT(e.prenom, ' ', e.nom) AS employe_nom,
                e.matricule                   AS employe_matricule,
                e.photo                       AS employe_photo,
                e.type                        AS employe_type,
                po.intitule                   AS poste_intitule,
                po.categorie                  AS poste_categorie,
                d.nom                         AS departement_nom,
                s.nom                         AS service_nom,
                a.type                        AS affectation_type
            FROM rh_presences p
            JOIN rh_employes  e  ON p.employe_id    = e.id
            LEFT JOIN rh_affectations a  ON p.affectation_id = a.id
            LEFT JOIN rh_postes       po ON a.poste_id       = po.id
            LEFT JOIN rh_departements d  ON a.departement_id = d.id
            LEFT JOIN rh_services     s  ON a.service_id     = s.id
            WHERE p.id = :id AND p.deleted_at IS NULL
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByEmploye(int $employeId, ?string $dateDebut = null, ?string $dateFin = null): array
    {
        $params = [':eid' => $employeId];
        $cond   = 'WHERE p.employe_id = :eid AND p.deleted_at IS NULL';

        if ($dateDebut) { $cond .= ' AND p.date_presence >= :dd'; $params[':dd'] = $dateDebut; }
        if ($dateFin)   { $cond .= ' AND p.date_presence <= :df'; $params[':df'] = $dateFin;   }

        $sql = "SELECT p.*, CONCAT(e.prenom,' ',e.nom) AS employe_nom
                FROM rh_presences p
                JOIN rh_employes e ON p.employe_id = e.id
                {$cond}
                ORDER BY p.date_presence DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findEnAttente(): array
    {
        $sql = "
            SELECT
                p.*,
                CONCAT(e.prenom, ' ', e.nom) AS employe_nom,
                e.matricule                   AS employe_matricule,
                d.nom                         AS departement_nom
            FROM rh_presences p
            JOIN rh_employes e  ON p.employe_id    = e.id
            LEFT JOIN rh_affectations a ON p.affectation_id = a.id
            LEFT JOIN rh_departements d ON a.departement_id = d.id
            WHERE p.statut_validation = 'en_attente'
              AND p.deleted_at IS NULL
            ORDER BY p.date_presence DESC, employe_nom ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findRegularisations(int $presenceId): array
    {
        $sql = "SELECT * FROM rh_presences_regularisations
                WHERE presence_id = :pid
                ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':pid' => $presenceId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Vérifications de doublons ──────────────────────────────────────────────

    public function hasPresenceForDate(int $employeId, string $date, int $excludeId = 0): bool
    {
        $sql = "SELECT COUNT(*) FROM rh_presences
                WHERE employe_id = :eid
                  AND date_presence = :d
                  AND deleted_at IS NULL
                  AND id != :exc";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':eid' => $employeId, ':d' => $date, ':exc' => $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // ── Écriture ──────────────────────────────────────────────────────────────

    public function insert(array $data): int
    {
        $cols = implode(', ', array_keys($data));
        $plch = implode(', ', array_map(fn($k) => ':' . $k, array_keys($data)));

        $stmt = $this->db->prepare("INSERT INTO rh_presences ({$cols}) VALUES ({$plch})");
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($data)));
        $stmt = $this->db->prepare("UPDATE rh_presences SET {$sets} WHERE id = :id");
        $stmt->execute(array_merge($data, [':id' => $id]));
    }

    public function updateValidation(int $id, array $data): void
    {
        $this->update($id, $data);
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->db->prepare(
            "UPDATE rh_presences SET deleted_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
    }

    public function restore(int $id): void
    {
        $stmt = $this->db->prepare(
            "UPDATE rh_presences SET deleted_at = NULL WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
    }

    // ── Régularisations ───────────────────────────────────────────────────────

    public function insertRegularisation(array $data): int
    {
        $cols = implode(', ', array_keys($data));
        $plch = implode(', ', array_map(fn($k) => ':' . $k, array_keys($data)));
        $stmt = $this->db->prepare(
            "INSERT INTO rh_presences_regularisations ({$cols}) VALUES ({$plch})"
        );
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function statistiques(): array
    {
        $today = date('Y-m-d');
        $mois  = date('Y-m');

        // Aujourd'hui
        $stmtToday = $this->db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(statut = 'present')          AS presents,
                SUM(statut = 'absent')           AS absents,
                SUM(statut = 'retard')           AS retards,
                SUM(statut = 'heure_sup')        AS heures_sup,
                SUM(statut_validation = 'en_attente') AS en_attente
            FROM rh_presences
            WHERE date_presence = :today AND deleted_at IS NULL
        ");
        $stmtToday->execute([':today' => $today]);
        $aujtd = $stmtToday->fetch(\PDO::FETCH_ASSOC) ?: [];

        // Ce mois
        $stmtMois = $this->db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(statut = 'present') AS presents,
                SUM(statut = 'absent')  AS absents,
                SUM(statut = 'retard')  AS retards,
                SUM(retard_minutes)     AS total_retard_min,
                SUM(heures_supp_minutes)AS total_heures_sup_min
            FROM rh_presences
            WHERE date_presence LIKE :mois AND deleted_at IS NULL
        ");
        $stmtMois->execute([':mois' => $mois . '%']);
        $moisData = $stmtMois->fetch(\PDO::FETCH_ASSOC) ?: [];

        // 7 derniers jours
        $stmtWeek = $this->db->prepare("
            SELECT
                date_presence,
                SUM(statut IN ('present','retard','heure_sup','mi_temps','sortie_anticipee','mission')) AS nb_presents,
                SUM(statut = 'absent') AS nb_absents,
                SUM(statut = 'retard') AS nb_retards
            FROM rh_presences
            WHERE date_presence >= DATE_SUB(:today, INTERVAL 6 DAY)
              AND deleted_at IS NULL
            GROUP BY date_presence
            ORDER BY date_presence ASC
        ");
        $stmtWeek->execute([':today' => $today]);
        $semaine = $stmtWeek->fetchAll(\PDO::FETCH_ASSOC);

        // Par statut (ce mois)
        $stmtSt = $this->db->prepare("
            SELECT statut, COUNT(*) AS nb
            FROM rh_presences
            WHERE date_presence LIKE :mois AND deleted_at IS NULL
            GROUP BY statut
        ");
        $stmtSt->execute([':mois' => $mois . '%']);
        $par_statut = $stmtSt->fetchAll(\PDO::FETCH_ASSOC);

        // Par mode (ce mois)
        $stmtMd = $this->db->prepare("
            SELECT mode_pointage, COUNT(*) AS nb
            FROM rh_presences
            WHERE date_presence LIKE :mois AND deleted_at IS NULL
            GROUP BY mode_pointage
        ");
        $stmtMd->execute([':mois' => $mois . '%']);
        $par_mode = $stmtMd->fetchAll(\PDO::FETCH_ASSOC);

        // Top absents ce mois
        $stmtAbs = $this->db->prepare("
            SELECT CONCAT(e.prenom,' ',e.nom) AS employe_nom, e.matricule,
                   COUNT(*) AS nb_absences
            FROM rh_presences p
            JOIN rh_employes e ON p.employe_id = e.id
            WHERE p.date_presence LIKE :mois AND p.statut = 'absent' AND p.deleted_at IS NULL
            GROUP BY p.employe_id
            ORDER BY nb_absences DESC
            LIMIT 10
        ");
        $stmtAbs->execute([':mois' => $mois . '%']);
        $top_absents = $stmtAbs->fetchAll(\PDO::FETCH_ASSOC);

        // Top retards ce mois
        $stmtRet = $this->db->prepare("
            SELECT CONCAT(e.prenom,' ',e.nom) AS employe_nom, e.matricule,
                   COUNT(*) AS nb_retards, SUM(p.retard_minutes) AS total_minutes
            FROM rh_presences p
            JOIN rh_employes e ON p.employe_id = e.id
            WHERE p.date_presence LIKE :mois AND p.statut = 'retard' AND p.deleted_at IS NULL
            GROUP BY p.employe_id
            ORDER BY nb_retards DESC
            LIMIT 10
        ");
        $stmtRet->execute([':mois' => $mois . '%']);
        $top_retards = $stmtRet->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'aujourd_hui' => $aujtd,
            'mois'        => $moisData,
            'semaine'     => $semaine,
            'par_statut'  => $par_statut,
            'par_mode'    => $par_mode,
            'top_absents' => $top_absents,
            'top_retards' => $top_retards,
        ];
    }

    // ── Référentiels ──────────────────────────────────────────────────────────

    public function findEmployes(): array
    {
        $sql = "SELECT id, CONCAT(prenom,' ',nom) AS nom_complet, matricule, type
                FROM rh_employes WHERE deleted_at IS NULL ORDER BY nom ASC, prenom ASC";
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findAffectationsActives(?int $employeId = null): array
    {
        $sql    = "SELECT a.id, a.type, a.date_debut,
                          CONCAT(e.prenom,' ',e.nom) AS employe_nom,
                          po.intitule AS poste_intitule, d.nom AS departement_nom
                   FROM rh_affectations a
                   JOIN rh_employes e ON a.employe_id = e.id
                   LEFT JOIN rh_postes po ON a.poste_id = po.id
                   LEFT JOIN rh_departements d ON a.departement_id = d.id
                   WHERE a.statut = 'active' AND a.deleted_at IS NULL";
        $params = [];
        if ($employeId !== null) {
            $sql    .= ' AND a.employe_id = :eid';
            $params[':eid'] = $employeId;
        }
        $sql .= ' ORDER BY employe_nom, a.type';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findDepartements(): array
    {
        $sql = "SELECT id, nom FROM rh_departements WHERE deleted_at IS NULL ORDER BY nom";
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Filtre dynamique ──────────────────────────────────────────────────────

    private function buildWhere(AttendanceFiltersDTO $f): array
    {
        $conditions = [];
        $params     = [];

        if (!$f->includeArch) {
            $conditions[] = 'p.deleted_at IS NULL';
        }

        if ($f->q !== null) {
            $conditions[] = "(CONCAT(e.prenom,' ',e.nom) LIKE :q OR e.matricule LIKE :q)";
            $params[':q'] = '%' . $f->q . '%';
        }

        if ($f->statut !== null) {
            $conditions[] = 'p.statut = :statut';
            $params[':statut'] = $f->statut;
        }

        if ($f->statutValidation !== null) {
            $conditions[] = 'p.statut_validation = :sv';
            $params[':sv'] = $f->statutValidation;
        }

        if ($f->employeId !== null) {
            $conditions[] = 'p.employe_id = :eid';
            $params[':eid'] = $f->employeId;
        }

        if ($f->departementId !== null) {
            $conditions[] = 'a.departement_id = :did';
            $params[':did'] = $f->departementId;
        }

        if ($f->datePresence !== null) {
            $conditions[] = 'p.date_presence = :dp';
            $params[':dp'] = $f->datePresence;
        } else {
            if ($f->dateDebut !== null) {
                $conditions[] = 'p.date_presence >= :dd';
                $params[':dd'] = $f->dateDebut;
            }
            if ($f->dateFin !== null) {
                $conditions[] = 'p.date_presence <= :df';
                $params[':df'] = $f->dateFin;
            }
        }

        if ($f->mode !== null) {
            $conditions[] = 'p.mode_pointage = :mode';
            $params[':mode'] = $f->mode;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$where, $params];
    }
}
