<?php
declare(strict_types=1);

namespace App\Modules\Api\Exceptions;

use Core\Logger;

/**
 * Convertit toute exception en réponse JSON structurée.
 * Enregistré via set_exception_handler() dans ApiBaseController.
 */
class ApiExceptionHandler
{
    public static function handle(\Throwable $e): never
    {
        if ($e instanceof RateLimitException) {
            self::respond($e->httpStatus, $e->errorCode, $e->getMessage(), [], $e->retryAfter);
        } elseif ($e instanceof ApiException) {
            self::respond($e->httpStatus, $e->errorCode, $e->getMessage(), $e->details);
        } elseif ($e instanceof \PDOException) {
            $ref = self::logError($e);
            self::respond(500, 'database_error', "Erreur base de données. Réf. : $ref");
        } else {
            $ref = self::logError($e);
            self::respond(500, 'internal_error', "Erreur interne. Réf. : $ref");
        }
    }

    private static function respond(
        int    $status,
        string $code,
        string $message,
        array  $details = [],
        ?int   $retryAfter = null
    ): never {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            if ($retryAfter !== null) {
                header("Retry-After: $retryAfter");
            }
        }
        $body = [
            'success' => false,
            'error'   => [
                'code'    => $code,
                'message' => $message,
            ],
        ];
        if (!empty($details)) {
            $body['error']['details'] = $details;
        }
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function logError(\Throwable $e): string
    {
        $ref = 'ERR-' . strtoupper(substr(md5($e->getMessage() . $e->getFile() . $e->getLine()), 0, 8));
        Logger::error("[API $ref] " . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        return $ref;
    }
}
