<?php

namespace App\Models;

use Core\Model;

class RapportModel extends Model
{
    protected string $table = 'eleves';

    // ─── KPIs globaux ────────────────────────────────────────────────────────

    public function getKpiGlobal(): array
    {
        $eleves = $this->queryOne(
            "SELECT COUNT(*) AS total,
                    SUM(actif = 1)    AS actifs,
                    SUM(sexe = 'M')   AS garcons,
                    SUM(sexe = 'F')   AS filles
             FROM `eleves`"
        );
        $classes = $this->queryOne("SELECT COUNT(*) AS n FROM `classes`");
        $profs   = $this->queryOne("SELECT COUNT(*) AS n FROM `professeurs`");
        $absToday = $this->queryOne(
            "SELECT COUNT(*) AS n FROM `absences` WHERE `date_absence` = CURDATE()"
        );

        return [
            'total_eleves'   => (int)($eleves?->total    ?? 0),
            'eleves_actifs'  => (int)($eleves?->actifs   ?? 0),
            'garcons'        => (int)($eleves?->garcons  ?? 0),
            'filles'         => (int)($eleves?->filles   ?? 0),
            'classes'        => (int)($classes?->n       ?? 0),
            'professeurs'    => (int)($profs?->n         ?? 0),
            'absences_today' => (int)($absToday?->n      ?? 0),
        ];
    }

    public function getKpiFinance(string $annee): array
    {
        [$y1, $y2] = $this->anneeYears($annee);

        $rec = $this->queryOne(
            "SELECT COALESCE(SUM(montant), 0) AS total FROM `paiements`
             WHERE (YEAR(date_paiement) = ? AND MONTH(date_paiement) >= 9)
                OR (YEAR(date_paiement) = ? AND MONTH(date_paiement) < 9)",
            [$y1, $y2]
        );
        $dep = $this->queryOne(
            "SELECT COALESCE(SUM(montant), 0) AS total FROM `depenses`
             WHERE (YEAR(date_depense) = ? AND MONTH(date_depense) >= 9)
                OR (YEAR(date_depense) = ? AND MONTH(date_depense) < 9)",
            [$y1, $y2]
        );
        $fraisTotal = $this->queryOne(
            "SELECT COALESCE(SUM(montant), 0) AS total FROM `frais_eleves`
             WHERE annee_scolaire = ?",
            [$annee]
        );
        $impayes = $this->queryOne(
            "SELECT COALESCE(SUM(fe.montant - COALESCE(ps.paye, 0)), 0) AS total
             FROM `frais_eleves` fe
             LEFT JOIN (
                 SELECT frais_eleve_id, SUM(montant) AS paye
                 FROM `paiements` GROUP BY frais_eleve_id
             ) ps ON ps.frais_eleve_id = fe.id
             WHERE fe.annee_scolaire = ? AND fe.statut != 'paye'",
            [$annee]
        );

        $recettes = (float)($rec?->total      ?? 0);
        $depenses = (float)($dep?->total      ?? 0);
        $fTotal   = (float)($fraisTotal?->total ?? 0);
        $imp      = (float)($impayes?->total   ?? 0);

        return [
            'recettes'          => $recettes,
            'depenses'          => $depenses,
            'impayes'           => $imp,
            'frais_total'       => $fTotal,
            'solde'             => $recettes - $depenses,
            'taux_recouvrement' => $fTotal > 0 ? round($recettes / $fTotal * 100, 1) : 0,
        ];
    }

    public function getKpiReussite(?int $periodeId = null): array
    {
        $where  = $periodeId ? "WHERE mg.periode_id = ?" : "";
        $params = $periodeId ? [$periodeId] : [];

        $r = $this->queryOne(
            "SELECT COUNT(*) AS total,
                    SUM(mg.moyenne_generale >= 10) AS reussis,
                    ROUND(AVG(mg.moyenne_generale), 2) AS moy_generale
             FROM `moyennes_generales` mg {$where}",
            $params
        );

        $total   = (int)($r?->total ?? 0);
        $reussis = (int)($r?->reussis ?? 0);

        return [
            'total'         => $total,
            'reussis'       => $reussis,
            'taux_reussite' => $total > 0 ? round($reussis / $total * 100, 1) : 0,
            'moy_generale'  => (float)($r?->moy_generale ?? 0),
        ];
    }

