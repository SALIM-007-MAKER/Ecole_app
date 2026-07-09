<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\NoteModel;
use App\Models\PeriodeModel;
use App\Models\ClasseModel;
use App\Models\EleveModel;

class BulletinController extends Controller
{
    private NoteModel    $noteModel;
    private PeriodeModel $periodeModel;
    private ClasseModel  $classeModel;
    private EleveModel   $eleveModel;

    public function __construct()
    {
        parent::__construct();
        $this->noteModel    = new NoteModel();
        $this->periodeModel = new PeriodeModel();
        $this->classeModel  = new ClasseModel();
        $this->eleveModel   = new EleveModel();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SÉLECTION CLASSE / PÉRIODE
    // ═══════════════════════════════════════════════════════════════════════════

    public function index(): void
    {
        $this->requirePermission('bulletins.view');

        $this->render('bulletins/index', [
            'title'   => 'Bulletins de notes',
            'classes' => $this->classeModel->findAll('niveau'),
            'periodes'=> $this->periodeModel->findAll('id'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // RÉSULTATS D'UNE CLASSE
    // ═══════════════════════════════════════════════════════════════════════════

    public function classe(): void
    {
        $this->requirePermission('bulletins.view');

        $classeId  = (int)$this->request->get('classe_id',  0);
        $periodeId = (int)$this->request->get('periode_id', 0);

        if (!$classeId || !$periodeId) {
            $this->redirect(BASE_URL . '/bulletins');
            return;
        }

        $classe    = $this->classeModel->findById($classeId);
        $periode   = $this->periodeModel->findById($periodeId);
        $matieres  = $this->noteModel->getMatieresPourClasse($classeId, $periodeId);
        $notes     = $this->noteModel->getNotesForClasse($classeId, $periodeId);
        $classement = $this->noteModel->getClassementClasse($classeId, $periodeId);

        // Organiser les notes en 2D : [eleve_id][matiere_id] => moyenne
        $grille = [];
        $eleveInfos = [];
        foreach ($notes as $n) {
            $grille[$n->eleve_id][$n->matiere_id] = $n->moyenne;
            if (!isset($eleveInfos[$n->eleve_id])) {
                $eleveInfos[$n->eleve_id] = [
                    'nom'              => $n->nom,
                    'prenom'           => $n->prenom,
                    'moyenne_generale' => $n->moyenne_generale,
                    'rang'             => $n->rang,
                    'mention'          => $n->mention,
                ];
            }
        }

        $this->render('bulletins/classe', [
            'title'      => 'Résultats — ' . ($classe?->niveau . ' ' . $classe?->nom ?? '') . ' — ' . ($periode?->nom ?? ''),
            'classe'     => $classe,
            'periode'    => $periode,
            'matieres'   => $matieres,
            'grille'     => $grille,
            'eleveInfos' => $eleveInfos,
            'classement' => $classement,
            'classes'    => $this->classeModel->findAll('niveau'),
            'periodes'   => $this->periodeModel->findAll('id'),
            'filters'    => compact('classeId', 'periodeId'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CLASSEMENT
    // ═══════════════════════════════════════════════════════════════════════════

    public function classement(): void
    {
        $this->requirePermission('bulletins.view');

        $classeId  = (int)$this->request->get('classe_id',  0);
        $periodeId = (int)$this->request->get('periode_id', 0);

        if (!$classeId || !$periodeId) {
            $this->redirect(BASE_URL . '/bulletins');
            return;
        }

        $classe     = $this->classeModel->findById($classeId);
        $periode    = $this->periodeModel->findById($periodeId);
        $classement = $this->noteModel->getClassementClasse($classeId, $periodeId);

        $this->render('bulletins/classement', [
            'title'      => 'Classement — ' . ($classe?->niveau . ' ' . $classe?->nom ?? ''),
            'classe'     => $classe,
            'periode'    => $periode,
            'classement' => $classement,
            'classes'    => $this->classeModel->findAll('niveau'),
            'periodes'   => $this->periodeModel->findAll('id'),
            'filters'    => compact('classeId', 'periodeId'),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // BULLETIN INDIVIDUEL
    // ═══════════════════════════════════════════════════════════════════════════

    public function eleve(string $id): void
    {
        $this->requirePermission('bulletins.view');

        $classeId  = (int)$this->request->get('classe_id',  0);
        $periodeId = (int)$this->request->get('periode_id', 0);
        $eleveId   = (int)$id;

        $eleve = $this->eleveModel->findById($eleveId);
        if (!$eleve) {
            Session::flash('error', 'Élève introuvable.');
            $this->redirect(BASE_URL . '/bulletins');
            return;
        }

        if (!$classeId) $classeId = (int)$eleve->classe_id;
        $classe  = $this->classeModel->findById($classeId);
        $periode = $periodeId ? $this->periodeModel->findById($periodeId) : null;

        if (!$classe || !$periode) {
            Session::flash('error', 'Classe ou période introuvable.');
            $this->redirect(BASE_URL . '/bulletins');
            return;
        }

        $bulletin = $this->noteModel->getBulletinData($eleveId, $classeId, $periodeId);

        $this->render('bulletins/eleve', [
            'title'   => 'Bulletin — ' . $eleve->prenom . ' ' . $eleve->nom,
            'eleve'   => $eleve,
            'classe'  => $classe,
            'periode' => $periode,
            'bulletin'=> $bulletin,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // BULLETIN IMPRIMABLE (layout print, sans navbar)
    // ═══════════════════════════════════════════════════════════════════════════

    public function printBulletin(string $id): void
    {
        $this->requirePermission('bulletins.view');

        $classeId  = (int)$this->request->get('classe_id',  0);
        $periodeId = (int)$this->request->get('periode_id', 0);
        $eleveId   = (int)$id;

        $eleve = $this->eleveModel->findById($eleveId);
        if (!$eleve) {
            $this->redirect(BASE_URL . '/bulletins');
            return;
        }

        if (!$classeId) $classeId = (int)$eleve->classe_id;
        $classe  = $this->classeModel->findById($classeId);
        $periode = $periodeId ? $this->periodeModel->findById($periodeId) : null;

        if (!$classe || !$periode) {
            $this->redirect(BASE_URL . '/bulletins');
            return;
        }

        $bulletin = $this->noteModel->getBulletinData($eleveId, $classeId, $periodeId);

        $this->render('bulletins/print', [
            'title'   => 'Bulletin — ' . $eleve->prenom . ' ' . $eleve->nom,
            'eleve'   => $eleve,
            'classe'  => $classe,
            'periode' => $periode,
            'bulletin'=> $bulletin,
        ], 'print');
    }
}
