<?php

namespace App\Modules\Academique\Services;

use App\Modules\Academique\ValueObjects\AverageValue;
use App\Modules\Academique\ValueObjects\GradeValue;
use App\Modules\Academique\ValueObjects\MentionValue;

/**
 * Moteur de calcul académique — source unique de vérité.
 *
 * RÈGLES D'OR :
 *   • Aucun calcul ne doit exister hors de ce service.
 *   • Aucun accès base de données ici — ce service est pur (inputs → outputs).
 *   • Aucun dispatch d'événements — c'est la responsabilité des appelants.
 *
 * CONVENTIONS D'ENTRÉE :
 *   $noteItem = [
 *       'valeur'             => float|null,  // null si non saisie
 *       'note_max'           => float,
 *       'coefficient'        => float,       // coefficient de l'évaluation
 *       'est_absent'         => int|bool,
 *       'est_eliminatoire'   => int|bool,    // type_evaluation.est_eliminatoire
 *       'seuil_eliminatoire' => float|null,  // type_evaluation.seuil_eliminatoire (/20)
 *   ]
 *
 *   $matiereItem = [
 *       'moyenne'            => AverageValue,   // résultat de moyenneMatiere()
 *       'coefficient'        => float,           // matieres.coefficient
 *   ]
 */
class AcademicCalculationService
{
    // ── Options par défaut ────────────────────────────────────────────────────

    const OPTIONS_DEFAULTS = [
        'absent_vaut_zero'    => true,   // ABS = 0/noteMax dans le calcul
        'non_remis_vaut_zero' => true,   // note non saisie (null) = 0
        'arrondi'             => 2,      // décimales pour tous les arrondis
        'note_passage'        => 10.0,   // seuil de passage /20
    ];

    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = array_merge(self::OPTIONS_DEFAULTS, $options);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SECTION 1 — Calculs atomiques
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Ramène une note sur /20 (normalisation universelle).
     */
    public function noteRameneeSur20(float $valeur, float $noteMax): float
    {
        if ($noteMax <= 0) return 0.0;
        return $this->arrondir(($valeur / $noteMax) * 20.0);
    }

    /**
     * Note pondérée = noteRamenée/20 × coefficient.
     */
    public function notePonderee(float $valeur, float $noteMax, float $coefficient): float
    {
        return $this->arrondir($this->noteRameneeSur20($valeur, $noteMax) * $coefficient);
    }

    /**
     * Arrondi paramétrable (utilise l'option 'arrondi' par défaut).
     */
    public function arrondir(float $value, ?int $precision = null): float
    {
        return round($value, $precision ?? $this->options['arrondi']);
    }

