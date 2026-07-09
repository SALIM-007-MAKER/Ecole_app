<?php

namespace App\Models;

use Core\Model;

class EmploiDuTempsModel extends Model
{
    protected string $table = 'emplois_du_temps';

    const JOURS = [
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi',
    ];

    const COLORS = [
        '#4e73df', '#1cc88a', '#36b9cc', '#e67e22', '#e74a3b',
        '#6f42c1', '#fd7e14', '#20c997', '#d63384', '#0dcaf0',
        '#6c757d', '#2c3e50', '#16a085', '#8e44ad', '#c0392b',
    ];

    public static function getColor(int $matiereId, ?string $custom = null): string
    {
        if ($custom) return $custom;
        return self::COLORS[$matiereId % count(self::COLORS)];
    }

    // ─── Base SELECT (tous les modules utilisent ça) ──────────────────────────
    private function baseSelect(): string
    {
        return "SELECT edt.*,
                    c.nom AS classe_nom, c.niveau AS classe_niveau,
                    m.nom AS matiere_nom, m.nom AS matiere_code,
                    CONCAT(p.prenom, ' ', p.nom) AS prof_fullname,
                    p.nom AS prof_nom, p.prenom AS prof_prenom,
                    s.nom AS salle_nom, s.batiment AS salle_batiment,
                    cr.nom AS creneau_nom, cr.heure_debut, cr.heure_fin,
                    cr.type AS creneau_type, cr.ordre AS creneau_ordre
                FROM emplois_du_temps edt
                JOIN classes c ON c.id = edt.classe_id
                JOIN matieres m ON m.id = edt.matiere_id
                JOIN professeurs p ON p.id = edt.professeur_id
                LEFT JOIN salles s ON s.id = edt.salle_id
                JOIN creneaux cr ON cr.id = edt.creneau_id";
    }

    private function buildWhere(array $filters, string $annee): array
    {
        $where  = ['edt.actif = 1'];
        $params = [];

        if ($annee) {
            $where[]  = 'edt.annee_scolaire = ?';
            $params[] = $annee;
        }
        if (!empty($filters['classe_id'])) {
            $where[]  = 'edt.classe_id = ?';
            $params[] = (int)$filters['classe_id'];
        }
        if (!empty($filters['professeur_id'])) {
            $where[]  = 'edt.professeur_id = ?';
            $params[] = (int)$filters['professeur_id'];
        }
        if (!empty($filters['salle_id'])) {
            $where[]  = 'edt.salle_id = ?';
            $params[] = (int)$filters['salle_id'];
        }
        return [$where, $params];
    }

    // ─── Grille hebdomadaire [jour][creneau_id][] = seance ───────────────────
    public function getWeekGrid(array $filters = [], string $annee = ''): array
    {
        [$where, $params] = $this->buildWhere($filters, $annee);

        $sql     = $this->baseSelect() . ' WHERE ' . implode(' AND ', $where)
                 . ' ORDER BY edt.jour_semaine ASC, cr.ordre ASC';
        $seances = $this->query($sql, $params);

        $grid = [];
        foreach ($seances as $s) {
            $grid[$s->jour_semaine][$s->creneau_id][] = $s;
        }
        return $grid;
    }

