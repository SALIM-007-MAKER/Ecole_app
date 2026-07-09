<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use Core\Tenant\BrandingService;
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

    public function __construct()
    {
        parent::__construct();
        $this->branding = BrandingService::make();
        $this->storage  = new TenantStorageService();
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

    public function showBranding(): void
    {
        $this->requirePermission('branding.view');

        $etabId = $this->currentEtablissementId();

        $this->render('settings/branding', [
            'title'    => 'Branding de l\'établissement',
            'branding' => $this->branding->get($etabId),
            'canEdit'  => $this->can('branding.update'),
            'errors'   => Session::getFlash('errors', []),
        ]);
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

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', $fields);
            $this->redirect(BASE_URL . '/parametres/branding');
        }

        $this->branding->save($etabId, $fields);

        \Core\Logger::security('BRANDING_UPDATED', "Branding modifié pour etablissement_id={$etabId} par user_id=" . (Session::getUser()['id'] ?? '?'));

        Session::flash('success', 'Branding mis à jour avec succès.');
        $this->redirect(BASE_URL . '/parametres/branding');
    }
}
