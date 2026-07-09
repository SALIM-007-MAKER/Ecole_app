<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1;

use App\Modules\Api\Controllers\ApiBaseController;
use App\Modules\Api\Exceptions\NotFoundException;
use App\Modules\Api\Exceptions\ValidationException;
use App\Modules\Api\Repositories\WebhookRepository;

class WebhookApiController extends ApiBaseController
{
    private WebhookRepository $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new WebhookRepository();
    }

    /** GET /api/v1/webhooks */
    public function index(): void
    {
        $ctx  = $this->requireApiAuth();
        $this->requireApiPermission('webhooks.manage');

        $subs  = $this->repo->listForEtab($ctx->etablissementId);
        $items = array_map(fn($s) => [
            'id'         => (int)$s['id'],
            'name'       => $s['name'],
            'url'        => $s['url'],
            'events'     => json_decode($s['events'], true),
            'active'     => (bool)$s['active'],
            'created_at' => $s['created_at'],
        ], $subs);

        $this->apiSuccess(['data' => $items]);
    }

    /** POST /api/v1/webhooks */
    public function store(): void
    {
        $ctx  = $this->requireApiAuth();
        $this->requireApiPermission('webhooks.manage');
        $body = $this->body();
        $this->requireFields($body, ['name', 'url', 'events']);

        if (!filter_var($body['url'], FILTER_VALIDATE_URL)) {
            throw new ValidationException(['url' => 'URL invalide.']);
        }
        if (!is_array($body['events']) || empty($body['events'])) {
            throw new ValidationException(['events' => 'Au moins un événement requis.']);
        }

        $secret = bin2hex(random_bytes(24));
        $id     = $this->repo->create([
            'etablissement_id' => $ctx->etablissementId,
            'name'             => $body['name'],
            'url'              => $body['url'],
            'secret'           => $secret,
            'events'           => $body['events'],  // repo fait json_encode()
            'headers'          => $body['headers'] ?? null,
            'verify_ssl'       => $body['verify_ssl'] ?? 1,
            'created_by'       => $ctx->userId,
        ]);

        $this->apiCreated([
            'id'     => $id,
            'name'   => $body['name'],
            'url'    => $body['url'],
            'events' => $body['events'],
            'active' => true,
            'secret' => $secret,  // affiché une seule fois
        ], 'Webhook créé. Conservez le secret, il ne sera plus affiché.');
    }

    /** DELETE /api/v1/webhooks/{id} */
    public function destroy(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('webhooks.manage');

        $sub = $this->repo->findById((int)$id, $ctx->etablissementId);
        if (!$sub) {
            throw new NotFoundException('Webhook');
        }

        $this->repo->deactivate((int)$id);
        $this->apiNoContent();
    }

    /** GET /api/v1/webhooks/{id}/deliveries */
    public function deliveries(string $id): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('webhooks.manage');

        $sub = $this->repo->findById((int)$id, $ctx->etablissementId);
        if (!$sub) {
            throw new NotFoundException('Webhook');
        }

        $deliveries = $this->repo->getDeliveriesForSubscription((int)$id, 20);
        $this->apiSuccess(['data' => $deliveries]);
    }
}
