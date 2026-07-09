<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Auth\ApiKeyService;
use App\Modules\Api\Auth\AuthContext;
use App\Modules\Api\Auth\JwtService;
use App\Modules\Api\Exceptions\ApiExceptionHandler;
use App\Modules\Api\Exceptions\AuthException;
use App\Modules\Api\Exceptions\PermissionException;
use App\Modules\Api\Repositories\ApiKeyRepository;
use App\Modules\Api\Repositories\ApiRequestLogRepository;
use App\Shared\Api\ApiRequestContext;
use App\Shared\Api\ApiResponseBuilder;
use App\Shared\Api\RateLimiter;
use App\Modules\Api\Repositories\RateLimitRepository;
use Core\Controller;
use Core\Session;

abstract class ApiBaseController extends Controller
{
    protected ?AuthContext $authContext = null;
    private float $bootTime;

    public function __construct()
    {
        parent::__construct();

        ApiRequestContext::init();
        $this->bootTime = microtime(true);

        // Override exception handler → JSON pour toutes les routes API
        set_exception_handler([ApiExceptionHandler::class, 'handle']);

        // Headers API communs
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('API-Version: 1');
            header('Request-ID: ' . (ApiRequestContext::getRequestId() ?? ''));
            header('X-Content-Type-Options: nosniff');
            // Retirer X-Frame-Options positionné par le parent (inutile en API)
        }

        // CORS (lecture depuis config)
        $this->applyCors();

