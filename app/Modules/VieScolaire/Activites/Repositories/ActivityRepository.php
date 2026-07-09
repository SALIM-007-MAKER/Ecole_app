<?php

namespace App\Modules\VieScolaire\Activites\Repositories;

use App\Modules\VieScolaire\Activites\DTO\ActivityFiltersDTO;
use Core\Database;

class ActivityRepository
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ── Activités ─────────────────────────────────────────────────────────────

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT a.*,
                   c.nom     AS categorie_nom,
                   c.couleur AS categorie_couleur,
                   c.icone   AS categorie_icone,
                   u.nom     AS cree_par_nom,
                   u.prenom  AS cree_par_prenom,
                   org.nom   AS organisateur_nom,
                   org.prenom AS organisateur_prenom,
                   (SELECT COUNT(*) FROM vs_activite_inscriptions
                    WHERE activite_id = a.id AND statut = 'inscrit' AND deleted_at IS NULL) AS nb_inscrits,
                   (SELECT COUNT(*) FROM vs_activite_inscriptions
                    WHERE activite_id = a.id AND statut = 'liste_attente' AND deleted_at IS NULL) AS nb_attente
            FROM   vs_activites a
            JOIN   vs_activite_categories c  ON c.id  = a.categorie_id
            JOIN   users                  u  ON u.id  = a.cree_par
            LEFT JOIN users               org ON org.id = a.organisateur_id
            WHERE  a.id = :id AND a.deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findAll(ActivityFiltersDTO $f): array
    {
        [$where, $params] = $this->buildWhere($f);
        $offset = ($f->page - 1) * $f->perPage;

        $stmt = $this->db->prepare("
            SELECT a.*,
                   c.nom     AS categorie_nom,
                   c.couleur AS categorie_couleur,
                   c.icone   AS categorie_icone,
                   (SELECT COUNT(*) FROM vs_activite_inscriptions
                    WHERE activite_id = a.id AND statut = 'inscrit' AND deleted_at IS NULL) AS nb_inscrits
            FROM   vs_activites a
            JOIN   vs_activite_categories c ON c.id = a.categorie_id
            {$where}
            ORDER  BY a.date_activite ASC, a.heure_debut ASC
            LIMIT  :limit OFFSET :offset
        ");
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit',  $f->perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,     \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countAll(ActivityFiltersDTO $f): int
    {
        [$where, $params] = $this->buildWhere($f);
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM vs_activites a {$where}
        ");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function insert(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO vs_activites
                (categorie_id, titre, description, lieu,
                 date_activite, heure_debut, heure_fin, capacite_max,
                 annee_scolaire, organisateur_id, cree_par)
            VALUES
                (:cid, :titre, :desc, :lieu,
                 :date, :hdeb, :hfin, :cap,
                 :annee, :org, :cpar)
        ");
        $stmt->execute([
            ':cid'   => $data['categorie_id'],
            ':titre' => $data['titre'],
            ':desc'  => $data['description']    ?? null,
            ':lieu'  => $data['lieu']           ?? null,
            ':date'  => $data['date_activite'],
            ':hdeb'  => $data['heure_debut'],
            ':hfin'  => $data['heure_fin'],
            ':cap'   => $data['capacite_max'],
            ':annee' => $data['annee_scolaire'],
            ':org'   => $data['organisateur_id'] ?? null,
            ':cpar'  => $data['cree_par'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE vs_activites
            SET categorie_id   = :cid,
                titre          = :titre,
                description    = :desc,
                lieu           = :lieu,
                date_activite  = :date,
                heure_debut    = :hdeb,
                heure_fin      = :hfin,
                capacite_max   = :cap,
                annee_scolaire = :annee,
                organisateur_id= :org,
                updated_at     = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':cid'   => $data['categorie_id'],
            ':titre' => $data['titre'],
            ':desc'  => $data['description']    ?? null,
            ':lieu'  => $data['lieu']           ?? null,
            ':date'  => $data['date_activite'],
            ':hdeb'  => $data['heure_debut'],
            ':hfin'  => $data['heure_fin'],
            ':cap'   => $data['capacite_max'],
            ':annee' => $data['annee_scolaire'],
            ':org'   => $data['organisateur_id'] ?? null,
            ':id'    => $id,
        ]);
        return $stmt->rowCount() >= 0;
    }

    public function updateStatut(int $id, string $statut, array $extra = []): bool
    {
        $sets = ['statut = :statut', 'updated_at = NOW()'];
        $params = [':statut' => $statut, ':id' => $id];

        if ($statut === 'publie' && isset($extra['publie_par'])) {
            $sets[] = 'publie_par = :ppar';
            $sets[] = 'publie_le = NOW()';
            $params[':ppar'] = $extra['publie_par'];
        }
        if ($statut === 'annule' && isset($extra['annule_par'])) {
            $sets[] = 'annule_par = :apar';
            $sets[] = 'annule_le = NOW()';
            $sets[] = 'motif_annulation = :motif';
            $params[':apar']  = $extra['annule_par'];
            $params[':motif'] = $extra['motif_annulation'] ?? null;
        }
        if ($statut === 'termine') {
            $sets[] = 'termine_le = NOW()';
        }

        $sql  = 'UPDATE vs_activites SET ' . implode(', ', $sets) . ' WHERE id = :id AND deleted_at IS NULL';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    // ── Classes & Responsables ────────────────────────────────────────────────

    public function insertClasses(int $activiteId, array $classeIds): void
    {
        if (empty($classeIds)) return;
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO vs_activite_classes (activite_id, classe_id) VALUES (:aid, :cid)
        ");
        foreach ($classeIds as $classeId) {
            $stmt->execute([':aid' => $activiteId, ':cid' => $classeId]);
        }
    }

    public function deleteClasses(int $activiteId): void
    {
        $this->db->prepare("DELETE FROM vs_activite_classes WHERE activite_id = ?")->execute([$activiteId]);
    }

    public function insertResponsables(int $activiteId, array $enseignantIds): void
    {
        if (empty($enseignantIds)) return;
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO vs_activite_responsables (activite_id, enseignant_id) VALUES (:aid, :eid)
        ");
        foreach ($enseignantIds as $eid) {
            $stmt->execute([':aid' => $activiteId, ':eid' => $eid]);
        }
    }

    public function deleteResponsables(int $activiteId): void
    {
        $this->db->prepare("DELETE FROM vs_activite_responsables WHERE activite_id = ?")->execute([$activiteId]);
    }

    public function findClasses(int $activiteId): array
    {
        $stmt = $this->db->prepare("
            SELECT c.id, c.nom FROM vs_activite_classes ac
            JOIN classes c ON c.id = ac.classe_id
            WHERE ac.activite_id = :aid ORDER BY c.nom
        ");
        $stmt->execute([':aid' => $activiteId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findResponsables(int $activiteId): array
    {
        $stmt = $this->db->prepare("
            SELECT u.id, u.nom, u.prenom FROM vs_activite_responsables ar
            JOIN users u ON u.id = ar.enseignant_id
            WHERE ar.activite_id = :aid ORDER BY u.nom
        ");
        $stmt->execute([':aid' => $activiteId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Inscriptions ──────────────────────────────────────────────────────────

    public function findInscriptions(int $activiteId): array
    {
        $stmt = $this->db->prepare("
            SELECT i.*,
                   e.nom         AS eleve_nom,
                   e.prenom      AS eleve_prenom,
                   e.matricule   AS eleve_matricule,
                   cl.nom        AS classe_nom
            FROM   vs_activite_inscriptions i
            JOIN   eleves e  ON e.id  = i.eleve_id
            LEFT JOIN classes cl ON cl.id = (
                SELECT classe_id FROM inscriptions
                WHERE eleve_id = i.eleve_id
                ORDER BY created_at DESC LIMIT 1
            )
            WHERE  i.activite_id = :aid AND i.deleted_at IS NULL
            ORDER  BY i.statut ASC, i.date_inscription ASC
        ");
        $stmt->execute([':aid' => $activiteId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countInscrits(int $activiteId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM vs_activite_inscriptions
            WHERE activite_id = :aid AND statut = 'inscrit' AND deleted_at IS NULL
        ");
        $stmt->execute([':aid' => $activiteId]);
        return (int)$stmt->fetchColumn();
    }

    public function findInscriptionById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT i.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom
            FROM vs_activite_inscriptions i
            JOIN eleves e ON e.id = i.eleve_id
            WHERE i.id = :id AND i.deleted_at IS NULL
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findInscriptionByEleveAndActivite(int $eleveId, int $activiteId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM vs_activite_inscriptions
            WHERE eleve_id = :eid AND activite_id = :aid AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':eid' => $eleveId, ':aid' => $activiteId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function insertInscription(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO vs_activite_inscriptions
                (activite_id, eleve_id, statut, inscrit_par, note)
            VALUES (:aid, :eid, :stat, :ipar, :note)
        ");
        $stmt->execute([
            ':aid'  => $data['activite_id'],
            ':eid'  => $data['eleve_id'],
            ':stat' => $data['statut'],
            ':ipar' => $data['inscrit_par'] ?? null,
            ':note' => $data['note']        ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateInscriptionStatut(int $id, string $statut, ?int $userId = null): bool
    {
        if ($statut === 'annule' && $userId !== null) {
            $stmt = $this->db->prepare("
                UPDATE vs_activite_inscriptions
                SET statut = :s, annule_par = :u, annule_le = NOW(), updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL
            ");
            $stmt->execute([':s' => $statut, ':u' => $userId, ':id' => $id]);
        } else {
            $stmt = $this->db->prepare("
                UPDATE vs_activite_inscriptions
                SET statut = :s, updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL
            ");
            $stmt->execute([':s' => $statut, ':id' => $id]);
        }
        return $stmt->rowCount() > 0;
    }

    public function findFirstListeAttente(int $activiteId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM vs_activite_inscriptions
            WHERE activite_id = :aid AND statut = 'liste_attente' AND deleted_at IS NULL
            ORDER BY date_inscription ASC LIMIT 1
        ");
        $stmt->execute([':aid' => $activiteId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updatePresences(int $activiteId, array $presences): void
    {
        // $presences = [inscription_id => 'present'|'absent']
        $stmt = $this->db->prepare("
            UPDATE vs_activite_inscriptions
            SET statut = :s, updated_at = NOW()
            WHERE id = :id AND activite_id = :aid AND deleted_at IS NULL
              AND statut IN ('inscrit','present','absent')
        ");
        foreach ($presences as $inscriptionId => $statut) {
            if (!in_array($statut, ['present', 'absent'], true)) continue;
            $stmt->execute([':s' => $statut, ':id' => $inscriptionId, ':aid' => $activiteId]);
        }
    }

    // ── Conflits EDT ─────────────────────────────────────────────────────────

    public function checkConflitsEdt(array $activite, array $classeIds, array $responsableIds): array
    {
        $conflits = [];
        $date = $activite['date_activite'];
        $jour = (int)date('N', strtotime($date)); // 1=Lun…7=Dim
        if ($jour > 6) return []; // Dimanche
        $annee     = $activite['annee_scolaire'];
        $heureDebut = $activite['heure_debut'];
        $heureFin   = $activite['heure_fin'];

        // Plages horaires qui chevauchent [heure_debut, heure_fin]
        $stmt = $this->db->prepare("
            SELECT id FROM vs_edt_plages_horaires
            WHERE actif = 1 AND heure_debut < :hfin AND heure_fin > :hdeb
        ");
        $stmt->execute([':hdeb' => $heureDebut, ':hfin' => $heureFin]);
        $plageIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($plageIds)) return [];

        $inPlages = implode(',', array_map('intval', $plageIds));

        // Conflits sur les classes
        if (!empty($classeIds)) {
            $inClasses = implode(',', array_map('intval', $classeIds));
            $stmt = $this->db->prepare("
                SELECT cr.classe_id, c.nom AS classe_nom, m.nom AS matiere_nom,
                       p.heure_debut AS plage_debut, p.heure_fin AS plage_fin
                FROM   vs_edt_creneaux cr
                JOIN   classes c  ON c.id = cr.classe_id
                JOIN   matieres m ON m.id = cr.matiere_id
                JOIN   vs_edt_plages_horaires p ON p.id = cr.plage_id
                JOIN   vs_emplois_du_temps e ON e.id = cr.emploi_du_temps_id
                WHERE  cr.classe_id IN ({$inClasses})
                  AND  cr.jour = :jour
                  AND  cr.plage_id IN ({$inPlages})
                  AND  cr.annee_scolaire = :annee
                  AND  cr.deleted_at IS NULL
                  AND  e.statut != 'archive' AND e.deleted_at IS NULL
                LIMIT 5
            ");
            $stmt->execute([':jour' => $jour, ':annee' => $annee]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $conflits[] = [
                    'type'    => 'classe',
                    'message' => "La classe {$row['classe_nom']} a cours de {$row['matiere_nom']} ({$row['plage_debut']}–{$row['plage_fin']}).",
                ];
            }
        }

        // Conflits sur les responsables (enseignants)
        if (!empty($responsableIds)) {
            $inEns = implode(',', array_map('intval', $responsableIds));
            $stmt = $this->db->prepare("
                SELECT cr.enseignant_id, u.nom, u.prenom, m.nom AS matiere_nom,
                       p.heure_debut, p.heure_fin
                FROM   vs_edt_creneaux cr
                JOIN   users u  ON u.id = cr.enseignant_id
                JOIN   matieres m ON m.id = cr.matiere_id
                JOIN   vs_edt_plages_horaires p ON p.id = cr.plage_id
                JOIN   vs_emplois_du_temps e ON e.id = cr.emploi_du_temps_id
                WHERE  cr.enseignant_id IN ({$inEns})
                  AND  cr.jour = :jour
                  AND  cr.plage_id IN ({$inPlages})
                  AND  cr.annee_scolaire = :annee
                  AND  cr.deleted_at IS NULL
                  AND  e.statut != 'archive' AND e.deleted_at IS NULL
                LIMIT 5
            ");
            $stmt->execute([':jour' => $jour, ':annee' => $annee]);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $conflits[] = [
                    'type'    => 'responsable',
                    'message' => "{$row['prenom']} {$row['nom']} enseigne {$row['matiere_nom']} ({$row['heure_debut']}–{$row['heure_fin']}) ce jour.",
                ];
            }
        }

        return $conflits;
    }

    // ── Historique ────────────────────────────────────────────────────────────

    public function insertHistorique(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO vs_activite_historique
                (activite_id, action, description, data_json, user_id)
            VALUES (:aid, :action, :desc, :data, :uid)
        ");
        $stmt->execute([
            ':aid'    => $data['activite_id'],
            ':action' => $data['action'],
            ':desc'   => $data['description'] ?? null,
            ':data'   => isset($data['data']) ? json_encode($data['data'], JSON_UNESCAPED_UNICODE) : null,
            ':uid'    => $data['user_id'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function findHistorique(int $activiteId): array
    {
        $stmt = $this->db->prepare("
            SELECT h.*, u.nom AS user_nom, u.prenom AS user_prenom
            FROM vs_activite_historique h
            JOIN users u ON u.id = h.user_id
            WHERE h.activite_id = :aid
            ORDER BY h.created_at DESC
        ");
        $stmt->execute([':aid' => $activiteId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Référentiels ──────────────────────────────────────────────────────────

    public function findAllCategories(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM vs_activite_categories WHERE actif = 1 ORDER BY nom
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function statsByAnnee(string $annee): array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.nom     AS categorie_nom,
                c.couleur AS categorie_couleur,
                COUNT(a.id) AS nb_activites,
                SUM(CASE WHEN a.statut = 'termine' THEN 1 ELSE 0 END) AS nb_terminees,
                SUM(CASE WHEN a.statut = 'annule'  THEN 1 ELSE 0 END) AS nb_annulees,
                (SELECT COUNT(*) FROM vs_activite_inscriptions i2
                 JOIN vs_activites a2 ON a2.id = i2.activite_id
                 WHERE a2.categorie_id = c.id AND a2.annee_scolaire = :annee
                   AND i2.statut = 'inscrit' AND i2.deleted_at IS NULL
                   AND a2.deleted_at IS NULL) AS total_inscrits
            FROM vs_activite_categories c
            LEFT JOIN vs_activites a ON a.categorie_id = c.id
                AND a.annee_scolaire = :annee2 AND a.deleted_at IS NULL
            GROUP BY c.id, c.nom, c.couleur
            ORDER BY nb_activites DESC
        ");
        $stmt->execute([':annee' => $annee, ':annee2' => $annee]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Privé ─────────────────────────────────────────────────────────────────

    private function buildWhere(ActivityFiltersDTO $f): array
    {
        $conditions = ['a.deleted_at IS NULL'];
        $params     = [];

        if ($f->categorieId !== null) {
            $conditions[] = 'a.categorie_id = :cat';
            $params[':cat'] = $f->categorieId;
        }
        if ($f->statut !== null) {
            $conditions[] = 'a.statut = :statut';
            $params[':statut'] = $f->statut;
        }
        if ($f->anneeScolaire !== null) {
            $conditions[] = 'a.annee_scolaire = :annee';
            $params[':annee'] = $f->anneeScolaire;
        }
        if ($f->dateFrom !== null) {
            $conditions[] = 'a.date_activite >= :dfrom';
            $params[':dfrom'] = $f->dateFrom;
        }
        if ($f->dateTo !== null) {
            $conditions[] = 'a.date_activite <= :dto';
            $params[':dto'] = $f->dateTo;
        }
        if ($f->classeId !== null) {
            $conditions[] = 'EXISTS (SELECT 1 FROM vs_activite_classes ac WHERE ac.activite_id = a.id AND ac.classe_id = :cid)';
            $params[':cid'] = $f->classeId;
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }
}