    /**
     * Vérifie si une note est éliminatoire.
     * $seuil est exprimé /20.
     */
    public function estEliminatoire(?float $valeur, float $noteMax, ?float $seuil): bool
    {
        if ($valeur === null || $seuil === null) return false;
        $ramenee = $this->noteRameneeSur20($valeur, $noteMax);
        return $ramenee < $seuil;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SECTION 2 — Moyenne d'une évaluation (toute la classe)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Moyenne brute de toutes les notes d'une évaluation.
     *
     * @param array $notes  [{valeur, est_absent}]
     * @param float $noteMax  barème de l'évaluation
     */
    public function moyenneEvaluation(array $notes, float $noteMax): AverageValue
    {
        if (empty($notes)) return AverageValue::empty();

        $sum   = 0.0;
        $count = 0;

        foreach ($notes as $n) {
            $v = $this->valeurEffective(
                isset($n['valeur']) ? (float)$n['valeur'] : null,
                (bool)($n['est_absent'] ?? false)
            );
            if ($v === null) continue; // exclue si option non_remis_vaut_zero=false
            $sum += $this->noteRameneeSur20($v, $noteMax);
            $count++;
        }

        if ($count === 0) return AverageValue::empty();
        return new AverageValue($sum / $count);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SECTION 3 — Moyenne d'un élève par matière
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Moyenne pondérée d'un élève dans une matière sur une période.
     *
     * Formule :
     *   Σ(noteRamenée/20 × coeffEval) / Σ(coeffEval des notes valides)
     *
     * @param array $items  [{valeur, note_max, coefficient, est_absent,
     *                        est_eliminatoire, seuil_eliminatoire}]
     * @return array{moyenne: AverageValue, aEliminatoire: bool, nbNotes: int, nbAbsents: int}
     */
    public function moyenneMatiere(array $items): array
    {
        if (empty($items)) {
            return [
                'moyenne'        => AverageValue::empty(),
                'aEliminatoire'  => false,
                'nbNotes'        => 0,
                'nbAbsents'      => 0,
            ];
        }

        $sommePonderee = 0.0;
        $sommeCoeff    = 0.0;
        $aEliminatoire = false;
        $nbNotes       = 0;
        $nbAbsents     = 0;

        foreach ($items as $item) {
            $estAbsent = (bool)($item['est_absent'] ?? false);
            $noteMax   = (float)($item['note_max']   ?? 20.0);
            $coeff     = (float)($item['coefficient'] ?? 1.0);
            $rawValeur = isset($item['valeur']) && $item['valeur'] !== null
                ? (float)$item['valeur'] : null;

            if ($estAbsent) $nbAbsents++;

            $valeur = $this->valeurEffective($rawValeur, $estAbsent);
            if ($valeur === null) continue; // exclue

            $nbNotes++;
            $ramenee        = $this->noteRameneeSur20($valeur, $noteMax);
            $sommePonderee += $ramenee * $coeff;
            $sommeCoeff    += $coeff;

            // Vérification note éliminatoire
            if ((bool)($item['est_eliminatoire'] ?? false)) {
                $seuil = isset($item['seuil_eliminatoire']) && $item['seuil_eliminatoire'] !== null
                    ? (float)$item['seuil_eliminatoire'] : null;
                if ($this->estEliminatoire($valeur, $noteMax, $seuil)) {
                    $aEliminatoire = true;
                }
            }
        }

        if ($sommeCoeff <= 0) {
            return [
                'moyenne'        => AverageValue::empty(),
                'aEliminatoire'  => $aEliminatoire,
                'nbNotes'        => $nbNotes,
                'nbAbsents'      => $nbAbsents,
            ];
        }

        return [
            'moyenne'        => new AverageValue($sommePonderee / $sommeCoeff),
            'aEliminatoire'  => $aEliminatoire,
            'nbNotes'        => $nbNotes,
            'nbAbsents'      => $nbAbsents,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SECTION 4 — Moyenne de période (toutes matières d'un élève)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Moyenne générale d'un élève pour une période.
     *
     * Formule :
     *   Σ(moyenneMatiere × coeffMatière) / Σ(coeffMatière des matières avec note)
     *
     * @param array $matieres  [{moyenne: AverageValue, coefficient: float}]
     * @return array{moyenne: AverageValue, aEliminatoire: bool, nbMatieres: int}
     */
    public function moyennePeriode(array $matieres, bool $aEliminatoire = false): array
    {
        if (empty($matieres)) {
            return [
                'moyenne'       => AverageValue::empty(),
                'aEliminatoire' => false,
                'nbMatieres'    => 0,
            ];
        }

        $sommePonderee = 0.0;
        $sommeCoeff    = 0.0;
        $nbMatieres    = 0;

        foreach ($matieres as $m) {
            /** @var AverageValue $moy */
            $moy   = $m['moyenne'];
            $coeff = (float)($m['coefficient'] ?? 1.0);

            if ($moy->isEmpty()) continue;

            $sommePonderee += $moy->getValue() * $coeff;
            $sommeCoeff    += $coeff;
            $nbMatieres++;
        }

        if ($sommeCoeff <= 0) {
            return [
                'moyenne'       => AverageValue::empty(),
                'aEliminatoire' => $aEliminatoire,
                'nbMatieres'    => $nbMatieres,
            ];
        }

        return [
            'moyenne'       => new AverageValue($sommePonderee / $sommeCoeff),
            'aEliminatoire' => $aEliminatoire,
            'nbMatieres'    => $nbMatieres,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SECTION 5 — Moyenne générale (toutes périodes)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Moyenne pondérée sur toutes les périodes (ex: 3 trimestres).
     *
     * @param array $periodes  [{moyenne: AverageValue, poids: float}]
     *                         poids = 1 (trimestre), 2 (semestre principal), etc.
     */
    public function moyenneGenerale(array $periodes): AverageValue
    {
        if (empty($periodes)) return AverageValue::empty();

        $sommePonderee = 0.0;
        $sommePoids    = 0.0;

        foreach ($periodes as $p) {
            /** @var AverageValue $moy */
            $moy   = $p['moyenne'];
            $poids = (float)($p['poids'] ?? 1.0);

            if ($moy->isEmpty()) continue;

            $sommePonderee += $moy->getValue() * $poids;
            $sommePoids    += $poids;
        }

        if ($sommePoids <= 0) return AverageValue::empty();
        return new AverageValue($sommePonderee / $sommePoids);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SECTION 6 — Statistiques de classe
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Calcule les statistiques d'une classe à partir d'un tableau de moyennes.
     *
     * @param AverageValue[] $moyennes
     * @return array{count, admis, echoues, min, max, moyenne, ecart_type, taux_reussite}
     */
    public function statistiquesClasse(array $moyennes): array
    {
        $valides = array_filter($moyennes, fn(AverageValue $m) => !$m->isEmpty());
        $count   = count($valides);

        if ($count === 0) {
            return [
                'count'         => 0,
                'admis'         => 0,
                'echoues'       => 0,
                'min'           => null,
                'max'           => null,
                'moyenne'       => null,
                'ecart_type'    => null,
                'taux_reussite' => null,
            ];
        }

        $values  = array_map(fn(AverageValue $m) => $m->getValue(), $valides);
        $seuil   = $this->options['note_passage'];
        $admis   = count(array_filter($values, fn($v) => $v >= $seuil));
        $somme   = array_sum($values);
        $moyenne = $somme / $count;

        return [
            'count'         => $count,
            'admis'         => $admis,
            'echoues'       => $count - $admis,
            'min'           => $this->arrondir(min($values)),
            'max'           => $this->arrondir(max($values)),
            'moyenne'       => $this->arrondir($moyenne),
            'ecart_type'    => $this->arrondir($this->ecartType($values, $moyenne)),
            'taux_reussite' => $this->arrondir(($admis / $count) * 100),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SECTION 7 — Mention
    // ═══════════════════════════════════════════════════════════════════════════

    public function mention(AverageValue $moyenne): MentionValue
    {
        if ($moyenne->isEmpty()) {
            return MentionValue::fromAverage(0.0);
        }
        return MentionValue::fromAverage($moyenne->getValue());
    }

    /**
     * Surcharge acceptant un float directement (usage interne/tests).
     */
    public function mentionFromFloat(float $value): MentionValue
    {
        return MentionValue::fromAverage($value);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SECTION 8 — Classement
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Trie les élèves par moyenne décroissante et attribue les rangs.
     *
     * @param array $eleves  [{eleve_id, moyenne: AverageValue, ...}]
     * @return array  Même structure avec 'rang' ajouté (ex-aequo = même rang)
     */
    public function classement(array $eleves): array
    {
        usort($eleves, fn($a, $b) =>
            $b['moyenne']->compareTo($a['moyenne'])
        );

        // Rang standard : ex-aequo partagent le même rang,
        // le rang suivant saute autant de positions qu'il y a d'ex-aequo.
        $rang = 1;
        foreach ($eleves as $i => &$e) {
            if ($i > 0) {
                $prev = $eleves[$i - 1]['moyenne'];
                $curr = $e['moyenne'];
                $same = !$prev->isEmpty() && !$curr->isEmpty()
                     && abs($prev->getValue() - $curr->getValue()) < 0.001;
                if (!$same) {
                    $rang = $i + 1;
                }
            }
            $e['rang'] = $rang;
        }
        unset($e);

        return $eleves;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SECTION 9 — Décision de passage (préparation Phase 2.7)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Prépare la décision de passage sans la persister.
     *
     * @return array{decision: string, motif: string, admissible: bool}
     *   decision : 'admis' | 'refuse' | 'rattrapage' | 'indeterminate'
     */
    public function prepareDecision(
        AverageValue $moyenne,
        bool         $aEliminatoire = false,
        ?float       $seuilRattrapage = null
    ): array {
        if ($moyenne->isEmpty()) {
            return [
                'decision'    => 'indeterminate',
                'motif'       => 'Données insuffisantes pour décider.',
                'admissible'  => false,
            ];
        }

        $val   = $moyenne->getValue();
        $seuil = $this->options['note_passage'];

        if ($aEliminatoire) {
            return [
                'decision'   => 'refuse',
                'motif'      => 'Note éliminatoire détectée.',
                'admissible' => false,
            ];
        }

        if ($val >= $seuil) {
            return [
                'decision'   => 'admis',
                'motif'      => "Moyenne {$val}/20 ≥ {$seuil}.",
                'admissible' => true,
            ];
        }

        if ($seuilRattrapage !== null && $val >= $seuilRattrapage) {
            return [
                'decision'   => 'rattrapage',
                'motif'      => "Moyenne {$val}/20 entre {$seuilRattrapage} et {$seuil}.",
                'admissible' => false,
            ];
        }

        return [
            'decision'   => 'refuse',
            'motif'      => "Moyenne {$val}/20 < {$seuil}.",
            'admissible' => false,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SECTION 10 — Helpers privés
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Résout la valeur effective d'une note selon les options.
     * Retourne null si la note doit être exclue du calcul.
     */
    private function valeurEffective(?float $rawValeur, bool $estAbsent): ?float
    {
        if ($estAbsent) {
            return $this->options['absent_vaut_zero'] ? 0.0 : null;
        }
        if ($rawValeur === null) {
            return $this->options['non_remis_vaut_zero'] ? 0.0 : null;
        }
        return $rawValeur;
    }

    private function ecartType(array $values, float $moyenne): float
    {
        $count = count($values);
        if ($count < 2) return 0.0;

        $sumSqDiff = array_sum(
            array_map(fn($v) => ($v - $moyenne) ** 2, $values)
        );
        return sqrt($sumSqDiff / $count);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SECTION 11 — API utilitaire publique
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Calcule la moyenne d'un élève pour un bulletin complet.
     * Façade pratique utilisée par BulletinService (Phase 2.6).
     *
     * @param array $matieresData  [
     *     matiereId => [
     *         'coefficient' => float,
     *         'notes'       => $noteItems[],
     *     ]
     * ]
     * @return array{
     *     matieres: array,        // matiereId => {moyenne, aEliminatoire, ...}
     *     periode: array,         // {moyenne, aEliminatoire, nbMatieres}
     *     mention: MentionValue,
     *     decision: array,
     * }
     */
    public function calculerBulletin(
        array  $matieresData,
        bool   $avecDecision     = false,
        ?float $seuilRattrapage  = null
    ): array {
        $matieres     = [];
        $aEliminatoire = false;

        foreach ($matieresData as $matiereId => $data) {
            $result = $this->moyenneMatiere($data['notes'] ?? []);
            $matieres[$matiereId] = $result;
            if ($result['aEliminatoire']) $aEliminatoire = true;
        }

        // Construire les inputs pour moyennePeriode
        $matiereInputs = [];
        foreach ($matieresData as $matiereId => $data) {
            $matiereInputs[] = [
                'moyenne'     => $matieres[$matiereId]['moyenne'],
                'coefficient' => (float)($data['coefficient'] ?? 1.0),
            ];
        }

        $periode  = $this->moyennePeriode($matiereInputs, $aEliminatoire);
        $mention  = $this->mention($periode['moyenne']);
        $decision = $avecDecision
            ? $this->prepareDecision($periode['moyenne'], $aEliminatoire, $seuilRattrapage)
            : [];

        return [
            'matieres' => $matieres,
            'periode'  => $periode,
            'mention'  => $mention,
            'decision' => $decision,
        ];
    }

    /**
     * Retourne les options actives (utile pour le debug et les tests).
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
