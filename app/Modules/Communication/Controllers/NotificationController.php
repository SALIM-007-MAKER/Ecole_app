<?php

declare(strict_types=1);

namespace App\Modules\Communication\Controllers;

use App\Modules\Communication\Services\NotificationService;
use App\Modules\Communication\Services\PushService;
use Core\Controller;

class NotificationController extends Controller
{
    private NotificationService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new NotificationService();
    }

    public function index(): void
    {
        $this->requirePermission('communication.view');
        $page   = (int) ($_GET['page'] ?? 1);
        $result = $this->service->lister((int) $this->user['id'], $page);
        $unread = $this->service->compterNonLus((int) $this->user['id']);

        $this->render('Communication::notifications/index', [
            'notifications' => $result['data'],
            'total'         => $result['total'],
            'page'          => $result['page'],
            'per_page'      => $result['per_page'],
            'unread'        => $unread,
            'titre'         => 'Mes notifications',
        ]);
    }

    public function markRead(int $id): void
    {
        $this->requirePermission('communication.view');
        $this->verifyCsrf();
        $this->service->marquerLu($id, (int) $this->user['id']);
        $this->json(['success' => true]);
    }

    public function markAllRead(): void
    {
        $this->requirePermission('communication.view');
        $this->verifyCsrf();
        $count = $this->service->marquerTousLus((int) $this->user['id']);
        $this->json(['success' => true, 'count' => $count]);
    }

    public function unreadCount(): void
    {
        $this->requirePermission('communication.view');
        $count = $this->service->compterNonLus((int) $this->user['id']);
        $this->json(['unread' => $count]);
    }

    public function poll(): void
    {
        $this->requirePermission('communication.view');
        $count = $this->service->compterNonLus((int) $this->user['id']);
        $this->json(['unread' => $count]);
    }

    public function archive(int $id): void
    {
        $this->requirePermission('communication.view');
        $this->verifyCsrf();
        $this->service->supprimer($id, (int) $this->user['id']);
        $this->json(['success' => true]);
    }

    public function panel(): void
    {
        $this->requirePermission('communication.view');
        $count   = $this->service->compterNonLus((int) $this->user['id']);
        $recents = $this->service->lister((int) $this->user['id'], 1, 5)['data'] ?? [];
        $this->render('Communication::notifications/panel', [
            'unread'  => $count,
            'recents' => $recents,
        ]);
    }

    public function pushSubscribe(): void
    {
        $this->requirePermission('communication.view');
        $this->verifyCsrf();
        $endpoint = $_POST['endpoint'] ?? '';
        $p256dh   = $_POST['p256dh']   ?? '';
        $auth     = $_POST['auth']      ?? '';
        if (empty($endpoint)) {
            $this->json(['success' => false, 'error' => 'Endpoint manquant'], 422);
            return;
        }
        (new PushService())->subscribe((int) $this->user['id'], $endpoint, $p256dh, $auth);
        $this->json(['success' => true]);
    }

    public function pushUnsubscribe(): void
    {
        $this->requirePermission('communication.view');
        $this->verifyCsrf();
        $endpoint = $_POST['endpoint'] ?? '';
        (new PushService())->unsubscribe((int) $this->user['id'], $endpoint);
        $this->json(['success' => true]);
    }
}
