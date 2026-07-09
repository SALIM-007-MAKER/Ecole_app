<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Controllers;

use App\Modules\Bibliotheque\Policies\BiblioPolicy;
use App\Modules\Bibliotheque\Services\BiblioAnalyticsService;
use Core\Controller;

class AnalyticsController extends Controller
{
    private BiblioAnalyticsService $service;
    private BiblioPolicy           $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new BiblioAnalyticsService();
        $this->policy  = new BiblioPolicy();
    }

    public function dashboard(): void
    {
        $this->requirePermission('biblio.analytics');
        $etablissementId = (int)($this->user['etablissement_id'] ?? 1);
        $data = $this->service->dashboard($etablissementId);

        $this->render('Bibliotheque::analytics/dashboard', [
            'stats'   => $data,
            'titre'   => 'Analytique Bibliothèque',
        ]);
    }

    public function export(): void
    {
        $this->requirePermission('biblio.analytics');
        $etablissementId = (int)($this->user['etablissement_id'] ?? 1);
        $format = $_GET['format'] ?? 'json';

        $content = $this->service->export($format, $etablissementId);

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="bibliotheque-analytics-' . date('Y-m-d') . '.json"');
        echo $content;
    }
}
