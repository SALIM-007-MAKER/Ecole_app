<?php

namespace App\Models;

use Core\Model;

class AbsenceModel extends Model
{
    protected string $table = 'absences';
    protected bool $tenantScoped = true;

    public const SESSIONS = [
        'matin'      => 'Matin',
        'apres_midi' => 'Après-midi',
        'journee'    => 'Journée complète',
    ];

    public const HEURES = [
        'matin'      => 4,
        'apres_midi' => 4,
        'journee'    => 8,
    ];

    public const TYPES = [
        'absence' => 'Absence',
        'retard'  => 'Retard',
    ];

    public const STATUTS_JUSTIF = [
        'non_justifiee' => ['label' => 'Non justifiée', 'class' => 'danger'],
        'en_attente'    => ['label' => 'En attente',    'class' => 'warning'],
        'justifiee'     => ['label' => 'Justifiée',     'class' => 'success'],
        'refusee'       => ['label' => 'Refusée',       'class' => 'secondary'],
    ];

    public const SEUILS_ALERTE = [
        ['limite' => 10, 'niveau' => 'danger',  'label' => 'Critique'],
        ['limite' =>  5, 'niveau' => 'warning', 'label' => 'Sérieux'],
        ['limite' =>  3, 'niveau' => 'info',    'label' => 'À surveiller'],
    ];

    // ─── Requête de base ─────────────────────────────────────────────────────

    private function baseSelect(): string
    {
        return "SELECT a.*,
                CONCAT(e.prenom, ' ', e.nom) AS eleve_nom,
                e.matricule,
                c.nom   AS classe_nom,
                c.niveau AS classe_niveau,
                CONCAT(COALESCE(u.prenom,''), ' ', COALESCE(u.nom,'')) AS signale_par_nom,
                j.id       AS justif_id,
                j.motif    AS justif_motif,
                j.statut   AS justif_statut,
                j.document_path AS justif_doc
                FROM `absences` a
                JOIN  `eleves`  e ON e.id = a.eleve_id
                JOIN  `classes` c ON c.id = a.classe_id
                LEFT JOIN `users`          u ON u.id = a.signale_par
                LEFT JOIN `justifications` j ON j.absence_id = a.id";
    }

    // ─── Liste paginée filtrée ────────────────────────────────────────────────

    public function paginateFiltered(int $page, int $perPage, array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $total = (int)($this->queryOne(
            "SELECT COUNT(*) AS n FROM `absences` a
             JOIN `eleves`  e ON e.id = a.eleve_id
             JOIN `classes` c ON c.id = a.classe_id
             {$where}",
            $params
        )->n ?? 0);

        $offset = ($page - 1) * $perPage;
        $rows   = $this->query(
            $this->baseSelect() . " {$where}
            ORDER BY a.date_absence DESC, e.nom ASC
            LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items'       => $rows,
            'total'       => $total,
            'pages'       => (int)ceil($total / $perPage),
            'currentPage' => $page,
        ];
    }

    private function buildWhere(array $filters): array
    {
        $cond   = ["a.etablissement_id = ?"];
        $params = [$this->tenantId()];

        if (!empty($filters['q'])) {
            $term = '%' . $filters['q'] . '%';
            $cond[]   = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.matricule LIKE ?)";
            array_push($params, $term, $term, $term);
        }
        if (!empty($filters['classe_id'])) {
            $cond[]   = "a.classe_id = ?";
            $params[] = (int)$filters['classe_id'];
        }
        if (!empty($filters['eleve_id'])) {
            $cond[]   = "a.eleve_id = ?";
            $params[] = (int)$filters['eleve_id'];
        }
        if (!empty($filters['type'])) {
            $cond[]   = "a.type = ?";
            $params[] = $filters['type'];
        }
        if (!empty($filters['statut_justif'])) {
            $cond[]   = "a.statut_justif = ?";
            $params[] = $filters['statut_justif'];
        }
        if (!empty($filters['date_debut'])) {
            $cond[]   = "a.date_absence >= ?";
            $params[] = $filters['date_debut'];
        }
        if (!empty($filters['date_fin'])) {
            $cond[]   = "a.date_absence <= ?";
            $params[] = $filters['date_fin'];
        }
        if (!empty($filters['classes_ids']) && is_array($filters['classes_ids'])) {
            $ph       = implode(',', array_fill(0, count($filters['classes_ids']), '?'));
            $cond[]   = "a.classe_id IN ({$ph})";
            array_push($params, ...$filters['classes_ids']);
        }

