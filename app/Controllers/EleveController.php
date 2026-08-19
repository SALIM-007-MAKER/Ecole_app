<?php

namespace App\Controllers;

use Core\Controller;
use Core\EventDispatcher;
use Core\Session;
use App\Events\EleveCreated;
use App\Events\ImportCsvCompleted;
use App\Models\EleveModel;
use App\Models\ClasseModel;
use App\Models\UserModel;
use App\Services\UploadService;

class EleveController extends Controller
{
    private EleveModel  $eleveModel;
    private ClasseModel $classeModel;
    private UserModel   $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->eleveModel  = new EleveModel();
        $this->classeModel = new ClasseModel();
        $this->userModel   = new UserModel();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // LISTE
    // ═══════════════════════════════════════════════════════════════════════════

    public function index(): void
    {
        $this->requirePermission('eleves.view');

        $filters = $this->collectFilters();
        $page    = max(1, (int)$this->request->get('page', 1));
        $perPage = 15;

        $pagination = $this->eleveModel->paginateFiltered($page, $perPage, $filters);
        $classes    = $this->classeModel->findForSelect();

        $this->render('eleves/index', [
            'title'      => 'Élèves',
            'pagination' => $pagination,
            'filters'    => $filters,
            'classes'    => $classes,
            'stats'      => [
                'total'  => $this->eleveModel->count('actif = 1'),
                'garcons'=> (int)($this->countSexe('M')),
                'filles' => (int)($this->countSexe('F')),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CRÉER
    // ═══════════════════════════════════════════════════════════════════════════

    public function create(): void
    {
        $this->requirePermission('eleves.create');

        $this->render('eleves/create', [
            'title'      => 'Nouvel élève',
            'classes'    => $this->classeModel->findForSelect(),
            'parents'    => $this->userModel->findAllWithRole('parent'),
            'matricule'  => $this->eleveModel->generateMatricule(),
            'errors'     => Session::getFlash('errors', []),
            'old'        => Session::getFlash('old', []),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('eleves.create');
        $this->verifyCsrf();

        $data   = $this->collectFormData();
        $errors = $this->validateEleve($data);

        // Vérifier l'unicité du matricule
        if (!empty($data['matricule']) && $this->eleveModel->matriculeExists($data['matricule'])) {
            $errors['matricule'][] = 'Ce matricule est déjà utilisé.';
        }

        // Photo
        $photoFile = $this->request->file('photo');
        if (!empty($photoFile['name'])) {
            $photoPath = $this->handlePhoto($photoFile, $errors);
            if ($photoPath) $data['photo'] = $photoPath;
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/eleves/create');
        }

        $id = $this->eleveModel->insert($this->prepareDbData($data));

        EventDispatcher::dispatch(new EleveCreated(
            eleveId:     $id,
            nom:         $data['nom'],
            prenom:      $data['prenom'],
            matricule:   $data['matricule'] ?? '',
            classeId:    (int)($data['classe_id'] ?? 0),
            createdById: (int)$this->currentUser()['id'],
        ));

        Session::flash('success', "L'élève {$data['nom']} {$data['prenom']} a été créé avec succès.");
        $this->redirect(BASE_URL . '/eleves/' . $id);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CONSULTER
    // ═══════════════════════════════════════════════════════════════════════════

    public function show(string $id): void
    {
        $this->requirePermission('eleves.view');

        $eleve = $this->eleveModel->findWithDetails((int)$id);
        if (!$eleve) {
            Session::flash('error', 'Élève introuvable.');
            $this->redirect(BASE_URL . '/eleves');
        }

        $this->render('eleves/show', [
            'title' => $eleve->prenom . ' ' . $eleve->nom,
            'eleve' => $eleve,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // MODIFIER
    // ═══════════════════════════════════════════════════════════════════════════

    public function edit(string $id): void
    {
        $this->requirePermission('eleves.edit');

        $eleve = $this->eleveModel->findById((int)$id);
        if (!$eleve) {
            Session::flash('error', 'Élève introuvable.');
            $this->redirect(BASE_URL . '/eleves');
        }

        $this->render('eleves/edit', [
            'title'   => 'Modifier — ' . $eleve->prenom . ' ' . $eleve->nom,
            'eleve'   => $eleve,
            'classes' => $this->classeModel->findForSelect(),
            'parents' => $this->userModel->findAllWithRole('parent'),
            'errors'  => Session::getFlash('errors', []),
            'old'     => Session::getFlash('old', []),
        ]);
    }

    public function update(string $id): void
    {
        $this->requirePermission('eleves.edit');
        $this->verifyCsrf();

        $eleve = $this->eleveModel->findById((int)$id);
        if (!$eleve) {
            Session::flash('error', 'Élève introuvable.');
            $this->redirect(BASE_URL . '/eleves');
        }

        $data   = $this->collectFormData();
        $errors = $this->validateEleve($data);

        // Vérifier l'unicité du matricule (sauf pour le même élève)
        if (!empty($data['matricule']) && $this->eleveModel->matriculeExists($data['matricule'], (int)$id)) {
            $errors['matricule'][] = 'Ce matricule est déjà utilisé par un autre élève.';
        }

        // Photo (mise à jour optionnelle)
        $photoFile = $this->request->file('photo');
        if (!empty($photoFile['name'])) {
            $photoPath = $this->handlePhoto($photoFile, $errors);
            if ($photoPath) {
                // Supprimer l'ancienne photo (chemin storage/ ou public/ legacy)
                if (!empty($eleve->photo)) {
                    (new UploadService())->delete($eleve->photo);
                    // Fallback legacy public/uploads/
                    $legacyPath = ROOT_PATH . '/public/' . $eleve->photo;
                    if (is_file($legacyPath)) {
                        @unlink($legacyPath);
                    }
                }
                $data['photo'] = $photoPath;
            }
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $data);
            $this->redirect(BASE_URL . '/eleves/' . $id . '/edit');
        }

        $this->eleveModel->update((int)$id, $this->prepareDbData($data));

        Session::flash('success', "L'élève {$data['nom']} {$data['prenom']} a été mis à jour.");
        $this->redirect(BASE_URL . '/eleves/' . $id);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // SUPPRIMER
    // ═══════════════════════════════════════════════════════════════════════════

    public function delete(string $id): void
    {
        $this->requirePermission('eleves.delete');
        $this->verifyCsrf();

        $eleve = $this->eleveModel->findById((int)$id);
        if (!$eleve) {
            Session::flash('error', 'Élève introuvable.');
            $this->redirect(BASE_URL . '/eleves');
        }

        // Supprimer la photo
        if (!empty($eleve->photo)) {
            $path = ROOT_PATH . '/public/' . $eleve->photo;
            if (file_exists($path)) @unlink($path);
        }

        $this->eleveModel->delete((int)$id);

        Session::flash('success', "L'élève {$eleve->nom} {$eleve->prenom} a été supprimé.");
        $this->redirect(BASE_URL . '/eleves');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // EXPORT PDF (impression navigateur)
    // ═══════════════════════════════════════════════════════════════════════════

    public function exportPdf(): void
    {
        $this->requirePermission('eleves.view');

        $filters = $this->collectFilters();
        $filters['actif'] = $filters['actif'] ?? '1';
        $eleves  = $this->eleveModel->findAllFiltered($filters);
        $classes = $this->classeModel->findForSelect();

        $this->render('eleves/export-pdf', [
            'title'   => 'Liste des élèves',
            'eleves'  => $eleves,
            'filters' => $filters,
            'classes' => $classes,
            'total'   => count($eleves),
        ], 'none');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // EXPORT EXCEL (CSV avec BOM UTF-8)
    // ═══════════════════════════════════════════════════════════════════════════

    public function exportExcel(): void
    {
        $this->requirePermission('eleves.view');

        $filters = $this->collectFilters();
        $eleves  = $this->eleveModel->findAllFiltered($filters);

        $filename = 'eleves_' . date('Y-m-d_H-i') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');

        $out = fopen('php://output', 'w');

        // BOM UTF-8 pour Excel
        fprintf($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'Matricule', 'Nom', 'Prénom', 'Sexe', 'Date de naissance',
            'Classe', 'Niveau', 'Téléphone', 'Email', 'Adresse', 'Statut',
        ], ';');

        foreach ($eleves as $e) {
            fputcsv($out, [
                $e->matricule,
                $e->nom,
                $e->prenom,
                $e->sexe === 'M' ? 'Masculin' : 'Féminin',
                $e->date_naissance ? date('d/m/Y', strtotime($e->date_naissance)) : '',
                $e->classe_nom   ?? '',
                $e->classe_niveau ?? '',
                $e->telephone    ?? '',
                $e->email        ?? '',
                $e->adresse      ?? '',
                $e->actif ? 'Actif' : 'Inactif',
            ], ';');
        }

        fclose($out);
        exit;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // IMPORT CSV
    // ═══════════════════════════════════════════════════════════════════════════

    public function showImport(): void
    {
        $this->requirePermission('eleves.create');

        // Téléchargement du modèle CSV
        if ($this->request->get('template') === '1') {
            $this->downloadCsvTemplate();
            return;
        }

        $this->render('eleves/import', [
            'title'   => 'Importer des élèves',
            'classes' => $this->classeModel->findAll(),
        ]);
    }

    public function import(): void
    {
        $this->requirePermission('eleves.create');
        $this->verifyCsrf();

        $file = $this->request->file('csv_file');

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Veuillez sélectionner un fichier CSV valide.');
            $this->redirect(BASE_URL . '/eleves/import');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'], true)) {
            Session::flash('error', 'Format accepté : .csv ou .txt');
            $this->redirect(BASE_URL . '/eleves/import');
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            Session::flash('error', 'Le fichier ne doit pas dépasser 5 Mo.');
            $this->redirect(BASE_URL . '/eleves/import');
        }

        // Parser le CSV
        $handle = fopen($file['tmp_name'], 'r');
        // Détecter et ignorer le BOM UTF-8
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            fseek($handle, 0);
        }

        // Détecter le délimiteur (;  ou ,)
        $firstLine = fgets($handle);
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        fseek($handle, ($bom === "\xEF\xBB\xBF") ? 3 : 0);

        // Lire les entêtes
        $headers = fgetcsv($handle, 0, $delimiter);
        if (!$headers) {
            fclose($handle);
            Session::flash('error', 'Fichier CSV vide ou mal formaté.');
            $this->redirect(BASE_URL . '/eleves/import');
        }

        $headers = array_map(fn($h) => strtolower(trim(str_replace([' ', 'é','è','ê'], ['_','e','e','e'], $h))), $headers);

        // Construire la map des classes (nom_classe → id)
        $classeMap = [];
        foreach ($this->classeModel->findAll() as $c) {
            $classeMap[$c->nom] = $c->id;
            $classeMap[$c->niveau . ' - ' . $c->nom] = $c->id;
        }

        // Parser les lignes
        $rows    = [];
        $lineNum = 2;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($row) < 2) { $lineNum++; continue; }
            $rows[$lineNum] = array_combine($headers, array_pad($row, count($headers), ''));
            $lineNum++;
        }
        fclose($handle);

        if (empty($rows)) {
            Session::flash('error', 'Aucune donnée à importer.');
            $this->redirect(BASE_URL . '/eleves/import');
        }

        $results = $this->eleveModel->importFromCsv($rows, $classeMap);

        // Dispatcher les EleveCreated individuels (COR-3) puis un résumé d'import
        $user       = $this->currentUser();
        $classeId   = (int)$this->request->post('classe_id', 0);
        foreach ($results['ids'] ?? [] as $newId => $rowData) {
            EventDispatcher::dispatch(new EleveCreated(
                eleveId:      (int)$newId,
                nom:          $rowData['nom']       ?? '',
                prenom:       $rowData['prenom']    ?? '',
                matricule:    $rowData['matricule'] ?? '',
                classeId:     (int)($rowData['classe_id'] ?? $classeId),
                createdById:  (int)$user['id'],
                fromCsvImport: true,
            ));
        }

        EventDispatcher::dispatch(new ImportCsvCompleted(
            importedById: (int)$user['id'],
            totalImported: $results['imported'],
            totalSkipped:  $results['skipped'],
            filename:      $file['name'] ?? 'import.csv',
            classeId:      $classeId,
            errors:        $results['errors'],
        ));

        Session::flash('import_results', $results);
        $this->redirect(BASE_URL . '/eleves/import');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ═══════════════════════════════════════════════════════════════════════════

    private function collectFormData(): array
    {
        return [
            'matricule'      => strtoupper(trim($this->request->post('matricule', ''))),
            'nom'            => trim($this->request->sanitize('nom')),
            'prenom'         => trim($this->request->sanitize('prenom')),
            'sexe'           => $this->request->post('sexe', ''),
            'date_naissance' => $this->request->post('date_naissance', ''),
            'adresse'        => trim($this->request->sanitize('adresse')),
            'telephone'      => trim($this->request->sanitize('telephone')),
            'email'          => trim($this->request->sanitize('email')),
            'classe_id'      => $this->request->post('classe_id') ?: null,
            'parent_id'      => $this->request->post('parent_id') ?: null,
            'actif'          => (int)(bool)$this->request->post('actif', 0),
        ];
    }

    private function prepareDbData(array $data): array
    {
        $db = [
            'matricule'      => $data['matricule'],
            'nom'            => $data['nom'],
            'prenom'         => $data['prenom'],
            'sexe'           => $data['sexe'],
            'date_naissance' => $data['date_naissance'],
            'adresse'        => $data['adresse'] ?: null,
            'telephone'      => $data['telephone'] ?: null,
            'email'          => $data['email'] ?: null,
            'classe_id'      => $data['classe_id'] ? (int)$data['classe_id'] : null,
            'parent_id'      => $data['parent_id'] ? (int)$data['parent_id'] : null,
            'actif'          => $data['actif'] ?? 1,
        ];
        if (isset($data['photo'])) {
            $db['photo'] = $data['photo'];
        }
        return $db;
    }

    private function validateEleve(array $data): array
    {
        $errors = $this->validate($data, [
            'matricule'      => 'required|min:3|max:20',
            'nom'            => 'required|min:2|max:100',
            'prenom'         => 'required|min:2|max:100',
            'sexe'           => 'required',
            'date_naissance' => 'required',
        ]);

        if (!in_array($data['sexe'], ['M', 'F'], true)) {
            $errors['sexe'][] = 'Sexe invalide.';
        }

        if (!empty($data['date_naissance']) && !strtotime($data['date_naissance'])) {
            $errors['date_naissance'][] = 'Date de naissance invalide.';
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Adresse email invalide.';
        }

        return $errors;
    }

    private function handlePhoto(array $file, array &$errors): ?string
    {
        try {
            $svc  = new UploadService();
            $path = $svc->upload($file, 'photo_eleve', 'eleve_');
            return $path; // ex: storage/uploads/eleves/eleve_abc123.jpg
        } catch (\RuntimeException $e) {
            $errors['photo'][] = $e->getMessage();
            return null;
        }
    }

    private function collectFilters(): array
    {
        return [
            'q'         => trim($this->request->get('q', '')),
            'classe_id' => $this->request->get('classe_id', ''),
            'sexe'      => $this->request->get('sexe', ''),
            'actif'     => $this->request->get('actif', '1'),
        ];
    }

    private function countSexe(string $sexe): int
    {
        $row = $this->eleveModel->queryOne(
            "SELECT COUNT(*) AS n FROM `eleves` WHERE sexe = ? AND actif = 1",
            [$sexe]
        );
        return $row ? (int)$row->n : 0;
    }

    private function downloadCsvTemplate(): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="modele_import_eleves.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, "\xEF\xBB\xBF");
        fputcsv($out, ['matricule', 'nom', 'prenom', 'sexe', 'date_naissance', 'classe', 'telephone', 'email', 'adresse'], ';');
        fputcsv($out, ['2024-0100', 'Dupont', 'Ahmed', 'M', '2007-06-15', 'Seconde', '0550000001', 'ahmed@edu.dz', '1 Rue Exemple, Alger'], ';');
        fputcsv($out, ['2024-0101', 'Kaci',   'Lina',  'F', '2007-09-20', 'Première', '0550000002', 'lina@edu.dz',  '2 Rue Exemple, Blida'], ';');
        fclose($out);
        exit;
    }
}
