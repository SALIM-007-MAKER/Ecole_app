<?php

namespace App\Modules\Academique\Repositories;

use Core\Database;

/**
 * Charge les données brutes (notes × évaluations × élèves) sans aucun calcul.
 * Le calcul des moyennes est délégué à AcademicCalculationService.
 */
class RankingRepository
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Toutes les évaluations publiées/verrouillées d'une classe pour une période,
     * croisées avec les notes de chaque élève.
     *
     * Retourne une ligne par (élève × évaluation), y compris les évaluations
     * sans note (LEFT JOIN) pour détecter les non-remis.
     */
    public function notesParClasseEtPeriode(int $classeId, int $periodeId): array
    {
        $sql = "
            SELECT
                e.id           AS eleve_id,
                e.nom,
                e.prenom,
                e.matricule,
                e.classe_id,
                NULL           AS classe_nom,
                m.id           AS matiere_id,
                m.nom          AS matiere_nom,
                m.coefficient  AS coeff_matiere,
                ev.id          AS evaluation_id,
                ev.note_max,
                ev.coefficient AS coeff_eval,
                n.valeur,
                COALESCE(n.est_absent, 0) AS est_absent,
                COALESCE(te.est_eliminatoire, 0) AS est_eliminatoire,
                te.seuil_eliminatoire
            FROM eleves e
            JOIN evaluations ev
                ON  ev.classe_id           = e.classe_id
                AND ev.periode_scolaire_id = :periode_id
                AND ev.statut IN ('publiee', 'verrouillee')
            JOIN matieres m
                ON  m.id = ev.matiere_id
            LEFT JOIN notes_v2 n
                ON  n.eleve_id      = e.id
                AND n.evaluation_id = ev.id
            LEFT JOIN types_evaluations te
                ON  te.id = ev.type_evaluation_id
            WHERE e.classe_id = :classe_id
            ORDER BY e.nom, e.prenom, m.id, ev.id
        ";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':classe_id' => $classeId, ':periode_id' => $periodeId]);
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Même structure que notesParClasseEtPeriode mais pour tout un niveau.
     * Inclut classe_nom pour différencier les classes dans l'affichage.
     */
    public function notesParNiveauEtPeriode(string $niveau, int $periodeId): array
    {
        $sql = "
            SELECT
                e.id           AS eleve_id,
                e.nom,
                e.prenom,
                e.matricule,
                e.classe_id,
                c.nom          AS classe_nom,
                m.id           AS matiere_id,
                m.nom          AS matiere_nom,
                m.coefficient  AS coeff_matiere,
                ev.id          AS evaluation_id,
                ev.note_max,
                ev.coefficient AS coeff_eval,
                n.valeur,
                COALESCE(n.est_absent, 0) AS est_absent,
                COALESCE(te.est_eliminatoire, 0) AS est_eliminatoire,
                te.seuil_eliminatoire
            FROM eleves e
            JOIN classes c
                ON  c.id     = e.classe_id
                AND c.niveau = :niveau
            JOIN evaluations ev
                ON  ev.classe_id           = e.classe_id
                AND ev.periode_scolaire_id = :periode_id
                AND ev.statut IN ('publiee', 'verrouillee')
            JOIN matieres m
                ON  m.id = ev.matiere_id
            LEFT JOIN notes_v2 n
                ON  n.eleve_id      = e.id
                AND n.evaluation_id = ev.id
            LEFT JOIN types_evaluations te
                ON  te.id = ev.type_evaluation_id
            ORDER BY c.nom, e.nom, e.prenom, m.id, ev.id
        ";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':niveau' => $niveau, ':periode_id' => $periodeId]);
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Notes pour une matière spécifique.
     * classeId optionnel : null = toutes les classes du niveau.
     */
    public function notesParMatiereEtPeriode(int $matiereId, int $periodeId, ?int $classeId = null): array
    {
        $classFilter = $classeId !== null ? 'AND e.classe_id = :classe_id' : '';
        $sql = "
            SELECT
                e.id           AS eleve_id,
                e.nom,
                e.prenom,
                e.matricule,
                e.classe_id,
                c.nom          AS classe_nom,
                m.id           AS matiere_id,
                m.nom          AS matiere_nom,
                m.coefficient  AS coeff_matiere,
                ev.id          AS evaluation_id,
                ev.note_max,
                ev.coefficient AS coeff_eval,
                n.valeur,
                COALESCE(n.est_absent, 0) AS est_absent,
                COALESCE(te.est_eliminatoire, 0) AS est_eliminatoire,
                te.seuil_eliminatoire
            FROM eleves e
            JOIN classes c ON c.id = e.classe_id
            JOIN evaluations ev
                ON  ev.matiere_id          = :matiere_id
                AND ev.classe_id           = e.classe_id
                AND ev.periode_scolaire_id = :periode_id
                AND ev.statut IN ('publiee', 'verrouillee')
            JOIN matieres m ON m.id = ev.matiere_id
            LEFT JOIN notes_v2 n
                ON  n.eleve_id      = e.id
                AND n.evaluation_id = ev.id
            LEFT JOIN types_evaluations te
                ON  te.id = ev.type_evaluation_id
            WHERE 1=1 $classFilter
            ORDER BY c.nom, e.nom, e.prenom, ev.id
        ";
        try {
            $params = [':matiere_id' => $matiereId, ':periode_id' => $periodeId];
            if ($classeId !== null) $params[':classe_id'] = $classeId;
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Notes multi-périodes pour un classement général annuel.
     * Retourne les mêmes colonnes + periode_id.
     */
    public function notesParClasseMultiPeriodes(int $classeId, array $periodeIds): array
    {
        if (empty($periodeIds)) return [];
        $placeholders = implode(',', array_fill(0, count($periodeIds), '?'));
        $sql = "
            SELECT
                e.id           AS eleve_id,
                e.nom,
                e.prenom,
                e.matricule,
                e.classe_id,
                NULL           AS classe_nom,
                m.id           AS matiere_id,
                m.nom          AS matiere_nom,
                m.coefficient  AS coeff_matiere,
                ev.id          AS evaluation_id,
                ev.periode_scolaire_id AS periode_id,
                ev.note_max,
                ev.coefficient AS coeff_eval,
                n.valeur,
                COALESCE(n.est_absent, 0) AS est_absent,
                COALESCE(te.est_eliminatoire, 0) AS est_eliminatoire,
                te.seuil_eliminatoire
            FROM eleves e
            JOIN evaluations ev
                ON  ev.classe_id           = e.classe_id
                AND ev.periode_scolaire_id IN ($placeholders)
                AND ev.statut IN ('publiee', 'verrouillee')
            JOIN matieres m ON m.id = ev.matiere_id
            LEFT JOIN notes_v2 n
                ON  n.eleve_id      = e.id
                AND n.evaluation_id = ev.id
            LEFT JOIN types_evaluations te
                ON  te.id = ev.type_evaluation_id
            WHERE e.classe_id = ?
            ORDER BY e.nom, e.prenom, ev.periode_scolaire_id, m.id, ev.id
        ";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge(array_values($periodeIds), [$classeId]));
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            return [];
        }
    }
}
