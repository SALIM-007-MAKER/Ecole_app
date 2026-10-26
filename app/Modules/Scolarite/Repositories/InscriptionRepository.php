<?php

namespace App\Modules\Scolarite\Repositories;

use Core\Database;
use Core\Tenant\TenantScope;
use PDO;

class InscriptionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ─── Requête de base ─────────────────────────────────────────────────────

    private function baseSelect(): string
    {
        return "SELECT i.*,
                       e.nom        AS eleve_nom,
                       e.prenom     AS eleve_prenom,
                       e.matricule  AS eleve_matricule,
                       e.sexe       AS eleve_sexe,
                       c.nom        AS classe_nom,
                       c.niveau     AS classe_niveau,
                       CONCAT(c.niveau, ' ', c.nom) AS classe_label,
                       u.nom        AS valideur_nom,
                       u.prenom     AS valideur_prenom";
    }

    private function baseFrom(): string
    {
        return "FROM `inscriptions` i
                INNER JOIN `eleves`  e ON e.id = i.eleve_id
                LEFT JOIN  `classes` c ON c.id = i.classe_id
                LEFT JOIN  `users`   u ON u.id = i.valide_par";
    }

    // ─── Lecture ─────────────────────────────────────────────────────────────

    /**
     * Liste paginée avec jointures élève + classe.
     */
    public function paginate(
        string $anneeScolaire = '',
        string $statut        = '',
        string $classeId      = '',
        string $q             = '',
        int    $page          = 1,
        int    $perPage       = 25,
    ): array {
        [$where, $params] = $this->buildWhere($anneeScolaire, $statut, $classeId, $q);

        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*) {$this->baseFrom()} $where"
        );
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt   = $this->pdo->prepare(
            "{$this->baseSelect()} {$this->baseFrom()} $where
             ORDER BY i.created_at DESC
             LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

        return [
            'data'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
        ];
    }

    /**
     * Détail complet d'une inscription.
     */
    public function findWithDetails(int $id): ?\stdClass
    {
        $stmt = $this->pdo->prepare(
            "{$this->baseSelect()} {$this->baseFrom()} WHERE i.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    /**
     * Historique de toutes les inscriptions d'un élève (toutes années).
     */
    public function historyByEleve(int $eleveId): array
    {
        $stmt = $this->pdo->prepare(
            "{$this->baseSelect()} {$this->baseFrom()}
             WHERE i.eleve_id = ?
             ORDER BY i.annee_scolaire DESC, i.created_at DESC"
        );
        $stmt->execute([$eleveId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Inscription active (en_attente ou validee) d'un élève pour une année.
     */
    public function findActiveByEleve(int $eleveId, string $anneeScolaire): ?\stdClass
    {
        $stmt = $this->pdo->prepare(
            "{$this->baseSelect()} {$this->baseFrom()}
             WHERE i.eleve_id = ? AND i.annee_scolaire = ?
               AND i.statut IN ('en_attente', 'validee')
             LIMIT 1"
        );
        $stmt->execute([$eleveId, $anneeScolaire]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: $this->inscriptionImplicite($eleveId, $anneeScolaire);
    }

    /**
     * Inscription IMPLICITE (écran Élèves / Classes de la V1, module Scolarité désactivé) : un élève actif affecté à une classe de l'année
     * est inscrit pour cette année tant qu'AUCUNE ligne `inscriptions` ne le concerne pour cette année. Une ligne explicite (annulée, rejetée…)
     * l'emporte toujours : elle n'est jamais contournée par ce repli.
     */
    private function inscriptionImplicite(int $eleveId, string $anneeScolaire): ?\stdClass
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.id AS eleve_id, e.classe_id, c.annee_scolaire, 'validee' AS statut, 1 AS implicite
               FROM `eleves` e JOIN `classes` c ON c.id = e.classe_id
              WHERE e.id = ? AND e.actif = 1 AND c.annee_scolaire = ? AND " . TenantScope::sqlEtab('eleves', 'e.etablissement_id') . "
                AND NOT EXISTS (SELECT 1 FROM `inscriptions` i WHERE i.eleve_id = e.id AND i.annee_scolaire = c.annee_scolaire)
              LIMIT 1"
        );
        $stmt->execute([$eleveId, $anneeScolaire]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    /**
     * Nombre d'inscriptions en attente (badge dashboard).
     */
    public function countEnAttente(): int
    {
        return (int)$this->pdo->query(
            "SELECT COUNT(*) FROM `inscriptions` WHERE `statut` = 'en_attente'"
        )->fetchColumn();
    }

    /**
     * Statistiques globales des inscriptions.
     */
    public function countStats(): array
    {
        $rows = $this->pdo->query(
            "SELECT statut, COUNT(*) AS n FROM `inscriptions` GROUP BY statut"
        )->fetchAll(PDO::FETCH_OBJ);

        $stats = ['total' => 0, 'en_attente' => 0, 'validee' => 0, 'rejetee' => 0, 'annulee' => 0];
        foreach ($rows as $r) {
            $stats[$r->statut] = (int)$r->n;
            $stats['total']   += (int)$r->n;
        }
        return $stats;
    }

    /**
     * Statistiques pour une année scolaire donnée.
     */
    public function countStatsByAnnee(string $anneeScolaire): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT statut, COUNT(*) AS n FROM `inscriptions`
             WHERE annee_scolaire = ? GROUP BY statut"
        );
        $stmt->execute([$anneeScolaire]);
        $rows = $stmt->fetchAll(PDO::FETCH_OBJ);

        $stats = ['total' => 0, 'en_attente' => 0, 'validee' => 0, 'rejetee' => 0, 'annulee' => 0];
        foreach ($rows as $r) {
            $stats[$r->statut] = (int)$r->n;
            $stats['total']   += (int)$r->n;
        }
        return $stats;
    }

    /**
     * Vrai si au moins un élève est activement inscrit (en_attente ou validee)
     * dans cette classe pour cette année scolaire.
     */
    public function existsForClasseEtAnnee(int $classeId, string $anneeScolaire): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM `inscriptions`
             WHERE classe_id = ? AND annee_scolaire = ?
               AND statut IN ('en_attente', 'validee')
             LIMIT 1"
        );
        $stmt->execute([$classeId, $anneeScolaire]);
        if ((bool)$stmt->fetchColumn()) {
            return true;
        }
        // Repli V1 (voir inscriptionImplicite) : élèves actifs de la classe sans ligne `inscriptions` pour l'année.
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM `eleves` e JOIN `classes` c ON c.id = e.classe_id
              WHERE c.id = ? AND c.annee_scolaire = ? AND e.actif = 1 AND " . TenantScope::sqlEtab('classes', 'c.etablissement_id') . "
                AND NOT EXISTS (SELECT 1 FROM `inscriptions` i WHERE i.eleve_id = e.id AND i.annee_scolaire = c.annee_scolaire)
              LIMIT 1"
        );
        $stmt->execute([$classeId, $anneeScolaire]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Années scolaires distinctes présentes dans inscriptions.
     */
    public function listAnneesScolaires(): array
    {
        $rows = $this->pdo->query(
            "SELECT DISTINCT annee_scolaire FROM `inscriptions` ORDER BY annee_scolaire DESC"
        )->fetchAll(PDO::FETCH_COLUMN);
        return $rows;
    }

    // ─── Helpers privés ──────────────────────────────────────────────────────

    private function buildWhere(
        string $anneeScolaire,
        string $statut,
        string $classeId,
        string $q,
    ): array {
        $conditions = [];
        $params     = [];

        if ($anneeScolaire !== '') {
            $conditions[] = 'i.annee_scolaire = ?';
            $params[]     = $anneeScolaire;
        }
        if ($statut !== '') {
            $conditions[] = 'i.statut = ?';
            $params[]     = $statut;
        }
        if ($classeId !== '') {
            $conditions[] = 'i.classe_id = ?';
            $params[]     = (int)$classeId;
        }
        if ($q !== '') {
            $conditions[] = '(e.nom LIKE ? OR e.prenom LIKE ? OR e.matricule LIKE ?)';
            $params[]     = "%$q%";
            $params[]     = "%$q%";
            $params[]     = "%$q%";
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$where, $params];
    }
}
