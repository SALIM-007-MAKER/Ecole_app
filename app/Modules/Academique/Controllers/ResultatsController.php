<?php

declare(strict_types=1);

namespace App\Modules\Academique\Controllers;

use App\Modules\Academique\Repositories\EvaluationRepository;
use App\Modules\Academique\Repositories\RankingRepository;
use App\Modules\Academique\Services\BulletinEngineFactory;
use App\Modules\Academique\Services\RankingEngine;
use Core\Controller;

/**
 * Écrans de consultation "résultats" du module Académique V2 — équivalents
 * des anciens écrans V1 (BulletinController::classe()/classement(),
 * NoteController::moyennes()), reconstruits sur RankingEngine uniquement
 * (aucun calcul manuel de moyenne ici, aucune lecture des tables `notes`/
 * `controles`/`periodes` V1 — voir RankingRepository, qui ne lit que
 * `notes_v2`/`evaluations`).
 *
 * Le RankingEngine utilisé est celui déjà configuré par BulletinEngineFactory
 * (mêmes seuils note_passage/rattrapage par établissement que pour la
 * génération des bulletins) — voir BulletinGenerator::getRankingEngine(),
 * pour que "rang" affiché ici corresponde toujours au rang du bulletin.
 */
class ResultatsController extends Controller
{
    private EvaluationRepository $evalRepo;
    private RankingRepository    $rankingRepo;
    private RankingEngine        $rankingEngine;

    public function __construct()
    {
        parent::__construct();
        $this->evalRepo    = new EvaluationRepository();
        $this->rankingRepo = new RankingRepository();

        $etablissementId    = (int)($this->currentUser()['etablissement_id'] ?? 1);
        $this->rankingEngine = BulletinEngineFactory::make($etablissementId)->getRankingEngine();
    }

    // ─────────────────────────────────────────────────────────────────
    //  1. Résultats classe — équivalent de /bulletins/classe
    // ─────────────────────────────────────────────────────────────────

