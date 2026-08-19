<?php

namespace App\Modules\Academique\Repositories;

use Core\Database;

/**
 * Requêtes analytiques optimisées — agrégations SQL uniquement.
 *
 * Convention "note_sur_20" :
 *   CASE WHEN n.est_absent = 1 THEN 0.0
 *        WHEN n.valeur IS NOT NULL THEN (n.valeur / ev.note_max) * 20.0
 *        ELSE 0.0 END
 *
 * Ces expressions reflètent les options par défaut de AcademicCalculationService
 * (absent_vaut_zero = true, non_remis_vaut_zero = true).
 *
 * AVERTISSEMENT : la "moyenne" SQL ici est une moyenne de notes individuelles,
 * pas une moyenne pondérée par matière-coefficient comme dans AcademicCalculationService.
 * Elle convient pour les indicateurs de tableau de bord et les tendances.
 * Pour les bulletins et classements exacts, utiliser BulletinGenerator / RankingEngine.
 */
class AnalyticsRepository
{
    private \PDO $db;

    // SQL fragment réutilisable — note ramenée sur 20
    private const N20 = "CASE
        WHEN n.est_absent = 1                    THEN 0.0
        WHEN n.valeur IS NOT NULL                THEN (n.valeur / NULLIF(ev.note_max, 0)) * 20.0
        ELSE 0.0 END";

    // SQL fragment — statut évaluation publiée/verrouillée
    private const STATUT_OK = "ev.statut IN ('publiee', 'verrouillee')";

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Moyennes agrégées
    // ─────────────────────────────────────────────────────────────────

    /**
     * Moyenne globale de l'établissement pour une période.
     * Retourne la moyenne des moyennes de classe (pondération uniforme par classe).
     */
    public function moyenneEtablissement(int $periodeId): array
    {
        $sql = "
            SELECT
                COUNT(DISTINCT e.id)   AS nb_eleves,
                COUNT(n.id)            AS nb_notes,
                AVG(" . self::N20 . ") AS moyenne,
                SUM(CASE WHEN n.est_absent = 1 THEN 1 ELSE 0 END) AS nb_absences
            FROM eleves e
            JOIN evaluations ev ON ev.classe_id = e.classe_id
                AND ev.periode_scolaire_id = :periode_id
                AND " . self::STATUT_OK . "
            LEFT JOIN notes_v2 n ON n.eleve_id = e.id AND n.evaluation_id = ev.id
        ";
        return $this->safe($sql, [':periode_id' => $periodeId], fn($s) =>
            $s->fetch(\PDO::FETCH_ASSOC) ?: []
        );
    }

