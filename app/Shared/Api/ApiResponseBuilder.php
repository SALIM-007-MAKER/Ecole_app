<?php
declare(strict_types=1);

namespace App\Shared\Api;

/**
 * Construit les réponses JSON standardisées de l'API V2.
 */
class ApiResponseBuilder
{
    public static function success(mixed $data, int $status = 200, string $message = ''): never
    {
        $body = ['success' => true, 'data' => $data];
        if ($message !== '') {
            $body['message'] = $message;
        }
        self::send($body, $status);
    }

    public static function collection(array $data, array $meta, array $links = []): never
    {
        $body = ['success' => true, 'data' => $data, 'meta' => $meta];
        if (!empty($links)) {
            $body['links'] = $links;
        }
        self::send($body, 200);
    }

    public static function created(mixed $data, string $message = ''): never
    {
        $body = ['success' => true, 'data' => $data];
        if ($message !== '') {
            $body['message'] = $message;
        }
        self::send($body, 201);
    }

    public static function noContent(): never
    {
        if (!headers_sent()) {
            http_response_code(204);
        }
        exit;
    }

    public static function error(string $code, string $message, int $status = 400, array $details = []): never
    {
        $body = [
            'success' => false,
            'error'   => ['code' => $code, 'message' => $message],
        ];
        if (!empty($details)) {
            $body['error']['details'] = $details;
        }
        self::send($body, $status);
    }

    public static function accepted(string $message = 'Traitement en cours.'): never
    {
        self::send(['success' => true, 'message' => $message], 202);
    }

    private static function send(array $body, int $status): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('API-Version: 1');
            header('Request-ID: ' . (ApiRequestContext::getRequestId() ?? 'unknown'));
        }
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}
