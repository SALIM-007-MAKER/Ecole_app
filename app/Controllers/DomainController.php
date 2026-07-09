<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use Core\Tenant\DomainVerificationService;

/**
 * Administration des domaines personnalisés par établissement (Phase 14.7).
 * L'établissement cible est TOUJOURS celui de l'utilisateur connecté
 * (session['etablissement_id']) — jamais un identifiant fourni par la
 * requête. Toute action sur un domaine (vérifier/activer/supprimer)
 * revérifie l'appartenance du domaine à CET établissement avant d'agir
 * (Core\Tenant\DomainVerificationService::findOwned).
 */
class DomainController extends Controller
{
    private DomainVerificationService $domains;

    public function __construct()
    {
        parent::__construct();
        $this->domains = DomainVerificationService::make();
    }

    private function currentEtablissementId(): int
    {
        $user = Session::getUser();
        $etabId = $user['etablissement_id'] ?? null;
        if ($etabId === null) {
            $config = require ROOT_PATH . '/config/tenant.php';
            return (int)($config['default_id'] ?? 1);
        }
        return (int)$etabId;
    }

    public function index(): void
    {
        $this->requirePermission('domains.view');

        $etabId = $this->currentEtablissementId();

        $this->render('settings/domains', [
            'title'   => 'Domaines personnalisés',
            'domains' => $this->domains->listForEtablissement($etabId),
            'canEdit' => $this->can('domains.manage'),
            'errors'  => Session::getFlash('errors', []),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('domains.manage');
        $this->verifyCsrf();

        $etabId = $this->currentEtablissementId();
        $domain = trim((string)$this->request->post('domain', ''));

        try {
            $result = $this->domains->addDomain($etabId, $domain, 'custom');
            \Core\Logger::security('DOMAIN_ADDED', "etablissement_id={$etabId} a ajouté le domaine {$result['domain']}");
            Session::flash('success', 'Domaine ajouté. Configurez l\'enregistrement DNS TXT indiqué puis cliquez sur "Vérifier".');
        } catch (\InvalidArgumentException $e) {
            Session::flash('errors', [$e->getMessage()]);
        }

        $this->redirect(BASE_URL . '/parametres/domaines');
    }

    public function verify(int $id): void
    {
        $this->requirePermission('domains.manage');
        $this->verifyCsrf();

        $etabId = $this->currentEtablissementId();
        $ok = $this->domains->verify($etabId, $id);

        if ($ok) {
            \Core\Logger::security('DOMAIN_VERIFIED', "etablissement_id={$etabId}, domain_id={$id}");
            Session::flash('success', 'Domaine vérifié avec succès.');
        } else {
            Session::flash('errors', ['Vérification échouée : l\'enregistrement DNS TXT attendu est introuvable (ou le domaine ne vous appartient pas).']);
        }

        $this->redirect(BASE_URL . '/parametres/domaines');
    }

    public function toggle(int $id): void
    {
        $this->requirePermission('domains.manage');
        $this->verifyCsrf();

        $etabId = $this->currentEtablissementId();
        $active = $this->request->post('active') === '1';

        if ($this->domains->toggleActive($etabId, $id, $active)) {
            \Core\Logger::security('DOMAIN_TOGGLED', "etablissement_id={$etabId}, domain_id={$id}, active=" . ($active ? '1' : '0'));
            Session::flash('success', $active ? 'Domaine réactivé.' : 'Domaine désactivé.');
        } else {
            Session::flash('errors', ['Domaine introuvable.']);
        }

        $this->redirect(BASE_URL . '/parametres/domaines');
    }

    public function destroy(int $id): void
    {
        $this->requirePermission('domains.manage');
        $this->verifyCsrf();

        $etabId = $this->currentEtablissementId();

        if ($this->domains->delete($etabId, $id)) {
            \Core\Logger::security('DOMAIN_DELETED', "etablissement_id={$etabId}, domain_id={$id}");
            Session::flash('success', 'Domaine supprimé.');
        } else {
            Session::flash('errors', ['Domaine introuvable.']);
        }

        $this->redirect(BASE_URL . '/parametres/domaines');
    }
}
