<?php

declare(strict_types=1);

namespace App\Modules\Communication\Controllers;

use App\Modules\Communication\DTO\PreferenceDTO;
use App\Modules\Communication\Services\PreferenceService;
use App\Modules\Communication\Services\PushService;
use Core\Controller;

class PreferenceController extends Controller
{
    private PreferenceService $service;
    private PushService       $pushService;

    public function __construct()
    {
        parent::__construct();
        $this->service     = new PreferenceService();
        $this->pushService = new PushService();
    }

    public function show(): void
    {
        $this->requirePermission('communication.view');
        $prefs = $this->service->obtenirPourUser((int) $this->user['id']);

        $this->render('Communication::preferences/index', [
            'prefs' => $prefs,
            'titre' => 'Mes préférences de notification',
        ]);
    }

    public function update(): void
    {
        $this->requirePermission('communication.view');
        $this->verifyCsrf();

        $items = $_POST['preferences'] ?? [];
        foreach ((array) $items as $type => $canaux) {
            $dto = new PreferenceDTO(
                typeNotification: (string) $type,
                canalInternal:    (bool) ($canaux['internal'] ?? false),
                canalEmail:       (bool) ($canaux['email']    ?? false),
                canalSms:         (bool) ($canaux['sms']      ?? false),
                canalPush:        (bool) ($canaux['push']     ?? false),
            );
            $this->service->mettreAJour((int) $this->user['id'], $dto);
        }

        $this->json(['success' => true]);
    }

    public function reset(): void
    {
        $this->requirePermission('communication.view');
        $this->verifyCsrf();
        $this->service->reinitialiser((int) $this->user['id']);
        $this->json(['success' => true]);
    }

    public function registerPush(): void
    {
        $this->requirePermission('communication.view');
        $this->verifyCsrf();

        $token      = $_POST['token']       ?? '';
        $plateforme = $_POST['plateforme']  ?? 'web';

        if (empty($token)) {
            $this->json(['success' => false, 'error' => 'Token manquant'], 422);
            return;
        }

        $this->pushService->enregistrerToken((int) $this->user['id'], $token, $plateforme);
        $this->json(['success' => true]);
    }
}
