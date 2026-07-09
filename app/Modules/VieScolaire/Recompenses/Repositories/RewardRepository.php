<?php

namespace App\Modules\VieScolaire\Recompenses\Repositories;

use App\Modules\VieScolaire\Recompenses\DTO\RewardFiltersDTO;
use Core\Database;

class RewardRepository
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ── Récompenses ───────────────────────────────────────────────────────────

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT r.*,
                   e.nom          AS eleve_nom,
                   e.prenom       AS eleve_prenom,
                   e.matricule    AS eleve_matricule,
                   c.nom          AS classe_nom,
                   cat.nom        AS categorie_nom,
                   cat.code       AS categorie_code,
                   cat.couleur    AS categorie_couleur,
                   u.nom          AS attribue_par_nom,
                   u.prenom       AS attribue_par_prenom,
                   v.nom          AS valide_par_nom,
                   v.prenom       AS valide_par_prenom
            FROM   vs_recompenses r
            JOIN   eleves              e   ON e.id   = r.eleve_id
            JOIN   classes             c   ON c.id   = r.classe_id
            JOIN   vs_recompense_categories cat ON cat.id = r.categorie_id
            JOIN   users               u   ON u.id   = r.attribue_par
            LEFT JOIN users            v   ON v.id   = r.valide_par
            WHERE  r.id = :id AND r.deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findAll(RewardFiltersDTO $f): array
    {
        [$where, $params] = $this->buildWhere($f);
        $offset = ($f->page - 1) * $f->perPage;

        $stmt = $this->db->prepare("
            SELECT r.*,
                   e.nom       AS eleve_nom,
                   e.prenom    AS eleve_prenom,
                   c.nom       AS classe_nom,
                   cat.nom     AS categorie_nom,
                   cat.code    AS categorie_code,
                   cat.couleur AS categorie_couleur,
                   u.nom       AS attribue_par_nom,
                   u.prenom    AS attribue_par_prenom
            FROM   vs_recompenses r
            JOIN   eleves              e   ON e.id   = r.eleve_id
            JOIN   classes             c   ON c.id   = r.classe_id
            JOIN   vs_recompense_categories cat ON cat.id = r.categorie_id
            JOIN   users               u   ON u.id   = r.attribue_par
            {$where}
            ORDER  BY r.date_attribution DESC, r.created_at DESC
            LIMIT  :limit OFFSET :offset
        ");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $f->perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,     \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countAll(RewardFiltersDTO $f): int
    {
        [$where, $params] = $this->buildWhere($f);
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM vs_recompenses r {$where}
        ");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function insert(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO vs_recompenses
                (eleve_id, classe_id, annee_scolaire, categorie_id,
                 motif, niveau, date_attribution, attribue_par, piece_jointe)
            VALUES
                (:eleve_id, :classe_id, :annee_scolaire, :categorie_id,
                 :motif, :niveau, :date_attribution, :attribue_par, :piece_jointe)
        ");
        $stmt->execute([
            ':eleve_id'        => $data['eleve_id'],
            ':classe_id'       => $data['classe_id'],
            ':annee_scolaire'  => $data['annee_scolaire'],
            ':categorie_id'    => $data['categorie_id'],
            ':motif'           => $data['motif'],
            ':niveau'          => $data['niveau'],
            ':date_attribution'=> $data['date_attribution'],
            ':attribue_par'    => $data['attribue_par'],
            ':piece_jointe'    => $data['piece_jointe'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE vs_recompenses
            SET categorie_id     = :categorie_id,
                motif            = :motif,
                niveau           = :niveau,
                date_attribution = :date_attribution,
                updated_at       = NOW()
            WHERE id = :id AND deleted_at IS NULL AND statut = 'attribuee'
        ");
        $stmt->execute([
            ':categorie_id'    => $data['categorie_id'],
            ':motif'           => $data['motif'],
            ':niveau'          => $data['niveau'],
            ':date_attribution'=> $data['date_attribution'],
            ':id'              => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function valider(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE vs_recompenses
            SET statut = 'validee', valide_par = :vpar, valide_le = NOW(), updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL AND statut = 'attribuee'
        ");
        $stmt->execute([':vpar' => $userId, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function revoquer(int $id, int $userId, string $motif): bool
    {
        $stmt = $this->db->prepare("
            UPDATE vs_recompenses
            SET statut = 'revoquee', revoque_par = :rpar,
                revoque_le = NOW(), motif_revocation = :motif,
                updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL AND statut IN ('attribuee','validee')
        ");
        $stmt->execute([':rpar' => $userId, ':motif' => $motif, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE vs_recompenses SET deleted_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ── Historique ────────────────────────────────────────────────────────────

    public function insertHistorique(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO vs_recompenses_historique
                (recompense_id, ancien_statut, nouveau_statut, motif, modifie_par)
            VALUES (:rid, :as, :ns, :motif, :mpar)
        ");
        $stmt->execute([
            ':rid'   => $data['recompense_id'],
            ':as'    => $data['ancien_statut'] ?? null,
            ':ns'    => $data['nouveau_statut'],
            ':motif' => $data['motif'] ?? null,
            ':mpar'  => $data['modifie_par'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function findHistorique(int $rewardId): array
    {
        $stmt = $this->db->prepare("
            SELECT h.*,
                   u.nom    AS modifie_par_nom,
                   u.prenom AS modifie_par_prenom
            FROM   vs_recompenses_historique h
            JOIN   users u ON u.id = h.modifie_par
            WHERE  h.recompense_id = :rid
            ORDER  BY h.modifie_le DESC
        ");
        $stmt->execute([':rid' => $rewardId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Catégories ────────────────────────────────────────────────────────────

    public function findAllCategories(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM vs_recompense_categories WHERE actif = 1 ORDER BY nom ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findCategoryById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM vs_recompense_categories WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function statsByEleve(int $eleveId, string $annee): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(r.id)                           AS total,
                SUM(r.statut = 'validee')             AS validees,
                SUM(r.statut = 'revoquee')            AS revoquees,
                SUM(r.niveau = 'etablissement')       AS niveau_etab,
                SUM(r.niveau = 'academique')          AS niveau_acad,
                cat.nom                               AS categorie_la_plus_frequente
            FROM vs_recompenses r
            LEFT JOIN vs_recompense_categories cat ON cat.id = (
                SELECT categorie_id FROM vs_recompenses
                WHERE eleve_id = :eid AND annee_scolaire = :annee AND deleted_at IS NULL
                GROUP BY categorie_id ORDER BY COUNT(*) DESC LIMIT 1
            )
            WHERE r.eleve_id = :eid2 AND r.annee_scolaire = :annee2
              AND r.deleted_at IS NULL
        ");
        $stmt->execute([
            ':eid'   => $eleveId, ':annee'  => $annee,
            ':eid2'  => $eleveId, ':annee2' => $annee,
        ]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    public function statsByClasse(int $classeId, string $annee): array
    {
        $stmt = $this->db->prepare("
            SELECT
                e.id                                AS eleve_id,
                e.nom                               AS eleve_nom,
                e.prenom                            AS eleve_prenom,
                COUNT(r.id)                         AS total_recompenses,
                SUM(r.statut = 'validee')           AS validees,
                SUM(r.niveau IN ('etablissement','academique')) AS distinctions_hautes
            FROM   eleves e
            LEFT JOIN vs_recompenses r ON r.eleve_id = e.id
                AND r.classe_id = :cid AND r.annee_scolaire = :annee
                AND r.deleted_at IS NULL AND r.statut != 'revoquee'
            WHERE  e.classe_id = :cid2 AND e.actif = 1
            GROUP  BY e.id, e.nom, e.prenom
            ORDER  BY total_recompenses DESC, e.nom ASC
        ");
        $stmt->execute([':cid' => $classeId, ':annee' => $annee, ':cid2' => $classeId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Classement comportemental : récompenses positives - pénalités discipline.
     * Discipline est requêtée directement (pas d'import) pour rester découplé.
     */
    public function classementComportemental(int $classeId, string $annee): array
    {
        $stmt = $this->db->prepare("
            SELECT
                e.id                                                AS eleve_id,
                e.nom                                              AS eleve_nom,
                e.prenom                                           AS eleve_prenom,
                COALESCE(rw.total_recompenses, 0)                 AS total_recompenses,
                COALESCE(inc.total_incidents, 0)                   AS total_incidents,
                (COALESCE(rw.total_recompenses, 0) * 2
                    - COALESCE(inc.total_incidents, 0))            AS score_comportemental
            FROM eleves e

            LEFT JOIN (
                SELECT eleve_id, COUNT(*) AS total_recompenses
                FROM vs_recompenses
                WHERE classe_id = :cid AND annee_scolaire = :annee
                  AND deleted_at IS NULL AND statut != 'revoquee'
                GROUP BY eleve_id
            ) rw ON rw.eleve_id = e.id

            LEFT JOIN (
                SELECT d.eleve_id, COUNT(i.id) AS total_incidents
                FROM vs_incidents_discipline i
                JOIN vs_dossiers_discipline d ON d.id = i.dossier_id
                WHERE d.classe_id = :cid2 AND d.annee_scolaire = :annee2
                  AND i.deleted_at IS NULL AND d.deleted_at IS NULL
                GROUP BY d.eleve_id
            ) inc ON inc.eleve_id = e.id

            WHERE e.classe_id = :cid3 AND e.actif = 1
            ORDER BY score_comportemental DESC, e.nom ASC
        ");
        $stmt->execute([
            ':cid'   => $classeId, ':annee'  => $annee,
            ':cid2'  => $classeId, ':annee2' => $annee,
            ':cid3'  => $classeId,
        ]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countByEleveAndAnnee(int $eleveId, string $annee): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM vs_recompenses
            WHERE eleve_id = :eid AND annee_scolaire = :annee
              AND deleted_at IS NULL AND statut != 'revoquee'
        ");
        $stmt->execute([':eid' => $eleveId, ':annee' => $annee]);
        return (int)$stmt->fetchColumn();
    }

    // ── Privé ─────────────────────────────────────────────────────────────────

    private function buildWhere(RewardFiltersDTO $f): array
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
        if ($f->categorieId !== null) {
            $conditions[] = 'r.categorie_id = :categorie_id';
            $params[':categorie_id'] = $f->categorieId;
        }
        if ($f->niveau !== null) {
            $conditions[] = 'r.niveau = :niveau';
            $params[':niveau'] = $f->niveau;
        }
        if ($f->statut !== null) {
            $conditions[] = 'r.statut = :statut';
            $params[':statut'] = $f->statut;
        }
        if ($f->dateDebut !== null) {
            $conditions[] = 'r.date_attribution >= :date_debut';
            $params[':date_debut'] = $f->dateDebut;
        }
        if ($f->dateFin !== null) {
            $conditions[] = 'r.date_attribution <= :date_fin';
            $params[':date_fin'] = $f->dateFin;
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);
        return [$where, $params];
    }
}
