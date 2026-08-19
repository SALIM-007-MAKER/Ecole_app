<?php

namespace App\Models;

use App\Modules\Academique\Repositories\AnalyticsRepository;
use App\Modules\Finance\Repositories\FinancialReportRepository;
use Core\Model;

class RapportModel extends Model
{
    protected string $table = 'eleves';

    /** Libellés des codes de mention (source : Academique\ValueObjects\MentionValue). */
    private const MENTION_LABELS = [
        'TB'  => 'Très Bien',
        'B'   => 'Bien',
        'AB'  => 'Assez Bien',
        'P'   => 'Passable',
        'INS' => 'Insuffisant',
    ];

    private FinancialReportRepository $financeRepo;
    private AnalyticsRepository       $analyticsRepo;

    public function __construct()
    {
        parent::__construct();
        $this->financeRepo   = new FinancialReportRepository();
        $this->analyticsRepo = new AnalyticsRepository();
    }

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

    /**
     * Source V2 exclusive : FinancialReportRepository::getKpiFinanceAnnuel()
     * (finance_paiements / finance_decaissements / finance_factures).
     * Migré depuis les tables V1 paiements/depenses/frais_eleves.
     */
    public function getKpiFinance(string $annee): array
    {
        $kpi = $this->financeRepo->getKpiFinanceAnnuel($annee);

        return [
            'recettes'          => $kpi->recettes,
            'depenses'          => $kpi->depenses,
            'impayes'           => $kpi->impayes,
            'frais_total'       => $kpi->frais_total,
            'solde'             => $kpi->solde,
            'taux_recouvrement' => $kpi->taux_recouvrement,
        ];
    }

    /**
     * Source V2 exclusive : bulletins_v2 (moyenne réelle par élève, calculée
     * par AcademicCalculationService/BulletinGenerator). Migré depuis
     * moyennes_generales (V1), plus alimentée depuis l'activation du module
     * académique (cf. ACADEMIQUE_MODULE_FREEZE.md).
     */
    public function getKpiReussite(?int $periodeId = null): array
    {
        $where  = $periodeId ? "WHERE b.periode_id = ?" : "";
        $params = $periodeId ? [$periodeId] : [];

        $r = $this->queryOne(
            "SELECT COUNT(*) AS total,
                    SUM(b.moyenne >= 10) AS reussis,
                    ROUND(AVG(b.moyenne), 2) AS moy_generale
             FROM `bulletins_v2` b {$where}",
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
             GROUP BY c.id ORDER BY " . \App\Models\ClasseModel::ordreNiveauSql('c.niveau') . ", c.nom"
        );
    }

    public function getElevesParNiveau(): array
    {
        return $this->query(
            "SELECT c.niveau, COUNT(e.id) AS nb
             FROM `eleves` e JOIN `classes` c ON c.id = e.classe_id
             WHERE e.actif = 1
             GROUP BY c.niveau ORDER BY " . \App\Models\ClasseModel::ordreNiveauSql('c.niveau')
        );
    }

    public function getDistributionNotes(?int $periodeId): ?object
    {
        $where  = $periodeId ? "AND b.periode_id = ?" : "";
        $params = $periodeId ? [$periodeId] : [];

        return $this->queryOne(
            "SELECT
                SUM(b.moyenne < 5)                          AS t0_5,
                SUM(b.moyenne >= 5  AND b.moyenne < 10)      AS t5_10,
                SUM(b.moyenne >= 10 AND b.moyenne < 12)      AS t10_12,
                SUM(b.moyenne >= 12 AND b.moyenne < 14)      AS t12_14,
                SUM(b.moyenne >= 14 AND b.moyenne < 16)      AS t14_16,
                SUM(b.moyenne >= 16)                         AS t16_20
             FROM `bulletins_v2` b WHERE 1=1 {$where}",
            $params
        );
    }

    // ─── Finance ──────────────────────────────────────────────────────────────

    /**
     * Source V2 exclusive : FinancialReportRepository::getRecettesDepensesParMois()
     * (finance_paiements / finance_decaissements). Migré depuis paiements/depenses.
     */
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

        $data = $this->financeRepo->getRecettesDepensesParMois($annee);

        foreach ($data['recettes'] as $r) {
            if (isset($map[$r->mois])) $map[$r->mois]['recettes'] = (float)$r->total;
        }
        foreach ($data['depenses'] as $d) {
            if (isset($map[$d->mois])) $map[$d->mois]['depenses'] = (float)$d->total;
        }

