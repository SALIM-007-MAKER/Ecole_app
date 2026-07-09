<?php
declare(strict_types=1);

namespace App\Modules\Api\Webhooks;

use App\Modules\Api\Repositories\WebhookRepository;
use Core\EventDispatcher;

/**
 * Écoute tous les événements de l'application et enqueue des deliveries
 * pour les subscriptions webhook qui correspondent à l'événement.
 */
final class WebhookDispatcher
{
    private static bool $registered = false;

    /** Appel unique depuis bootstrap (module Api enabled). */
    public static function register(): void
    {
        if (self::$registered) return;
        self::$registered = true;

        // Wildcard — intercepte tout événement dispatchié
        EventDispatcher::listen('*', [self::class, 'onEvent']);
    }

    /** Handler générique appelé pour chaque événement. */
    public static function onEvent(string $eventName, object $event): void
    {
        try {
            $repo   = new WebhookRepository();
            $etabId = self::extractEtabId($event);
            $subs   = $repo->findActiveForEvent($eventName, $etabId);
            if (empty($subs)) return;

            $payload = self::buildPayload($eventName, $event);
            $body    = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            foreach ($subs as $sub) {
                // Sign with secret_hash (stored hash) — verified by subscriber using the raw secret
                $sig = WebhookSigner::sign($body, $sub['secret_hash']);
                $repo->createDelivery((int)$sub['id'], $eventName, ['body' => $body, 'signature' => $sig]);
            }
        } catch (\Throwable) {
            // Webhook dispatch never blocks the main flow
        }
    }

    private static function buildPayload(string $eventName, object $event): array
    {
        return [
            'id'         => bin2hex(random_bytes(8)),
            'event'      => $eventName,
            'created_at' => date('c'),
            'data'       => method_exists($event, 'toArray') ? $event->toArray() : (array)$event,
        ];
    }

    /** Extrait l'etablissement_id de l'event (propriété commune à tous les events du projet). */
    private static function extractEtabId(object $event): ?int
    {
        foreach (['etablissementId', 'etabId', 'etablissement_id'] as $prop) {
            if (isset($event->$prop) && (int)$event->$prop > 0) {
                return (int)$event->$prop;
            }
        }
        // Fallback: toArray may expose it
        if (method_exists($event, 'toArray')) {
            $arr = $event->toArray();
            foreach (['etablissementId', 'etabId', 'etablissement_id'] as $key) {
                if (isset($arr[$key]) && (int)$arr[$key] > 0) {
                    return (int)$arr[$key];
                }
            }
        }
        return null;
    }
}
