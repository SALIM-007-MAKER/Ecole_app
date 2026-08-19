<?php

declare(strict_types=1);

namespace App\Modules\RH\Contrats\Repositories;

use PDO;
use Core\Database;
use App\Modules\RH\Contrats\DTO\ContractFiltersDTO;

class ContractRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    public function findAll(ContractFiltersDTO $f): array
    {
        [$where, $params] = $this->buildWhere($f);

        $sql = "SELECT c.*,
                       CONCAT(e.prenom, ' ', e.nom) AS employe_nom,
                       e.matricule                   AS employe_matricule,
                       p.intitule                    AS poste_intitule,
                       d.nom                         AS departement_nom,
                       ancien.numero_contrat         AS renouvelle_depuis_numero,
                       DATEDIFF(c.date_fin, CURDATE()) AS jours_restants
                FROM rh_contrats c
                JOIN rh_employes e ON e.id = c.employe_id
                LEFT JOIN rh_postes p       ON p.id = c.poste_id
                LEFT JOIN rh_departements d ON d.id = c.departement_id
                LEFT JOIN rh_contrats ancien ON ancien.id = c.renouvelle_depuis
                WHERE c.deleted_at IS NULL AND $where
                ORDER BY c.date_debut DESC, c.id DESC
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

    public function count(ContractFiltersDTO $f): int
    {
        [$where, $params] = $this->buildWhere($f);
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM rh_contrats c
             JOIN rh_employes e ON e.id = c.employe_id
             WHERE c.deleted_at IS NULL AND $where"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function findById(int $id, bool $withDeleted = false): ?array
    {
        $cond = $withDeleted ? '' : 'AND c.deleted_at IS NULL';
        $sql = "SELECT c.*,
                       CONCAT(e.prenom, ' ', e.nom) AS employe_nom,
                       e.matricule AS employe_matricule,
                       e.email_pro AS employe_email,
                       p.intitule  AS poste_intitule,
                       p.categorie AS poste_categorie,
                       d.nom       AS departement_nom,
                       d.code      AS departement_code,
                       ancien.numero_contrat AS renouvelle_depuis_numero,
                       DATEDIFF(c.date_fin, CURDATE()) AS jours_restants
                FROM rh_contrats c
                JOIN rh_employes e ON e.id = c.employe_id
                LEFT JOIN rh_postes p       ON p.id = c.poste_id
                LEFT JOIN rh_departements d ON d.id = c.departement_id
                LEFT JOIN rh_contrats ancien ON ancien.id = c.renouvelle_depuis
                WHERE c.id = :id $cond";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByEmploye(int $employeId, bool $actifSeulement = false): array
    {
        $cond = $actifSeulement ? "AND c.statut = 'actif'" : '';
        $sql = "SELECT c.*, p.intitule AS poste_intitule, d.nom AS departement_nom
                FROM rh_contrats c
                LEFT JOIN rh_postes p       ON p.id = c.poste_id
                LEFT JOIN rh_departements d ON d.id = c.departement_id
                WHERE c.employe_id = :eid AND c.deleted_at IS NULL $cond
                ORDER BY c.date_debut DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':eid' => $employeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Vérifie si l'employé a déjà un contrat actif (excluant $excludeId) */
    public function hasContratActif(int $employeId, int $excludeId = 0): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM rh_contrats
             WHERE employe_id = :eid AND statut = 'actif' AND deleted_at IS NULL AND id != :excl"
        );
        $stmt->execute([':eid' => $employeId, ':excl' => $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /** Détecte les chevauchements de dates pour un même employé */
    public function hasOverlap(int $employeId, string $dateDebut, ?string $dateFin, int $excludeId = 0): bool
    {
        if ($dateFin === null) {
            // CDI : chevauchement si un contrat existant est actif/suspendu
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM rh_contrats
                 WHERE employe_id = :eid AND statut IN ('actif','suspendu','brouillon')
                 AND deleted_at IS NULL AND id != :excl"
            );
            $stmt->execute([':eid' => $employeId, ':excl' => $excludeId]);
        } else {
            // CDD : chevauchement si intervalle se recoupe avec un contrat actif/suspendu
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM rh_contrats
                 WHERE employe_id = :eid AND statut IN ('actif','suspendu','brouillon')
                 AND deleted_at IS NULL AND id != :excl
                 AND date_debut < :fin
                 AND (date_fin IS NULL OR date_fin > :debut)"
            );
            $stmt->execute([':eid' => $employeId, ':excl' => $excludeId,
                            ':debut' => $dateDebut, ':fin' => $dateFin]);
        }
        return (int)$stmt->fetchColumn() > 0;
    }

    public function findExpiredToProcess(): array
    {
        $stmt = $this->pdo->query(
            "SELECT c.*, CONCAT(e.prenom,' ',e.nom) AS employe_nom
             FROM rh_contrats c
             JOIN rh_employes e ON e.id = c.employe_id
             WHERE c.statut = 'actif' AND c.date_fin IS NOT NULL
             AND c.date_fin < CURDATE() AND c.deleted_at IS NULL"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Contrats actifs expirant dans N jours */
    public function findEcheances(int $jours = 90): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*,
                    CONCAT(e.prenom,' ',e.nom) AS employe_nom,
                    e.matricule AS employe_matricule,
                    p.intitule AS poste_intitule,
                    d.nom AS departement_nom,
                    DATEDIFF(c.date_fin, CURDATE()) AS jours_restants
             FROM rh_contrats c
             JOIN rh_employes e ON e.id = c.employe_id
             LEFT JOIN rh_postes p       ON p.id = c.poste_id
             LEFT JOIN rh_departements d ON d.id = c.departement_id
             WHERE c.statut = 'actif'
               AND c.date_fin IS NOT NULL
               AND c.date_fin >= CURDATE()
               AND DATEDIFF(c.date_fin, CURDATE()) <= :jours
               AND c.deleted_at IS NULL
             ORDER BY c.date_fin ASC"
        );
        $stmt->bindValue(':jours', $jours, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Écriture contrat ──────────────────────────────────────────────────────

    public function insert(array $data): int
    {
        $cols = implode(', ', array_keys($data));
        $plh  = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
        $this->pdo->prepare("INSERT INTO rh_contrats ($cols) VALUES ($plh)")->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
        $data['id'] = $id;
        $this->pdo->prepare("UPDATE rh_contrats SET $sets, updated_at = NOW() WHERE id = :id")->execute($data);
    }

    public function updateStatut(int $id, string $statut, ?string $motifFin = null): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE rh_contrats SET statut = :s, motif_fin = COALESCE(:m, motif_fin),
             updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':s' => $statut, ':m' => $motifFin, ':id' => $id]);
    }

    public function softDelete(int $id): void
    {
        $this->pdo->prepare("UPDATE rh_contrats SET deleted_at = NOW() WHERE id = :id")
                  ->execute([':id' => $id]);
    }

    // ── Numéro contrat ────────────────────────────────────────────────────────

    public function genererNumero(): string
    {
        $annee = (int)date('Y');
        $this->pdo->exec("INSERT INTO rh_contrat_sequences (annee, seq) VALUES ($annee, 1)
                          ON DUPLICATE KEY UPDATE seq = seq + 1");
        $seq = (int)$this->pdo->query("SELECT seq FROM rh_contrat_sequences WHERE annee = $annee")->fetchColumn();
        return sprintf('CNT-%d-%04d', $annee, $seq);
    }

    // ── Avenants ──────────────────────────────────────────────────────────────

    public function findAvenants(int $contratId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM rh_contrat_avenants WHERE contrat_id = :id ORDER BY numero ASC"
        );
        $stmt->execute([':id' => $contratId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function nextAvenantNumero(int $contratId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(MAX(numero), 0) + 1 FROM rh_contrat_avenants WHERE contrat_id = :id"
        );
        $stmt->execute([':id' => $contratId]);
        return (int)$stmt->fetchColumn();
    }

    public function insertAvenant(int $contratId, array $data, int $numero, int $createdBy): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO rh_contrat_avenants
             (contrat_id, numero, type, objet, description, date_effet, ancienne_valeur, nouvelle_valeur, created_by)
             VALUES (:cid, :num, :type, :objet, :desc, :date, :av, :nv, :cb)"
        );
        $stmt->execute([
            ':cid'  => $contratId,
            ':num'  => $numero,
            ':type' => $data['type'],
            ':objet'=> $data['objet'],
            ':desc' => $data['description'] ?? null,
            ':date' => $data['date_effet'],
            ':av'   => isset($data['ancienne_valeur']) ? json_encode($data['ancienne_valeur'], JSON_UNESCAPED_UNICODE) : null,
            ':nv'   => isset($data['nouvelle_valeur']) ? json_encode($data['nouvelle_valeur'], JSON_UNESCAPED_UNICODE) : null,
            ':cb'   => $createdBy,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function statistiques(): array
    {
        return [
            'par_statut'  => $this->pdo->query(
                "SELECT statut, COUNT(*) AS nb FROM rh_contrats WHERE deleted_at IS NULL GROUP BY statut"
            )->fetchAll(PDO::FETCH_ASSOC),
            'par_type'    => $this->pdo->query(
                "SELECT type, COUNT(*) AS nb FROM rh_contrats WHERE deleted_at IS NULL AND statut NOT IN ('archive') GROUP BY type ORDER BY nb DESC"
            )->fetchAll(PDO::FETCH_ASSOC),
            'actifs_total'=> (int)$this->pdo->query(
                "SELECT COUNT(*) FROM rh_contrats WHERE statut = 'actif' AND deleted_at IS NULL"
            )->fetchColumn(),
            'cdi_total'   => (int)$this->pdo->query(
                "SELECT COUNT(*) FROM rh_contrats WHERE type = 'cdi' AND statut = 'actif' AND deleted_at IS NULL"
            )->fetchColumn(),
            'echeances_30' => (int)$this->pdo->query(
                "SELECT COUNT(*) FROM rh_contrats WHERE statut = 'actif' AND date_fin IS NOT NULL
                 AND DATEDIFF(date_fin, CURDATE()) BETWEEN 0 AND 30 AND deleted_at IS NULL"
            )->fetchColumn(),
            'echeances_90' => (int)$this->pdo->query(
                "SELECT COUNT(*) FROM rh_contrats WHERE statut = 'actif' AND date_fin IS NOT NULL
                 AND DATEDIFF(date_fin, CURDATE()) BETWEEN 0 AND 90 AND deleted_at IS NULL"
            )->fetchColumn(),
            'masse_salariale' => (float)$this->pdo->query(
                "SELECT COALESCE(SUM(salaire_brut), 0) FROM rh_contrats WHERE statut = 'actif' AND deleted_at IS NULL"
            )->fetchColumn(),
        ];
    }

    // ── Référentiels ──────────────────────────────────────────────────────────

    public function findEmployes(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, CONCAT(prenom,' ',nom,' (',matricule,')') AS label
             FROM rh_employes WHERE deleted_at IS NULL ORDER BY nom, prenom"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findPostes(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, intitule, code, categorie FROM rh_postes
             WHERE deleted_at IS NULL AND actif = 1 ORDER BY categorie, intitule"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findDepartements(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, nom, code FROM rh_departements
             WHERE deleted_at IS NULL AND actif = 1 ORDER BY nom"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    private function buildWhere(ContractFiltersDTO $f): array
    {
        $where  = 'TRUE';
        $params = [];

        if ($f->q !== '') {
            $where .= " AND (c.numero_contrat LIKE :q OR CONCAT(e.prenom,' ',e.nom) LIKE :q)";
            $params[':q'] = '%' . $f->q . '%';
        }
        if ($f->type !== '') {
            $where .= ' AND c.type = :type';
            $params[':type'] = $f->type;
        }
        if ($f->statut !== '') {
            $where .= ' AND c.statut = :statut';
            $params[':statut'] = $f->statut;
        }
        if ($f->employeId !== null) {
            $where .= ' AND c.employe_id = :eid';
            $params[':eid'] = $f->employeId;
        }
        if ($f->departementId !== null) {
            $where .= ' AND c.departement_id = :deptId';
            $params[':deptId'] = $f->departementId;
        }
        if ($f->echeanceDans !== '' && is_numeric($f->echeanceDans)) {
            $jours = (int)$f->echeanceDans;
            $where .= " AND c.date_fin IS NOT NULL AND c.statut = 'actif'"
                    . " AND DATEDIFF(c.date_fin, CURDATE()) BETWEEN 0 AND :echeance";
            $params[':echeance'] = $jours;
        }

        return [$where, $params];
    }
}