    // ─── Données mensuel : dates du mois + séances par dow ───────────────────
    public function getMonthData(int $year, int $month, array $filters, string $annee): array
    {
        $grid       = $this->getWeekGrid($filters, $annee);
        $firstDay   = mktime(0, 0, 0, $month, 1, $year);
        $daysInMonth = (int)date('t', $firstDay);

        $calendar = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $ts  = mktime(0, 0, 0, $month, $d, $year);
            $dow = (int)date('N', $ts); // 1=Lun … 7=Dim
            $calendar[] = [
                'date'    => date('Y-m-d', $ts),
                'day'     => $d,
                'dow'     => $dow,
                'seances' => $dow <= 6 ? ($grid[$dow] ?? []) : [],
            ];
        }
        return $calendar;
    }

    // ─── Détection de conflits ────────────────────────────────────────────────
    public function checkConflicts(
        int  $classeId,
        int  $profId,
        ?int $salleId,
        int  $creneauId,
        int  $jour,
        string $annee,
        ?int $exceptId = null
    ): array {
        $conflicts = [];
        $base      = 'creneau_id = ? AND jour_semaine = ? AND annee_scolaire = ? AND actif = 1';
        $bParams   = [$creneauId, $jour, $annee];
        $except    = $exceptId ? ' AND edt.id != ?' : '';
        $eParam    = $exceptId ? [$exceptId] : [];

        // Conflict de classe
        if ($classeId) {
            $r = $this->queryOne(
                "SELECT m.nom AS mat
                 FROM emplois_du_temps edt
                 JOIN matieres m ON m.id = edt.matiere_id
                 WHERE edt.classe_id = ? AND $base$except",
                array_merge([$classeId], $bParams, $eParam)
            );
            if ($r) {
                $conflicts[] = [
                    'type'    => 'classe',
                    'message' => "La classe a déjà un cours de « {$r->mat} » à ce créneau.",
                ];
            }
        }

        // Conflict de professeur
        if ($profId) {
            $r = $this->queryOne(
                "SELECT m.nom AS mat, c.niveau, c.nom AS cnm
                 FROM emplois_du_temps edt
                 JOIN matieres m ON m.id = edt.matiere_id
                 JOIN classes c  ON c.id  = edt.classe_id
                 WHERE edt.professeur_id = ? AND $base$except",
                array_merge([$profId], $bParams, $eParam)
            );
            if ($r) {
                $conflicts[] = [
                    'type'    => 'professeur',
                    'message' => "L'enseignant est déjà en {$r->niveau} {$r->cnm} (« {$r->mat} ») à ce créneau.",
                ];
            }
        }

        // Conflict de salle
        if ($salleId) {
            $r = $this->queryOne(
                "SELECT m.nom AS mat, c.niveau, c.nom AS cnm
                 FROM emplois_du_temps edt
                 JOIN matieres m ON m.id = edt.matiere_id
                 JOIN classes c  ON c.id  = edt.classe_id
                 WHERE edt.salle_id = ? AND $base$except",
                array_merge([$salleId], $bParams, $eParam)
            );
            if ($r) {
                $conflicts[] = [
                    'type'    => 'salle',
                    'message' => "La salle est déjà occupée par {$r->niveau} {$r->cnm} (« {$r->mat} ») à ce créneau.",
                ];
            }
        }

        return $conflicts;
    }

    // ─── Détail d'une seule séance ────────────────────────────────────────────
    public function findWithDetails(int $id): ?object
    {
        return $this->queryOne($this->baseSelect() . ' WHERE edt.id = ?', [$id]);
    }

    // ─── Pour l'impression ────────────────────────────────────────────────────
    public function findForPrint(array $filters, string $annee): array
    {
        [$where, $params] = $this->buildWhere($filters, $annee);
        $sql = $this->baseSelect() . ' WHERE ' . implode(' AND ', $where)
             . ' ORDER BY edt.jour_semaine ASC, cr.ordre ASC';
        return $this->query($sql, $params);
    }

    // ─── Statistiques globales ────────────────────────────────────────────────
    public function getStats(string $annee): object
    {
        $r = $this->queryOne(
            'SELECT COUNT(*)                  AS total_seances,
                    COUNT(DISTINCT classe_id)      AS total_classes,
                    COUNT(DISTINCT professeur_id)  AS total_profs,
                    COUNT(DISTINCT matiere_id)     AS total_matieres,
                    COUNT(DISTINCT jour_semaine)   AS jours_actifs
             FROM emplois_du_temps WHERE annee_scolaire = ? AND actif = 1',
            [$annee]
        );
        return $r ?? (object)['total_seances'=>0,'total_classes'=>0,'total_profs'=>0,'total_matieres'=>0,'jours_actifs'=>0];
    }

    // ─── Tableau de bord enseignant ───────────────────────────────────────────
    public function countHeuresProf(int $profId, string $annee): float
    {
        $seances = $this->query(
            "SELECT cr.heure_debut, cr.heure_fin
             FROM emplois_du_temps edt
             JOIN creneaux cr ON cr.id = edt.creneau_id
             WHERE edt.professeur_id = ? AND edt.annee_scolaire = ? AND edt.actif = 1 AND cr.type = 'cours'",
            [$profId, $annee]
        );
        $total = 0;
        foreach ($seances as $s) {
            $debut = strtotime($s->heure_debut);
            $fin   = strtotime($s->heure_fin);
            $total += ($fin - $debut) / 3600;
        }
        return round($total, 1);
    }
}