    // ─── Scolaire ─────────────────────────────────────────────────────────────

    public function getElevesParClasse(): array
    {
        return $this->query(
            "SELECT c.id, c.nom, c.niveau,
                    COUNT(e.id)      AS nb_eleves,
                    SUM(e.sexe='M') AS garcons,
                    SUM(e.sexe='F') AS filles
             FROM `classes` c
             LEFT JOIN `eleves` e ON e.classe_id = c.id AND e.actif = 1
             GROUP BY c.id ORDER BY c.niveau, c.nom"
        );
    }

    public function getElevesParNiveau(): array
    {
        return $this->query(
            "SELECT c.niveau, COUNT(e.id) AS nb
             FROM `eleves` e JOIN `classes` c ON c.id = e.classe_id
             WHERE e.actif = 1
             GROUP BY c.niveau ORDER BY c.niveau"
        );
    }

    public function getDistributionNotes(?int $periodeId): ?object
    {
        $where  = $periodeId ? "AND mg.periode_id = ?" : "";
        $params = $periodeId ? [$periodeId] : [];

        return $this->queryOne(
            "SELECT
                SUM(mg.moyenne_generale < 5)                                    AS t0_5,
                SUM(mg.moyenne_generale >= 5  AND mg.moyenne_generale < 10)     AS t5_10,
                SUM(mg.moyenne_generale >= 10 AND mg.moyenne_generale < 12)     AS t10_12,
                SUM(mg.moyenne_generale >= 12 AND mg.moyenne_generale < 14)     AS t12_14,
                SUM(mg.moyenne_generale >= 14 AND mg.moyenne_generale < 16)     AS t14_16,
                SUM(mg.moyenne_generale >= 16)                                  AS t16_20
             FROM `moyennes_generales` mg WHERE 1=1 {$where}",
            $params
        );
    }

    // ─── Finance ──────────────────────────────────────────────────────────────

    public function getFinanceParMois(string $annee): array
    {
        [$y1, $y2] = $this->anneeYears($annee);

        $moisFr = ['01'=>'Sep','02'=>'Oct','03'=>'Nov','04'=>'Déc',
                   '05'=>'Jan','06'=>'Fév','07'=>'Mar','08'=>'Avr',
                   '09'=>'Mai','10'=>'Jun','11'=>'Jul','12'=>'Aoû'];

        // Build ordered month keys Sept→Aug
        $months = [];
        for ($m = 9; $m <= 12; $m++) $months[sprintf('%04d-%02d', $y1, $m)] = sprintf('%02d', $m);
        for ($m = 1;  $m <= 8;  $m++) $months[sprintf('%04d-%02d', $y2, $m)] = sprintf('%02d', $m);

        $map = [];
        $i = 0;
        foreach ($months as $ym => $mm) {
            $map[$ym] = ['label' => $moisFr[sprintf('%02d', $i + 1)], 'recettes' => 0.0, 'depenses' => 0.0];
            $i++;
        }

        $recettes = $this->query(
            "SELECT DATE_FORMAT(date_paiement,'%Y-%m') AS mois, SUM(montant) AS total
             FROM `paiements`
             WHERE (YEAR(date_paiement)=? AND MONTH(date_paiement)>=9)
                OR (YEAR(date_paiement)=? AND MONTH(date_paiement)<9)
             GROUP BY mois",
            [$y1, $y2]
        );
        $depenses = $this->query(
            "SELECT DATE_FORMAT(date_depense,'%Y-%m') AS mois, SUM(montant) AS total
             FROM `depenses`
             WHERE (YEAR(date_depense)=? AND MONTH(date_depense)>=9)
                OR (YEAR(date_depense)=? AND MONTH(date_depense)<9)
             GROUP BY mois",
            [$y1, $y2]
        );

        foreach ($recettes as $r) {
            if (isset($map[$r->mois])) $map[$r->mois]['recettes'] = (float)$r->total;
        }
        foreach ($depenses as $d) {
            if (isset($map[$d->mois])) $map[$d->mois]['depenses'] = (float)$d->total;
        }

        return array_values($map);
    }

