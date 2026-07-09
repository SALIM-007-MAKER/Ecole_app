<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Models\NotificationModel;
use App\Models\NotificationLogModel;
use App\Models\NotificationPreferenceModel;
use App\Services\NotificationService;

class NotificationController extends Controller
{
    private NotificationModel           $notifModel;
    private NotificationLogModel        $logModel;
    private NotificationPreferenceModel $prefModel;

    public function __construct()
    {
        parent::__construct();
        $this->notifModel = new NotificationModel();
        $this->logModel   = new NotificationLogModel();
        $this->prefModel  = new NotificationPreferenceModel();
    }

    // ─── Boîte de réception ──────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();

        $this->notifModel->markAllRead((int)$user['id']);
        $notifications = $this->notifModel->findForUser((int)$user['id'], 50);

        $this->render('notifications/index', [
            'title'         => 'Mes notifications',
            'notifications' => $notifications,
            'types'         => NotificationModel::TYPES,
            'triggers'      => NotificationService::TRIGGERS,
        ]);
    }

    public function markRead(string $id): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->notifModel->markRead((int)$id, (int)$this->currentUser()['id']);
        $this->redirect(BASE_URL . '/notifications');
    }

    public function markAllRead(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->notifModel->markAllRead((int)$this->currentUser()['id']);
        $this->redirect(BASE_URL . '/notifications');
    }

    // ─── Préférences utilisateur ─────────────────────────────────────────────

    public function preferences(): void
    {
        $this->requireAuth();
        $user  = $this->currentUser();
        $prefs = $this->prefModel->getAllForUser((int)$user['id']);

        $this->render('notifications/preferences', [
            'title'    => 'Préférences de notifications',
            'prefs'    => $prefs,
            'triggers' => NotificationService::TRIGGERS,
            'user'     => $user,
        ]);
    }

    public function savePreferences(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $user = $this->currentUser();

        $submitted = $this->request->post('prefs', []);
        $triggers  = NotificationPreferenceModel::TRIGGERS;

        $prefs = [];
        foreach ($triggers as $t) {
            $prefs[$t] = [
                'interne' => isset($submitted[$t]['interne']) ? 1 : 0,
                'email'   => isset($submitted[$t]['email'])   ? 1 : 0,
                'sms'     => isset($submitted[$t]['sms'])     ? 1 : 0,
            ];
            // Interne toujours activé
            $prefs[$t]['interne'] = 1;
        }

        $this->prefModel->saveForUser((int)$user['id'], $prefs);
        Session::flash('success', 'Préférences enregistrées.');
        $this->redirect(BASE_URL . '/notifications/preferences');
    }

    // ─── Historique admin ─────────────────────────────────────────────────────

    public function history(): void
    {
        $this->requirePermission('notifications.manage');

        $page    = max(1, (int)$this->request->get('page', 1));
        $filters = [
            'q'          => $this->request->get('q', ''),
            'canal'      => $this->request->get('canal', ''),
            'trigger'    => $this->request->get('trigger', ''),
            'statut'     => $this->request->get('statut', ''),
            'date_debut' => $this->request->get('date_debut', ''),
            'date_fin'   => $this->request->get('date_fin', ''),
        ];

        $result  = $this->logModel->paginateFiltered($page, 30, $filters);
        $stats   = $this->trySafe(fn() => $this->logModel->getStats(), []);

        $this->render('notifications/history', [
            'title'    => 'Historique des notifications',
            'result'   => $result,
            'stats'    => $stats,
            'filters'  => $filters,
            'triggers' => NotificationService::TRIGGERS,
        ]);
    }

    public function adminTest(): void
    {
        $this->requirePermission('notifications.manage');
        $this->verifyCsrf();

        $user    = $this->currentUser();
        $canal   = $this->request->post('canal', 'interne');
        $message = trim($this->request->post('message', 'Ceci est un test de notification.'));

        $svc = new NotificationService();
        $ok  = $svc->sendTest((int)$user['id'], $canal, $message);

        Session::flash($ok ? 'success' : 'error',
            $ok ? "Notification test envoyée via canal « {$canal} »."
                : "Échec de l'envoi test via canal « {$canal} ». Vérifiez la configuration."
        );
        $this->redirect(BASE_URL . '/admin/notifications');
    }

    public function adminPurge(): void
    {
        $this->requirePermission('notifications.manage');
        $this->verifyCsrf();

        $days = max(7, (int)$this->request->post('days', 90));
        $this->logModel->deleteOld($days);

        Session::flash('success', "Journal purgé (entrées de plus de {$days} jours supprimées).");
        $this->redirect(BASE_URL . '/admin/notifications');
    }

    private function trySafe(callable $fn, mixed $default = []): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return $default;
        }
    }
}