        $where = $cond ? 'WHERE ' . implode(' AND ', $cond) : '';
        return [$where, $params];
    }

    // ─── Pointage — préparer la grille ───────────────────────────────────────

    public function findForPointage(int $classeId, string $date, string $session): array
    {
        return $this->query(
            "SELECT e.id AS eleve_id,
                    e.nom, e.prenom, e.matricule,
                    a.id        AS absence_id,
                    a.type      AS absence_type,
                    a.duree_retard,
                    a.motif,
                    a.statut_justif
             FROM   `eleves` e
             LEFT JOIN `absences` a
                    ON  a.eleve_id     = e.id
                    AND a.date_absence = ?
                    AND a.session      = ?
                    AND a.etablissement_id = ?
             WHERE  e.classe_id = ? AND e.actif = 1 AND e.etablissement_id = ?
             ORDER BY e.nom, e.prenom",
            [$date, $session, $this->tenantId(), $classeId, $this->tenantId()]
        );
    }

    // ─── Sauvegarder le pointage (upsert ou delete) ───────────────────────────

    public function storePointage(
        int    $classeId,
        string $date,
        string $session,
        array  $statuts,      // [eleve_id => 'present'|'absence'|'retard']
        array  $durees,       // [eleve_id => int minutes]
        array  $motifs,       // [eleve_id => string]
        int    $userId
    ): void {
        $tenantId = $this->tenantId();
        foreach ($statuts as $eleveId => $statut) {
            $eleveId = (int)$eleveId;
            if ($statut === 'present') {
                $this->execute(
                    "DELETE FROM `absences`
                     WHERE eleve_id=? AND date_absence=? AND session=? AND etablissement_id=?",
                    [$eleveId, $date, $session, $tenantId]
                );
            } else {
                $type  = in_array($statut, ['absence','retard'], true) ? $statut : 'absence';
                $duree = $type === 'retard' ? max(1, (int)($durees[$eleveId] ?? 15)) : null;
                $motif = trim((string)($motifs[$eleveId] ?? '')) ?: null;

                $this->execute(
                    "INSERT INTO `absences`
                        (eleve_id, classe_id, date_absence, session, type, duree_retard, motif, signale_par, etablissement_id)
                     VALUES (?,?,?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE
                        type=VALUES(type), duree_retard=VALUES(duree_retard),
                        motif=VALUES(motif), signale_par=VALUES(signale_par),
                        updated_at=NOW()",
                    [$eleveId, $classeId, $date, $session, $type, $duree, $motif, $userId, $tenantId]
                );
            }
        }
    }

    // ─── Trouver une absence avec jointures ───────────────────────────────────

    public function findWithDetails(int $id): ?\stdClass
    {
        return $this->queryOne(
            $this->baseSelect() . " WHERE a.id = ? AND a.etablissement_id = ?",
            [$id, $this->tenantId()]
        ) ?: null;
    }

    // ─── Absences d'un élève (vue parent/élève) ───────────────────────────────

    public function findForEleve(int $eleveId): array
    {
        return $this->query(
            $this->baseSelect() . "
            WHERE a.eleve_id = ? AND a.etablissement_id = ?
            ORDER BY a.date_absence DESC, a.session",
            [$eleveId, $this->tenantId()]
        );
    }

    // ─── Absences de tous les enfants d'un parent ────────────────────────────

    public function findForParent(int $parentUserId): array
    {
        return $this->query(
            $this->baseSelect() . "
            JOIN eleves ep ON ep.id = a.eleve_id AND ep.parent_id = ?
            WHERE a.etablissement_id = ?
            ORDER BY a.date_absence DESC",
            [$parentUserId, $this->tenantId()]
        );
    }

    // ─── Statistiques globales (dashboard) ───────────────────────────────────

    public function getStatsGlobales(): array
    {
        $today = date('Y-m-d');
        $row = $this->queryOne(
            "SELECT
                COUNT(*)                                                  AS total_absences,
                SUM(type = 'retard')                                      AS total_retards,
                SUM(statut_justif = 'non_justifiee')                      AS non_justifiees,
                SUM(statut_justif = 'en_attente')                         AS en_attente,
                SUM(statut_justif = 'justifiee')                          AS justifiees,
                SUM(date_absence = ?)                                     AS aujourd_hui,
                SUM(date_absence >= DATE_SUB(?, INTERVAL 7 DAY))         AS cette_semaine
             FROM `absences`
             WHERE etablissement_id = ?",
            [$today, $today, $this->tenantId()]
        );
        return (array)($row ?? new \stdClass());
    }

    // ─── Statistiques par élève ───────────────────────────────────────────────

    public function getStatsEleve(int $eleveId): array
    {
        $rows = $this->query(
            "SELECT
                type,
                session,
                statut_justif,
                COUNT(*)  AS nb
             FROM `absences`
             WHERE eleve_id = ? AND etablissement_id = ?
             GROUP BY type, session, statut_justif",
            [$eleveId, $this->tenantId()]
        );

        $stats = [
            'nb_absences'      => 0,
            'nb_retards'       => 0,
            'nb_justifiees'    => 0,
            'nb_non_justifiees'=> 0,
            'heures_absence'   => 0,
        ];
        foreach ($rows as $r) {
            $nb = (int)$r->nb;
            if ($r->type === 'absence') {
                $stats['nb_absences'] += $nb;
                $stats['heures_absence'] += $nb * (self::HEURES[$r->session] ?? 4);
            } else {
                $stats['nb_retards'] += $nb;
            }
            if ($r->statut_justif === 'justifiee') {
                $stats['nb_justifiees'] += $nb;
            } elseif ($r->statut_justif === 'non_justifiee') {
                $stats['nb_non_justifiees'] += $nb;
            }
        }
        return $stats;
    }

    // ─── Statistiques agrégées par classe ────────────────────────────────────

    public function getStatsParClasse(): array
    {
        return $this->query(
            "SELECT
                c.id, c.nom, c.niveau,
                COUNT(a.id)                      AS nb_absences,
                SUM(a.type = 'retard')            AS nb_retards,
                SUM(a.statut_justif = 'non_justifiee') AS nb_non_justifiees,
                COUNT(DISTINCT a.eleve_id)        AS nb_eleves_concernes
             FROM `classes` c
             LEFT JOIN `absences` a ON a.classe_id = c.id AND a.etablissement_id = ?
             WHERE c.etablissement_id = ?
             GROUP BY c.id, c.nom, c.niveau
             ORDER BY nb_absences DESC",
            [$this->tenantId(), $this->tenantId()]
        );
    }

    // ─── Tendance hebdomadaire (6 dernières semaines) ────────────────────────

    public function getTendanceHebdo(): array
    {
        return $this->query(
            "SELECT
                YEARWEEK(date_absence, 1) AS semaine,
                MIN(date_absence)         AS debut_semaine,
                COUNT(*)                  AS nb
             FROM `absences`
             WHERE date_absence >= DATE_SUB(CURDATE(), INTERVAL 6 WEEK) AND etablissement_id = ?
             GROUP BY semaine
             ORDER BY semaine",
            [$this->tenantId()]
        );
    }

    // ─── Top élèves les plus absents ─────────────────────────────────────────

    public function getTopAbsents(int $limit = 10, array $classesIds = []): array
    {
        $params      = [$this->tenantId()];
        if ($classesIds) {
            $ph          = implode(',', array_fill(0, count($classesIds), '?'));
            $whereClause = "WHERE a.etablissement_id = ? AND a.classe_id IN ({$ph}) AND a.type = 'absence'";
            array_push($params, ...$classesIds);
        } else {
            $whereClause = "WHERE a.etablissement_id = ? AND a.type = 'absence'";
        }

        return $this->query(
            "SELECT
                e.id, CONCAT(e.prenom,' ',e.nom) AS eleve_nom, e.matricule,
                c.nom AS classe_nom, c.niveau AS classe_niveau,
                COUNT(a.id)  AS nb_absences,
                SUM(a.statut_justif = 'non_justifiee') AS nb_nj
             FROM `absences` a
             JOIN `eleves`  e ON e.id = a.eleve_id
             JOIN `classes` c ON c.id = a.classe_id
             {$whereClause}
             GROUP BY e.id, e.nom, e.prenom, e.matricule, c.nom, c.niveau
             ORDER BY nb_absences DESC
             LIMIT {$limit}",
            $params
        );
    }

    // ─── Alertes — élèves dépassant un seuil ─────────────────────────────────

    public function getAlertes(int $seuil = 3, array $classesIds = []): array
    {
        $whereClause = "WHERE a.etablissement_id = ? AND a.type = 'absence' AND a.statut_justif = 'non_justifiee'";
        $params      = [$this->tenantId()];
        if ($classesIds) {
            $ph           = implode(',', array_fill(0, count($classesIds), '?'));
            $whereClause .= " AND a.classe_id IN ({$ph})";
            array_push($params, ...$classesIds);
        }

        return $this->query(
            "SELECT
                e.id, CONCAT(e.prenom,' ',e.nom) AS eleve_nom, e.matricule,
                c.nom AS classe_nom, c.niveau AS classe_niveau,
                COUNT(a.id) AS nb_non_justifiees,
                MAX(a.date_absence) AS derniere_absence,
                CONCAT(COALESCE(pu.prenom,''),' ',COALESCE(pu.nom,'')) AS parent_nom,
                pu.telephone AS parent_telephone,
                pu.email     AS parent_email
             FROM `absences` a
             JOIN `eleves`  e  ON e.id = a.eleve_id
             JOIN `classes` c  ON c.id = a.classe_id
             LEFT JOIN `users` pu ON pu.id = e.parent_id
             {$whereClause}
             GROUP BY e.id, e.nom, e.prenom, e.matricule, c.nom, c.niveau, pu.prenom, pu.nom, pu.telephone, pu.email
             HAVING nb_non_justifiees >= ?
             ORDER BY nb_non_justifiees DESC",
            [...$params, $seuil]
        );
    }

    // ─── Mettre à jour le statut de justification ────────────────────────────

    public function updateStatutJustif(int $id, string $statut): void
    {
        $allowed = ['non_justifiee','en_attente','justifiee','refusee'];
        if (!in_array($statut, $allowed, true)) return;

        $this->execute(
            "UPDATE `absences` SET statut_justif=?, updated_at=NOW() WHERE id=? AND etablissement_id=?",
            [$statut, $id, $this->tenantId()]
        );
    }
}
