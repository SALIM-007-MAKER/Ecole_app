<?php

namespace App\Modules\VieScolaire\Retards\Repositories;

use App\Modules\VieScolaire\Retards\DTO\LateFiltersDTO;
use Core\Database;

class LateRepository
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT r.*,
                   e.nom        AS eleve_nom,
                   e.prenom     AS eleve_prenom,
                   e.matricule  AS eleve_matricule,
                   c.nom        AS classe_nom,
                   u.nom        AS saisie_par_nom,
                   u.prenom     AS saisie_par_prenom
            FROM   vs_retards r
            JOIN   eleves  e ON e.id = r.eleve_id
            JOIN   classes c ON c.id = r.classe_id
            JOIN   users   u ON u.id = r.saisie_par
            WHERE  r.id = :id AND r.deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findAll(LateFiltersDTO $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $offset = ($filters->page - 1) * $filters->perPage;

        $stmt = $this->db->prepare("
            SELECT r.*,
                   e.nom        AS eleve_nom,
                   e.prenom     AS eleve_prenom,
                   e.matricule  AS eleve_matricule,
                   c.nom        AS classe_nom
            FROM   vs_retards r
            JOIN   eleves  e ON e.id = r.eleve_id
            JOIN   classes c ON c.id = r.classe_id
            {$where}
            ORDER BY r.date_retard DESC, r.created_at DESC
            LIMIT  :limit OFFSET :offset
        ");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $filters->perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,           \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countAll(LateFiltersDTO $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);

        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM vs_retards r {$where}
        ");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function findByPresenceId(int $presenceId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM vs_retards
            WHERE presence_id = :pid AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':pid' => $presenceId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByEleveAndDate(int $eleveId, string $date): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM vs_retards
            WHERE eleve_id = :eid AND date_retard = :date AND deleted_at IS NULL
            ORDER BY created_at DESC
        ");
        $stmt->execute([':eid' => $eleveId, ':date' => $date]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Écriture ──────────────────────────────────────────────────────────────

    public function insert(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO vs_retards
                (eleve_id, classe_id, annee_scolaire, date_retard,
                 heure_prevue, heure_arrivee, duree_minutes, statut,
                 appel_id, presence_id, saisie_par, observation)
            VALUES
                (:eleve_id, :classe_id, :annee_scolaire, :date_retard,
                 :heure_prevue, :heure_arrivee, :duree_minutes, 'non_justifie',
                 :appel_id, :presence_id, :saisie_par, :observation)
        ");
        $stmt->execute([
            ':eleve_id'       => $data['eleve_id'],
            ':classe_id'      => $data['classe_id'],
            ':annee_scolaire' => $data['annee_scolaire'],
            ':date_retard'    => $data['date_retard'],
            ':heure_prevue'   => $data['heure_prevue']  ?? null,
            ':heure_arrivee'  => $data['heure_arrivee'],
            ':duree_minutes'  => $data['duree_minutes']  ?? 0,
            ':appel_id'       => $data['appel_id']      ?? null,
            ':presence_id'    => $data['presence_id']   ?? null,
            ':saisie_par'     => $data['saisie_par'],
            ':observation'    => $data['observation']   ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateStatut(int $id, string $statut): bool
    {
        $stmt = $this->db->prepare("
            UPDATE vs_retards SET statut = :s, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([':s' => $statut, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE vs_retards
            SET heure_prevue  = :hp,
                heure_arrivee = :ha,
                duree_minutes = :dm,
                observation   = :obs,
                updated_at    = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':hp'  => $data['heure_prevue']  ?? null,
            ':ha'  => $data['heure_arrivee'],
            ':dm'  => $data['duree_minutes']  ?? 0,
            ':obs' => $data['observation']    ?? null,
            ':id'  => $id,
        ]);
        return $stmt->rowCount() >= 0;
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE vs_retards SET deleted_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function softDeleteByPresenceId(int $presenceId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE vs_retards SET deleted_at = NOW()
            WHERE presence_id = :pid AND deleted_at IS NULL
        ");
        $stmt->execute([':pid' => $presenceId]);
        return $stmt->rowCount() > 0;
    }

    // ── Justifications ────────────────────────────────────────────────────────

    public function findJustificationByRetard(int $retardId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT j.*,
                   u.nom    AS soumis_par_nom,
                   u.prenom AS soumis_par_prenom,
                   v.nom    AS valide_par_nom,
                   v.prenom AS valide_par_prenom
            FROM   vs_justifications_retards j
            JOIN   users u ON u.id = j.soumis_par
            LEFT JOIN users v ON v.id = j.valide_par
            WHERE  j.retard_id = :rid
        ");
        $stmt->execute([':rid' => $retardId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function insertJustification(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO vs_justifications_retards
                (retard_id, motif_description, fichier_justificatif, soumis_par)
            VALUES (:retard_id, :motif, :fichier, :soumis_par)
        ");
        $stmt->execute([
            ':retard_id' => $data['retard_id'],
            ':motif'     => $data['motif_description']    ?? null,
            ':fichier'   => $data['fichier_justificatif'] ?? null,
            ':soumis_par'=> $data['soumis_par'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function validerJustification(int $justificationId, int $valideParId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE vs_justifications_retards
            SET statut = 'validee', valide_par = :vpar, valide_le = NOW()
            WHERE id = :id AND statut = 'en_attente'
        ");
        $stmt->execute([':vpar' => $valideParId, ':id' => $justificationId]);
        return $stmt->rowCount() > 0;
    }

    public function refuserJustification(int $justificationId, int $rejeteParId, string $motifRefus): bool
    {
        $stmt = $this->db->prepare("
            UPDATE vs_justifications_retards
            SET statut = 'refusee', valide_par = :vpar, valide_le = NOW(), motif_refus = :motif
            WHERE id = :id AND statut = 'en_attente'
        ");
        $stmt->execute([':vpar' => $rejeteParId, ':id' => $justificationId, ':motif' => $motifRefus]);
        return $stmt->rowCount() > 0;
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function countByEleveAndAnnee(int $eleveId, string $anneeScolaire): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM vs_retards
            WHERE eleve_id = :eid AND annee_scolaire = :annee AND deleted_at IS NULL
        ");
        $stmt->execute([':eid' => $eleveId, ':annee' => $anneeScolaire]);
        return (int)$stmt->fetchColumn();
    }

    public function statsByEleve(int $eleveId, string $anneeScolaire): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*)                                          AS total,
                SUM(statut = 'justifie')                         AS justifies,
                SUM(statut = 'non_justifie')                     AS non_justifies,
                SUM(statut = 'en_attente')                       AS en_attente,
                SUM(statut = 'refuse')                           AS refuses,
                COALESCE(SUM(duree_minutes), 0)                  AS total_minutes,
                COALESCE(AVG(duree_minutes), 0)                  AS moy_minutes
            FROM vs_retards
            WHERE eleve_id = :eid AND annee_scolaire = :annee AND deleted_at IS NULL
        ");
        $stmt->execute([':eid' => $eleveId, ':annee' => $anneeScolaire]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    public function statsByClasse(int $classeId, string $anneeScolaire): array
    {
        $stmt = $this->db->prepare("
            SELECT
                e.id           AS eleve_id,
                e.nom          AS eleve_nom,
                e.prenom       AS eleve_prenom,
                COUNT(r.id)    AS total_retards,
                SUM(r.statut = 'justifie')     AS justifies,
                SUM(r.statut = 'non_justifie') AS non_justifies,
                COALESCE(SUM(r.duree_minutes), 0) AS total_minutes
            FROM   eleves e
            LEFT JOIN vs_retards r ON r.eleve_id = e.id
                AND r.classe_id      = :cid
                AND r.annee_scolaire = :annee
                AND r.deleted_at IS NULL
            WHERE  e.classe_id = :cid AND e.actif = 1
            GROUP  BY e.id, e.nom, e.prenom
            ORDER  BY total_retards DESC, e.nom ASC
        ");
        $stmt->execute([':cid' => $classeId, ':annee' => $anneeScolaire]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Privé ─────────────────────────────────────────────────────────────────

    private function buildWhere(LateFiltersDTO $f): array
    {
        $conditions = ['r.deleted_at IS NULL'];
        $params     = [];

        if ($f->eleveId !== null) {
            $conditions[] = 'r.eleve_id = :eleve_id';
            $params[':eleve_id'] = $f->eleveId;
        }
        if ($f->classeId !== null) {
            $conditions[] = 'r.classe_id = :classe_id';
            $params[':classe_id'] = $f->classeId;
        }
        if ($f->anneeScolaire !== null) {
            $conditions[] = 'r.annee_scolaire = :annee';
            $params[':annee'] = $f->anneeScolaire;
        }
        if ($f->dateDebut !== null) {
            $conditions[] = 'r.date_retard >= :date_debut';
            $params[':date_debut'] = $f->dateDebut;
        }
        if ($f->dateFin !== null) {
            $conditions[] = 'r.date_retard <= :date_fin';
            $params[':date_fin'] = $f->dateFin;
        }
        if ($f->statut !== null) {
            $conditions[] = 'r.statut = :statut';
            $params[':statut'] = $f->statut;
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);
        return [$where, $params];
    }
}
