<?php

namespace App\Controllers;

use Core\Platform\PlatformAuth;
use Core\Platform\PlatformController;
use Core\Platform\PlatformEtablissementService;
use Core\Session;
use Core\Tenant\DomainVerificationService;

/**
 * Administration des domaines personnalisés — Administration de la
 * plateforme (T026). Anciennement `DomainController` (self-service
 * établissement) ; déplacé ici car réservé aux opérateurs plateforme
 * (admin/super_admin), plus aux administrateurs d'établissement — voir
 * SAAS_CONFIGURATION_METIER_REPORT.md. L'établissement cible est TOUJOURS
 * l'identifiant fourni dans l'URL (un opérateur plateforme n'est rattaché
 * à aucun établissement), jamais une session établissement.
 */
class PlatformDomainController extends PlatformController
{
    private DomainVerificationService $domains;
    private PlatformEtablissementService $etabs;

    public function __construct()
    {
        parent::__construct();
        $this->domains = DomainVerificationService::make();
        $this->etabs   = PlatformEtablissementService::make();
    }

    public function index(int $id): void
    {
        $this->requirePlatformAuth();

        $etab = $this->etabs->find($id);
        if ($etab === null) {
            http_response_code(404);
            $this->render('errors/404', [], 'none');
            return;
        }

        $this->render('platform/etablissements/domaines', [
            'title'   => 'Domaines — ' . $etab['nom'],
            'etab'    => $etab,
            'domains' => $this->domains->listForEtablissement($id),
            'canEdit' => PlatformAuth::hasLevel('admin', 'super_admin'),
            'errors'  => Session::getFlash('errors', []),
        ], 'platform');
    }

    public function store(int $id): void
    {
        $this->requirePlatformLevel('admin', 'super_admin');
        $this->verifyCsrf();

        $domain = trim((string)$this->request->post('domain', ''));

        try {
            $result = $this->domains->addDomain($id, $domain, 'custom');
            \Core\Logger::security('DOMAIN_ADDED', "etablissement_id={$id} : domaine {$result['domain']} ajouté par opérateur plateforme");
            Session::flash('success', 'Domaine ajouté. Configurez l\'enregistrement DNS TXT indiqué puis cliquez sur "Vérifier".');
        } catch (\InvalidArgumentException $e) {
            Session::flash('errors', [$e->getMessage()]);
        }

        $this->redirect(BASE_URL . '/platform/etablissements/' . $id . '/domaines');
    }

    public function verify(int $id, int $domainId): void
    {
        $this->requirePlatformLevel('admin', 'super_admin');
        $this->verifyCsrf();

        $ok = $this->domains->verify($id, $domainId);

        if ($ok) {
            \Core\Logger::security('DOMAIN_VERIFIED', "etablissement_id={$id}, domain_id={$domainId}");
            Session::flash('success', 'Domaine vérifié avec succès.');
        } else {
            Session::flash('errors', ['Vérification échouée : l\'enregistrement DNS TXT attendu est introuvable.']);
        }

        $this->redirect(BASE_URL . '/platform/etablissements/' . $id . '/domaines');
    }

    public function toggle(int $id, int $domainId): void
    {
        $this->requirePlatformLevel('admin', 'super_admin');
        $this->verifyCsrf();

        $active = $this->request->post('active') === '1';

        if ($this->domains->toggleActive($id, $domainId, $active)) {
            \Core\Logger::security('DOMAIN_TOGGLED', "etablissement_id={$id}, domain_id={$domainId}, active=" . ($active ? '1' : '0'));
            Session::flash('success', $active ? 'Domaine réactivé.' : 'Domaine désactivé.');
        } else {
            Session::flash('errors', ['Domaine introuvable.']);
        }

        $this->redirect(BASE_URL . '/platform/etablissements/' . $id . '/domaines');
    }

    public function destroy(int $id, int $domainId): void
    {
        $this->requirePlatformLevel('admin', 'super_admin');
        $this->verifyCsrf();

        if ($this->domains->delete($id, $domainId)) {
            \Core\Logger::security('DOMAIN_DELETED', "etablissement_id={$id}, domain_id={$domainId}");
            Session::flash('success', 'Domaine supprimé.');
        } else {
            Session::flash('errors', ['Domaine introuvable.']);
        }

        $this->redirect(BASE_URL . '/platform/etablissements/' . $id . '/domaines');
    }
}
