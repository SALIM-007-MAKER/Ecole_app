<?php

namespace App\Controllers;

use App\Services\ProgrammeClasseService;
use App\Services\ProgrammeException;
use Core\Controller;
use Core\Session;

/**
 * Actions sur le programme pédagogique d'une classe (matières, coefficient, volume horaire propres à la classe).
 * Permission : classes.edit (la consultation, sur la fiche de classe, relève de classes.view). Les règles (verrou, confirmation, bornes,
 * isolation) sont dans ProgrammeClasseService ; ce contrôleur ne fait que lire la requête et afficher le résultat.
 */
class ClasseProgrammeController extends Controller
{
    private function service(): ProgrammeClasseService
    {
        return ProgrammeClasseService::pour((int)(Session::getUser()['id'] ?? 0) ?: null);
    }

    /** Vérifie permission, CSRF, disponibilité et classe ; retourne l'identifiant de classe, ou redirige. */
    private function ouvrir(string $id): int
    {
        $this->requirePermission('classes.edit');
        $this->verifyCsrf();
        $classeId = (int)$id;
        if (!ProgrammeClasseService::disponible()) {
            Session::flash('error', 'Le programme des classes n\'est pas encore disponible (migration T075 non appliquée).');
            $this->redirect(BASE_URL . '/classes/' . $classeId);
        }
        if ($this->service()->classe($classeId) === null) {
            Session::flash('error', 'Classe introuvable.');
            $this->redirect(BASE_URL . '/classes');
        }
        return $classeId;
    }

    private function retour(int $classeId): void
    {
        $this->redirect(BASE_URL . '/classes/' . $classeId . '?tab=programme');
    }

    public function ajouter(string $id): void
    {
        $classeId = $this->ouvrir($id);
        try {
            $this->service()->ajouter(
                $classeId,
                (int)$this->request->post('matiere_id', 0),
                $this->request->post('coefficient'),
                $this->request->post('volume_horaire'),
                (int)$this->request->post('ordre', 0),
                $this->request->post('facultative') !== '1'
            );
            Session::flash('success', 'Matière ajoutée au programme.');
        } catch (ProgrammeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->retour($classeId);
    }

    public function enregistrer(string $id): void
    {
        $classeId = $this->ouvrir($id);
        try {
            $lignes = $this->request->post('programme', []);
            $n = $this->service()->enregistrer($classeId, is_array($lignes) ? $lignes : [], $this->request->post('confirmer_notes') === '1');
            Session::flash('success', $n > 0 ? "Programme enregistré ({$n} matière(s) modifiée(s))." : 'Aucune modification.');
        } catch (ProgrammeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->retour($classeId);
    }

    public function retirer(string $id): void
    {
        $classeId = $this->ouvrir($id);
        try {
            $this->service()->retirer($classeId, (int)$this->request->post('matiere_id', 0), $this->request->post('confirmer_notes') === '1');
            Session::flash('success', 'Matière retirée du programme.');
        } catch (ProgrammeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->retour($classeId);
    }

    public function initialiser(string $id): void
    {
        $classeId = $this->ouvrir($id);
        try {
            $n = $this->service()->initialiserDepuisEnseignements($classeId);
            Session::flash('success', $n > 0 ? "{$n} matière(s) ajoutée(s) au programme depuis les enseignements." : 'Aucune matière à ajouter : tous les enseignements de la classe sont déjà au programme.');
        } catch (ProgrammeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->retour($classeId);
    }

    public function reproduire(string $id): void
    {
        $classeId = $this->ouvrir($id);
        try {
            $cibles = $this->request->post('cibles', []);
            $mode = (string)$this->request->post('mode', 'completer');
            $resultat = $this->service()->reproduire(
                $classeId,
                is_array($cibles) ? $cibles : [],
                $mode,
                $this->request->post('confirmer_ecrasement') === '1',
                $this->request->post('confirmer_notes') === '1'
            );
            $ok = array_filter($resultat, fn($r) => $r['ignoree'] === null);
            $ignorees = array_filter($resultat, fn($r) => $r['ignoree'] !== null);
            $msg = count($ok) . ' classe(s) mise(s) à jour (' . array_sum(array_column($ok, 'ajoutees')) . ' matière(s) ajoutée(s), ' . array_sum(array_column($ok, 'mises_a_jour')) . ' alignée(s)).';
            if ($ignorees !== []) {
                $msg .= ' Ignorée(s) : ' . implode(' ; ', array_map(fn($r) => $r['classe'] . ' — ' . $r['ignoree'], $ignorees)) . '.';
            }
            Session::flash($ignorees === [] ? 'success' : 'warning', $msg);
        } catch (ProgrammeException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->retour($classeId);
    }
}
