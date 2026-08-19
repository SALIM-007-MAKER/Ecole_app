<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\ReinscriptionModel;
use App\Models\ClasseModel;

class ReinscriptionController extends Controller
{
    private ReinscriptionModel $reinscModel;
    private ClasseModel        $classeModel;

    public function __construct()
    {
        parent::__construct();
        $this->reinscModel = new ReinscriptionModel();
        $this->classeModel = new ClasseModel();
    }

    // ─── Étape 1 : choix des années ─────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('classes.edit');

        $annees = $this->reinscModel->anneesDisponibles();
        $source = $this->request->get('source', $annees[0] ?? '');

        $this->render('reinscription/index', [
            'title'  => 'Réinscription — passage de classe',
            'annees' => $annees,
            'source' => $source,
        ]);
    }

    // ─── Étape 2 : plan de passage (mapping classe → classe) ───────────────

    public function plan(): void
    {
        $this->requirePermission('classes.edit');

        $source      = trim($this->request->get('source', ''));
        $destination = trim($this->request->get('destination', ''));

        if (!$source || !$destination) {
            Session::flash('error', 'Sélectionnez une année source et une année destination.');
            $this->redirect(BASE_URL . '/reinscription');
            return;
        }
        if ($source === $destination) {
            Session::flash('error', 'L\'année destination doit être différente de l\'année source.');
            $this->redirect(BASE_URL . '/reinscription');
            return;
        }

        $classesSource      = $this->reinscModel->classesAvecEffectif($source);
        $classesDestination = $this->reinscModel->classesAvecEffectif($destination);

        $suggestions = [];
        foreach ($classesSource as $cs) {
            $suggestions[$cs->id] = $this->reinscModel->suggestDestination($cs, $classesDestination);
        }

        $dejaTraites = $this->reinscModel->dejaTraites($source, $destination);

        $this->render('reinscription/plan', [
            'title'               => 'Plan de passage — ' . $source . ' → ' . $destination,
            'source'              => $source,
            'destination'         => $destination,
            'classesSource'       => $classesSource,
            'classesDestination'  => $classesDestination,
            'suggestions'         => $suggestions,
            'dejaTraites'         => $dejaTraites,
        ]);
    }

    // ─── Étape 3 : aperçu avant exécution ───────────────────────────────────

    public function apercu(): void
    {
        $this->requirePermission('classes.edit');
        $this->verifyCsrf();

        [$source, $destination, $mapping] = $this->lireMapping();
        if (!$source || !$destination) {
            $this->redirect(BASE_URL . '/reinscription');
            return;
        }

        $classesDestination = $this->reinscModel->classesAvecEffectif($destination);
        $classesById = [];
        foreach (array_merge($this->reinscModel->classesAvecEffectif($source), $classesDestination) as $c) {
            $classesById[$c->id] = $c;
        }

        $lignes = [];
        foreach ($mapping as $classeSourceId => $regle) {
            $classeSource = $classesById[$classeSourceId] ?? null;
            if (!$classeSource) {
                continue;
            }
            $destId = $regle['sortant'] ? null : $regle['destination_id'];
            $classeDest = $destId ? ($classesById[$destId] ?? null) : null;

            $placesRestantes = null;
            if ($classeDest) {
                $placesRestantes = (int)$classeDest->max_eleves > 0
                    ? (int)$classeDest->max_eleves - (int)$classeDest->nb_eleves
                    : null;
            }

            $lignes[] = [
                'classe_source'      => $classeSource,
                'classe_destination' => $classeDest,
                'sortant'            => $regle['sortant'],
                'places_restantes'   => $placesRestantes,
                'depassement'        => $placesRestantes !== null && $placesRestantes < (int)$classeSource->nb_eleves,
            ];
        }

        $this->render('reinscription/apercu', [
            'title'       => 'Confirmer la réinscription',
            'source'      => $source,
            'destination' => $destination,
            'lignes'      => $lignes,
            'mappingJson' => json_encode($mapping),
        ]);
    }

    // ─── Exécution ───────────────────────────────────────────────────────────

    public function executer(): void
    {
        $this->requirePermission('classes.edit');
        $this->verifyCsrf();

        [$source, $destination, $mapping] = $this->lireMapping();
        if (!$source || !$destination || empty($mapping)) {
            Session::flash('error', 'Plan de réinscription invalide ou expiré, veuillez recommencer.');
            $this->redirect(BASE_URL . '/reinscription');
            return;
        }

        $user = $this->currentUser();

        try {
            $resultat = $this->reinscModel->executer($mapping, $source, $destination, (int)$user['id']);
        } catch (\Throwable $e) {
            Session::flash('error', 'Erreur lors de la réinscription : ' . $e->getMessage());
            $this->redirect(BASE_URL . '/reinscription');
            return;
        }

        Session::flash('success', sprintf(
            '%d élève(s) réinscrit(s) vers %s, %d marqué(s) sortant(s)%s.',
            $resultat['deplaces'],
            htmlspecialchars($destination, ENT_QUOTES),
            $resultat['sortants'],
            !empty($resultat['ignores']) ? ', ' . count($resultat['ignores']) . ' ignoré(s) — capacité atteinte' : ''
        ));

        $this->redirect(BASE_URL . '/classes');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Lit le mapping soumis en POST — champs `classe_dest_{id}` (valeur
     * "sortant" ou l'id de la classe destination) pour chaque classe source
     * cochée dans `classes_source[]`.
     *
     * @return array{0:string,1:string,2:array}
     */
    private function lireMapping(): array
    {
        $source      = trim((string)$this->request->post('source', ''));
        $destination = trim((string)$this->request->post('destination', ''));
        $classesIds  = (array)$this->request->post('classes_source', []);

        $mapping = [];
        foreach ($classesIds as $classeSourceId) {
            $classeSourceId = (int)$classeSourceId;
            if ($classeSourceId <= 0) {
                continue;
            }
            $valeur = (string)$this->request->post('classe_dest_' . $classeSourceId, '');
            if ($valeur === 'sortant') {
                $mapping[$classeSourceId] = ['destination_id' => null, 'sortant' => true];
            } elseif (ctype_digit($valeur) && (int)$valeur > 0) {
                $mapping[$classeSourceId] = ['destination_id' => (int)$valeur, 'sortant' => false];
            }
        }

        return [$source, $destination, $mapping];
    }
}
