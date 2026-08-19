<?php

namespace App\Modules\VieScolaire\Discipline\Repositories;

use App\Modules\VieScolaire\Discipline\DTO\DisciplineFiltersDTO;
use Core\Database;

class DisciplineRepository
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ── Dossiers ──────────────────────────────────────────────────────────────

    public function findDossierById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT d.*,
                   e.nom          AS eleve_nom,
                   e.prenom       AS eleve_prenom,
                   e.matricule    AS eleve_matricule,
                   c.nom          AS classe_nom,
                   u.nom          AS cree_par_nom,
                   u.prenom       AS cree_par_prenom
            FROM   vs_dossiers_discipline d
            JOIN   eleves  e ON e.id = d.eleve_id
            JOIN   classes c ON c.id = d.classe_id
            JOIN   users   u ON u.id = d.cree_par
            WHERE  d.id = :id AND d.deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findDossierByEleveAndAnnee(int $eleveId, string $annee): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM vs_dossiers_discipline
            WHERE eleve_id = :eid AND annee_scolaire = :annee AND deleted_at IS NULL
        ");
        $stmt->execute([':eid' => $eleveId, ':annee' => $annee]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findDossiers(DisciplineFiltersDTO $f): array
    {
        [$where, $params] = $this->buildWhere($f);
        $offset = ($f->page - 1) * $f->perPage;

        $stmt = $this->db->prepare("
            SELECT d.*,
                   e.nom         AS eleve_nom,
                   e.prenom      AS eleve_prenom,
                   c.nom         AS classe_nom
            FROM   vs_dossiers_discipline d
            JOIN   eleves  e ON e.id = d.eleve_id
            JOIN   classes c ON c.id = d.classe_id
            {$where}
            ORDER  BY d.updated_at DESC
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

    public function countDossiers(DisciplineFiltersDTO $f): int
    {
        [$where, $params] = $this->buildWhere($f);
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM vs_dossiers_discipline d {$where}
        ");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function insertDossier(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO vs_dossiers_discipline
                (eleve_id, classe_id, annee_scolaire, statut, nb_incidents, cree_par)
            VALUES (:eid, :cid, :annee, 'ouvert', 0, :cpar)
        ");
        $stmt->execute([
            ':eid'   => $data['eleve_id'],
            ':cid'   => $data['classe_id'],
            ':annee' => $data['annee_scolaire'],
            ':cpar'  => $data['cree_par'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateDossierStatut(int $id, string $statut, ?int $userId = null): bool
    {
        if ($statut === 'clos') {
            $stmt = $this->db->prepare("
                UPDATE vs_dossiers_discipline
                SET statut = :s, clos_par = :cp, clos_le = NOW(), updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL
            ");
            $stmt->execute([':s' => $statut, ':cp' => $userId, ':id' => $id]);
        } else {
            $stmt = $this->db->prepare("
                UPDATE vs_dossiers_discipline
                SET statut = :s, updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL
            ");
            $stmt->execute([':s' => $statut, ':id' => $id]);
        }
        return $stmt->rowCount() > 0;
    }

    public function incrementNbIncidents(int $dossierId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE vs_dossiers_discipline
            SET nb_incidents = nb_incidents + 1, statut = 'en_cours', updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([':id' => $dossierId]);
        return $stmt->rowCount() > 0;
    }

    // ── Incidents ─────────────────────────────────────────────────────────────

    public function findIncidentById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT i.*,
                   c.nom   AS categorie_nom,
                   c.code  AS categorie_code,
                   u.nom   AS signale_par_nom,
                   u.prenom AS signale_par_prenom
            FROM   vs_incidents_discipline i
            JOIN   vs_discipline_categories c ON c.id = i.categorie_id
            JOIN   users u ON u.id = i.signale_par
            WHERE  i.id = :id AND i.deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findIncidentsByDossier(int $dossierId): array
    {
        $stmt = $this->db->prepare("
            SELECT i.*,
                   c.nom    AS categorie_nom,
                   c.code   AS categorie_code,
                   u.nom    AS signale_par_nom,
                   u.prenom AS signale_par_prenom
            FROM   vs_incidents_discipline i
            JOIN   vs_discipline_categories c ON c.id = i.categorie_id
            JOIN   users u ON u.id = i.signale_par
            WHERE  i.dossier_id = :did AND i.deleted_at IS NULL
            ORDER  BY i.date_incident DESC, i.created_at DESC
        ");
        $stmt->execute([':did' => $dossierId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function insertIncident(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO vs_incidents_discipline
                (dossier_id, categorie_id, gravite, description,
                 date_incident, heure_incident, lieu, matiere_id,
                 signale_par, piece_jointe)
            VALUES
                (:dossier_id, :categorie_id, :gravite, :description,
                 :date_incident, :heure_incident, :lieu, :matiere_id,
                 :signale_par, :piece_jointe)
        ");
        $stmt->execute([
            ':dossier_id'    => $data['dossier_id'],
            ':categorie_id'  => $data['categorie_id'],
            ':gravite'       => $data['gravite'],
            ':description'   => $data['description'],
            ':date_incident' => $data['date_incident'],
            ':heure_incident'=> $data['heure_incident'] ?? null,
            ':lieu'          => $data['lieu']           ?? null,
            ':matiere_id'    => $data['matiere_id']     ?? null,
            ':signale_par'   => $data['signale_par'],
            ':piece_jointe'  => $data['piece_jointe']   ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateIncidentStatut(int $id, string $statut): bool
    {
        $stmt = $this->db->prepare("
            UPDATE vs_incidents_discipline SET statut = :s, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([':s' => $statut, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function softDeleteIncident(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE vs_incidents_discipline SET deleted_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ── Sanctions ─────────────────────────────────────────────────────────────

    public function findSanctionById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT s.*,
                   p.nom    AS prononce_par_nom,
                   p.prenom AS prononce_par_prenom,
                   v.nom    AS valide_par_nom,
                   v.prenom AS valide_par_prenom
            FROM   vs_sanctions_discipline s
            JOIN   users p ON p.id = s.prononce_par
            LEFT JOIN users v ON v.id = s.valide_par
            WHERE  s.id = :id AND s.deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findSanctionsByDossier(int $dossierId): array
    {
        $stmt = $this->db->prepare("
            SELECT s.*,
                   p.nom    AS prononce_par_nom,
                   p.prenom AS prononce_par_prenom
            FROM   vs_sanctions_discipline s
            JOIN   users p ON p.id = s.prononce_par
            WHERE  s.dossier_id = :did AND s.deleted_at IS NULL
            ORDER  BY s.date_sanction DESC
        ");
        $stmt->execute([':did' => $dossierId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function insertSanction(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO vs_sanctions_discipline
                (dossier_id, incident_id, type_sanction, description, motif,
                 date_sanction, date_debut, date_fin, duree_jours, prononce_par)
            VALUES
                (:dossier_id, :incident_id, :type_sanction, :description, :motif,
                 :date_sanction, :date_debut, :date_fin, :duree_jours, :prononce_par)
        ");
        $stmt->execute([
            ':dossier_id'   => $data['dossier_id'],
            ':incident_id'  => $data['incident_id']  ?? null,
            ':type_sanction'=> $data['type_sanction'],
            ':description'  => $data['description']  ?? null,
            ':motif'        => $data['motif'],
            ':date_sanction'=> $data['date_sanction'],
            ':date_debut'   => $data['date_debut']   ?? null,
            ':date_fin'     => $data['date_fin']     ?? null,
            ':duree_jours'  => $data['duree_jours']  ?? null,
            ':prononce_par' => $data['prononce_par'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateSanctionStatut(int $id, string $statut, ?int $userId = null): bool
    {
        if ($statut === 'effective' && $userId !== null) {
            $stmt = $this->db->prepare("
                UPDATE vs_sanctions_discipline
                SET statut = :s, valide_par = :vpar, valide_le = NOW(), updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL
            ");
            $stmt->execute([':s' => $statut, ':vpar' => $userId, ':id' => $id]);
        } else {
            $stmt = $this->db->prepare("
                UPDATE vs_sanctions_discipline
                SET statut = :s, updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL
            ");
            $stmt->execute([':s' => $statut, ':id' => $id]);
        }
        return $stmt->rowCount() >= 0;
    }

    public function insertSanctionHistorique(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO vs_sanctions_historique
                (sanction_id, ancien_statut, nouveau_statut, motif, modifie_par)
            VALUES (:sid, :as, :ns, :motif, :mpar)
        ");
        $stmt->execute([
            ':sid'   => $data['sanction_id'],
            ':as'    => $data['ancien_statut'],
            ':ns'    => $data['nouveau_statut'],
            ':motif' => $data['motif'] ?? null,
            ':mpar'  => $data['modifie_par'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    // ── Appels ────────────────────────────────────────────────────────────────

    public function findAppelBySanction(int $sanctionId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT a.*,
                   u.nom    AS depose_par_nom,
                   u.prenom AS depose_par_prenom,
                   e.nom    AS examine_par_nom,
                   e.prenom AS examine_par_prenom
            FROM   vs_appels_discipline a
            JOIN   users u ON u.id = a.depose_par
            LEFT JOIN users e ON e.id = a.examine_par
            WHERE  a.sanction_id = :sid
        ");
        $stmt->execute([':sid' => $sanctionId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findAppelsByDossier(int $dossierId): array
    {
        $stmt = $this->db->prepare("
            SELECT a.*,
                   s.type_sanction,
                   u.nom    AS depose_par_nom,
                   u.prenom AS depose_par_prenom
            FROM   vs_appels_discipline a
            JOIN   vs_sanctions_discipline s ON s.id = a.sanction_id
            JOIN   users u ON u.id = a.depose_par
            WHERE  a.dossier_id = :did
            ORDER  BY a.depose_le DESC
        ");
        $stmt->execute([':did' => $dossierId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function insertAppel(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO vs_appels_discipline
                (sanction_id, dossier_id, description, piece_jointe, depose_par)
            VALUES (:sid, :did, :desc, :fich, :dpar)
        ");
        $stmt->execute([
            ':sid'  => $data['sanction_id'],
            ':did'  => $data['dossier_id'],
            ':desc' => $data['description'],
            ':fich' => $data['piece_jointe'] ?? null,
            ':dpar' => $data['depose_par'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateAppel(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE vs_appels_discipline
            SET statut = :s, examine_par = :epar, examine_le = NOW(),
                decision = :dec, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':s'    => $data['statut'],
            ':epar' => $data['examine_par'],
            ':dec'  => $data['decision'],
            ':id'   => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    // ── Référentiel ───────────────────────────────────────────────────────────

    public function findAllCategories(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM vs_discipline_categories WHERE actif = 1 ORDER BY nom ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function countIncidentsByEleveAndAnnee(int $eleveId, string $annee): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM vs_incidents_discipline i
            JOIN vs_dossiers_discipline d ON d.id = i.dossier_id
            WHERE d.eleve_id = :eid AND d.annee_scolaire = :annee
              AND i.deleted_at IS NULL AND d.deleted_at IS NULL
        ");
        $stmt->execute([':eid' => $eleveId, ':annee' => $annee]);
        return (int)$stmt->fetchColumn();
    }

    public function statsByEleve(int $eleveId, string $annee): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(i.id)                            AS total_incidents,
                SUM(i.gravite = 'mineur')              AS mineurs,
                SUM(i.gravite = 'moyen')               AS moyens,
                SUM(i.gravite = 'grave')               AS graves,
                SUM(i.gravite = 'tres_grave')          AS tres_graves,
                SUM(i.statut = 'traite')               AS traites,
                SUM(i.statut = 'classe')               AS classes
            FROM vs_incidents_discipline i
            JOIN vs_dossiers_discipline  d ON d.id = i.dossier_id
            WHERE d.eleve_id = :eid AND d.annee_scolaire = :annee
              AND i.deleted_at IS NULL AND d.deleted_at IS NULL
        ");
        $stmt->execute([':eid' => $eleveId, ':annee' => $annee]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    public function statsByClasse(int $classeId, string $annee): array
    {
        $stmt = $this->db->prepare("
            SELECT
                e.id                                   AS eleve_id,
                e.nom                                  AS eleve_nom,
                e.prenom                               AS eleve_prenom,
                COUNT(i.id)                            AS total_incidents,
                SUM(i.gravite IN ('grave','tres_grave')) AS incidents_graves,
                COUNT(s.id)                            AS total_sanctions
            FROM   eleves e
            LEFT JOIN vs_dossiers_discipline d ON d.eleve_id = e.id
                AND d.classe_id = :cid AND d.annee_scolaire = :annee AND d.deleted_at IS NULL
            LEFT JOIN vs_incidents_discipline i ON i.dossier_id = d.id AND i.deleted_at IS NULL
            LEFT JOIN vs_sanctions_discipline s ON s.dossier_id = d.id AND s.deleted_at IS NULL
            WHERE  e.classe_id = :cid AND e.actif = 1
            GROUP  BY e.id, e.nom, e.prenom
            ORDER  BY total_incidents DESC, e.nom ASC
        ");
        $stmt->execute([':cid' => $classeId, ':annee' => $annee]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Privé ─────────────────────────────────────────────────────────────────

    private function buildWhere(DisciplineFiltersDTO $f): array
    {
        $conditions = ['d.deleted_at IS NULL'];
        $params     = [];

        if ($f->eleveId !== null) {
            $conditions[] = 'd.eleve_id = :eleve_id';
            $params[':eleve_id'] = $f->eleveId;
        }
        if ($f->eleveIds !== null) {
            if (empty($f->eleveIds)) {
                $conditions[] = '1 = 0';
            } else {
                $placeholders = [];
                foreach (array_values($f->eleveIds) as $i => $eid) {
                    $key = ":scope_eleve_{$i}";
                    $placeholders[] = $key;
                    $params[$key] = $eid;
                }
                $conditions[] = 'd.eleve_id IN (' . implode(',', $placeholders) . ')';
            }
        }
        if ($f->classeId !== null) {
            $conditions[] = 'd.classe_id = :classe_id';
            $params[':classe_id'] = $f->classeId;
        }
        if ($f->anneeScolaire !== null) {
            $conditions[] = 'd.annee_scolaire = :annee';
            $params[':annee'] = $f->anneeScolaire;
        }
        if ($f->statut !== null) {
            $conditions[] = 'd.statut = :statut';
            $params[':statut'] = $f->statut;
        }
        if ($f->dateDebut !== null) {
            $conditions[] = 'd.created_at >= :date_debut';
            $params[':date_debut'] = $f->dateDebut . ' 00:00:00';
        }
        if ($f->dateFin !== null) {
            $conditions[] = 'd.created_at <= :date_fin';
            $params[':date_fin'] = $f->dateFin . ' 23:59:59';
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);
        return [$where, $params];
    }
}
