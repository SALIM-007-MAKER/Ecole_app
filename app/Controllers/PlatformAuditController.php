<?php

namespace App\Controllers;

use Core\Platform\PlatformAuditService;
use Core\Platform\PlatformAuth;
use Core\Platform\PlatformController;
use Core\Platform\PlatformEtablissementService;

/**
 * Consultation du journal d'audit GLOBAL de la plateforme (recherche, filtres, pagination, export CSV).
 *
 * Lecture seule : aucune action de modification ni de suppression n'existe (la table est en ajout seul, protégée par déclencheurs).
 * Consultation : niveaux admin et super_admin. Export CSV : super_admin uniquement, et l'export est lui-même inscrit au journal.
 */
class PlatformAuditController extends PlatformController
{
    private const FILTRES = ['q', 'date_from', 'date_to', 'operator_id', 'action', 'etablissement_id', 'resultat'];

    private PlatformAuditService $journal;

    public function __construct()
    {
        parent::__construct();
        $this->journal = PlatformAuditService::make();
    }

    public function index(): void
    {
        $this->requirePlatformLevel('admin', 'super_admin');

        $filtres = $this->filtres();
        $page = max(1, (int)$this->request->get('page', 1));
        $resultat = $this->journal->search($filtres, $page);

        $this->render('platform/audit/index', [
            'title'          => 'Journal d\'audit',
            'filtres'        => $filtres,
            'resultat'       => $resultat,
            'actions'        => $this->journal->actions(),
            'operateurs'     => $this->journal->operateurs(),
            'etablissements' => PlatformEtablissementService::make()->search([], 500),
            'peutExporter'   => PlatformAuth::hasLevel('super_admin'),
        ], 'platform');
    }

    public function show(int $id): void
    {
        $this->requirePlatformLevel('admin', 'super_admin');

        $ligne = $this->journal->find($id);
        if ($ligne === null) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Entrée introuvable'], 'none');
            exit;
        }
        $this->render('platform/audit/show', ['title' => 'Entrée du journal #' . $id, 'ligne' => $ligne], 'platform');
    }

    public function export(): void
    {
        $this->requirePlatformLevel('super_admin');

        $filtres = $this->filtres();
        // L'export est lui-même une opération sensible : il est inscrit au journal (avec les filtres utilisés) avant d'être servi.
        $this->journal->record('audit.exported', ['entity_type' => 'platform_audit_logs', 'apres' => ['filtres' => $filtres, 'lignes_max' => PlatformAuditService::EXPORT_MAX]]);

        $nom = 'journal-audit-plateforme-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nom . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");   // BOM UTF-8 : accents corrects dans Excel
        fputcsv($out, ['id', 'date', 'type_acteur', 'operateur_id', 'acteur', 'niveau', 'etablissement_id', 'etablissement', 'action',
                       'entite', 'entite_id', 'resultat', 'ancienne_valeur', 'nouvelle_valeur', 'ip', 'user_agent'], ';');
        foreach ($this->journal->iterer($filtres) as $l) {
            fputcsv($out, array_map([self::class, 'neutraliserFormule'], [
                $l['id'], $l['occurred_at'], $l['actor_type'], $l['operator_id'], $l['actor_email'], $l['actor_niveau'],
                $l['etablissement_id'], $l['etablissement_nom'], $l['action'], $l['entity_type'], $l['entity_id'], $l['resultat'],
                $l['ancienne_valeur'], $l['nouvelle_valeur'], $l['ip'], $l['user_agent'],
            ]), ';');
        }
        fclose($out);
        exit;
    }

    /** Protège contre l'injection de formules dans un tableur : une cellule commençant par = + - @ tabulation ou retour chariot est préfixée. */
    public static function neutraliserFormule(mixed $valeur): string
    {
        $texte = (string)($valeur ?? '');
        return $texte !== '' && strpbrk($texte[0], "=+-@\t\r") !== false ? "'" . $texte : $texte;
    }

    /** @return array<string,string> */
    private function filtres(): array
    {
        $f = [];
        foreach (self::FILTRES as $cle) {
            $v = $this->request->get($cle, '');
            $f[$cle] = is_string($v) ? trim($v) : '';
        }
        return $f;
    }
}
