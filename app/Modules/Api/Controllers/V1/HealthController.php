<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1;

use App\Modules\Api\Controllers\ApiBaseController;
use Core\Database;

class HealthController extends ApiBaseController
{
    /** GET /api/v1/health */
    public function check(): void
    {
        $db = 'ok';
        try {
            Database::getInstance()->getConnection()->query('SELECT 1');
        } catch (\Throwable) {
            $db = 'error';
        }

        $status = $db === 'ok' ? 200 : 503;

        $this->apiSuccess([
            'status'    => $status === 200 ? 'healthy' : 'degraded',
            'version'   => '1.0.0',
            'database'  => $db,
            'timestamp' => date('c'),
        ], $status);
    }
}
