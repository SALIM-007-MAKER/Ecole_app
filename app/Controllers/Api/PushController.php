<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use Core\Controller;
use Core\Session;
use Core\WebPush;
use Core\Logger;
use App\Models\PushSubscriptionModel;

/**
 * API Push Notifications
 * Routes :
 *   GET  /api/push/vapid-key      → clé publique VAPID
 *   POST /api/push/subscribe       → enregistrer un abonnement
 *   POST /api/push/unsubscribe     → supprimer un abonnement
 *   POST /api/push/send            → envoyer un ping (admin)
 */
class PushController extends Controller
{
    private PushSubscriptionModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new PushSubscriptionModel();
    }

    // ─── GET /api/push/vapid-key ──────────────────────────────────────────────
    public function vapidKey(): never
    {
        $this->apiJson(['publicKey' => $this->getVapidPublicKey()]);
    }

    // ─── POST /api/push/subscribe ─────────────────────────────────────────────
    public function subscribe(): never
    {
        $this->requireAuth();
        $this->verifyCsrfHeader();

        $body = $this->readJsonBody();
        $user = Session::getUser();

        if (empty($body['endpoint'])) {
            $this->apiJson(['error' => 'Endpoint manquant.'], 422);
        }

        $ok = $this->model->upsert((int)$user['id'], $body);

        if ($ok) {
            Logger::info("Push subscription enregistré pour user#{$user['id']}");
            $this->apiJson(['success' => true, 'message' => 'Abonnement enregistré.']);
        } else {
            $this->apiJson(['error' => 'Erreur lors de l\'enregistrement.'], 500);
        }
    }

    // ─── POST /api/push/unsubscribe ───────────────────────────────────────────
    public function unsubscribe(): never
    {
        $this->requireAuth();
        $this->verifyCsrfHeader();

        $body = $this->readJsonBody();
        $user = Session::getUser();

        if (empty($body['endpoint'])) {
            $this->apiJson(['error' => 'Endpoint manquant.'], 422);
        }

        $this->model->deleteByEndpoint($body['endpoint'], (int)$user['id']);

        Logger::info("Push subscription supprimé pour user#{$user['id']}");
        $this->apiJson(['success' => true]);
    }

    // ─── POST /api/push/send ──────────────────────────────────────────────────
    public function send(): never
    {
        $this->requireAuth();
        $this->requirePermission('users.view'); // Réservé aux admins
        $this->verifyCsrfHeader();

        $body  = $this->readJsonBody();
        $title = $this->safe($body['title'] ?? 'Ecole App');
        $msg   = $this->safe($body['message'] ?? '');
        $role  = $body['role'] ?? null; // null = tous les utilisateurs
        $url   = $body['url']  ?? BASE_URL . '/dashboard';

        // Stocker la notification en DB (pour que le SW puisse la récupérer)
        $this->storeNotification($title, $msg, $url, Session::getUser()['id']);

        // Récupérer les abonnements cibles
        $subscriptions = $role
            ? $this->model->getByRole($role)
            : $this->model->getAll();

        if (empty($subscriptions)) {
            $this->apiJson(['success' => true, 'sent' => 0, 'message' => 'Aucun abonné.']);
        }

        $privPem = $this->getVapidPrivateKey();
        $pubKey  = $this->getVapidPublicKey();

        $sent = 0;
        $failed = 0;
        $expired = [];

        foreach ($subscriptions as $sub) {
            try {
                $result = WebPush::sendPing(
                    endpoint:          $sub->endpoint,
                    vapidPublicKey:    $pubKey,
                    vapidPrivateKeyPem: $privPem,
                    ttl:               3600,
                    urgency:           $body['urgency'] ?? 'normal'
                );

                if ($result['success']) {
                    $sent++;
                } elseif (in_array($result['http_code'], [404, 410], true)) {
                    // Endpoint expiré → supprimer
                    $expired[] = $sub->endpoint;
                    $failed++;
                } else {
                    Logger::warning("Push failed ({$result['http_code']}) pour sub#{$sub->id}");
                    $failed++;
                }
            } catch (\Throwable $e) {
                Logger::error("Push exception pour sub#{$sub->id} : " . $e->getMessage());
                $failed++;
            }
        }

        // Nettoyer les endpoints expirés
        foreach ($expired as $ep) {
            $this->model->deleteInvalid($ep);
        }

        Logger::info("Push broadcast : {$sent} envoyés, {$failed} échoués, " . count($expired) . " expirés.");

        $this->apiJson([
            'success' => true,
            'sent'    => $sent,
            'failed'  => $failed,
            'expired' => count($expired),
        ]);
    }

    // ─── Helpers privés ───────────────────────────────────────────────────────

    private function getVapidPublicKey(): string
    {
        $key = $_ENV['VAPID_PUBLIC_KEY'] ?? '';
        if (!$key) {
            $this->apiJson(['error' => 'Clés VAPID non configurées.'], 503);
        }
        return $key;
    }

    private function getVapidPrivateKey(): string
    {
        $key = $_ENV['VAPID_PRIVATE_KEY'] ?? '';
        if (!$key) {
            throw new \RuntimeException('VAPID_PRIVATE_KEY non définie dans .env');
        }
        // Le .env stocke les sauts de ligne comme \n littéraux → les convertir
        return str_replace('\\n', "\n", $key);
    }

    private function readJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function verifyCsrfHeader(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Session::verifyCsrf($token)) {
            $this->apiJson(['error' => 'Token CSRF invalide.'], 403);
        }
    }

    private function storeNotification(string $title, string $body, string $url, int $fromUserId): void
    {
        try {
            $db   = \Core\Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO notifications (type, titre, message, url, user_id, created_at)
                VALUES ('push', :title, :body, :url, :uid, NOW())
            ");
            $stmt->execute([
                ':title' => $title,
                ':body'  => $body,
                ':url'   => $url,
                ':uid'   => $fromUserId,
            ]);
        } catch (\Throwable) {
            // La table notifications peut ne pas exister — continuer quand même
        }
    }

    private function apiJson(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
