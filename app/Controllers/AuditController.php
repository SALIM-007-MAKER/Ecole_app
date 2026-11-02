<?php

namespace App\Controllers;

use App\Services\AuditService;
use Core\Controller;
use Core\Tenant\TenantContext;

/**
 * Journal d'audit DE L'ÉTABLISSEMENT (Paramètres → Journal d'audit) : consultation en lecture seule.
 *
 * - Réservé aux titulaires de `audit.view` (rôles admin et directeur).
 * - L'établissement lu est EXCLUSIVEMENT celui du contexte tenant validé (TenantContext) : aucun paramètre de la requête (URL, filtre, identifiant)
 *   ne peut le changer. Sans contexte, l'accès est refusé (jamais d'établissement par défaut).
 * - Un identifiant d'événement d'un autre établissement répond 404, comme un identifiant inexistant.
 * - Aucune action de modification ni de suppression n'existe.
 */
class AuditController extends Controller
{
    private const FILTRES = ['q', 'user_id', 'module', 'action', 'date_debut', 'date_fin', 'resultat'];

    private AuditService $audit;

    public function __construct()
    {
        parent::__construct();
        $this->audit = new AuditService();
    }

    public function index(): void
    {
        $this->requirePermission('audit.view');
        $etab = $this->etablissementValide();

        if (!$this->audit->schemaPret()) {
            $this->render('audit/index', ['title' => 'Journal d\'audit', 'indisponible' => true]);
            return;
        }

        $filtres = [];
        foreach (self::FILTRES as $cle) {
            $v = $this->request->get($cle, '');
            $filtres[$cle] = is_string($v) ? trim($v) : '';
        }
        $page = max(1, (int)$this->request->get('page', 1));

        $this->render('audit/index', [
            'title'        => 'Journal d\'audit',
            'indisponible' => false,
            'filtres'      => $filtres,
            'resultat'     => $this->audit->rechercher($etab, $filtres, $page),
            'modules'      => $this->audit->modules($etab),
            'actions'      => $this->audit->actions($etab),
            'acteurs'      => $this->audit->acteurs($etab),
        ]);
    }

    public function show(string $id): void
    {
        $this->requirePermission('audit.view');
        $etab = $this->etablissementValide();

        $ligne = $this->audit->schemaPret() ? $this->audit->trouver($etab, (int)$id) : null;
        if ($ligne === null) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Événement introuvable'], 'none');
            exit;
        }
        $this->render('audit/show', ['title' => 'Événement d\'audit #' . (int)$ligne['id'], 'ligne' => $ligne]);
    }

    /** Établissement du contexte tenant validé ; sinon refus. Jamais l'établissement par défaut, jamais une valeur de la requête. */
    private function etablissementValide(): int
    {
        $id = TenantContext::current();
        if ($id === null || $id <= 0) {
            \Core\Logger::security('AUDIT_NO_TENANT', 'Consultation du journal sans contexte d\'établissement');
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Accès refusé'], 'none');
            exit;
        }
        return $id;
    }
}