    public function getDepensesParCategorie(string $annee): array
    {
        [$y1, $y2] = $this->anneeYears($annee);

        return $this->query(
            "SELECT dc.nom, dc.couleur,
                    COALESCE(SUM(d.montant), 0) AS total
             FROM `depenses_categories` dc
             LEFT JOIN `depenses` d ON d.categorie_id = dc.id
                AND ((YEAR(d.date_depense)=? AND MONTH(d.date_depense)>=9)
                  OR (YEAR(d.date_depense)=? AND MONTH(d.date_depense)<9))
             GROUP BY dc.id ORDER BY total DESC",
            [$y1, $y2]
        );
    }

    public function getRecouvrementParFrais(string $annee): array
    {
        return $this->query(
            "SELECT ft.nom, ft.categorie,
                    SUM(fe.montant)              AS montant_total,
                    COALESCE(SUM(ps.paye), 0)    AS montant_paye,
                    COUNT(fe.id)                 AS nb_eleves
             FROM `frais_eleves` fe
             JOIN `frais_types` ft ON ft.id = fe.frais_type_id
             LEFT JOIN (
                 SELECT frais_eleve_id, SUM(montant) AS paye
                 FROM `paiements` GROUP BY frais_eleve_id
             ) ps ON ps.frais_eleve_id = fe.id
             WHERE fe.annee_scolaire = ?
             GROUP BY ft.id ORDER BY montant_total DESC",
            [$annee]
        );
    }

    // ─── Présences ────────────────────────────────────────────────────────────

    public function getAbsencesParClasse(): array
    {
        return $this->query(
            "SELECT c.nom AS classe, c.niveau,
                    COUNT(DISTINCT e.id) AS nb_eleves,
                    COUNT(a.id)          AS nb_absences,
                    SUM(a.type='retard') AS nb_retards,
                    SUM(a.type='absence') AS nb_seches,
                    ROUND(COUNT(a.id) / NULLIF(COUNT(DISTINCT e.id), 0), 1) AS moy_par_eleve
             FROM `classes` c
             LEFT JOIN `eleves` e ON e.classe_id = c.id AND e.actif = 1
             LEFT JOIN `absences` a ON a.eleve_id = e.id
             GROUP BY c.id ORDER BY nb_absences DESC"
        );
    }

    public function getAbsencesHebdo(int $nbWeeks = 12): array
    {
        $rows = $this->query(
            "SELECT YEARWEEK(date_absence, 1) AS semaine_num,
                    MIN(date_absence)          AS debut_semaine,
                    COUNT(*)                   AS total,
                    SUM(type='absence')        AS absences,
                    SUM(type='retard')         AS retards
             FROM `absences`
             WHERE date_absence >= DATE_SUB(CURDATE(), INTERVAL ? WEEK)
             GROUP BY semaine_num ORDER BY semaine_num",
            [$nbWeeks]
        );

        $moisFr = ['01'=>'Jan','02'=>'Fév','03'=>'Mar','04'=>'Avr','05'=>'Mai','06'=>'Jun',
                   '07'=>'Jul','08'=>'Aoû','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Déc'];

        foreach ($rows as &$r) {
            $ts = strtotime($r->debut_semaine);
            $r->label = date('d', $ts) . ' ' . ($moisFr[date('m', $ts)] ?? '');
        }
        unset($r);

        return $rows;
    }

    public function getRepartitionAbsences(): array
    {
        $r = $this->queryOne(
            "SELECT COUNT(*) AS total,
                    SUM(a.type='absence')           AS absences,
                    SUM(a.type='retard')            AS retards,
                    SUM(j.valide = 1)               AS justifiees,
                    SUM(j.id IS NULL OR j.valide=0) AS non_justifiees
             FROM `absences` a
             LEFT JOIN `justifications` j ON j.absence_id = a.id"
        );

        return (array)($r ?? new \stdClass());
    }

    public function getTopAbsents(int $n = 10): array
    {
        return $this->query(
            "SELECT e.nom, e.prenom, e.matricule,
                    c.nom AS classe, c.niveau,
                    COUNT(a.id) AS nb_absences
             FROM `absences` a
             JOIN `eleves` e ON e.id = a.eleve_id
             LEFT JOIN `classes` c ON c.id = e.classe_id
             GROUP BY a.eleve_id
             ORDER BY nb_absences DESC LIMIT ?",
            [$n]
        );
    }

