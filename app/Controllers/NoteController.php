<?php

namespace App\Controllers;

use Core\Controller;
use Core\EventDispatcher;
use Core\Session;
use App\Events\NoteAjoutee;
use App\Events\ControleUpdated;
use App\Models\NoteModel;
use App\Models\ControleModel;
use App\Models\PeriodeModel;
use App\Models\ClasseModel;
use App\Models\MatiereModel;
use App\Models\ProfesseurModel;
use App\Models\EnseignementModel;

class NoteController extends Controller
{
    private NoteModel      $noteModel;
    private ControleModel  $ctrlModel;
    private PeriodeModel   $periodeModel;
    private ClasseModel    $classeModel;
    private MatiereModel   $matiereModel;

    public function __construct()
    {
        parent::__construct();
        $this->noteModel    = new NoteModel();
        $this->ctrlModel    = new ControleModel();
        $this->periodeModel = new PeriodeModel();
        $this->classeModel  = new ClasseModel();
        $this->matiereModel = new MatiereModel();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TABLEAU DE BORD NOTES
    // ═══════════════════════════════════════════════════════════════════════════

    public function index(): void
    {
        $this->requirePermission('notes.view');

        $user    = $this->currentUser();
        $periodes = $this->periodeModel->findWithStats();
        $stats   = $this->noteModel->globalStats();
        $classes = $this->getClassesForUser($user);

        $this->render('notes/index', [
            'title'   => 'Notes & Évaluations',
            'periodes'=> $periodes,
            'classes' => $classes,
            'stats'   => $stats,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CONTRÔLES — LISTE
    // ═══════════════════════════════════════════════════════════════════════════

    public function controles(): void
    {
        $this->requirePermission('notes.view');

        $classeId  = (int)$this->request->get('classe_id',  0);
        $periodeId = (int)$this->request->get('periode_id', 0);
        $matiereId = (int)$this->request->get('matiere_id', 0);

        $controles = [];
        if ($classeId && $periodeId) {
            $controles = $this->ctrlModel->findByContextFiltered($classeId, $matiereId, $periodeId);
        }

        $this->render('notes/controles', [
            'title'     => 'Contrôles et évaluations',
            'controles' => $controles,
            'classes'   => $this->classeModel->findAll('niveau'),
            'periodes'  => $this->periodeModel->findAll('id'),
            'matieres'  => $this->matiereModel->findAll('nom'),
            'filters'   => compact('classeId', 'periodeId', 'matiereId'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CONTRÔLE — CRÉER
    // ═══════════════════════════════════════════════════════════════════════════

    public function createControle(): void
    {
        $this->requirePermission('notes.create');

        $user = $this->currentUser();
        $this->render('notes/controle_form', [
            'title'    => 'Nouveau contrôle',
            'edit'     => false,
            'controle' => null,
            'classes'  => $this->getClassesForUser($user),
            'periodes' => $this->periodeModel->findAll('id'),
            'matieres' => $this->matiereModel->findAll('nom'),
            'types'    => ControleModel::TYPES,
            'errors'   => Session::getFlash('errors', []),
            'old'      => Session::getFlash('old', [
                'classe_id'  => (int)$this->request->get('classe_id', 0),
                'matiere_id' => (int)$this->request->get('matiere_id', 0),
                'periode_id' => (int)$this->request->get('periode_id', 0),
            ]),
        ]);
    }

    public function storeControle(): void
    {
        $this->requirePermission('notes.create');
        $this->verifyCsrf();

        $data   = $this->collectControleData();
        $errors = $this->validateControle($data);

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/notes/controles/create');
            return;
        }

        $id = $this->ctrlModel->insert($data);
        Session::flash('success', 'Contrôle créé. Vous pouvez saisir les notes ci-dessous.');
        $this->redirect(BASE_URL . '/notes/saisie/' . $id);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CONTRÔLE — MODIFIER
    // ═══════════════════════════════════════════════════════════════════════════

    public function editControle(string $id): void
    {
        $this->requirePermission('notes.edit');

        $controle = $this->ctrlModel->findWithDetails((int)$id);
        if (!$controle) {
            Session::flash('error', 'Contrôle introuvable.');
            $this->redirect(BASE_URL . '/notes/controles');
            return;
        }

        $user = $this->currentUser();
        $this->render('notes/controle_form', [
            'title'    => 'Modifier le contrôle',
            'edit'     => true,
            'controle' => $controle,
            'classes'  => $this->getClassesForUser($user),
            'periodes' => $this->periodeModel->findAll('id'),
            'matieres' => $this->matiereModel->findAll('nom'),
            'types'    => ControleModel::TYPES,
            'errors'   => Session::getFlash('errors', []),
            'old'      => Session::getFlash('old', []),
        ]);
    }

    public function updateControle(string $id): void
    {
        $this->requirePermission('notes.edit');
        $this->verifyCsrf();

        $controle = $this->ctrlModel->findById((int)$id);
        if (!$controle) {
            $this->redirect(BASE_URL . '/notes/controles');
            return;
        }

        $data   = $this->collectControleData();
        $errors = $this->validateControle($data);

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/notes/controles/' . $id . '/edit');
            return;
        }

        $this->ctrlModel->update((int)$id, $data);
        $this->noteModel->recalculerDepuisControle((int)$id);

        EventDispatcher::dispatch(new ControleUpdated(
            controleId:   (int)$id,
            updatedById:  (int)$this->currentUser()['id'],
            classeId:     (int)$data['classe_id'],
            periodeId:    (int)$data['periode_id'],
            matiereId:    (int)$data['matiere_id'],
        ));

        Session::flash('success', 'Contrôle modifié et moyennes recalculées.');
        $this->redirect(BASE_URL . '/notes/saisie/' . $id);
    }

    public function deleteControle(string $id): void
    {
        $this->requirePermission('notes.delete');
        $this->verifyCsrf();

        $controle = $this->ctrlModel->findById((int)$id);
        if (!$controle) {
            $this->redirect(BASE_URL . '/notes/controles');
            return;
        }

        $classeId  = (int)$controle->classe_id;
        $periodeId = (int)$controle->periode_id;

        $this->ctrlModel->delete((int)$id);
        $this->noteModel->recalculerClasse($classeId, $periodeId);

        Session::flash('success', 'Contrôle supprimé et moyennes recalculées.');
        $this->redirect(BASE_URL . '/notes/controles?classe_id=' . $classeId . '&periode_id=' . $periodeId);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SAISIE DES NOTES
    // ═══════════════════════════════════════════════════════════════════════════

    public function saisie(string $id): void
    {
        $this->requirePermission('notes.create');

        $controle = $this->ctrlModel->findWithDetails((int)$id);
        if (!$controle) {
            Session::flash('error', 'Contrôle introuvable.');
            $this->redirect(BASE_URL . '/notes/controles');
            return;
        }

        $eleves = $this->noteModel->findByControle((int)$id);

        $this->render('notes/saisie', [
            'title'    => 'Saisie — ' . $controle->libelle,
            'controle' => $controle,
            'eleves'   => $eleves,
            'types'    => ControleModel::TYPES,
            'success'  => Session::getFlash('success'),
        ]);
    }

    public function storeSaisie(string $id): void
    {
        $this->requirePermission('notes.create');
        $this->verifyCsrf();

        $controle = $this->ctrlModel->findById((int)$id);
        if (!$controle) {
            $this->redirect(BASE_URL . '/notes/controles');
            return;
        }

        $notesPost   = (array)$this->request->post('notes',   []);
        $absentsPost = (array)$this->request->post('absents', []);

        foreach ($notesPost as $eleveId => $noteVal) {
            $eleveId = (int)$eleveId;
            $absent  = isset($absentsPost[$eleveId]) ? 1 : 0;

            if ($absent) {
                $note = null;
            } elseif ($noteVal !== '' && $noteVal !== null) {
                $note = round(max(0, min((float)$controle->note_max, (float)$noteVal)), 2);
            } else {
                continue; // Cellule vide = non saisie, ne pas upsert
            }

            $this->noteModel->upsert($eleveId, (int)$id, $note, $absent);
        }

        // Sauvegarder les absents même si note vide
        foreach ($absentsPost as $eleveId => $v) {
            $eleveId = (int)$eleveId;
            if (!isset($notesPost[$eleveId])) {
                $this->noteModel->upsert($eleveId, (int)$id, null, 1);
            }
        }

        $this->noteModel->recalculerDepuisControle((int)$id);

        $nbNotes = count(array_filter($notesPost, fn($v) => $v !== '' && $v !== null));
        EventDispatcher::dispatch(new NoteAjoutee(
            controleId:  (int)$id,
            saisieParId: (int)$this->currentUser()['id'],
            classeId:    (int)$controle->classe_id,
            periodeId:   (int)$controle->periode_id,
            matiereId:   (int)$controle->matiere_id,
            nbNotes:     $nbNotes,
        ));

        Session::flash('success', 'Notes enregistrées et moyennes recalculées avec succès.');
        $this->redirect(BASE_URL . '/notes/saisie/' . $id);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TABLEAU DES MOYENNES
    // ═══════════════════════════════════════════════════════════════════════════

    public function moyennes(): void
    {
        $this->requirePermission('notes.view');

        $classeId  = (int)$this->request->get('classe_id',  0);
        $periodeId = (int)$this->request->get('periode_id', 0);

        $notes    = [];
        $matieres = [];
        $classe   = $classeId  ? $this->classeModel->findById($classeId)   : null;
        $periode  = $periodeId ? $this->periodeModel->findById($periodeId) : null;

        if ($classeId && $periodeId) {
            $notes    = $this->noteModel->getNotesForClasse($classeId, $periodeId);
            $matieres = $this->noteModel->getMatieresPourClasse($classeId, $periodeId);
        }

        $this->render('notes/moyennes', [
            'title'    => 'Tableau des moyennes',
            'notes'    => $notes,
            'matieres' => $matieres,
            'classe'   => $classe,
            'periode'  => $periode,
            'classes'  => $this->classeModel->findAll('niveau'),
            'periodes' => $this->periodeModel->findAll('id'),
            'filters'  => compact('classeId', 'periodeId'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PRIVÉ
    // ═══════════════════════════════════════════════════════════════════════════

    private function collectControleData(): array
    {
        return [
            'libelle'       => trim($this->request->post('libelle', '')),
            'type'          => $this->request->post('type', 'controle'),
            'coefficient'   => (float)$this->request->post('coefficient', 1),
            'note_max'      => (float)$this->request->post('note_max', 20),
            'matiere_id'    => (int)$this->request->post('matiere_id', 0),
            'classe_id'     => (int)$this->request->post('classe_id', 0),
            'periode_id'    => (int)$this->request->post('periode_id', 0),
            'date_controle' => $this->request->post('date_controle') ?: null,
        ];
    }

    private function validateControle(array $d): array
    {
        $errors = [];
        if (empty($d['libelle']))    $errors['libelle'][]    = 'Le libellé est obligatoire.';
        if ($d['matiere_id'] <= 0)   $errors['matiere_id'][] = 'La matière est obligatoire.';
        if ($d['classe_id'] <= 0)    $errors['classe_id'][]  = 'La classe est obligatoire.';
        if ($d['periode_id'] <= 0)   $errors['periode_id'][] = 'La période est obligatoire.';
        if ($d['coefficient'] <= 0)  $errors['coefficient'][]= 'Le coefficient doit être > 0.';
        if ($d['note_max'] <= 0)     $errors['note_max'][]   = 'La note maximum doit être > 0.';
        return $errors;
    }

    private function getClassesForUser(array $user): array
    {
        if ($user['role'] === 'enseignant') {
            $prof = (new ProfesseurModel())->findByUserId((int)$user['id']);
            if (!$prof) return [];
            $ens = (new EnseignementModel())->findByProfesseurId((int)$prof->id);
            $ids = array_unique(array_column($ens, 'classe_id'));
            return array_values(array_filter(
                array_map(fn($cid) => $this->classeModel->findById((int)$cid) ?: null, $ids)
            ));
        }
        return $this->classeModel->findAll('niveau');
    }
}
