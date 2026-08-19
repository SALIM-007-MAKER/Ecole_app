<?php

namespace App\Controllers;

use App\Modules\Finance\DTO\FraisTypeDTO;
use Core\Controller;
use Core\Session;
use Core\Tenant\BrandingService;
use Core\Tenant\SettingsService;
use Core\Tenant\TenantStorageService;

/**
 * Administration du branding par établissement (Phase 14.5).
 * L'établissement cible est TOUJOURS celui de l'utilisateur connecté
 * (session['etablissement_id']) — jamais un identifiant fourni par la
 * requête — pour qu'aucun admin ne puisse, même par erreur de formulaire,
 * modifier le branding d'un autre établissement.
 */
class SettingsController extends Controller
{
    private BrandingService $branding;
    private TenantStorageService $storage;
    private SettingsService $settings;

    public function __construct()
    {
        parent::__construct();
        $this->branding = BrandingService::make();
        $this->storage  = new TenantStorageService();
        $this->settings = SettingsService::make();
    }

    private function currentEtablissementId(): int
    {
        $user = Session::getUser();
        $etabId = $user['etablissement_id'] ?? null;
        if ($etabId === null) {
            // Repli — même établissement par défaut que le reste de
            // l'application tant que le multi-établissement réel n'est pas
            // activé (Phase 14.4+ / TenantMiddleware).
            $config = require ROOT_PATH . '/config/tenant.php';
            return (int)($config['default_id'] ?? 1);
        }
        return (int)$etabId;
    }

    /** Centre de configuration — vue d'ensemble des 12 catégories métier. */
    public function index(): void
    {
        $this->requireAuth();

        $user  = Session::getUser();
        $perms = $user['permissions'] ?? [];

        $categories = [
            ['id' => 'etablissement',    'label' => 'Établissement',            'icon' => 'school',              'url' => '/parametres/etablissement',   'perm' => 'branding.view',                 'desc' => 'Identité, adresse, contacts'],
            ['id' => 'annee-scolaire',   'label' => 'Année scolaire',           'icon' => 'calendar-range',      'url' => '/parametres/annee-scolaire',  'perm' => 'settings.general.view',         'desc' => 'Année scolaire active'],
            ['id' => 'academique',       'label' => 'Organisation académique',  'icon' => 'layers',              'url' => '/parametres/academique',      'perm' => 'settings.academique.view',      'desc' => 'Cycles et niveaux proposés'],
            ['id' => 'salles',           'label' => 'Salles',                   'icon' => 'door-open',           'url' => '/salles',                     'perm' => 'emploi_du_temps.view',          'desc' => 'Salles de classe, capacité, type'],
            ['id' => 'notation',         'label' => 'Système de notation',      'icon' => 'pencil-ruler',        'url' => '/parametres/notation',        'perm' => 'settings.notation.view',        'desc' => 'Barème, seuils de passage'],
            ['id' => 'finances',         'label' => 'Finances',                 'icon' => 'coins',               'url' => '/parametres/finances',        'perm' => 'settings.finances.view',        'desc' => 'Devise par défaut'],
            ['id' => 'documents',        'label' => 'Documents',                'icon' => 'file-text',           'url' => '/parametres/documents',       'perm' => 'settings.documents.view',       'desc' => 'Numérotation reçus/factures'],
            ['id' => 'notifications',    'label' => 'Notifications',            'icon' => 'bell',                'url' => '/parametres/notifications',   'perm' => 'settings.notifications.view',   'desc' => 'Canaux email/SMS'],
            ['id' => 'apparence',        'label' => 'Apparence',                'icon' => 'palette',             'url' => '/parametres/apparence',       'perm' => 'branding.view',                 'desc' => 'Logo, couleurs, thème'],
            ['id' => 'securite',         'label' => 'Sécurité',                 'icon' => 'shield',              'url' => '/parametres/securite',        'perm' => 'settings.securite.view',        'desc' => 'Politique de mot de passe'],
            ['id' => 'sauvegarde',       'label' => 'Sauvegarde & restauration','icon' => 'database-backup',     'url' => '/parametres/sauvegarde',      'perm' => 'settings.sauvegarde.view',      'desc' => 'Préférences de conservation'],
            ['id' => 'avance',           'label' => 'Paramètres avancés',       'icon' => 'sliders-horizontal',  'url' => '/parametres/avance',          'perm' => 'settings.avance.view',          'desc' => 'Préférences d\'affichage'],
        ];

        $categories = array_values(array_filter(
            $categories,
            fn(array $c) => in_array($c['perm'], $perms, true)
        ));

        $this->render('settings/index', [
            'title'      => 'Paramètres',
            'categories' => $categories,
        ]);
    }

