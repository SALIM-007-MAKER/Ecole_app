<?php
declare(strict_types=1);

namespace App\Modules\Api\Webhooks;

use App\Modules\Api\Repositories\WebhookRepository;

/**
 * Worker de livraison des webhooks.
 * Appelé en arrière-plan (CLI ou cron).
 */
final class WebhookWorker
{
    private const RETRY_DELAYS = [0, 60, 300, 1800, 7200]; // secondes

    public function __construct(private readonly WebhookRepository $repo) {}

    /** Traite jusqu'à $limit livraisons en attente. */
    public function process(int $limit = 50): array
    {
        $deliveries = $this->repo->getPendingDeliveries($limit);
        $stats      = ['processed' => 0, 'success' => 0, 'failed' => 0];

        foreach ($deliveries as $delivery) {
            $stats['processed']++;
            $ok = $this->deliver($delivery);
            $ok ? $stats['success']++ : $stats['failed']++;
        }

        return $stats;
    }

    private function deliver(array $delivery): bool
    {
        $sub = $this->repo->findSubscription($delivery['subscription_id']);
        if (!$sub || $sub['status'] !== 'active') {
            $this->repo->updateDelivery($delivery['id'], ['status' => 'cancelled']);
            return false;
        }

        $headers = [
            'Content-Type: application/json',
            'X-Webhook-Event: ' . $delivery['event_type'],
            'X-Webhook-Signature: ' . $delivery['signature'],
            'X-Webhook-Delivery: ' . $delivery['id'],
            'User-Agent: EcoleApp-Webhooks/1.0',
        ];

        $ch = curl_init($sub['target_url']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $delivery['payload'],
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $response   = curl_exec($ch);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        $success  = $httpCode >= 200 && $httpCode < 300;
        $attempts = $delivery['attempts'] + 1;

        if ($success) {
            $this->repo->updateDelivery($delivery['id'], [
                'status'        => 'delivered',
                'attempts'      => $attempts,
                'response_code' => $httpCode,
                'delivered_at'  => date('Y-m-d H:i:s'),
            ]);
            return true;
        }

        if ($attempts >= count(self::RETRY_DELAYS)) {
            $this->repo->updateDelivery($delivery['id'], [
                'status'        => 'failed',
                'attempts'      => $attempts,
                'response_code' => $httpCode,
                'last_error'    => $curlError ?: "HTTP $httpCode",
            ]);
            return false;
        }

        $nextDelay = self::RETRY_DELAYS[$attempts] ?? 7200;
        $this->repo->updateDelivery($delivery['id'], [
            'status'       => 'pending',
            'attempts'     => $attempts,
            'scheduled_at' => date('Y-m-d H:i:s', time() + $nextDelay),
            'last_error'   => $curlError ?: "HTTP $httpCode",
        ]);

        return false;
    }
}