    /**
     * Moyenne agrégée par classe, triées par niveau puis nom.
     */
    public function moyennesParClasse(int $periodeId, ?string $niveau = null): array
    {
        $niveauFilter = $niveau !== null ? 'AND c.niveau = :niveau' : '';
        $sql = "
            SELECT
                c.id       AS classe_id,
                c.nom      AS classe_nom,
                c.niveau,
                COUNT(DISTINCT e.id)   AS nb_eleves,
                COUNT(n.id)            AS nb_notes,
                AVG(" . self::N20 . ") AS moyenne,
                SUM(CASE WHEN " . self::N20 . " >= 10 THEN 1 ELSE 0 END) AS nb_passants,
                SUM(CASE WHEN n.est_absent = 1 THEN 1 ELSE 0 END)        AS nb_absences,
                MIN(CASE WHEN n.valeur IS NOT NULL AND n.est_absent = 0
                         THEN (n.valeur / NULLIF(ev.note_max, 0)) * 20.0 END) AS note_min,
                MAX(CASE WHEN n.valeur IS NOT NULL AND n.est_absent = 0
                         THEN (n.valeur / NULLIF(ev.note_max, 0)) * 20.0 END) AS note_max
            FROM classes c
            JOIN eleves e ON e.classe_id = c.id
            JOIN evaluations ev ON ev.classe_id = c.id
                AND ev.periode_scolaire_id = :periode_id
                AND " . self::STATUT_OK . "
            LEFT JOIN notes_v2 n ON n.eleve_id = e.id AND n.evaluation_id = ev.id
            WHERE 1=1 $niveauFilter
            GROUP BY c.id, c.nom, c.niveau
            ORDER BY " . \App\Models\ClasseModel::ordreNiveauSql('c.niveau') . ", c.nom
        ";
        $params = [':periode_id' => $periodeId];
        if ($niveau !== null) $params[':niveau'] = $niveau;
        return $this->safe($sql, $params, fn($s) => $s->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Moyenne agrégée par matière.
     */
    public function moyennesParMatiere(int $periodeId, ?int $classeId = null): array
    {
        $classeFilter = $classeId !== null ? 'AND ev.classe_id = :classe_id' : '';
        $sql = "
            SELECT
                m.id       AS matiere_id,
                m.nom      AS matiere_nom,
                m.coefficient,
                COUNT(DISTINCT e.id)   AS nb_eleves,
                COUNT(n.id)            AS nb_notes,
                AVG(" . self::N20 . ") AS moyenne,
                SUM(CASE WHEN " . self::N20 . " >= 10 THEN 1 ELSE 0 END) AS nb_passants,
                MIN(CASE WHEN n.valeur IS NOT NULL AND n.est_absent = 0
                         THEN (n.valeur / NULLIF(ev.note_max, 0)) * 20.0 END) AS note_min,
                MAX(CASE WHEN n.valeur IS NOT NULL AND n.est_absent = 0
                         THEN (n.valeur / NULLIF(ev.note_max, 0)) * 20.0 END) AS note_max
            FROM matieres m
            JOIN evaluations ev ON ev.matiere_id = m.id
                AND ev.periode_scolaire_id = :periode_id
                AND " . self::STATUT_OK . "
            JOIN eleves e ON e.classe_id = ev.classe_id
            LEFT JOIN notes_v2 n ON n.eleve_id = e.id AND n.evaluation_id = ev.id
            WHERE 1=1 $classeFilter
            GROUP BY m.id, m.nom, m.coefficient
            ORDER BY m.nom
        ";
        $params = [':periode_id' => $periodeId];
        if ($classeId !== null) $params[':classe_id'] = $classeId;
        return $this->safe($sql, $params, fn($s) => $s->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Moyenne agrégée par niveau.
     */
    public function moyennesParNiveau(int $periodeId): array
    {
        $sql = "
            SELECT
                c.niveau,
                COUNT(DISTINCT c.id)   AS nb_classes,
                COUNT(DISTINCT e.id)   AS nb_eleves,
                AVG(" . self::N20 . ") AS moyenne,
                SUM(CASE WHEN " . self::N20 . " >= 10 THEN 1 ELSE 0 END) AS nb_passants,
                COUNT(n.id)            AS nb_notes
            FROM classes c
            JOIN eleves e ON e.classe_id = c.id
            JOIN evaluations ev ON ev.classe_id = c.id
                AND ev.periode_scolaire_id = :periode_id
                AND " . self::STATUT_OK . "
            LEFT JOIN notes_v2 n ON n.eleve_id = e.id AND n.evaluation_id = ev.id
            GROUP BY c.niveau
            ORDER BY " . \App\Models\ClasseModel::ordreNiveauSql('c.niveau') . "
        ";
        return $this->safe($sql, [':periode_id' => $periodeId], fn($s) => $s->fetchAll(\PDO::FETCH_ASSOC));
    }

    // ─────────────────────────────────────────────────────────────────
    //  Distribution & Mentions
    // ─────────────────────────────────────────────────────────────────

    /**
     * Distribution des notes individuelles en 5 tranches.
     * Retourne [{tranche, min, max, nb}]
     */
    public function distributionNotes(int $periodeId, ?int $classeId = null, ?int $matiereId = null): array
    {
        $filters  = '';
        $params   = [':periode_id' => $periodeId];
        if ($classeId  !== null) { $filters .= ' AND ev.classe_id  = :classe_id';  $params[':classe_id']  = $classeId; }
        if ($matiereId !== null) { $filters .= ' AND ev.matiere_id = :matiere_id'; $params[':matiere_id'] = $matiereId; }

        $sql = "
            SELECT
                CASE
                    WHEN note_sur_20 >= 16 THEN 'TB'
                    WHEN note_sur_20 >= 14 THEN 'B'
                    WHEN note_sur_20 >= 12 THEN 'AB'
                    WHEN note_sur_20 >= 10 THEN 'P'
                    ELSE 'INS'
                END         AS tranche,
                COUNT(*)    AS nb
            FROM (
                SELECT (n.valeur / NULLIF(ev.note_max, 0)) * 20.0 AS note_sur_20
                FROM notes_v2 n
                JOIN evaluations ev ON ev.id = n.evaluation_id
                    AND ev.periode_scolaire_id = :periode_id
                    AND " . self::STATUT_OK . "
                WHERE n.est_absent = 0
                  AND n.valeur IS NOT NULL
                  $filters
            ) sub
            GROUP BY tranche
            ORDER BY FIELD(tranche, 'TB', 'B', 'AB', 'P', 'INS')
        ";
        return $this->safe($sql, $params, fn($s) => $s->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Taux d'absentéisme : (nb absences / nb créneaux évaluation) × 100.
     * Un créneau = une évaluation × un élève inscrit.
     */
    public function tauxAbsenteisme(int $periodeId, ?int $classeId = null): array
    {
        $filter = $classeId !== null ? 'AND ev.classe_id = :classe_id' : '';
        $params = [':periode_id' => $periodeId];
        if ($classeId !== null) $params[':classe_id'] = $classeId;

        $sql = "
            SELECT
                COUNT(*)                                                      AS nb_total,
                SUM(CASE WHEN n.est_absent = 1 THEN 1 ELSE 0 END)            AS nb_absences,
                SUM(CASE WHEN n.id IS NULL THEN 1 ELSE 0 END)                AS nb_non_saisis
            FROM eleves e
            JOIN evaluations ev ON ev.classe_id = e.classe_id
                AND ev.periode_scolaire_id = :periode_id
                AND " . self::STATUT_OK . "
            LEFT JOIN notes_v2 n ON n.eleve_id = e.id AND n.evaluation_id = ev.id
            WHERE 1=1 $filter
        ";
        return $this->safe($sql, $params, fn($s) => $s->fetch(\PDO::FETCH_ASSOC) ?: []);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Évolution & Tendances
    // ─────────────────────────────────────────────────────────────────

    /**
     * Évolution de la moyenne d'une classe sur plusieurs périodes.
     * @return array [{periode_id, periode_nom, moyenne, nb_eleves}]
     */
    public function evolutionParPeriodes(int $classeId, array $periodeIds): array
    {
        if (empty($periodeIds)) return [];
        $placeholders = implode(',', array_fill(0, count($periodeIds), '?'));
        $sql = "
            SELECT
                ps.id   AS periode_id,
                ps.nom  AS periode_nom,
                ps.date_debut,
                AVG(" . self::N20 . ") AS moyenne,
                COUNT(DISTINCT e.id)   AS nb_eleves
            FROM periodes_scolaires ps
            JOIN evaluations ev ON ev.periode_scolaire_id = ps.id
                AND ev.classe_id = ?
                AND " . self::STATUT_OK . "
            JOIN eleves e ON e.classe_id = ev.classe_id
            LEFT JOIN notes_v2 n ON n.eleve_id = e.id AND n.evaluation_id = ev.id
            WHERE ps.id IN ($placeholders)
            GROUP BY ps.id, ps.nom, ps.date_debut
            ORDER BY ps.date_debut ASC
        ";
        $params = array_merge([$classeId], $periodeIds);
        return $this->safe($sql, $params, fn($s) => $s->fetchAll(\PDO::FETCH_ASSOC));
    }

    // ─────────────────────────────────────────────────────────────────
    //  Top / Alertes
    // ─────────────────────────────────────────────────────────────────

    /**
     * Meilleurs élèves (approx. SQL, pondération uniforme).
     * @return array [{eleve_id, nom, prenom, matricule, classe_nom, moyenne_approx}]
     */
    public function topPerformers(int $periodeId, ?int $classeId = null, int $limit = 10): array
    {
        $filter = $classeId !== null ? 'AND ev.classe_id = :classe_id' : '';
        $params = [':periode_id' => $periodeId, ':limit' => $limit];
        if ($classeId !== null) $params[':classe_id'] = $classeId;

        $sql = "
            SELECT
                e.id AS eleve_id, e.nom, e.prenom, e.matricule,
                c.nom AS classe_nom,
                AVG(" . self::N20 . ") AS moyenne_approx
            FROM eleves e
            JOIN classes c ON c.id = e.classe_id
            JOIN evaluations ev ON ev.classe_id = e.classe_id
                AND ev.periode_scolaire_id = :periode_id
                AND " . self::STATUT_OK . "
            LEFT JOIN notes_v2 n ON n.eleve_id = e.id AND n.evaluation_id = ev.id
            WHERE 1=1 $filter
            GROUP BY e.id, e.nom, e.prenom, e.matricule, c.nom
            ORDER BY moyenne_approx DESC
            LIMIT :limit
        ";
        return $this->safe($sql, $params, fn($s) => $s->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Élèves en difficulté (moyenne < seuil).
     * @return array [{eleve_id, nom, prenom, matricule, classe_nom, moyenne_approx}]
     */
    public function alertesEleves(int $periodeId, ?int $classeId = null, float $seuil = 10.0, int $limit = 20): array
    {
        $filter = $classeId !== null ? 'AND ev.classe_id = :classe_id' : '';
        $params = [':periode_id' => $periodeId, ':seuil' => $seuil, ':limit' => $limit];
        if ($classeId !== null) $params[':classe_id'] = $classeId;

        $sql = "
            SELECT
                e.id AS eleve_id, e.nom, e.prenom, e.matricule,
                c.nom AS classe_nom,
                AVG(" . self::N20 . ") AS moyenne_approx
            FROM eleves e
            JOIN classes c ON c.id = e.classe_id
            JOIN evaluations ev ON ev.classe_id = e.classe_id
                AND ev.periode_scolaire_id = :periode_id
                AND " . self::STATUT_OK . "
            LEFT JOIN notes_v2 n ON n.eleve_id = e.id AND n.evaluation_id = ev.id
            WHERE 1=1 $filter
            GROUP BY e.id, e.nom, e.prenom, e.matricule, c.nom
            HAVING moyenne_approx < :seuil
            ORDER BY moyenne_approx ASC
            LIMIT :limit
        ";
        return $this->safe($sql, $params, fn($s) => $s->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Matières enseignées par un enseignant avec leurs moyennes.
     */
    public function matieresEnseignant(int $enseignantId, int $periodeId): array
    {
        $sql = "
            SELECT
                m.id AS matiere_id, m.nom AS matiere_nom,
                pm.classe_id, c.nom AS classe_nom,
                COUNT(DISTINCT e.id)   AS nb_eleves,
                AVG(" . self::N20 . ") AS moyenne,
                SUM(CASE WHEN " . self::N20 . " >= 10 THEN 1 ELSE 0 END) AS nb_passants
            FROM professeurs_matieres pm
            JOIN matieres m ON m.id = pm.matiere_id
            JOIN classes c ON c.id = pm.classe_id
            JOIN evaluations ev ON ev.matiere_id = m.id
                AND ev.classe_id = pm.classe_id
                AND ev.periode_scolaire_id = :periode_id
                AND " . self::STATUT_OK . "
            JOIN eleves e ON e.classe_id = c.id
            LEFT JOIN notes_v2 n ON n.eleve_id = e.id AND n.evaluation_id = ev.id
            WHERE pm.professeur_id = :enseignant_id
            GROUP BY m.id, m.nom, pm.classe_id, c.nom
            ORDER BY m.nom, c.nom
        ";
        return $this->safe($sql, [':enseignant_id' => $enseignantId, ':periode_id' => $periodeId],
            fn($s) => $s->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    /**
     * Informations d'une période scolaire.
     */
    public function infoPeriode(int $periodeId): ?object
    {
        return $this->safe(
            "SELECT id, nom, date_debut, date_fin FROM periodes_scolaires WHERE id = :id",
            [':id' => $periodeId],
            fn($s) => $s->fetch(\PDO::FETCH_OBJ) ?: null
        );
    }

    /**
     * Nombre total de classes et d'élèves (contexte établissement).
     */
    public function statsEtablissement(): array
    {
        $sql = "
            SELECT
                (SELECT COUNT(*) FROM classes) AS nb_classes,
                (SELECT COUNT(*) FROM eleves)  AS nb_eleves,
                (SELECT COUNT(DISTINCT niveau) FROM classes) AS nb_niveaux
        ";
        return $this->safe($sql, [], fn($s) => $s->fetch(\PDO::FETCH_ASSOC) ?: []);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Helper privé
    // ─────────────────────────────────────────────────────────────────

    /**
     * Exécute une requête avec try/catch pour compatibilité forward (tables absentes).
     */
    private function safe(string $sql, array $params, callable $extract): mixed
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $extract($stmt);
        } catch (\PDOException) {
            return match(true) {
                str_starts_with(ltrim($sql), 'SELECT') => [],
                default => null,
            };
        }
    }
}
