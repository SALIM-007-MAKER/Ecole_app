<?php

namespace App\Modules\Scolarite\Repositories;

use Core\Database;
use PDO;

class ClasseRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ─── Requête de base ─────────────────────────────────────────────────────

    private function baseSelect(): string
    {
        return "SELECT c.*,
                       COUNT(DISTINCT e.id)  AS nb_eleves,
                       COUNT(DISTINCT en.id) AS nb_enseignements";
    }

    private function baseFrom(): string
    {
        return "FROM `classes` c
                LEFT JOIN `eleves`        e  ON e.classe_id  = c.id
                LEFT JOIN `enseignements` en ON en.classe_id = c.id";
    }

    // ─── Lecture ─────────────────────────────────────────────────────────────

    /**
     * Liste paginée des classes avec effectifs et statistiques.
     */
    public function paginate(
        string $anneeScolaire = '',
        string $niveau        = '',
        string $q             = '',
        int    $page          = 1,
        int    $perPage       = 25,
    ): array {
        [$where, $params] = $this->buildWhere($anneeScolaire, $niveau, $q);

        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT c.id) {$this->baseFrom()} $where"
        );
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt   = $this->pdo->prepare(
            "{$this->baseSelect()} {$this->baseFrom()} $where
             GROUP BY c.id
             ORDER BY " . \App\Models\ClasseModel::ordreNiveauSql('c.niveau') . ", c.nom
             LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

        return [
            'data'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'perPage'     => $perPage,
            'totalPages'  => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
        ];
    }

    /**
     * Toutes les classes d'une année avec effectifs (pour l'index groupé par niveau).
     */
    public function listWithStats(string $anneeScolaire = ''): array
    {
        [$where, $params] = $this->buildWhere($anneeScolaire, '', '');

        $stmt = $this->pdo->prepare(
            "{$this->baseSelect()} {$this->baseFrom()} $where
             GROUP BY c.id
             ORDER BY " . \App\Models\ClasseModel::ordreNiveauSql('c.niveau') . ", c.nom"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Détail d'une classe avec effectifs et enseignements.
     */
    public function findWithDetails(int $id): ?\stdClass
    {
        $stmt = $this->pdo->prepare(
            "{$this->baseSelect()} {$this->baseFrom()}
             WHERE c.id = ?
             GROUP BY c.id"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    /**
     * Liste pour un <select> (id + label).
     */
    public function findForSelect(string $anneeScolaire = ''): array
    {
        if ($anneeScolaire !== '') {
            $stmt = $this->pdo->prepare(
                "SELECT id, CONCAT(niveau, ' — ', nom) AS label
                 FROM `classes`
                 WHERE annee_scolaire = ?
                 ORDER BY " . \App\Models\ClasseModel::ordreNiveauSql() . ", nom"
            );
            $stmt->execute([$anneeScolaire]);
        } else {
            $stmt = $this->pdo->query(
                "SELECT id, CONCAT(niveau, ' — ', nom) AS label
                 FROM `classes`
                 ORDER BY " . \App\Models\ClasseModel::ordreNiveauSql() . ", nom"
            );
        }
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Élèves inscrits dans une classe.
     */
    public function getEleves(int $classeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.id, e.nom, e.prenom, e.matricule, e.sexe, e.actif, e.photo
             FROM `eleves` e
             WHERE e.classe_id = ?
             ORDER BY e.nom, e.prenom"
        );
        $stmt->execute([$classeId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Élèves sans classe ou dans une autre classe (pour l'affectation).
     */
    public function getElevesDisponibles(int $classeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.id, e.nom, e.prenom, e.matricule, e.sexe,
                    c.nom    AS classe_actuelle,
                    c.niveau AS niveau_actuel
             FROM `eleves` e
             LEFT JOIN `classes` c ON c.id = e.classe_id
             WHERE (e.classe_id IS NULL OR e.classe_id != ?) AND e.actif = 1
             ORDER BY e.nom, e.prenom"
        );
        $stmt->execute([$classeId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Enseignements d'une classe avec détails prof/matière.
     */
    public function getEnseignements(int $classeId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT en.*,
                    CONCAT(p.prenom, ' ', p.nom) AS prof_nom,
                    p.specialite                  AS prof_specialite,
                    m.nom                         AS matiere_nom,
                    m.coefficient,
                    m.volume_horaire
             FROM `enseignements` en
             INNER JOIN `professeurs` p ON p.id = en.professeur_id
             INNER JOIN `matieres`    m ON m.id = en.matiere_id
             WHERE en.classe_id = ?
             ORDER BY m.nom, p.nom"
        );
        $stmt->execute([$classeId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Classes disponibles (pas encore pleines) pour affecter un élève.
     */
    public function listDisponibles(string $anneeScolaire, string $niveau = ''): array
    {
        $whereNiveau = $niveau !== '' ? 'AND c.niveau = ?' : '';
        $params      = $niveau !== '' ? [$anneeScolaire, $niveau] : [$anneeScolaire];

        $stmt = $this->pdo->prepare(
            "SELECT c.*,
                    COUNT(DISTINCT e.id) AS nb_eleves
             FROM `classes` c
             LEFT JOIN `eleves` e ON e.classe_id = c.id
             WHERE c.annee_scolaire = ? $whereNiveau
             GROUP BY c.id
             HAVING nb_eleves < c.max_eleves
             ORDER BY " . \App\Models\ClasseModel::ordreNiveauSql('c.niveau') . ", c.nom"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Vérifie si un enseignant est déjà affecté pour cette matière dans cette classe.
     */
    public function enseignantDejaAffecte(int $classeId, int $professeurId, int $matiereId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM `enseignements`
             WHERE classe_id = ? AND professeur_id = ? AND matiere_id = ?"
        );
        $stmt->execute([$classeId, $professeurId, $matiereId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Emploi du temps complet d'une classe (Phase 1.5 — emploi du temps module).
     */
    public function findEmploiDuTemps(int $classeId): array
    {
        // Implémenté lors de la migration du module Emploi du Temps en V2
        return [];
    }

    /**
     * Statistiques globales des classes.
     */
    public function countStats(): array
    {
        $row = $this->pdo->query(
            "SELECT
                COUNT(*)                          AS total,
                COUNT(DISTINCT niveau)             AS niveaux,
                COUNT(DISTINCT annee_scolaire)     AS annees,
                SUM((SELECT COUNT(*) FROM eleves e WHERE e.classe_id = c.id)) AS total_eleves
             FROM `classes` c"
        )->fetch(PDO::FETCH_OBJ);

        return [
            'total'        => (int)($row->total        ?? 0),
            'niveaux'      => (int)($row->niveaux       ?? 0),
            'annees'       => (int)($row->annees        ?? 0),
            'total_eleves' => (int)($row->total_eleves  ?? 0),
        ];
    }

    /**
     * Nombre de classes par niveau (pour une année donnée).
     */
    public function countByNiveau(string $anneeScolaire): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT niveau, COUNT(*) AS total
             FROM `classes`
             WHERE annee_scolaire = ?
             GROUP BY niveau
             ORDER BY " . \App\Models\ClasseModel::ordreNiveauSql()
        );
        $stmt->execute([$anneeScolaire]);
        $rows   = $stmt->fetchAll(PDO::FETCH_OBJ);
        $result = [];
        foreach ($rows as $r) {
            $result[$r->niveau] = (int)$r->total;
        }
        return $result;
    }

    // ─── Helpers privés ──────────────────────────────────────────────────────

    private function buildWhere(string $anneeScolaire, string $niveau, string $q): array
    {
        $conditions = [];
        $params     = [];

        if ($anneeScolaire !== '') {
            $conditions[] = 'c.annee_scolaire = ?';
            $params[]     = $anneeScolaire;
        }
        if ($niveau !== '') {
            $conditions[] = 'c.niveau = ?';
            $params[]     = $niveau;
        }
        if ($q !== '') {
            $conditions[] = '(c.nom LIKE ? OR c.niveau LIKE ?)';
            $params[]     = "%$q%";
            $params[]     = "%$q%";
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$where, $params];
    }
}