    // ─── Réussite ─────────────────────────────────────────────────────────────

    public function getReussiteParClasse(?int $periodeId = null): array
    {
        $where  = $periodeId ? "AND mg.periode_id = ?" : "";
        $params = $periodeId ? [$periodeId] : [];

        return $this->query(
            "SELECT c.nom AS classe, c.niveau,
                    COUNT(mg.eleve_id)                  AS total,
                    SUM(mg.moyenne_generale >= 10)      AS reussis,
                    ROUND(AVG(mg.moyenne_generale), 2)  AS moy_classe,
                    MAX(mg.moyenne_generale)             AS moy_max,
                    MIN(mg.moyenne_generale)             AS moy_min
             FROM `moyennes_generales` mg
             JOIN `classes` c ON c.id = mg.classe_id
             WHERE 1=1 {$where}
             GROUP BY mg.classe_id ORDER BY c.niveau, c.nom",
            $params
        );
    }

    public function getMentionsDistrib(?int $periodeId = null): array
    {
        $where  = $periodeId ? "WHERE periode_id = ?" : "";
        $params = $periodeId ? [$periodeId] : [];

        return $this->query(
            "SELECT mention, COUNT(*) AS nb
             FROM `moyennes_generales` {$where}
             GROUP BY mention ORDER BY nb DESC",
            $params
        );
    }

    public function getMoyennesParMatiere(?int $periodeId = null, ?int $classeId = null): array
    {
        $conds = [];
        $params = [];
        if ($periodeId) { $conds[] = "mm.periode_id = ?"; $params[] = $periodeId; }
        if ($classeId)  { $conds[] = "mm.classe_id  = ?"; $params[] = $classeId; }
        $where = $conds ? "WHERE " . implode(" AND ", $conds) : "";

        return $this->query(
            "SELECT m.nom AS matiere, m.coefficient,
                    ROUND(AVG(mm.moyenne), 2) AS moy_matiere,
                    COUNT(mm.eleve_id)        AS nb_eleves,
                    MAX(mm.moyenne)           AS moy_max,
                    MIN(mm.moyenne)           AS moy_min,
                    SUM(mm.moyenne >= 10)     AS reussis
             FROM `moyennes_matieres` mm
             JOIN `matieres` m ON m.id = mm.matiere_id
             {$where}
             GROUP BY mm.matiere_id ORDER BY moy_matiere DESC",
            $params
        );
    }

    public function getProgressionParPeriode(?int $classeId = null): array
    {
        $where  = $classeId ? "WHERE mg.classe_id = ?" : "";
        $params = $classeId ? [$classeId] : [];

        return $this->query(
            "SELECT p.nom AS periode, p.id AS periode_id,
                    ROUND(AVG(mg.moyenne_generale), 2) AS moy_generale,
                    SUM(mg.moyenne_generale >= 10)     AS reussis,
                    COUNT(mg.eleve_id)                 AS total
             FROM `moyennes_generales` mg
             JOIN `periodes` p ON p.id = mg.periode_id
             {$where}
             GROUP BY mg.periode_id ORDER BY p.date_debut",
            $params
        );
    }

    // ─── Utilitaires ──────────────────────────────────────────────────────────

    public static function currentAnnee(): string
    {
        $m = (int)date('m');
        $y = (int)date('Y');
        return $m >= 9 ? "{$y}-" . ($y + 1) : ($y - 1) . "-{$y}";
    }

    public static function anneesOptions(): array
    {
        $m   = (int)date('m');
        $y   = (int)date('Y');
        $cur = $m >= 9 ? $y : $y - 1;
        return [
            ($cur - 1) . '-' . $cur,
            $cur . '-' . ($cur + 1),
            ($cur + 1) . '-' . ($cur + 2),
        ];
    }

    private function anneeYears(string $annee): array
    {
        $parts = explode('-', $annee);
        return [(int)($parts[0] ?? date('Y')), (int)($parts[1] ?? (date('Y') + 1))];
    }
}
