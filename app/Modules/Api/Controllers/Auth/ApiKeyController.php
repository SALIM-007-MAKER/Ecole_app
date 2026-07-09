<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\Auth;

use App\Modules\Api\Auth\ApiKeyService;
use App\Modules\Api\Controllers\ApiBaseController;
use App\Modules\Api\Exceptions\NotFoundException;
use App\Modules\Api\Exceptions\PermissionException;
use App\Modules\Api\Repositories\ApiKeyRepository;

/**
 * Gestion des API Keys de l'établissement.
 */
class ApiKeyController extends ApiBaseController
{
    private ApiKeyService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ApiKeyService(new ApiKeyRepository());
    }

    /** GET /api/v1/auth/api-keys */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('admin.api_keys.view');

        $keys = $this->service->listForEtab($ctx->etablissementId);
        $this->apiSuccess($keys);
    }

    /** POST /api/v1/auth/api-keys */
    public function store(): void
    {
        $ctx  = $this->requireApiAuth();
        $this->requireApiPermission('admin.api_keys.create');
        $body = $this->body();

        $name       = trim($body['name'] ?? '');
        $scopes     = (array)($body['permissions'] ?? []);
        $rateLimit  = (int)($body['rate_limit'] ?? 1000);
        $isTest     = (bool)($body['test'] ?? false);
        $allowedIps = isset($body['allowed_ips']) ? (array)$body['allowed_ips'] : null;
        $expiresAt  = $body['expires_at'] ?? null;

        if ($name === '') {
            throw new \App\Modules\Api\Exceptions\ValidationException(['name' => ['Le nom est obligatoire.']]);
        }

        // Intersection avec les permissions du créateur
        $creatorPerms = $ctx->permissions;
        $finalPerms   = !empty($scopes) ? array_values(array_intersect($scopes, $creatorPerms)) : $creatorPerms;

        $result = $this->service->create(
            etablissementId: $ctx->etablissementId,
            name:            $name,
            permissions:     $finalPerms,
            createdBy:       $ctx->userId,
            isTest:          $isTest,
            rateLimit:       $rateLimit,
            allowedIps:      $allowedIps,
            expiresAt:       $expiresAt,
        );

        // La clé complète n'est retournée qu'à la création
        $this->apiCreated([
            'id'   => $result['id'],
            'key'  => $result['key'],
            'hint' => $result['hint'],
        ], 'API Key créée. Conservez la clé — elle ne sera plus visible.');
    }

    /** DELETE /api/v1/auth/api-keys/{id} */
    public function destroy(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('admin.api_keys.delete');

        $this->service->revoke((int)$id, $ctx->etablissementId);
        $this->apiNoContent();
    }
}
