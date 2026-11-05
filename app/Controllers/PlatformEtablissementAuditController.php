<?php

namespace App\Controllers;

use App\Services\AuditService;
use Core\Platform\PlatformAuditService;
use Core\Platform\PlatformController;
use Core\Platform\PlatformEtablissementService;

/**
 * Consultation, par le Super-Admin, du journal d'audit D'UN établissement (table `audit_logs`) : lecture seule.
 *
 * - Réservé au niveau super_admin (les écoles ne voient que leur propre journal ; les autres niveaux d'opérateur n'ont pas accès aux données
 *   d'une école).
 * - L'établissement lu est celui de l'URL, vérifié existant ; toutes les lectures filtrent dessus (AuditService exige un identifiant explicite).
 *   Un événement d'un autre établissement répond 404, comme un identifiant inexistant.
 * - TRAÇABILITÉ DE LA LECTURE : chaque consultation (liste, détail, export) est inscrite au journal GLOBAL AVANT d'être servie, en mode strict :
 *   si la trace ne peut pas être écrite, rien n'est affiché. Les filtres utilisés sont conservés ; le contenu consulté ne l'est pas.
 */
class PlatformEtablissementAuditController extends PlatformController
{
    private const FILTRES = ['q', 'user_id', 'module', 'action', 'date_debut', 'date_fin', 'resultat'];

    private AuditService $audit;

    public function __construct()
    {
        parent::__construct();
        $this->audit = new AuditService();
    }

    public function index(int $id): void
    {
        $this->requirePlatformLevel('super_admin');
        $etab = $this->etablissement($id);

        if (!$this->audit->schemaPret()) {
            $this->render('platform/etablissements/audit-index', ['title' => 'Journal d\'audit — ' . $etab['nom'], 'etab' => $etab, 'indisponible' => true], 'platform');
            return;
        }

        $filtres = $this->filtres();
        $page = max(1, (int)$this->request->get('page', 1));
        $this->tracer('etablissement.audit.viewed', $id, ['filtres' => array_filter($filtres, static fn($v) => $v !== ''), 'page' => $page]);

        $this->render('platform/etablissements/audit-index', [
            'title'        => 'Journal d\'audit — ' . $etab['nom'],
            'etab'         => $etab,
            'indisponible' => false,
            'filtres'      => $filtres,
            'resultat'     => $this->audit->rechercher($id, $filtres, $page),
            'modules'      => $this->audit->modules($id),
            'actions'      => $this->audit->actions($id),
            'acteurs'      => $this->audit->acteurs($id),
        ], 'platform');
    }

    public function show(int $id, int $evenement): void
    {
        $this->requirePlatformLevel('super_admin');
        $etab = $this->etablissement($id);

        $ligne = $this->audit->schemaPret() ? $this->audit->trouver($id, $evenement) : null;
        if ($ligne === null) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Événement introuvable'], 'none');
            exit;
        }
        $this->tracer('etablissement.audit.event_viewed', $id, ['evenement_id' => $evenement]);
        $this->render('platform/etablissements/audit-show', ['title' => 'Événement d\'audit #' . $evenement, 'etab' => $etab, 'ligne' => $ligne], 'platform');
    }

    public function export(int $id): void
    {
        $this->requirePlatformLevel('super_admin');
        $etab = $this->etablissement($id);
        if (!$this->audit->schemaPret()) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Journal indisponible'], 'none');
            exit;
        }

        $filtres = $this->filtres();
        $this->tracer('etablissement.audit.exported', $id, ['filtres' => array_filter($filtres, static fn($v) => $v !== ''), 'lignes_max' => 50000]);

        $nom = 'journal-audit-' . preg_replace('/[^a-z0-9-]+/i', '-', (string)$etab['slug']) . '-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nom . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['id', 'date', 'utilisateur_id', 'utilisateur', 'role', 'module', 'action', 'entite', 'entite_id', 'resultat', 'ip'], ';');
        foreach ($this->audit->iterer($id, $filtres) as $l) {
            fputcsv($out, array_map([PlatformAuditController::class, 'neutraliserFormule'], [
                $l['id'], $l['created_at'], $l['user_id'], $l['acteur_libelle'], $l['actor_role'] ?? '', $l['module'], $l['action'],
                $l['entite'], $l['entite_id'], $l['resultat'], $l['ip'] ?? '',
            ]), ';');
        }
        fclose($out);
        exit;
    }

    /** Établissement de l'URL, ou 404. @return array<string,mixed> */
    private function etablissement(int $id): array
    {
        $etab = $id > 0 ? PlatformEtablissementService::make()->find($id) : null;
        if ($etab === null) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Établissement introuvable'], 'none');
            exit;
        }
        return $etab;
    }

    /** Inscrit la consultation au journal global, en mode strict : sans trace, pas de lecture. */
    private function tracer(string $action, int $etablissementId, array $details): void
    {
        try {
            PlatformAuditService::make()->record($action, [
                'etablissement_id' => $etablissementId,
                'entity_type'      => 'audit_logs',
                'apres'            => $details,
                'strict'           => true,
            ]);
        } catch (\Throwable $e) {
            \Core\Logger::security('PLATFORM_AUDIT_READ_TRACE_FAILED', "action={$action} etablissement={$etablissementId}");
            http_response_code(503);
            $this->render('errors/403', ['title' => 'Consultation impossible : la traçabilité est indisponible'], 'none');
            exit;
        }
    }

    /** @return array<string,string> */
    private function filtres(): array
    {
        return $this->filtresGet(self::FILTRES);
    }
}
