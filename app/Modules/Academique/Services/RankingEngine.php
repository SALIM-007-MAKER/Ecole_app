<?php

namespace App\Modules\Academique\Services;

use App\Modules\Academique\DTO\RankingResultDTO;
use App\Modules\Academique\Repositories\RankingRepository;
use App\Modules\Academique\ValueObjects\AverageValue;

/**
 * Moteur de classement — source unique de vérité pour les rangs.
 *
 * INVARIANT : ce service ne calcule JAMAIS les moyennes.
 * Toute formule de calcul est déléguée à AcademicCalculationService.
 */
class RankingEngine
{
    /** Options par défaut transmises à AcademicCalculationService. */
    public const OPTIONS_DEFAULTS = [
        'absent_vaut_zero'     => true,
        'non_remis_vaut_zero'  => true,
        'arrondi'              => 2,
        'note_passage'         => 10.0,
        'exclure_sans_notes'   => false,  // exclure les élèves sans aucune note
        'seuil_exclusion_notes'=> 0,      // exclure si nbNotes < seuil (0 = pas de seuil)
    ];

    /** Options par défaut effectivement utilisées, fusion de OPTIONS_DEFAULTS et
     *  des overrides passés au constructeur (ex : note_passage configurée par
     *  établissement) — voir BulletinEngineFactory. */
    private array $optionsDefaults;

