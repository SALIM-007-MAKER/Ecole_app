<?php

namespace App\Services;

class SmsService
{
    private array $config;

    public function __construct()
    {
        $this->config = require ROOT_PATH . '/config/sms.php';
    }

    /**
     * @return array{ok: bool, error: string|null}
     */
    public function send(string $phone, string $message): array
    {
        $phone = $this->normalizePhone($phone);

        if (!$phone) {
            return ['ok' => false, 'error' => 'Numéro de téléphone invalide ou manquant'];
        }

        return match ($this->config['driver'] ?? 'stub') {
            'rest'  => $this->sendViaRest($phone, $message),
            default => ['ok' => false, 'error' => 'Gateway SMS non configurée (mode stub). Définir SMS_DRIVER=rest dans .env'],
        };
    }

    private function sendViaRest(string $phone, string $message): array
    {
        $apiUrl = trim($this->config['api_url'] ?? '');
        $apiKey = trim($this->config['api_key'] ?? '');
        $sender = trim($this->config['sender']  ?? 'EcoleApp');

        if (!$apiUrl) {
            return ['ok' => false, 'error' => 'SMS_API_URL non configurée'];
        }

        $payload = json_encode(
            ['to' => $phone, 'message' => $message, 'from' => $sender],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        $headers = "Content-Type: application/json\r\nContent-Length: " . strlen($payload);
        if ($apiKey) {
            $headers .= "\r\nAuthorization: Bearer {$apiKey}";
        }

        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => $headers,
                'content'       => $payload,
                'timeout'       => 10,
                'ignore_errors' => true,
            ],
        ]);

        try {
            $response = @file_get_contents($apiUrl, false, $ctx);

            if ($response === false) {
                return ['ok' => false, 'error' => 'Impossible de joindre le gateway SMS'];
            }

            $json = json_decode($response, true);

            if (isset($json['error'])) {
                return ['ok' => false, 'error' => (string)$json['error']];
            }

            // La plupart des gateways retournent status 2xx → succès
            $httpCode = 0;
            if (isset($http_response_header)) {
                preg_match('/HTTP\/\d\.\d (\d+)/', $http_response_header[0] ?? '', $m);
                $httpCode = (int)($m[1] ?? 0);
            }

            if ($httpCode >= 400) {
                return ['ok' => false, 'error' => "Erreur HTTP {$httpCode} : {$response}"];
            }

            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function normalizePhone(string $phone): string
    {
        // Garder uniquement les chiffres et le +
        $phone = preg_replace('/[^\d+]/', '', $phone);
        $phone = ltrim($phone, '0');

        if (strlen($phone) < 7) {
            return '';
        }

        // Ajouter le code pays si numéro local
        if (!str_starts_with($phone, '+') && !str_starts_with($phone, '00')) {
            $cc    = $this->config['country_code'] ?? '221';
            $phone = $cc . $phone;
        }

        return $phone;
    }
}
