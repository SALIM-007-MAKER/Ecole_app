<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\Repositories;

use App\Modules\VieScolaire\EmploisDuTemps\DTO\TimetableFiltersDTO;
use Core\Database;

class TimetableRepository
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ── Emplois du temps (en-têtes) ───────────────────────────────────────────

    public function findEdtById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT e.*,
                   c.nom     AS classe_nom,
                   u.nom     AS cree_par_nom,
                   u.prenom  AS cree_par_prenom,
                   v.nom     AS publie_par_nom,
                   v.prenom  AS publie_par_prenom
            FROM   vs_emplois_du_temps e
            JOIN   classes c ON c.id = e.classe_id
            JOIN   users   u ON u.id = e.cree_par
            LEFT JOIN users v ON v.id = e.publie_par
            WHERE  e.id = :id AND e.deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findEdtByClasse(int $classeId, string $annee, ?int $periodeId, string $semaineType): ?array
    {
        if ($periodeId !== null) {
            $stmt = $this->db->prepare("
                SELECT * FROM vs_emplois_du_temps
                WHERE classe_id = :cid AND annee_scolaire = :annee
                  AND periode_id = :pid AND semaine_type = :st
                  AND deleted_at IS NULL
                ORDER BY version DESC LIMIT 1
            ");
            $stmt->execute([':cid' => $classeId, ':annee' => $annee, ':pid' => $periodeId, ':st' => $semaineType]);
        } else {
            $stmt = $this->db->prepare("
                SELECT * FROM vs_emplois_du_temps
                WHERE classe_id = :cid AND annee_scolaire = :annee
                  AND periode_id IS NULL AND semaine_type = :st
                  AND deleted_at IS NULL
                ORDER BY version DESC LIMIT 1
            ");
            $stmt->execute([':cid' => $classeId, ':annee' => $annee, ':st' => $semaineType]);
        }
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findAllEdts(TimetableFiltersDTO $f): array
    {
        [$where, $params] = $this->buildWhere($f);
        $offset = ($f->page - 1) * $f->perPage;

        $stmt = $this->db->prepare("
            SELECT e.*,
                   c.nom AS classe_nom,
                   (SELECT COUNT(*) FROM vs_edt_creneaux cr
                    WHERE cr.emploi_du_temps_id = e.id AND cr.deleted_at IS NULL) AS nb_creneaux
            FROM   vs_emplois_du_temps e
            JOIN   classes c ON c.id = e.classe_id
            {$where}
            ORDER  BY e.updated_at DESC
            LIMIT  :limit OFFSET :offset
        ");
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit',  $f->perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,     \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countAllEdts(TimetableFiltersDTO $f): int
    {
        [$where, $params] = $this->buildWhere($f);
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM vs_emplois_du_temps e {$where}
        ");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function insertEdt(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO vs_emplois_du_temps
                (classe_id, annee_scolaire, periode_id, semaine_type, cree_par)
            VALUES (:cid, :annee, :pid, :st, :cpar)
        ");
        $stmt->execute([
            ':cid'  => $data['classe_id'],
            ':annee'=> $data['annee_scolaire'],
            ':pid'  => $data['periode_id'] ?? null,
            ':st'   => $data['semaine_type'] ?? 'standard',
            ':cpar' => $data['cree_par'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateEdtStatut(int $id, string $statut, ?int $userId = null): bool
    {
        if ($statut === 'publie' && $userId !== null) {
            $stmt = $this->db->prepare("
                UPDATE vs_emplois_du_temps
                SET statut = :s, publie_par = :upar, publie_le = NOW(), updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL
            ");
            $stmt->execute([':s' => $statut, ':upar' => $userId, ':id' => $id]);
        } else {
            $stmt = $this->db->prepare("
                UPDATE vs_emplois_du_temps
                SET statut = :s, updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL
            ");
            $stmt->execute([':s' => $statut, ':id' => $id]);
        }
        return $stmt->rowCount() > 0;
    }

    public function incrementVersion(int $id): int
    {
        $stmt = $this->db->prepare("
            UPDATE vs_emplois_du_temps
            SET version = version + 1, updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        $stmt2 = $this->db->prepare("SELECT version FROM vs_emplois_du_temps WHERE id = :id");
        $stmt2->execute([':id' => $id]);
        return (int)$stmt2->fetchColumn();
    }

    // ── Créneaux ──────────────────────────────────────────────────────────────

    public function findCreneauxByEdt(int $edtId): array
    {
        $stmt = $this->db->prepare("
            SELECT cr.*,
                   m.nom         AS matiere_nom,
                   m.couleur     AS matiere_couleur,
                   p.libelle     AS plage_libelle,
                   p.heure_debut AS plage_debut,
                   p.heure_fin   AS plage_fin,
                   p.ordre       AS plage_ordre,
                   e.nom         AS enseignant_nom,
                   e.prenom      AS enseignant_prenom,
                   s.nom         AS salle_nom,
                   s.code        AS salle_code
            FROM   vs_edt_creneaux     cr
            JOIN   matieres            m  ON m.id = cr.matiere_id
            JOIN   vs_edt_plages_horaires p ON p.id = cr.plage_id
            JOIN   users               e  ON e.id = cr.enseignant_id
            LEFT JOIN vs_edt_salles    s  ON s.id = cr.salle_id
            WHERE  cr.emploi_du_temps_id = :eid AND cr.deleted_at IS NULL
            ORDER  BY cr.jour ASC, p.ordre ASC
        ");
        $stmt->execute([':eid' => $edtId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findCreneauById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT cr.*,
                   m.nom         AS matiere_nom,
                   p.libelle     AS plage_libelle,
                   p.heure_debut AS plage_debut,
                   p.heure_fin   AS plage_fin,
                   e.nom         AS enseignant_nom,
                   e.prenom      AS enseignant_prenom,
                   s.nom         AS salle_nom
            FROM   vs_edt_creneaux     cr
            JOIN   matieres            m  ON m.id = cr.matiere_id
            JOIN   vs_edt_plages_horaires p ON p.id = cr.plage_id
            JOIN   users               e  ON e.id = cr.enseignant_id
            LEFT JOIN vs_edt_salles    s  ON s.id = cr.salle_id
            WHERE  cr.id = :id AND cr.deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function insertCreneau(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO vs_edt_creneaux
                (emploi_du_temps_id, classe_id, matiere_id, enseignant_id,
                 salle_id, jour, plage_id, annee_scolaire, type_cours, couleur, note)
            VALUES
                (:edt, :cid, :mid, :eid, :sid, :jour, :plage, :annee, :type, :couleur, :note)
        ");
        $stmt->execute([
            ':edt'   => $data['emploi_du_temps_id'],
            ':cid'   => $data['classe_id'],
            ':mid'   => $data['matiere_id'],
            ':eid'   => $data['enseignant_id'],
            ':sid'   => $data['salle_id']      ?? null,
            ':jour'  => $data['jour'],
            ':plage' => $data['plage_id'],
            ':annee' => $data['annee_scolaire'],
            ':type'  => $data['type_cours']    ?? 'cours',
            ':couleur'=> $data['couleur']      ?? null,
            ':note'  => $data['note']          ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateCreneau(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE vs_edt_creneaux
            SET matiere_id  = :mid,
                enseignant_id = :eid,
                salle_id    = :sid,
                jour        = :jour,
                plage_id    = :plage,
                type_cours  = :type,
                couleur     = :couleur,
                note        = :note,
                updated_at  = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':mid'   => $data['matiere_id'],
            ':eid'   => $data['enseignant_id'],
            ':sid'   => $data['salle_id']    ?? null,
            ':jour'  => $data['jour'],
            ':plage' => $data['plage_id'],
            ':type'  => $data['type_cours']  ?? 'cours',
            ':couleur'=> $data['couleur']    ?? null,
            ':note'  => $data['note']        ?? null,
            ':id'    => $id,
        ]);
        return $stmt->rowCount() >= 0;
    }

    public function softDeleteCreneau(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE vs_edt_creneaux SET deleted_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ── Détection de conflits ─────────────────────────────────────────────────

    public function checkConflitEnseignant(int $enseignantId, int $jour, int $plageId, string $annee, ?int $excludeId = null): ?array
    {
        $sql = "
            SELECT cr.*, c.nom AS classe_nom, m.nom AS matiere_nom
            FROM   vs_edt_creneaux cr
            JOIN   classes c ON c.id = cr.classe_id
            JOIN   matieres m ON m.id = cr.matiere_id
            JOIN   vs_emplois_du_temps e ON e.id = cr.emploi_du_temps_id
            WHERE  cr.enseignant_id = :eid AND cr.jour = :jour
              AND  cr.plage_id = :plage AND cr.annee_scolaire = :annee
              AND  cr.deleted_at IS NULL AND e.statut != 'archive' AND e.deleted_at IS NULL
        ";
        if ($excludeId !== null) $sql .= " AND cr.id != :excl";
        $stmt = $this->db->prepare($sql);
        $params = [':eid' => $enseignantId, ':jour' => $jour, ':plage' => $plageId, ':annee' => $annee];
        if ($excludeId !== null) $params[':excl'] = $excludeId;
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function checkConflitSalle(int $salleId, int $jour, int $plageId, string $annee, ?int $excludeId = null): ?array
    {
        $sql = "
            SELECT cr.*, c.nom AS classe_nom, m.nom AS matiere_nom
            FROM   vs_edt_creneaux cr
            JOIN   classes c ON c.id = cr.classe_id
            JOIN   matieres m ON m.id = cr.matiere_id
            JOIN   vs_emplois_du_temps e ON e.id = cr.emploi_du_temps_id
            WHERE  cr.salle_id = :sid AND cr.jour = :jour
              AND  cr.plage_id = :plage AND cr.annee_scolaire = :annee
              AND  cr.deleted_at IS NULL AND e.statut != 'archive' AND e.deleted_at IS NULL
        ";
        if ($excludeId !== null) $sql .= " AND cr.id != :excl";
        $stmt = $this->db->prepare($sql);
        $params = [':sid' => $salleId, ':jour' => $jour, ':plage' => $plageId, ':annee' => $annee];
        if ($excludeId !== null) $params[':excl'] = $excludeId;
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function checkConflitClasse(int $classeId, int $jour, int $plageId, string $annee, ?int $excludeId = null): ?array
    {
        $sql = "
            SELECT cr.*, m.nom AS matiere_nom
            FROM   vs_edt_creneaux cr
            JOIN   matieres m ON m.id = cr.matiere_id
            JOIN   vs_emplois_du_temps e ON e.id = cr.emploi_du_temps_id
            WHERE  cr.classe_id = :cid AND cr.jour = :jour
              AND  cr.plage_id = :plage AND cr.annee_scolaire = :annee
              AND  cr.deleted_at IS NULL AND e.statut != 'archive' AND e.deleted_at IS NULL
        ";
        if ($excludeId !== null) $sql .= " AND cr.id != :excl";
        $stmt = $this->db->prepare($sql);
        $params = [':cid' => $classeId, ':jour' => $jour, ':plage' => $plageId, ':annee' => $annee];
        if ($excludeId !== null) $params[':excl'] = $excludeId;
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Référentiels ──────────────────────────────────────────────────────────

    public function findAllPlages(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM vs_edt_plages_horaires WHERE actif = 1 ORDER BY ordre ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findAllSalles(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM vs_edt_salles WHERE actif = 1 ORDER BY nom ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── EDT par enseignant ────────────────────────────────────────────────────

    public function findCreneauxByEnseignant(int $enseignantId, string $annee): array
    {
        $stmt = $this->db->prepare("
            SELECT cr.*,
                   c.nom         AS classe_nom,
                   m.nom         AS matiere_nom,
                   m.couleur     AS matiere_couleur,
                   p.libelle     AS plage_libelle,
                   p.heure_debut AS plage_debut,
                   p.heure_fin   AS plage_fin,
                   p.ordre       AS plage_ordre,
                   s.nom         AS salle_nom
            FROM   vs_edt_creneaux     cr
            JOIN   classes             c  ON c.id = cr.classe_id
            JOIN   matieres            m  ON m.id = cr.matiere_id
            JOIN   vs_edt_plages_horaires p ON p.id = cr.plage_id
            LEFT JOIN vs_edt_salles    s  ON s.id = cr.salle_id
            JOIN   vs_emplois_du_temps e  ON e.id = cr.emploi_du_temps_id
            WHERE  cr.enseignant_id = :eid AND cr.annee_scolaire = :annee
              AND  cr.deleted_at IS NULL AND e.deleted_at IS NULL AND e.statut != 'archive'
            ORDER  BY cr.jour ASC, p.ordre ASC
        ");
        $stmt->execute([':eid' => $enseignantId, ':annee' => $annee]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countHeuresByEnseignant(int $enseignantId, string $annee): array
    {
        $stmt = $this->db->prepare("
            SELECT
                cr.matiere_id,
                m.nom AS matiere_nom,
                COUNT(*) AS nb_creneaux,
                COUNT(*) * (
                    SELECT TIME_TO_SEC(TIMEDIFF(p2.heure_fin, p2.heure_debut)) / 3600.0
                    FROM vs_edt_plages_horaires p2
                    WHERE p2.id = cr.plage_id LIMIT 1
                ) AS heures_estimees
            FROM vs_edt_creneaux cr
            JOIN matieres m ON m.id = cr.matiere_id
            JOIN vs_emplois_du_temps e ON e.id = cr.emploi_du_temps_id
            WHERE cr.enseignant_id = :eid AND cr.annee_scolaire = :annee
              AND cr.deleted_at IS NULL AND e.deleted_at IS NULL AND e.statut != 'archive'
            GROUP BY cr.matiere_id, m.nom
            ORDER BY nb_creneaux DESC
        ");
        $stmt->execute([':eid' => $enseignantId, ':annee' => $annee]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Versions ──────────────────────────────────────────────────────────────

    public function insertVersion(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO vs_edt_versions
                (emploi_du_temps_id, version, snapshot, motif, modifie_par)
            VALUES (:eid, :ver, :snap, :motif, :mpar)
        ");
        $stmt->execute([
            ':eid'   => $data['emploi_du_temps_id'],
            ':ver'   => $data['version'],
            ':snap'  => $data['snapshot'],
            ':motif' => $data['motif'] ?? null,
            ':mpar'  => $data['modifie_par'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function findVersions(int $edtId): array
    {
        $stmt = $this->db->prepare("
            SELECT v.*,
                   u.nom    AS modifie_par_nom,
                   u.prenom AS modifie_par_prenom
            FROM   vs_edt_versions v
            JOIN   users u ON u.id = v.modifie_par
            WHERE  v.emploi_du_temps_id = :eid
            ORDER  BY v.version DESC
        ");
        $stmt->execute([':eid' => $edtId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Remplacements ─────────────────────────────────────────────────────────

    public function insertRemplacement(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO vs_edt_remplacements
                (creneau_id, date_remplacement, enseignant_absent_id, remplacant_id,
                 matiere_remplacement_id, salle_remplacement_id, motif_absence, cree_par)
            VALUES (:cid, :date, :eabs, :rpl, :mat, :sal, :motif, :cpar)
        ");
        $stmt->execute([
            ':cid'  => $data['creneau_id'],
            ':date' => $data['date_remplacement'],
            ':eabs' => $data['enseignant_absent_id'],
            ':rpl'  => $data['remplacant_id']          ?? null,
            ':mat'  => $data['matiere_remplacement_id']?? null,
            ':sal'  => $data['salle_remplacement_id']  ?? null,
            ':motif'=> $data['motif_absence']          ?? null,
            ':cpar' => $data['cree_par'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function findRemplacementsByDate(string $date): array
    {
        $stmt = $this->db->prepare("
            SELECT r.*,
                   cr.jour, cr.plage_id,
                   p.libelle     AS plage_libelle,
                   p.heure_debut AS plage_debut,
                   c.nom         AS classe_nom,
                   m.nom         AS matiere_nom,
                   ea.nom        AS absent_nom,
                   ea.prenom     AS absent_prenom,
                   rpl.nom       AS remplacant_nom,
                   rpl.prenom    AS remplacant_prenom
            FROM   vs_edt_remplacements r
            JOIN   vs_edt_creneaux cr       ON cr.id  = r.creneau_id
            JOIN   vs_edt_plages_horaires p ON p.id   = cr.plage_id
            JOIN   classes c                ON c.id   = cr.classe_id
            JOIN   matieres m               ON m.id   = cr.matiere_id
            JOIN   users ea                 ON ea.id  = r.enseignant_absent_id
            LEFT JOIN users rpl             ON rpl.id = r.remplacant_id
            WHERE  r.date_remplacement = :date AND r.deleted_at IS NULL
            ORDER  BY cr.jour ASC, p.ordre ASC
        ");
        $stmt->execute([':date' => $date]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findRemplacementsByCreneau(int $creneauId): array
    {
        $stmt = $this->db->prepare("
            SELECT r.*,
                   ea.nom    AS absent_nom,
                   ea.prenom AS absent_prenom,
                   rpl.nom   AS remplacant_nom,
                   rpl.prenom AS remplacant_prenom
            FROM   vs_edt_remplacements r
            JOIN   users ea               ON ea.id  = r.enseignant_absent_id
            LEFT JOIN users rpl           ON rpl.id = r.remplacant_id
            WHERE  r.creneau_id = :cid AND r.deleted_at IS NULL
            ORDER  BY r.date_remplacement DESC
        ");
        $stmt->execute([':cid' => $creneauId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function updateRemplacementStatut(int $id, string $statut): bool
    {
        $stmt = $this->db->prepare("
            UPDATE vs_edt_remplacements SET statut = :s, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([':s' => $statut, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function statsByClasse(int $classeId, string $annee): array
    {
        $stmt = $this->db->prepare("
            SELECT
                m.nom        AS matiere_nom,
                COUNT(cr.id) AS nb_creneaux,
                u.nom        AS enseignant_nom,
                u.prenom     AS enseignant_prenom
            FROM vs_edt_creneaux cr
            JOIN matieres m ON m.id = cr.matiere_id
            JOIN users    u ON u.id = cr.enseignant_id
            JOIN vs_emplois_du_temps e ON e.id = cr.emploi_du_temps_id
            WHERE cr.classe_id = :cid AND cr.annee_scolaire = :annee
              AND cr.deleted_at IS NULL AND e.deleted_at IS NULL AND e.statut != 'archive'
            GROUP BY cr.matiere_id, cr.enseignant_id, m.nom, u.nom, u.prenom
            ORDER BY nb_creneaux DESC
        ");
        $stmt->execute([':cid' => $classeId, ':annee' => $annee]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Privé ─────────────────────────────────────────────────────────────────

    private function buildWhere(TimetableFiltersDTO $f): array
    {
        $conditions = ['e.deleted_at IS NULL'];
        $params     = [];

        if ($f->classeId !== null) {
            $conditions[] = 'e.classe_id = :classe_id';
            $params[':classe_id'] = $f->classeId;
        }
        if ($f->anneeScolaire !== null) {
            $conditions[] = 'e.annee_scolaire = :annee';
            $params[':annee'] = $f->anneeScolaire;
        }
        if ($f->statut !== null) {
            $conditions[] = 'e.statut = :statut';
            $params[':statut'] = $f->statut;
        }
        if ($f->semaineType !== null) {
            $conditions[] = 'e.semaine_type = :semaine_type';
            $params[':semaine_type'] = $f->semaineType;
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);
        return [$where, $params];
    }
}
