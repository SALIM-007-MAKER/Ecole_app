<?php

namespace App\Controllers\Api;

use Core\Controller;
use App\Models\FraisEleveModel;

class FraisEleveApiController extends Controller
{
    private FraisEleveModel $feModel;

    public function __construct()
    {
        $this->feModel = new FraisEleveModel();
    }

    public function index(): void
    {
        $this->requireAuth();

        $eleveId = isset($_GET['eleve_id']) ? (int)$_GET['eleve_id'] : 0;
        $annee   = $_GET['annee'] ?? '';

        if (!$eleveId) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([]);
            exit;
        }

        $items = $this->feModel->findForEleve($eleveId, $annee);

        $result = array_filter($items, fn($fe) => $fe->statut !== 'paye');
        $result = array_values($result);

        foreach ($result as $fe) {
            $fe->reste = (float)$fe->montant - (float)($fe->montant_paye ?? 0);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