    /** @deprecated Remplacé par les pages dédiées "Établissement" et "Apparence" (T026). Conservé en redirection pour ne pas casser un lien existant. */
    public function showBranding(): void
    {
        $this->redirect(BASE_URL . '/parametres/etablissement');
    }

    public function updateBranding(): void
    {
        $this->requirePermission('branding.update');
        $this->verifyCsrf();

        $etabId = $this->currentEtablissementId();
        $current = $this->branding->get($etabId);

        $errors = [];
        $primaryColor = trim($this->request->post('primary_color', $current->primaryColor));
        $secondaryColor = trim($this->request->post('secondary_color', $current->secondaryColor));

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor)) {
            $errors['primary_color'] = 'Couleur primaire invalide (format attendu : #7c3aed).';
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $secondaryColor)) {
            $errors['secondary_color'] = 'Couleur secondaire invalide (format attendu : #0ea5e9).';
        }

        $fields = [
            'primary_color'    => $primaryColor,
            'secondary_color'  => $secondaryColor,
            'app_name'         => trim($this->request->post('app_name', $current->appName)) ?: $current->appName,
            'welcome_message'  => trim($this->request->post('welcome_message', (string)$current->welcomeMessage)),
            'font_family'      => trim($this->request->post('font_family', $current->fontFamily)) ?: $current->fontFamily,
            'theme_mode'       => in_array($this->request->post('theme_mode', ''), ['light', 'dark', 'auto'], true)
                ? $this->request->post('theme_mode')
                : $current->themeMode,
            'contact_phone'    => trim($this->request->post('contact_phone', (string)$current->contactPhone)),
            'contact_email'    => trim($this->request->post('contact_email', (string)$current->contactEmail)),
            'contact_address'  => trim($this->request->post('contact_address', (string)$current->contactAddress)),
            'footer_text'      => trim($this->request->post('footer_text', (string)$current->footerText)),
            'show_breadcrumbs' => $this->request->post('show_breadcrumbs') !== null,
        ];

        // Uploads (logo / favicon / image de connexion) — chacun optionnel
        foreach ([
            'logo'         => ['field' => 'logo_url',        'old' => $current->logoUrl],
            'favicon'      => ['field' => 'favicon_url',      'old' => $current->faviconUrl],
            'login_image'  => ['field' => 'login_image_url',  'old' => $current->loginImageUrl],
        ] as $inputName => $meta) {
            $file = $this->request->file($inputName);
            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                try {
                    $url = $this->storage->put($etabId, $inputName, $file);
                    $this->storage->delete($etabId, $meta['old']);
                    $fields[$meta['field']] = $url;
                } catch (\RuntimeException $e) {
                    $errors[$inputName] = $e->getMessage();
                }
            }
        }

        // Les formulaires "Établissement" et "Apparence" postent tous deux ici
        // (chacun avec un sous-ensemble de champs — les champs absents gardent
        // leur valeur actuelle, voir $current->x en repli ci-dessus) ; on
        // revient sur la page d'origine plutôt que toujours "Apparence".
        $returnTo = in_array($this->request->post('_retour', ''), ['etablissement', 'apparence'], true)
            ? $this->request->post('_retour')
            : 'branding';
        $returnUrl = BASE_URL . '/parametres/' . ($returnTo === 'branding' ? 'branding' : $returnTo);

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $fields);
            $this->redirect($returnUrl);
        }

        $this->branding->save($etabId, $fields);

        \Core\Logger::security('BRANDING_UPDATED', "Branding modifié pour etablissement_id={$etabId} par user_id=" . (Session::getUser()['id'] ?? '?'));

        Session::flash('success', 'Mis à jour avec succès.');
        $this->redirect($returnUrl);
    }

    // ─── Établissement (identité, adresse, contacts) — sous-ensemble de branding ──

    public function showEtablissement(): void
    {
        $this->requirePermission('branding.view');

        $etabId = $this->currentEtablissementId();

        $this->render('settings/etablissement', [
            'title'    => 'Établissement',
            'branding' => $this->branding->get($etabId),
            'canEdit'  => $this->can('branding.update'),
            'errors'   => Session::getFlash('errors', []),
        ]);
    }

    // ─── Apparence (logo, couleurs, thème) — sous-ensemble de branding ──────────

    public function showApparence(): void
    {
        $this->requirePermission('branding.view');

        $etabId = $this->currentEtablissementId();

        $this->render('settings/apparence', [
            'title'    => 'Apparence',
            'branding' => $this->branding->get($etabId),
            'canEdit'  => $this->can('branding.update'),
            'errors'   => Session::getFlash('errors', []),
        ]);
    }

    // ─── Année scolaire active ────────────────────────────────────────────────

    public function showAnneeScolaire(): void
    {
        $this->requirePermission('settings.general.view');

        $etabId = $this->currentEtablissementId();

        $this->render('settings/annee_scolaire', [
            'title'         => 'Année scolaire',
            'anneeScolaire' => $this->settings->get($etabId, 'academique', 'annee_scolaire_active', $this->anneeScolaireParDefaut()),
            'canEdit'       => $this->can('settings.general.update'),
            'errors'        => Session::getFlash('errors', []),
        ]);
    }

    public function updateAnneeScolaire(): void
    {
        $this->requirePermission('settings.general.update');
        $this->verifyCsrf();

        $etabId = $this->currentEtablissementId();
        $userId = (int)(Session::getUser()['id'] ?? 0);

        $anneeScolaire = trim($this->request->post('annee_scolaire_active', ''));

        $errors = [];
        if (!preg_match('/^\d{4}-\d{4}$/', $anneeScolaire)) {
            $errors['annee_scolaire_active'] = 'Format invalide. Attendu : YYYY-YYYY (ex: 2025-2026).';
        } else {
            [$y1, $y2] = explode('-', $anneeScolaire);
            if ((int)$y2 !== (int)$y1 + 1) {
                $errors['annee_scolaire_active'] = "L'année de fin doit être l'année de début + 1.";
            }
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            $this->redirect(BASE_URL . '/parametres/annee-scolaire');
        }

        $this->settings->set($etabId, 'academique', 'annee_scolaire_active', $anneeScolaire, 'string', $userId);

        \Core\Logger::security('SETTINGS_ANNEE_SCOLAIRE_UPDATED', "Année scolaire active modifiée pour etablissement_id={$etabId} par user_id={$userId}");

        Session::flash('success', 'Année scolaire active mise à jour avec succès.');
        $this->redirect(BASE_URL . '/parametres/annee-scolaire');
    }

    /** Repli si aucune année scolaire active n'a encore été configurée — même calcul que l'ancien comportement ad hoc éparpillé dans ~27 fichiers. */
    private function anneeScolaireParDefaut(): string
    {
        $mois = (int)date('n');
        $an   = (int)date('Y');
        return $mois >= 8 ? "{$an}-" . ($an + 1) : ($an - 1) . "-{$an}";
    }

    // ─── Organisation académique (cycles proposés par l'établissement) ───────

    public function showAcademique(): void
    {
        $this->requirePermission('settings.academique.view');

        $etabId = $this->currentEtablissementId();
        $cyclesActifs = $this->settings->get($etabId, 'academique', 'cycles_actifs', array_keys(\App\Models\ClasseModel::NIVEAUX));

        $this->render('settings/academique', [
            'title'        => 'Organisation académique',
            'cyclesTous'   => \App\Models\ClasseModel::NIVEAUX,
            'cyclesActifs' => $cyclesActifs,
            'canEdit'      => $this->can('settings.academique.update'),
            'errors'       => Session::getFlash('errors', []),
        ]);
    }

    public function updateAcademique(): void
    {
        $this->requirePermission('settings.academique.update');
        $this->verifyCsrf();

        $etabId = $this->currentEtablissementId();
        $userId = (int)(Session::getUser()['id'] ?? 0);

        $cyclesPostes = (array)$this->request->post('cycles', []);
        $cyclesValides = array_values(array_intersect($cyclesPostes, array_keys(\App\Models\ClasseModel::NIVEAUX)));

        if (empty($cyclesValides)) {
            Session::flash('errors', ['cycles' => 'Sélectionnez au moins un cycle.']);
            $this->redirect(BASE_URL . '/parametres/academique');
        }

        $this->settings->set($etabId, 'academique', 'cycles_actifs', $cyclesValides, 'json', $userId);

        \Core\Logger::security('SETTINGS_ACADEMIQUE_UPDATED', "Organisation académique modifiée pour etablissement_id={$etabId} par user_id={$userId}");

        Session::flash('success', 'Organisation académique mise à jour avec succès.');
        $this->redirect(BASE_URL . '/parametres/academique');
    }

    // ─── Système de notation ──────────────────────────────────────────────────

    public function showNotation(): void
    {
        $this->requirePermission('settings.notation.view');

        $etabId = $this->currentEtablissementId();

        $this->render('settings/notation', [
            'title'           => 'Système de notation',
            'baremeMax'       => (int)$this->settings->get($etabId, 'academique', 'bareme_max', 20),
            'notePassage'     => $this->settings->get($etabId, 'academique', 'note_passage', '10'),
            'seuilRattrapage' => $this->settings->get($etabId, 'academique', 'seuil_rattrapage', '8'),
            'canEdit'         => $this->can('settings.notation.update'),
            'errors'          => Session::getFlash('errors', []),
        ]);
    }

    public function updateNotation(): void
    {
        $this->requirePermission('settings.notation.update');
        $this->verifyCsrf();

        $etabId = $this->currentEtablissementId();
        $userId = (int)(Session::getUser()['id'] ?? 0);

        $baremeMax       = (int)$this->request->post('bareme_max', 20);
        $notePassage     = (float)str_replace(',', '.', (string)$this->request->post('note_passage', '10'));
        $seuilRattrapage = (float)str_replace(',', '.', (string)$this->request->post('seuil_rattrapage', '8'));

        $errors = [];
        if ($baremeMax < 5 || $baremeMax > 100) {
            $errors['bareme_max'] = 'Le barème doit être compris entre 5 et 100.';
        }
        if ($notePassage < 0 || $notePassage > $baremeMax) {
            $errors['note_passage'] = "Le seuil de passage doit être compris entre 0 et {$baremeMax}.";
        }
        if ($seuilRattrapage < 0 || $seuilRattrapage > $notePassage) {
            $errors['seuil_rattrapage'] = "Le seuil de rattrapage doit être compris entre 0 et le seuil de passage ({$notePassage}).";
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            $this->redirect(BASE_URL . '/parametres/notation');
        }

        $this->settings->set($etabId, 'academique', 'bareme_max', $baremeMax, 'integer', $userId);
        $this->settings->set($etabId, 'academique', 'note_passage', (string)$notePassage, 'string', $userId);
        $this->settings->set($etabId, 'academique', 'seuil_rattrapage', (string)$seuilRattrapage, 'string', $userId);

        \Core\Logger::security('SETTINGS_NOTATION_UPDATED', "Système de notation modifié pour etablissement_id={$etabId} par user_id={$userId}");

        Session::flash('success', 'Système de notation mis à jour avec succès.');
        $this->redirect(BASE_URL . '/parametres/notation');
    }

    // ─── Finances (devise) ─────────────────────────────────────────────────────

    public function showFinances(): void
    {
        $this->requirePermission('settings.finances.view');

        $etabId = $this->currentEtablissementId();

        $this->render('settings/finances', [
            'title'   => 'Finances',
            'devise'  => $this->settings->get($etabId, 'finance', 'devise_defaut', 'XOF'),
            'devises' => FraisTypeDTO::DEVISES,
            'canEdit' => $this->can('settings.finances.update'),
            'errors'  => Session::getFlash('errors', []),
        ]);
    }

    public function updateFinances(): void
    {
        $this->requirePermission('settings.finances.update');
        $this->verifyCsrf();

        $etabId = $this->currentEtablissementId();
        $userId = (int)(Session::getUser()['id'] ?? 0);

        $devise = trim($this->request->post('devise_defaut', 'XOF'));

        if (!array_key_exists($devise, FraisTypeDTO::DEVISES)) {
            Session::flash('errors', ['devise_defaut' => 'Devise non supportée.']);
            $this->redirect(BASE_URL . '/parametres/finances');
        }

        $this->settings->set($etabId, 'finance', 'devise_defaut', $devise, 'string', $userId);

        \Core\Logger::security('SETTINGS_FINANCES_UPDATED', "Configuration financière modifiée pour etablissement_id={$etabId} par user_id={$userId}");

        Session::flash('success', 'Configuration financière mise à jour avec succès.');
        $this->redirect(BASE_URL . '/parametres/finances');
    }

    // ─── Documents (numérotation) ──────────────────────────────────────────────

    public function showDocuments(): void
    {
        $this->requirePermission('settings.documents.view');

        $etabId = $this->currentEtablissementId();

        $this->render('settings/documents', [
            'title'          => 'Documents',
            'prefixeRecu'    => $this->settings->get($etabId, 'documents', 'prefixe_recu', 'REC'),
            'prefixeFacture' => $this->settings->get($etabId, 'documents', 'prefixe_facture', 'FCT'),
            'canEdit'        => $this->can('settings.documents.update'),
            'errors'         => Session::getFlash('errors', []),
        ]);
    }

    public function updateDocuments(): void
    {
        $this->requirePermission('settings.documents.update');
        $this->verifyCsrf();

        $etabId = $this->currentEtablissementId();
        $userId = (int)(Session::getUser()['id'] ?? 0);

        $prefixeRecu    = strtoupper(trim((string)$this->request->post('prefixe_recu', 'REC')));
        $prefixeFacture = strtoupper(trim((string)$this->request->post('prefixe_facture', 'FCT')));

        $errors = [];
        if (!preg_match('/^[A-Z0-9]{2,10}$/', $prefixeRecu)) {
            $errors['prefixe_recu'] = 'Le préfixe doit contenir entre 2 et 10 lettres/chiffres (majuscules).';
        }
        if (!preg_match('/^[A-Z0-9]{2,10}$/', $prefixeFacture)) {
            $errors['prefixe_facture'] = 'Le préfixe doit contenir entre 2 et 10 lettres/chiffres (majuscules).';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            $this->redirect(BASE_URL . '/parametres/documents');
        }

        $this->settings->set($etabId, 'documents', 'prefixe_recu', $prefixeRecu, 'string', $userId);
        $this->settings->set($etabId, 'documents', 'prefixe_facture', $prefixeFacture, 'string', $userId);

        \Core\Logger::security('SETTINGS_DOCUMENTS_UPDATED', "Configuration documents modifiée pour etablissement_id={$etabId} par user_id={$userId}");

        Session::flash('success', 'Configuration des documents mise à jour avec succès.');
        $this->redirect(BASE_URL . '/parametres/documents');
    }

    // ─── Notifications (activation par établissement) ─────────────────────────

    public function showNotifications(): void
    {
        $this->requirePermission('settings.notifications.view');

        $etabId = $this->currentEtablissementId();

        $this->render('settings/notifications', [
            'title'      => 'Notifications',
            'emailActif' => (bool)$this->settings->get($etabId, 'notifications', 'email_actif', true),
            'smsActif'   => (bool)$this->settings->get($etabId, 'notifications', 'sms_actif', false),
            'canEdit'    => $this->can('settings.notifications.update'),
            'errors'     => Session::getFlash('errors', []),
        ]);
    }

    public function updateNotifications(): void
    {
        $this->requirePermission('settings.notifications.update');
        $this->verifyCsrf();

        $etabId = $this->currentEtablissementId();
        $userId = (int)(Session::getUser()['id'] ?? 0);

        $this->settings->set($etabId, 'notifications', 'email_actif', $this->request->post('email_actif') !== null, 'boolean', $userId);
        $this->settings->set($etabId, 'notifications', 'sms_actif', $this->request->post('sms_actif') !== null, 'boolean', $userId);

        \Core\Logger::security('SETTINGS_NOTIFICATIONS_UPDATED', "Configuration notifications modifiée pour etablissement_id={$etabId} par user_id={$userId}");

        Session::flash('success', 'Configuration des notifications mise à jour avec succès.');
        $this->redirect(BASE_URL . '/parametres/notifications');
    }

    // ─── Sécurité ───────────────────────────────────────────────────────────────

    public function showSecurite(): void
    {
        $this->requirePermission('settings.securite.view');

        $etabId = $this->currentEtablissementId();

        $this->render('settings/securite', [
            'title'                 => 'Sécurité',
            'passwordMinLength'     => (int)$this->settings->get($etabId, 'securite', 'password_min_length', 8),
            'sessionLifetimeMinutes'=> (int)$this->settings->get($etabId, 'securite', 'session_lifetime_minutes', 120),
            'canEdit'               => $this->can('settings.securite.update'),
            'errors'                => Session::getFlash('errors', []),
        ]);
    }

    public function updateSecurite(): void
    {
        $this->requirePermission('settings.securite.update');
        $this->verifyCsrf();

        $etabId = $this->currentEtablissementId();
        $userId = (int)(Session::getUser()['id'] ?? 0);

        $passwordMinLength = (int)$this->request->post('password_min_length', 8);
        $sessionLifetime   = (int)$this->request->post('session_lifetime_minutes', 120);

        $errors = [];
        if ($passwordMinLength < 6 || $passwordMinLength > 32) {
            $errors['password_min_length'] = 'La longueur minimale doit être comprise entre 6 et 32 caractères.';
        }
        if ($sessionLifetime < 15 || $sessionLifetime > 1440) {
            $errors['session_lifetime_minutes'] = 'La durée de session doit être comprise entre 15 et 1440 minutes.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            $this->redirect(BASE_URL . '/parametres/securite');
        }

        $this->settings->set($etabId, 'securite', 'password_min_length', $passwordMinLength, 'integer', $userId);
        $this->settings->set($etabId, 'securite', 'session_lifetime_minutes', $sessionLifetime, 'integer', $userId);

        \Core\Logger::security('SETTINGS_SECURITE_UPDATED', "Paramètres de sécurité modifiés pour etablissement_id={$etabId} par user_id={$userId}");

        Session::flash('success', 'Paramètres de sécurité mis à jour avec succès. La longueur minimale du mot de passe s\'applique aux prochains changements de mot de passe.');
        $this->redirect(BASE_URL . '/parametres/securite');
    }

    // ─── Sauvegarde & restauration (préférences ; exécution gérée par la plateforme) ──

    public function showSauvegarde(): void
    {
        $this->requirePermission('settings.sauvegarde.view');

        $etabId = $this->currentEtablissementId();

        $this->render('settings/sauvegarde', [
            'title'           => 'Sauvegarde & restauration',
            'retentionJours'  => (int)$this->settings->get($etabId, 'sauvegarde', 'retention_jours', 30),
            'canEdit'         => $this->can('settings.sauvegarde.update'),
            'errors'          => Session::getFlash('errors', []),
        ]);
    }

    public function updateSauvegarde(): void
    {
        $this->requirePermission('settings.sauvegarde.update');
        $this->verifyCsrf();

        $etabId = $this->currentEtablissementId();
        $userId = (int)(Session::getUser()['id'] ?? 0);

        $retention = (int)$this->request->post('retention_jours', 30);

        if ($retention < 7 || $retention > 365) {
            Session::flash('errors', ['retention_jours' => 'La durée de conservation doit être comprise entre 7 et 365 jours.']);
            $this->redirect(BASE_URL . '/parametres/sauvegarde');
        }

        $this->settings->set($etabId, 'sauvegarde', 'retention_jours', $retention, 'integer', $userId);

        \Core\Logger::security('SETTINGS_SAUVEGARDE_UPDATED', "Préférences de sauvegarde modifiées pour etablissement_id={$etabId} par user_id={$userId}");

        Session::flash('success', 'Préférences de sauvegarde mises à jour avec succès.');
        $this->redirect(BASE_URL . '/parametres/sauvegarde');
    }

    // ─── Paramètres avancés ─────────────────────────────────────────────────────

    public function showAvance(): void
    {
        $this->requirePermission('settings.avance.view');

        $etabId = $this->currentEtablissementId();

        $this->render('settings/avance', [
            'title'            => 'Paramètres avancés',
            'paginationParPage'=> (int)$this->settings->get($etabId, 'avance', 'pagination_par_page', 15),
            'canEdit'          => $this->can('settings.avance.update'),
            'errors'           => Session::getFlash('errors', []),
        ]);
    }

    public function updateAvance(): void
    {
        $this->requirePermission('settings.avance.update');
        $this->verifyCsrf();

        $etabId = $this->currentEtablissementId();
        $userId = (int)(Session::getUser()['id'] ?? 0);

        $pagination = (int)$this->request->post('pagination_par_page', 15);

        if (!in_array($pagination, [10, 15, 20, 30, 50, 100], true)) {
            Session::flash('errors', ['pagination_par_page' => 'Valeur non autorisée.']);
            $this->redirect(BASE_URL . '/parametres/avance');
        }

        $this->settings->set($etabId, 'avance', 'pagination_par_page', $pagination, 'integer', $userId);

        \Core\Logger::security('SETTINGS_AVANCE_UPDATED', "Paramètres avancés modifiés pour etablissement_id={$etabId} par user_id={$userId}");

        Session::flash('success', 'Paramètres avancés mis à jour avec succès.');
        $this->redirect(BASE_URL . '/parametres/avance');
    }
}
