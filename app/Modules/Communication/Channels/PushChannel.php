<?php

declare(strict_types=1);

namespace App\Modules\Communication\Channels;

class PushChannel implements ChannelInterface
{
    public function envoyer(array $job): bool
    {
        $token = $job['push_token'] ?? '';
        $titre = $job['sujet'] ?? '';
        $corps = $job['corps'] ?? '';

        if (empty($token)) {
            return false;
        }

        // Utilise VAPID configuré dans le module PWA si disponible
        $vapidPublic  = defined('VAPID_PUBLIC_KEY')  ? VAPID_PUBLIC_KEY  : null;
        $vapidPrivate = defined('VAPID_PRIVATE_KEY') ? VAPID_PRIVATE_KEY : null;

        if ($vapidPublic === null || $vapidPrivate === null) {
            // Stub : log uniquement
            $logDir  = ROOT_PATH . '/storage/logs';
            $logFile = $logDir . '/push_' . date('Y-m-d') . '.log';
            $line    = date('Y-m-d H:i:s') . " | TOKEN:{$token} | TITRE:{$titre} | MSG:{$corps}" . PHP_EOL;
            if (is_dir($logDir)) {
                file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
            }
            return true;
        }

        // Payload Web Push
        $payload = json_encode([
            'title' => substr($titre, 0, 100),
            'body'  => substr($corps, 0, 200),
            'icon'  => '/icons/icon-192x192.png',
            'data'  => ['url' => $job['url_action'] ?? '/'],
        ]);

        // Envoi via Web Push API (curl vers l'endpoint du token)
        // Le token Web Push est une subscription JSON : endpoint + keys
        $subscription = json_decode($token, true);
        if (!isset($subscription['endpoint'])) {
            return false;
        }

        // Appel HTTP minimal (sans librairie) — V3 utilisera web-push-php
        $ch = curl_init($subscription['endpoint']);
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'TTL: 86400',
            ],
        ]);
        $code = 0;
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $code >= 200 && $code < 300;
    }

    public function disponible(): bool
    {
        return function_exists('curl_init');
    }

    public function canal(): string
    {
        return 'push';
    }
}
