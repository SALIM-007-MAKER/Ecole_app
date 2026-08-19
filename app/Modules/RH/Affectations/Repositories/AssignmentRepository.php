<?php

declare(strict_types=1);

namespace App\Modules\RH\Affectations\Repositories;

use PDO;
use Core\Database;
use App\Modules\RH\Affectations\DTO\AssignmentFiltersDTO;

class AssignmentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    public function findAll(AssignmentFiltersDTO $f): array
    {
        [$where, $params] = $this->buildWhere($f);

        $sql = "SELECT a.*,
                       CONCAT(e.prenom, ' ', e.nom)  AS employe_nom,
                       e.matricule                    AS employe_matricule,
                       p.intitule                     AS poste_intitule,
                       p.categorie                    AS poste_categorie,
                       d.nom                          AS departement_nom,
                       s.nom                          AS service_nom,
                       CONCAT(r.prenom, ' ', r.nom)  AS responsable_nom,
                       c.numero_contrat               AS contrat_numero,
                       c.statut                       AS contrat_statut
                FROM rh_affectations a
                JOIN rh_employes e        ON e.id = a.employe_id
                LEFT JOIN rh_postes p        ON p.id = a.poste_id
                LEFT JOIN rh_departements d  ON d.id = a.departement_id
                LEFT JOIN rh_services s      ON s.id = a.service_id
                LEFT JOIN rh_employes r      ON r.id = a.responsable_id
                LEFT JOIN rh_contrats c      ON c.id = a.contrat_id
                WHERE $where
                ORDER BY a.statut = 'active' DESC, a.date_debut DESC
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

    public function count(AssignmentFiltersDTO $f): int
    {
        [$where, $params] = $this->buildWhere($f);
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM rh_affectations a
             JOIN rh_employes e ON e.id = a.employe_id
             WHERE $where"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT a.*,
                       CONCAT(e.prenom, ' ', e.nom) AS employe_nom,
                       e.matricule AS employe_matricule,
                       e.email_pro AS employe_email,
                       p.intitule  AS poste_intitule,
                       p.categorie AS poste_categorie,
                       d.nom       AS departement_nom,
                       d.code      AS departement_code,
                       s.nom       AS service_nom,
                       CONCAT(r.prenom, ' ', r.nom) AS responsable_nom,
                       r.matricule AS responsable_matricule,
                       c.numero_contrat AS contrat_numero,
                       c.type          AS contrat_type,
                       c.statut        AS contrat_statut
                FROM rh_affectations a
                JOIN rh_employes e       ON e.id = a.employe_id
                LEFT JOIN rh_postes p       ON p.id = a.poste_id
                LEFT JOIN rh_departements d ON d.id = a.departement_id
                LEFT JOIN rh_services s     ON s.id = a.service_id
                LEFT JOIN rh_employes r     ON r.id = a.responsable_id
                LEFT JOIN rh_contrats c     ON c.id = a.contrat_id
                WHERE a.id = :id AND a.deleted_at IS NULL";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByEmploye(int $employeId, bool $activeOnly = false): array
    {
        $cond = $activeOnly ? "AND a.statut = 'active'" : '';
        $sql = "SELECT a.*,
                       p.intitule AS poste_intitule,
                       d.nom AS departement_nom,
                       s.nom AS service_nom
                FROM rh_affectations a
                LEFT JOIN rh_postes p       ON p.id = a.poste_id
                LEFT JOIN rh_departements d ON d.id = a.departement_id
                LEFT JOIN rh_services s     ON s.id = a.service_id
                WHERE a.employe_id = :eid AND a.deleted_at IS NULL $cond
                ORDER BY a.type = 'principale' DESC, a.date_debut DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':eid' => $employeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Matières / Classes (enseignants) ──────────────────────────────────────

    public function findMatieres(int $affectationId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT am.*,
                    m.nom AS matiere_nom,
                    cl.nom AS classe_nom
             FROM rh_affectation_matieres am
             LEFT JOIN matieres m  ON m.id = am.matiere_id
             LEFT JOIN classes  cl ON cl.id = am.classe_id
             WHERE am.affectation_id = :id
             ORDER BY am.date_debut DESC"
        );
        $stmt->execute([':id' => $affectationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertMatiere(int $affectationId, array $data, int $createdBy): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO rh_affectation_matieres
             (affectation_id, matiere_id, classe_id, niveau, heures_hebdo, date_debut, date_fin, notes, created_by)
             VALUES (:aid, :mid, :cid, :niv, :hh, :dd, :df, :notes, :cb)"
        );
        $stmt->execute([
            ':aid'   => $affectationId,
            ':mid'   => $data['matiere_id'],
            ':cid'   => $data['classe_id'],
            ':niv'   => $data['niveau'],
            ':hh'    => $data['heures_hebdo'],
            ':dd'    => $data['date_debut'],
            ':df'    => $data['date_fin'],
            ':notes' => $data['notes'],
            ':cb'    => $createdBy,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function closeMatiereAssignment(int $matiereId, string $dateFin): void
    {
        $this->pdo->prepare("UPDATE rh_affectation_matieres SET date_fin = :df WHERE id = :id")
                  ->execute([':df' => $dateFin, ':id' => $matiereId]);
    }

    // ── Historique ────────────────────────────────────────────────────────────

    public function findHistorique(int $affectationId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM rh_historique_affectations
             WHERE affectation_id = :id
             ORDER BY created_at DESC"
        );
        $stmt->execute([':id' => $affectationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function logHistorique(
        int     $affectationId,
        string  $typeChangement,
        ?array  $ancienneValeur,
        ?array  $nouvelleValeur,
        ?string $motif,
        int     $userId,
        string  $userName
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO rh_historique_affectations
             (affectation_id, type_changement, ancienne_valeur, nouvelle_valeur, motif, modifie_par, modifie_par_nom)
             VALUES (:aid, :type, :av, :nv, :motif, :uid, :uname)"
        );
        $stmt->execute([
            ':aid'   => $affectationId,
            ':type'  => $typeChangement,
            ':av'    => $ancienneValeur !== null ? json_encode($ancienneValeur, JSON_UNESCAPED_UNICODE) : null,
            ':nv'    => $nouvelleValeur !== null ? json_encode($nouvelleValeur, JSON_UNESCAPED_UNICODE) : null,
            ':motif' => $motif,
            ':uid'   => $userId,
            ':uname' => $userName,
        ]);
    }

    // ── Contrôles métier ──────────────────────────────────────────────────────

    /**
     * L'employé a-t-il déjà une affectation principale active ?
     * Utilisé pour bloquer la création de 2 affectations 'principale' simultanées.
     */
    public function hasPrincipaleActive(int $employeId, int $excludeId = 0): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM rh_affectations
             WHERE employe_id = :eid AND type = 'principale' AND statut = 'active'
             AND deleted_at IS NULL AND id != :excl"
        );
        $stmt->execute([':eid' => $employeId, ':excl' => $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Détecte un chevauchement de dates pour les affectations secondaire/temporaire
     * avec le même poste ET département (doublon exact).
     */
    public function hasExactOverlap(
        int     $employeId,
        string  $type,
        string  $dateDebut,
        ?string $dateFin,
        ?int    $posteId,
        ?int    $departementId,
        int     $excludeId = 0
    ): bool {
        $posteCond = $posteId !== null ? 'AND a.poste_id = :pid' : 'AND a.poste_id IS NULL';
        $deptCond  = $departementId !== null ? 'AND a.departement_id = :did' : 'AND a.departement_id IS NULL';

        if ($dateFin === null) {
            $dateCond = "AND (a.date_fin IS NULL OR a.date_fin >= :debut)";
        } else {
            $dateCond = "AND a.date_debut < :fin AND (a.date_fin IS NULL OR a.date_fin > :debut)";
        }

        $sql = "SELECT COUNT(*) FROM rh_affectations a
                WHERE a.employe_id = :eid AND a.type = :type
                AND a.statut IN ('active','suspendue')
                AND a.deleted_at IS NULL AND a.id != :excl
                $posteCond $deptCond $dateCond";

        $params = [':eid' => $employeId, ':type' => $type, ':excl' => $excludeId, ':debut' => $dateDebut];
        if ($posteId !== null)       $params[':pid'] = $posteId;
        if ($departementId !== null) $params[':did'] = $departementId;
        if ($dateFin !== null)       $params[':fin'] = $dateFin;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Vérifie si un contrat appartient à cet employé et est actif.
     */
    public function contratActifEmploye(int $employeId, int $contratId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM rh_contrats
             WHERE id = :cid AND employe_id = :eid AND statut = 'actif' AND deleted_at IS NULL"
        );
        $stmt->execute([':cid' => $contratId, ':eid' => $employeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // ── Écriture ─────────────────────────────────────────────────────────────

    public function insert(array $data): int
    {
        $cols = implode(', ', array_keys($data));
        $plh  = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
        $this->pdo->prepare("INSERT INTO rh_affectations ($cols) VALUES ($plh)")->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets       = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
        $data['id'] = $id;
        $this->pdo->prepare("UPDATE rh_affectations SET $sets, updated_at = NOW() WHERE id = :id")->execute($data);
    }

    public function updateStatut(int $id, string $statut): void
    {
        $this->pdo->prepare("UPDATE rh_affectations SET statut = :s, updated_at = NOW() WHERE id = :id")
                  ->execute([':s' => $statut, ':id' => $id]);
    }

    public function softDelete(int $id): void
    {
        $this->pdo->prepare("UPDATE rh_affectations SET deleted_at = NOW() WHERE id = :id")
                  ->execute([':id' => $id]);
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function statistiques(): array
    {
        return [
            'par_type'   => $this->pdo->query(
                "SELECT type, COUNT(*) AS nb FROM rh_affectations
                 WHERE deleted_at IS NULL AND statut = 'active' GROUP BY type"
            )->fetchAll(PDO::FETCH_ASSOC),

            'par_statut' => $this->pdo->query(
                "SELECT statut, COUNT(*) AS nb FROM rh_affectations
                 WHERE deleted_at IS NULL GROUP BY statut"
            )->fetchAll(PDO::FETCH_ASSOC),

            'par_departement' => $this->pdo->query(
                "SELECT d.nom AS departement, COUNT(a.id) AS nb
                 FROM rh_affectations a
                 JOIN rh_departements d ON d.id = a.departement_id
                 WHERE a.deleted_at IS NULL AND a.statut = 'active'
                 GROUP BY d.id, d.nom ORDER BY nb DESC LIMIT 10"
            )->fetchAll(PDO::FETCH_ASSOC),

            'total_actives'    => (int)$this->pdo->query(
                "SELECT COUNT(*) FROM rh_affectations WHERE statut = 'active' AND deleted_at IS NULL"
            )->fetchColumn(),

            'total_enseignants_affectes' => (int)$this->pdo->query(
                "SELECT COUNT(DISTINCT a.employe_id) FROM rh_affectations a
                 JOIN rh_employes e ON e.id = a.employe_id
                 WHERE a.statut = 'active' AND a.deleted_at IS NULL AND e.type_personnel = 'enseignant'"
            )->fetchColumn(),

            'total_matieres_actives' => (int)$this->pdo->query(
                "SELECT COUNT(*) FROM rh_affectation_matieres WHERE date_fin IS NULL OR date_fin >= CURDATE()"
            )->fetchColumn(),
        ];
    }

    // ── Référentiels ──────────────────────────────────────────────────────────

    public function findEmployes(): array
    {
        return $this->pdo->query(
            "SELECT id, CONCAT(prenom,' ',nom,' (',matricule,')') AS label, type_personnel AS type
             FROM rh_employes WHERE deleted_at IS NULL ORDER BY nom, prenom"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findContratsActifsByEmploye(int $employeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, numero_contrat, type, date_debut, date_fin
             FROM rh_contrats
             WHERE employe_id = :eid AND statut = 'actif' AND deleted_at IS NULL
             ORDER BY date_debut DESC"
        );
        $stmt->execute([':eid' => $employeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAllContratsActifs(): array
    {
        return $this->pdo->query(
            "SELECT c.id, c.numero_contrat, c.type, c.employe_id,
                    CONCAT(e.prenom,' ',e.nom) AS employe_nom
             FROM rh_contrats c
             JOIN rh_employes e ON e.id = c.employe_id
             WHERE c.statut = 'actif' AND c.deleted_at IS NULL
             ORDER BY e.nom, e.prenom"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findPostes(): array
    {
        return $this->pdo->query(
            "SELECT id, intitule, code, categorie, departement_id
             FROM rh_postes WHERE deleted_at IS NULL AND actif = 1 ORDER BY categorie, intitule"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findDepartements(): array
    {
        return $this->pdo->query(
            "SELECT id, nom, code FROM rh_departements WHERE deleted_at IS NULL AND actif = 1 ORDER BY nom"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findServices(?int $departementId = null): array
    {
        if ($departementId !== null) {
            $stmt = $this->pdo->prepare(
                "SELECT id, nom, code FROM rh_services
                 WHERE deleted_at IS NULL AND actif = 1 AND departement_id = :did ORDER BY nom"
            );
            $stmt->execute([':did' => $departementId]);
        } else {
            $stmt = $this->pdo->query(
                "SELECT id, nom, code, departement_id FROM rh_services
                 WHERE deleted_at IS NULL AND actif = 1 ORDER BY nom"
            );
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findMatieresList(): array
    {
        return $this->pdo->query(
            "SELECT id, nom FROM matieres WHERE actif = 1 ORDER BY nom"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findClassesList(): array
    {
        return $this->pdo->query(
            "SELECT id, nom FROM classes ORDER BY nom"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    private function buildWhere(AssignmentFiltersDTO $f): array
    {
        $cond = $f->includeArch ? 'TRUE' : 'a.deleted_at IS NULL';
        $params = [];

        if ($f->q !== '') {
            $cond .= " AND (CONCAT(e.prenom,' ',e.nom) LIKE :q OR e.matricule LIKE :q)";
            $params[':q'] = '%' . $f->q . '%';
        }
        if ($f->type !== '') {
            $cond .= ' AND a.type = :type';
            $params[':type'] = $f->type;
        }
        if ($f->statut !== '') {
            $cond .= ' AND a.statut = :statut';
            $params[':statut'] = $f->statut;
        }
        if ($f->employeId !== null) {
            $cond .= ' AND a.employe_id = :eid';
            $params[':eid'] = $f->employeId;
        }
        if ($f->departementId !== null) {
            $cond .= ' AND a.departement_id = :did';
            $params[':did'] = $f->departementId;
        }
        if ($f->posteId !== null) {
            $cond .= ' AND a.poste_id = :pid';
            $params[':pid'] = $f->posteId;
        }

        return [$cond, $params];
    }
}
