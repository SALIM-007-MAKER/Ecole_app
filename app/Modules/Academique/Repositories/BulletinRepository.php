<?php

namespace App\Modules\Academique\Repositories;

use Core\Database;
use Core\Tenant\BrandingService;
use Core\Tenant\TenantContext;
use App\Modules\Academique\DTO\BulletinData;

/**
 * Accès DB pour les bulletins.
 * Ne contient aucun calcul — seulement des opérations de lecture/écriture.
 */
class BulletinRepository
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Lecture — données sources
    // ─────────────────────────────────────────────────────────────────

    /**
     * Notes d'un élève pour toutes les évaluations publiées d'une période.
     * Retourne une ligne par évaluation (LEFT JOIN → null si note absente).
     */
    public function notesEleveParPeriode(int $eleveId, int $classeId, int $periodeId): array
    {
        $sql = "
            SELECT
                m.id           AS matiere_id,
                m.nom          AS matiere_nom,
                m.coefficient  AS coeff_matiere,
                m.categorie    AS matiere_categorie,
                ev.id          AS evaluation_id,
                ev.note_max,
                ev.coefficient AS coeff_eval,
                n.valeur,
                COALESCE(n.est_absent, 0)        AS est_absent,
                COALESCE(te.est_eliminatoire, 0) AS est_eliminatoire,
                te.seuil_eliminatoire,
                te.code                          AS type_evaluation_code
            FROM evaluations ev
            JOIN matieres m ON m.id = ev.matiere_id
            LEFT JOIN notes_v2 n
                ON  n.eleve_id      = :eleve_id
                AND n.evaluation_id = ev.id
            LEFT JOIN types_evaluations te ON te.id = ev.type_evaluation_id
            WHERE ev.classe_id           = :classe_id
              AND ev.periode_scolaire_id = :periode_id
              AND ev.statut IN ('publiee', 'verrouillee')
            ORDER BY m.nom, ev.id
        ";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':eleve_id'  => $eleveId,
                ':classe_id' => $classeId,
                ':periode_id'=> $periodeId,
            ]);
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\PDOException) {
            return [];
        }
    }

    /**
     * Informations complètes d'un élève (nom, prénom, matricule, classe, niveau).
     */
    public function infoEleve(int $eleveId): ?object
    {
        $sql = "
            SELECT e.id, e.nom, e.prenom,
                   COALESCE(e.matricule, '') AS matricule,
                   e.photo,
                   e.classe_id,
                   c.nom   AS classe_nom,
                   c.niveau
            FROM eleves e
            JOIN classes c ON c.id = e.classe_id
            WHERE e.id = :id
        ";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $eleveId]);
            $row = $stmt->fetch(\PDO::FETCH_OBJ);
            return $row ?: null;
        } catch (\PDOException) {
            return null;
        }
    }

    /**
     * Informations d'une période scolaire.
     */
    public function infoPeriode(int $periodeId): ?object
    {
        $sql = "
            SELECT id, nom,
                   COALESCE(annee_scolaire, CONCAT(YEAR(date_debut), '/', YEAR(date_fin))) AS annee_scolaire,
                   date_debut, date_fin
            FROM periodes_scolaires
            WHERE id = :id
        ";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $periodeId]);
            $row = $stmt->fetch(\PDO::FETCH_OBJ);
            return $row ?: null;
        } catch (\PDOException) {
            return null;
        }
    }

    /**
     * Informations de l'établissement depuis la table des paramètres.
     * Retourne un tableau avec clés 'nom', 'adresse', 'logo'.
     */
    /**
     * Identité de l'établissement pour l'en-tête des bulletins — lit le
     * branding par établissement (Core\Tenant\BrandingService), qui est la
     * source unique de nom/logo/adresse en base (voir NIGER_APP_
     * CONFIGURATION_AUDIT.md). L'ancienne implémentation lisait une table
     * `parametres` qui n'a jamais existé et retombait donc toujours sur
     * des valeurs par défaut génériques — corrigé.
     */
    public function infoEtablissement(): array
    {
        $etablissementId = TenantContext::isSet()
            ? TenantContext::id()
            : (int)((require ROOT_PATH . '/config/tenant.php')['default_id'] ?? 1);

        $branding = BrandingService::make()->get($etablissementId);

        return [
            'nom'       => $branding->appName,
            'adresse'   => $branding->contactAddress,
            'logo'      => $branding->logoUrl,
            'telephone' => $branding->contactPhone,
            'email'     => $branding->contactEmail,
        ];
    }

    /**
     * Effectif réel de la classe (tous les élèves actifs), indépendant du
     * nombre d'élèves notés/classés pour une période donnée — évite qu'un
     * bulletin affiche "0" tant qu'aucune évaluation n'a été publiée.
     */
    public function effectifClasse(int $classeId): int
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM eleves WHERE classe_id = :cid AND actif = 1"
            );
            $stmt->execute([':cid' => $classeId]);
            return (int)$stmt->fetchColumn();
        } catch (\PDOException) {
            return 0;
        }
    }

    /**
     * IDs et noms des élèves d'une classe (pour genererBulletinsClasse).
     */
    public function elevesParClasse(int $classeId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT id FROM eleves WHERE classe_id = :cid ORDER BY nom, prenom"
            );
            $stmt->execute([':cid' => $classeId]);
            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\PDOException) {
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Lecture — bulletins_v2
    // ─────────────────────────────────────────────────────────────────

    public function findByToken(string $token): ?array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM bulletins_v2 WHERE verification_token = :t"
            );
            $stmt->execute([':t' => $token]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\PDOException) {
            return null;
        }
    }

    public function findByEleveEtPeriode(int $eleveId, int $periodeId): ?array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM bulletins_v2
                  WHERE eleve_id = :e AND periode_id = :p"
            );
            $stmt->execute([':e' => $eleveId, ':p' => $periodeId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\PDOException) {
            return null;
        }
    }

    /**
     * Moyenne du bulletin le plus récent d'un élève (toutes périodes/années
     * confondues), pour les cartes-résumé des portails parent/élève.
     */
    public function derniereMoyenne(int $eleveId): ?float
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT b.moyenne
                   FROM bulletins_v2 b
                   JOIN periodes_scolaires ps ON ps.id = b.periode_id
                  WHERE b.eleve_id = :e
                  ORDER BY ps.date_fin DESC, b.generated_at DESC
                  LIMIT 1"
            );
            $stmt->execute([':e' => $eleveId]);
            $val = $stmt->fetchColumn();
            return $val !== false && $val !== null ? (float)$val : null;
        } catch (\PDOException) {
            return null;
        }
    }

    /** Résumés de tous les bulletins d'une classe pour une période. */
    public function listByClasse(int $classeId, int $periodeId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT b.*, e.nom, e.prenom, e.matricule
                   FROM bulletins_v2 b
                   JOIN eleves e ON e.id = b.eleve_id
                  WHERE b.classe_id  = :c
                    AND b.periode_id = :p
                  ORDER BY b.rang ASC"
            );
            $stmt->execute([':c' => $classeId, ':p' => $periodeId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Écriture
    // ─────────────────────────────────────────────────────────────────

    /**
     * Insère ou met à jour un bulletin (upsert basé sur le token unique).
     * Retourne l'ID de la ligne insérée/mise à jour.
     */
    public function saveBulletin(BulletinData $b): int
    {
        $dataJson = json_encode($b->toArray(), JSON_UNESCAPED_UNICODE);

        $sql = "
            INSERT INTO bulletins_v2
                (eleve_id, classe_id, periode_id, verification_token,
                 moyenne, rang, nb_eleves, mention_code, decision,
                 appreciation_pp, appreciation_directeur,
                 statut, data_json, generated_by, generated_at)
            VALUES
                (:eleve_id, :classe_id, :periode_id, :token,
                 :moyenne, :rang, :nb_eleves, :mention_code, :decision,
                 :app_pp, :app_dir,
                 :statut, :data_json, :gen_by, :gen_at)
            ON DUPLICATE KEY UPDATE
                moyenne                 = VALUES(moyenne),
                rang                    = VALUES(rang),
                nb_eleves               = VALUES(nb_eleves),
                mention_code            = VALUES(mention_code),
                decision                = VALUES(decision),
                appreciation_pp         = VALUES(appreciation_pp),
                appreciation_directeur  = VALUES(appreciation_directeur),
                statut                  = VALUES(statut),
                data_json               = VALUES(data_json),
                generated_by            = VALUES(generated_by),
                generated_at            = VALUES(generated_at),
                updated_at              = NOW()
        ";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':eleve_id'   => $b->eleveId,
                ':classe_id'  => $b->classeId,
                ':periode_id' => $b->periodeId,
                ':token'      => $b->verificationToken,
                ':moyenne'    => $b->moyennePeriode,
                ':rang'       => $b->rang,
                ':nb_eleves'  => $b->nbEleves,
                ':mention_code'=> $b->mentionCode,
                ':decision'   => $b->decision,
                ':app_pp'     => $b->appreciationPp,
                ':app_dir'    => $b->appreciationDirecteur,
                ':statut'     => $b->statut,
                ':data_json'  => $dataJson,
                ':gen_by'     => $b->generatedById,
                ':gen_at'     => $b->generatedAt,
            ]);
            return (int)$this->db->lastInsertId() ?: 0;
        } catch (\PDOException $e) {
            throw new \RuntimeException('Erreur sauvegarde bulletin: ' . $e->getMessage());
        }
    }

    /**
     * Met à jour uniquement le statut d'un bulletin (publication/archivage).
     */
    public function updateStatut(
        string  $token,
        string  $statut,
        int     $userId,
        ?string $publishedAt = null,
        ?string $archivedAt  = null,
    ): bool {
        $sets   = ['statut = :statut', 'updated_at = NOW()'];
        $params = [':token' => $token, ':statut' => $statut];

        if ($publishedAt !== null) {
            $sets[] = 'published_at = :pub_at';
            $sets[] = 'published_by = :pub_by';
            $params[':pub_at'] = $publishedAt;
            $params[':pub_by'] = $userId;
        }
        if ($archivedAt !== null) {
            $sets[] = 'archived_at = :arc_at';
            $sets[] = 'archived_by = :arc_by';
            $params[':arc_at'] = $archivedAt;
            $params[':arc_by'] = $userId;
        }

        try {
            $sql  = "UPDATE bulletins_v2 SET " . implode(', ', $sets)
                  . " WHERE verification_token = :token";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            throw new \RuntimeException('Erreur mise à jour statut: ' . $e->getMessage());
        }
    }

    /**
     * Met à jour uniquement l'appréciation du chef d'établissement — ne touche
     * pas `data_json` : BulletinController::imprimer() fait déjà primer cette
     * colonne sur le snapshot au moment de l'affichage.
     */
    public function updateAppreciationDirecteur(string $token, ?string $appreciation): bool
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE bulletins_v2
                    SET appreciation_directeur = :app_dir, updated_at = NOW()
                  WHERE verification_token = :token"
            );
            $stmt->execute([':app_dir' => $appreciation, ':token' => $token]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            throw new \RuntimeException("Erreur mise à jour de l'appréciation direction: " . $e->getMessage());
        }
    }
}
