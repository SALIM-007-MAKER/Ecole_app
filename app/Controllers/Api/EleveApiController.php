<?php

namespace App\Controllers\Api;

use Core\Controller;
use App\Models\EleveModel;

class EleveApiController extends Controller
{
    private EleveModel $eleveModel;

    public function __construct()
    {
        $this->eleveModel = new EleveModel();
    }

    public function index(): void
    {
        $this->requireAuth();
        $classeId = $_GET['classe_id'] ?? null;
        $q        = $_GET['q'] ?? '';

        if ($classeId) {
            $eleves = $this->eleveModel->findByClasse((int)$classeId);
        } elseif ($q) {
            $eleves = $this->eleveModel->search($q, 20);
        } else {
            $eleves = $this->eleveModel->findAll('nom ASC', 100);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($eleves, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function show(int $id): void
    {
        $this->requireAuth();
        $eleve = $this->eleveModel->findById($id);
        header('Content-Type: application/json; charset=utf-8');
        if (!$eleve) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
        } else {
            echo json_encode($eleve, JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}