    public function __construct(
        private AcademicCalculationService $calculator,
        private RankingRepository          $repo,
        array                               $optionsDefaults = [],
    ) {
        $this->optionsDefaults = array_merge(self::OPTIONS_DEFAULTS, $optionsDefaults);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  API publique
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Classement général d'une classe pour une période scolaire.
     */
    public function classementClasse(int $classeId, int $periodeId, array $options = []): RankingResultDTO
    {
        $rows = $this->repo->notesParClasseEtPeriode($classeId, $periodeId);
        return $this->computeFromNoteRows($rows, 'classe', $periodeId, $classeId, null, null, $options);
    }

    /**
     * Classement inter-classes pour un niveau (ex : tous les 3e confondus).
     */
    public function classementNiveau(string $niveau, int $periodeId, array $options = []): RankingResultDTO
    {
        $rows = $this->repo->notesParNiveauEtPeriode($niveau, $periodeId);
        return $this->computeFromNoteRows($rows, 'niveau', $periodeId, null, null, $niveau, $options);
    }

    /**
     * Classement par matière — dans une classe ou inter-classes (classeId = null).
     */
    public function classementMatiere(int $matiereId, int $periodeId, ?int $classeId = null, array $options = []): RankingResultDTO
    {
        $rows = $this->repo->notesParMatiereEtPeriode($matiereId, $periodeId, $classeId);
        return $this->computeFromNoteRows($rows, 'matiere', $periodeId, $classeId, $matiereId, null, $options);
    }

    /**
     * Classement général annuel (plusieurs périodes, moyennes pondérées).
     *
     * @param array $periodeIds  IDs des périodes à inclure
     * @param array $poids       [periodeId => poids] ; poids égal si absent
     */
    public function classementGeneral(int $classeId, array $periodeIds, array $poids = [], array $options = []): RankingResultDTO
    {
        if (empty($periodeIds)) {
            return $this->emptyResult('general', 0, $classeId);
        }

        $allRows = $this->repo->notesParClasseMultiPeriodes($classeId, $periodeIds);

        // Grouper les lignes par période, puis calculer un résultat par période
        $rowsByPeriode = [];
        foreach ($allRows as $row) {
            $rowsByPeriode[(int)$row->periode_id][] = $row;
        }

        // Collecter les infos élève
        $eleveInfo = [];
        foreach ($allRows as $row) {
            $eid = (int)$row->eleve_id;
            if (!isset($eleveInfo[$eid])) {
                $eleveInfo[$eid] = $this->extractEleveInfo($row);
            }
        }

        // Pour chaque période, calculer la moyennePeriode par élève
        $periodesMoyennes = [];   // [eleve_id][periode_id] => AverageValue
        foreach ($rowsByPeriode as $pid => $rows) {
            $grouped = $this->groupRowsByEleve($rows);
            foreach ($grouped as $eid => $matieres) {
                $bulletin = $this->calculator->calculerBulletin($matieres);
                $periodesMoyennes[$eid][$pid] = $bulletin['periode']['moyenne'];
            }
        }

        // Calculer la moyenne générale par élève
        $opts    = array_merge($this->optionsDefaults, $options);
        $defaultPoids = 1.0 / count($periodeIds);

        $eleveMoyennes = [];
        foreach ($eleveInfo as $eid => $info) {
            $periodesInput = [];
            foreach ($periodeIds as $pid) {
                $moy   = $periodesMoyennes[$eid][$pid] ?? AverageValue::empty();
                $p     = (float)($poids[$pid] ?? $defaultPoids);
                $periodesInput[] = ['moyenne' => $moy, 'poids' => $p];
            }
            $moyGenerale = $this->calculator->moyenneGenerale($periodesInput);
            $eleveMoyennes[] = array_merge($info, [
                'moyenne'       => $moyGenerale,
                'aEliminatoire' => false,
            ]);
        }

        $eleveMoyennes = $this->applyExclusions($eleveMoyennes, $opts);
        $ranked        = $this->rank($eleveMoyennes);
        $stats         = $this->computeStats($ranked);

        return new RankingResultDTO(
            type         : 'general',
            periodeId    : 0,
            classeId     : $classeId,
            matiereId    : null,
            niveau       : null,
            rankings     : $ranked,
            statistiques : $stats,
            nbTotal      : count($ranked),
            nbAdmis      : $stats['admis'] ?? 0,
            generatedAt  : date('Y-m-d H:i:s'),
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Méthode de calcul centrale — publique pour les tests unitaires
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calcule le classement à partir de lignes de données brutes.
     *
     * Chaque ligne doit avoir :
     *   eleve_id, nom, prenom, matricule?, classe_nom?,
     *   matiere_id, coeff_matiere, note_max, coeff_eval,
     *   valeur, est_absent, est_eliminatoire, seuil_eliminatoire
     */
    public function computeFromNoteRows(
        array   $rows,
        string  $type,
        int     $periodeId,
        ?int    $classeId,
        ?int    $matiereId,
        ?string $niveau,
        array   $options = [],
    ): RankingResultDTO {
        if (empty($rows)) {
            return $this->emptyResult($type, $periodeId, $classeId, $matiereId, $niveau);
        }

        $opts       = array_merge($this->optionsDefaults, $options);
        $eleveInfo  = [];
        $grouped    = $this->groupRowsByEleve($rows, $eleveInfo);

        // Pour chaque élève, déléguer le calcul de bulletin à AcademicCalculationService
        $eleveMoyennes = [];
        foreach ($grouped as $eid => $matieres) {
            $bulletin = $this->calculator->calculerBulletin($matieres, false, null, $opts);
            $eleveMoyennes[] = array_merge($eleveInfo[$eid], [
                'moyenne'       => $bulletin['periode']['moyenne'],
                'aEliminatoire' => $bulletin['periode']['aEliminatoire'],
                'nb_notes'      => $bulletin['periode']['nbNotes'] ?? 0,
                'nb_absents'    => $bulletin['periode']['nbAbsents'] ?? 0,
            ]);
        }

        $eleveMoyennes = $this->applyExclusions($eleveMoyennes, $opts);
        $ranked        = $this->rank($eleveMoyennes);
        $stats         = $this->computeStats($ranked);

        return new RankingResultDTO(
            type         : $type,
            periodeId    : $periodeId,
            classeId     : $classeId,
            matiereId    : $matiereId,
            niveau       : $niveau,
            rankings     : $ranked,
            statistiques : $stats,
            nbTotal      : count($ranked),
            nbAdmis      : $stats['admis'] ?? 0,
            generatedAt  : date('Y-m-d H:i:s'),
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Méthodes internes
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Regroupe les lignes brutes par [eleve_id][matiere_id].
     * Remplit $eleveInfo au passage (par référence).
     *
     * @return array [eleve_id => [matiere_id => ['coefficient'=>float, 'notes'=>array]]]
     */
    private function groupRowsByEleve(array $rows, array &$eleveInfo = []): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $row = (array)$row;
            $eid = (int)$row['eleve_id'];
            $mid = (int)$row['matiere_id'];

            if (!isset($eleveInfo[$eid])) {
                $eleveInfo[$eid] = $this->extractEleveInfo($row);
            }

            if (!isset($grouped[$eid][$mid])) {
                $grouped[$eid][$mid] = [
                    'coefficient' => (float)($row['coeff_matiere'] ?? 1.0),
                    'notes'       => [],
                ];
            }

            $grouped[$eid][$mid]['notes'][] = [
                'valeur'             => isset($row['valeur']) ? (float)$row['valeur'] : null,
                'note_max'           => (float)($row['note_max']   ?? 20.0),
                'coefficient'        => (float)($row['coeff_eval'] ?? 1.0),
                'est_absent'         => (bool)($row['est_absent']  ?? false),
                'est_eliminatoire'   => (bool)($row['est_eliminatoire'] ?? false),
                'seuil_eliminatoire' => isset($row['seuil_eliminatoire'])
                    ? (float)$row['seuil_eliminatoire']
                    : null,
            ];
        }
        return $grouped;
    }

    private function extractEleveInfo(array|object $row): array
    {
        $row = (array)$row;
        return [
            'eleve_id'   => (int)$row['eleve_id'],
            'nom'        => $row['nom']        ?? '',
            'prenom'     => $row['prenom']     ?? '',
            'matricule'  => $row['matricule']  ?? null,
            'classe_id'  => isset($row['classe_id']) ? (int)$row['classe_id'] : null,
            'classe_nom' => $row['classe_nom'] ?? null,
        ];
    }

    /**
     * Filtre les élèves selon les options d'exclusion.
     */
    private function applyExclusions(array $eleves, array $opts): array
    {
        if ($opts['exclure_sans_notes']) {
            $eleves = array_values(array_filter(
                $eleves,
                fn($e) => !($e['moyenne'] instanceof AverageValue && $e['moyenne']->isEmpty())
            ));
        }

        $seuil = (int)($opts['seuil_exclusion_notes'] ?? 0);
        if ($seuil > 0) {
            $eleves = array_values(array_filter(
                $eleves,
                fn($e) => ($e['nb_notes'] ?? 0) >= $seuil
            ));
        }

        return $eleves;
    }

    /**
     * Délègue le classement + l'enrichissement mention à AcademicCalculationService.
     */
    private function rank(array $eleves): array
    {
        // AcademicCalculationService::classement attend ['moyenne' => AverageValue, ...]
        $ranked = $this->calculator->classement($eleves);

        foreach ($ranked as &$e) {
            $mention           = $this->calculator->mention($e['moyenne']);
            $e['mention_label'] = $mention->getLabel();
            $e['mention_code']  = $mention->getCode();
            $e['mention_css']   = $mention->getCssColor();
            $e['admis']         = $mention->isAdmis();
        }
        unset($e);

        return $ranked;
    }

    /**
     * Calcule les statistiques de classe via AcademicCalculationService.
     */
    private function computeStats(array $ranked): array
    {
        $moyennes = array_map(fn($e) => $e['moyenne'], $ranked);
        return $this->calculator->statistiquesClasse($moyennes);
    }

    private function emptyResult(
        string $type, int $periodeId,
        ?int $classeId = null, ?int $matiereId = null, ?string $niveau = null
    ): RankingResultDTO {
        return new RankingResultDTO(
            type         : $type,
            periodeId    : $periodeId,
            classeId     : $classeId,
            matiereId    : $matiereId,
            niveau       : $niveau,
            rankings     : [],
            statistiques : [],
            nbTotal      : 0,
            nbAdmis      : 0,
            generatedAt  : date('Y-m-d H:i:s'),
        );
    }
}