    public function classe(): void
    {
        $this->requirePermission('academique.moyennes.view');

        [$anneesScolaires, $periodes, $classes, $anneeScolaire, $periodeId, $classeId] = $this->resolveFiltres();

        $classement = null;
        if ($classeId && $periodeId) {
            $classement = $this->rankingEngine->classementClasse($classeId, $periodeId);
        }

        $this->render('Academique::resultats/classe', [
            'title'           => 'Résultats de classe — Académique V2',
            'anneesScolaires' => $anneesScolaires,
            'periodes'        => $periodes,
            'classes'         => $classes,
            'anneeScolaire'   => $anneeScolaire,
            'periodeId'       => $periodeId,
            'classeId'        => $classeId,
            'periode'         => $this->trouverPeriode($periodes, $periodeId),
            'classe'          => $this->trouverClasse($classes, $classeId),
            'classement'      => $classement,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  2. Classement — basé sur RankingEngine, rang + évolution
    // ─────────────────────────────────────────────────────────────────

    public function classement(): void
    {
        $this->requirePermission('academique.classement.view');

        [$anneesScolaires, $periodes, $classes, $anneeScolaire, $periodeId, $classeId] = $this->resolveFiltres();

        $classement = null;
        $evolutions = [];

        if ($classeId && $periodeId) {
            $classement = $this->rankingEngine->classementClasse($classeId, $periodeId);

            $periodePrecedente = $this->trouverPeriodePrecedente($periodes, $periodeId);
            if ($periodePrecedente !== null) {
                $classementPrecedent = $this->rankingEngine->classementClasse($classeId, (int)$periodePrecedente->id);
                foreach ($classementPrecedent->rankings as $entry) {
                    $evolutions[(int)$entry['eleve_id']] = (int)$entry['rang'];
                }
            }
        }

        $this->render('Academique::resultats/classement', [
            'title'           => 'Classement — Académique V2',
            'anneesScolaires' => $anneesScolaires,
            'periodes'        => $periodes,
            'classes'         => $classes,
            'anneeScolaire'   => $anneeScolaire,
            'periodeId'       => $periodeId,
            'classeId'        => $classeId,
            'periode'         => $this->trouverPeriode($periodes, $periodeId),
            'classe'          => $this->trouverClasse($classes, $classeId),
            'classement'      => $classement,
            'rangsPrecedents' => $evolutions,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  3. Moyennes — moyenne par matière + moyenne générale (notes_v2)
    // ─────────────────────────────────────────────────────────────────

    public function moyennes(): void
    {
        $this->requirePermission('academique.moyennes.view');

        [$anneesScolaires, $periodes, $classes, $anneeScolaire, $periodeId, $classeId] = $this->resolveFiltres();

        $classement    = null;
        $lignesMatiere = [];

        if ($classeId && $periodeId) {
            $classement = $this->rankingEngine->classementClasse($classeId, $periodeId);

            foreach ($this->matieresDeLaClasse($classeId, $periodeId) as $matiereId => $matiereNom) {
                $classementMatiere = $this->rankingEngine->classementMatiere($matiereId, $periodeId, $classeId);
                $lignesMatiere[] = [
                    'matiere_id'    => $matiereId,
                    'matiere_nom'   => $matiereNom,
                    'statistiques'  => $classementMatiere->statistiques,
                    'moyenneClasse' => $classementMatiere->getMoyenneClasse(),
                    'nbEleves'      => $classementMatiere->nbTotal,
                ];
            }
            usort($lignesMatiere, fn($a, $b) => $a['matiere_nom'] <=> $b['matiere_nom']);
        }

        $this->render('Academique::resultats/moyennes', [
            'title'           => 'Tableau des moyennes — Académique V2',
            'anneesScolaires' => $anneesScolaires,
            'periodes'        => $periodes,
            'classes'         => $classes,
            'anneeScolaire'   => $anneeScolaire,
            'periodeId'       => $periodeId,
            'classeId'        => $classeId,
            'periode'         => $this->trouverPeriode($periodes, $periodeId),
            'classe'          => $this->trouverClasse($classes, $classeId),
            'classement'      => $classement,
            'lignesMatiere'   => $lignesMatiere,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Aides privées — sélecteurs / résolution des filtres GET
    // ─────────────────────────────────────────────────────────────────

    /**
     * Lit les filtres communs aux 3 écrans (année scolaire, période, classe)
     * et prépare les listes pour les sélecteurs. Aucun calcul ici — pure
     * lecture de listes déjà exposées par EvaluationRepository (mêmes
     * méthodes que EvaluationController::index(), pas de duplication).
     *
     * @return array{0: array, 1: array, 2: array, 3: string, 4: int, 5: int}
     *   [anneesScolaires, periodes (filtrées sur l'année si fournie), classes,
     *    anneeScolaire retenue, periodeId, classeId]
     */
    private function resolveFiltres(): array
    {
        $toutesPeriodes  = $this->evalRepo->listPeriodesForSelect();
        $anneesScolaires = array_values(array_unique(array_map(
            fn($p) => $p->annee_scolaire,
            $toutesPeriodes
        )));

        $anneeScolaire = trim((string)$this->request->get('annee_scolaire', ''));
        $periodes      = $anneeScolaire === ''
            ? $toutesPeriodes
            : array_values(array_filter($toutesPeriodes, fn($p) => $p->annee_scolaire === $anneeScolaire));

        $classes   = $this->evalRepo->listClassesForSelect();
        $periodeId = (int)$this->request->get('periode_id', 0);
        $classeId  = (int)$this->request->get('classe_id', 0);

        return [$anneesScolaires, $periodes, $classes, $anneeScolaire, $periodeId, $classeId];
    }

    private function trouverPeriode(array $periodes, int $periodeId): ?object
    {
        foreach ($periodes as $p) {
            if ((int)$p->id === $periodeId) return $p;
        }
        return null;
    }

    private function trouverClasse(array $classes, int $classeId): ?object
    {
        foreach ($classes as $c) {
            if ((int)$c->id === $classeId) return $c;
        }
        return null;
    }

    /**
     * Période immédiatement antérieure à $periodeId dans la liste fournie
     * (déjà ordonnée par EvaluationRepository::listPeriodesForSelect() —
     * annee_scolaire DESC, ordre ASC, numero ASC) — utilisée pour
     * l'évolution du rang au tableau de classement. Retourne null si
     * $periodeId est la première période de son année ou n'a pas de
     * prédécesseur connu.
     */
    private function trouverPeriodePrecedente(array $periodes, int $periodeId): ?object
    {
        $index = null;
        foreach ($periodes as $i => $p) {
            if ((int)$p->id === $periodeId) { $index = $i; break; }
        }
        if ($index === null || $index === 0) return null;

        $candidate = $periodes[$index - 1];
        $current   = $periodes[$index];
        return $candidate->annee_scolaire === $current->annee_scolaire ? $candidate : null;
    }

    /**
     * Matières distinctes ayant au moins une évaluation publiée/verrouillée
     * pour cette classe et cette période — dérivées des mêmes lignes brutes
     * que RankingEngine::classementClasse() interroge en interne
     * (RankingRepository::notesParClasseEtPeriode(), notes_v2/evaluations
     * uniquement), sans y ajouter de logique de calcul : on ne fait
     * qu'extraire les paires (matiere_id, matiere_nom) déjà présentes sur
     * chaque ligne pour savoir sur quelles matières boucler
     * classementMatiere().
     *
     * @return array matiereId => matiereNom
     */
    private function matieresDeLaClasse(int $classeId, int $periodeId): array
    {
        $rows     = $this->rankingRepo->notesParClasseEtPeriode($classeId, $periodeId);
        $matieres = [];
        foreach ($rows as $row) {
            $matieres[(int)$row->matiere_id] = $row->matiere_nom;
        }
        return $matieres;
    }
}