        // Résolution d'identité (ne lève pas d'exception — stocke dans $authContext)
        $this->authContext = $this->tryResolveAuth();
    }

    // ── Authentification ─────────────────────────────────────────────────────

    /**
     * Tente de résoudre l'identité depuis JWT → API Key → Session.
     * Retourne null si aucune identité reconnue.
     */
    private function tryResolveAuth(): ?AuthContext
    {
        // 1. JWT Bearer
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            try {
                $jwt     = new JwtService();
                $payload = $jwt->decode($token);
                return new AuthContext(
                    userId:          (int)($payload['sub'] ?? 0),
                    etablissementId: (int)($payload['etab'] ?? 0),
                    role:            (string)($payload['role'] ?? ''),
                    permissions:     (array)($payload['perms'] ?? []),
                    authMethod:      'jwt',
                    jti:             $payload['jti'] ?? null,
                );
            } catch (\Throwable) {
                return null;
            }
        }

        // 2. API Key
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? '');
        if ($apiKey !== '') {
            $svc = new ApiKeyService(new ApiKeyRepository());
            $row = $svc->resolve((string)$apiKey, $this->getClientIp());
            if ($row !== null) {
                return new AuthContext(
                    userId:          (int)$row['created_by'],
                    etablissementId: (int)$row['etablissement_id'],
                    role:            'api',
                    permissions:     json_decode($row['permissions'] ?? '[]', true) ?? [],
                    authMethod:      'api_key',
                    apiKeyId:        (string)$row['id'],
                );
            }
        }

        // 3. Session PHP (backward compat)
        if (Session::isLogged()) {
            $user = Session::getUser();
            if ($user !== null) {
                return new AuthContext(
                    userId:          (int)($user['id'] ?? 0),
                    etablissementId: (int)($user['etablissement_id'] ?? 0),
                    role:            (string)($user['role'] ?? ''),
                    permissions:     (array)($user['permissions'] ?? []),
                    authMethod:      'session',
                );
            }
        }

        return null;
    }

    /**
     * Exige une authentification valide. Lève AuthException sinon.
     */
    protected function requireApiAuth(): AuthContext
    {
        if ($this->authContext === null) {
            throw new AuthException('Authentification requise.', 'auth_required');
        }
        return $this->authContext;
    }

    /**
     * Exige une permission spécifique. Lève PermissionException sinon.
     */
    protected function requireApiPermission(string $permission): void
    {
        $ctx = $this->requireApiAuth();
        if (!$ctx->hasPermission($permission)) {
            throw new PermissionException($permission);
        }
    }

    /**
     * Vérifie le rate limit pour un groupe donné.
     */
    protected function checkRateLimit(string $group = 'default'): void
    {
        $limiter = new RateLimiter(new RateLimitRepository());
        $limiter->check($group, $this->authContext, $this->getClientIp());
    }

    // ── Tenant ───────────────────────────────────────────────────────────────

    protected function getEtabId(): int
    {
        $id = $this->authContext?->etablissementId ?? 0;
        if ($id === 0) {
            throw new AuthException('Établissement non résolu.', 'tenant_not_found');
        }
        return $id;
    }

    protected function getUserId(): int
    {
        return $this->authContext?->userId ?? 0;
    }

    // ── Réponses ──────────────────────────────────────────────────────────────

    protected function apiSuccess(mixed $data, int $status = 200, string $message = ''): never
    {
        $this->logRequest($status);
        ApiResponseBuilder::success($data, $status, $message);
    }

    protected function apiCreated(mixed $data, string $message = ''): never
    {
        $this->logRequest(201);
        ApiResponseBuilder::created($data, $message);
    }

    protected function apiNoContent(): never
    {
        $this->logRequest(204);
        ApiResponseBuilder::noContent();
    }

    protected function apiCollection(array $data, array $meta, array $links = []): never
    {
        $this->logRequest(200);
        ApiResponseBuilder::collection($data, $meta, $links);
    }

    // ── Input helpers ─────────────────────────────────────────────────────────

    protected function body(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }
        return json_decode($raw, true) ?? [];
    }

    protected function bodyParam(string $key, mixed $default = null): mixed
    {
        return $this->body()[$key] ?? $default;
    }

    protected function queryInt(string $key, int $default = 0): int
    {
        return (int)($this->request->get($key) ?? $default);
    }

    protected function queryString(string $key, string $default = ''): string
    {
        return (string)($this->request->get($key) ?? $default);
    }

    // ── CORS ──────────────────────────────────────────────────────────────────

    private function applyCors(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin === '') return;

        try {
            $cfg     = require ROOT_PATH . '/config/api.php';
            $allowed = $cfg['cors']['allowed_origins'] ?? [];
        } catch (\Throwable) {
            return;
        }

        $isAllowed = false;
        foreach ($allowed as $pattern) {
            $regex = '#^' . str_replace('\*', '[^.]+', preg_quote($pattern, '#')) . '$#';
            if (preg_match($regex, $origin)) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) return;

        if (!headers_sent()) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key, X-Requested-With, Accept');
            header('Access-Control-Expose-Headers: X-RateLimit-Limit, X-RateLimit-Remaining, API-Version, Request-ID');
            header('Access-Control-Max-Age: 86400');
        }

        // Répondre immédiatement aux preflight OPTIONS
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    // ── Logging ───────────────────────────────────────────────────────────────

    private function logRequest(int $status): void
    {
        try {
            $cfg = require ROOT_PATH . '/config/api.php';
            if (!($cfg['logging']['enabled'] ?? true)) return;

            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
            foreach ($cfg['logging']['exclude_paths'] ?? [] as $excluded) {
                if (str_starts_with($path, $excluded)) return;
            }

            $repo = new ApiRequestLogRepository();
            $repo->log([
                'request_id'       => ApiRequestContext::getRequestId(),
                'method'           => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
                'path'             => $path,
                'query_string'     => $_SERVER['QUERY_STRING'] ?? null,
                'status_code'      => $status,
                'duration_ms'      => ApiRequestContext::getDurationMs(),
                'user_id'          => $this->authContext?->userId,
                'etablissement_id' => $this->authContext?->etablissementId,
                'auth_method'      => $this->authContext?->authMethod,
                'api_key_id'       => $this->authContext?->apiKeyId,
                'ip_address'       => $this->getClientIp(),
                'user_agent'       => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (\Throwable) {}
    }

    // ── Utilitaires ──────────────────────────────────────────────────────────

    protected function getClientIp(): string
    {
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff !== '') {
            return trim(explode(',', $xff)[0]);
        }
        return $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