        return array_values($map);
    }

    /**
     * Source V2 exclusive : FinancialReportRepository::getDepensesParCategorieScolaire()
     * (finance_categories_depenses / finance_decaissements). Migré depuis depenses_categories/depenses.
     */
    public function getDepensesParCategorie(string $annee): array
    {
        return $this->financeRepo->getDepensesParCategorieScolaire($annee);
    }

    /**
     * Source V2 exclusive : FinancialReportRepository::getRecouvrementParTypeFrais()
     * (finance_lignes_facture / finance_frais_types / finance_factures).
     * Migré depuis frais_eleves/frais_types/paiements.
     */
    public function getRecouvrementParFrais(string $annee): array
    {
        return $this->financeRepo->getRecouvrementParTypeFrais($annee);
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
                    SUM(a.type='absence')                    AS absences,
                    SUM(a.type='retard')                     AS retards,
                    SUM(a.statut_justif = 'justifiee')       AS justifiees,
                    SUM(a.statut_justif != 'justifiee')      AS non_justifiees
             FROM `absences` a"
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
        $where  = $periodeId ? "AND b.periode_id = ?" : "";
        $params = $periodeId ? [$periodeId] : [];

        return $this->query(
            "SELECT c.nom AS classe, c.niveau,
                    COUNT(b.eleve_id)                  AS total,
                    SUM(b.moyenne >= 10)                AS reussis,
                    ROUND(AVG(b.moyenne), 2)             AS moy_classe,
                    MAX(b.moyenne)                       AS moy_max,
                    MIN(b.moyenne)                       AS moy_min
             FROM `bulletins_v2` b
             JOIN `classes` c ON c.id = b.classe_id
             WHERE 1=1 {$where}
             GROUP BY b.classe_id ORDER BY " . \App\Models\ClasseModel::ordreNiveauSql('c.niveau') . ", c.nom",
            $params
        );
    }

    /**
     * Distribution par mention — source `bulletins_v2.mention_code` (TB/B/AB/P/INS),
     * traduit en libellé complet via self::MENTION_LABELS pour l'affichage.
     */
    public function getMentionsDistrib(?int $periodeId = null): array
    {
        $where  = $periodeId ? "WHERE periode_id = ?" : "";
        $params = $periodeId ? [$periodeId] : [];

        $rows = $this->query(
            "SELECT mention_code AS mention, COUNT(*) AS nb
             FROM `bulletins_v2` {$where}
             GROUP BY mention_code ORDER BY nb DESC",
            $params
        );
        foreach ($rows as $r) {
            $r->mention = self::MENTION_LABELS[$r->mention] ?? $r->mention;
        }
        return $rows;
    }

    /**
     * Source V2 : Academique\Repositories\AnalyticsRepository::moyennesParMatiere()
     * — moyenne de notes individuelles ramenées sur 20 (approximation SQL,
     * pas pondérée par coefficient de matière comme un bulletin exact ;
     * suffisante pour ce tableau de bord agrégé, cf. avertissement dans
     * AnalyticsRepository).
     */
    public function getMoyennesParMatiere(?int $periodeId = null, ?int $classeId = null): array
    {
        if (!$periodeId) return [];

        $rows = $this->analyticsRepo->moyennesParMatiere($periodeId, $classeId);

        return array_map(fn(array $r) => (object)[
            'matiere'     => $r['matiere_nom'],
            'coefficient' => $r['coefficient'],
            'moy_matiere' => $r['moyenne'],
            'nb_eleves'   => $r['nb_eleves'],
            'moy_max'     => $r['note_max'],
            'moy_min'     => $r['note_min'],
            'reussis'     => $r['nb_passants'],
        ], $rows);
    }

    public function getProgressionParPeriode(?int $classeId = null): array
    {
        $where  = $classeId ? "AND b.classe_id = ?" : "";
        $params = $classeId ? [$classeId] : [];

        return $this->query(
            "SELECT ps.nom AS periode, ps.id AS periode_id,
                    ROUND(AVG(b.moyenne), 2) AS moy_generale,
                    SUM(b.moyenne >= 10)      AS reussis,
                    COUNT(b.eleve_id)         AS total
             FROM `bulletins_v2` b
             JOIN `periodes_scolaires` ps ON ps.id = b.periode_id
             WHERE 1=1 {$where}
             GROUP BY b.periode_id ORDER BY ps.date_debut",
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
