<?php
declare(strict_types=1);

namespace App\Modules\Api\Repositories;

use Core\Database;

class ApiRequestLogRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function log(array $data): void
    {
        try {
            $this->pdo->prepare(
                'INSERT INTO api_request_logs
                 (request_id, method, path, query_string, status_code, duration_ms,
                  response_bytes, user_id, etablissement_id, auth_method, api_key_id,
                  ip_address, user_agent, error_code, created_at)
                 VALUES
                 (:rid, :method, :path, :qs, :status, :dur,
                  :bytes, :uid, :etab, :auth, :key,
                  :ip, :ua, :err, NOW(3))'
            )->execute([
                ':rid'    => $data['request_id'],
                ':method' => $data['method'],
                ':path'   => $data['path'],
                ':qs'     => $data['query_string'] ?? null,
                ':status' => $data['status_code'],
                ':dur'    => $data['duration_ms'],
                ':bytes'  => $data['response_bytes'] ?? null,
                ':uid'    => $data['user_id'] ?? null,
                ':etab'   => $data['etablissement_id'] ?? null,
                ':auth'   => $data['auth_method'] ?? null,
                ':key'    => $data['api_key_id'] ?? null,
                ':ip'     => $data['ip_address'] ?? '',
                ':ua'     => $data['user_agent'] ?? null,
                ':err'    => $data['error_code'] ?? null,
            ]);
        } catch (\Throwable) {
            // Journalisation non critique — ne pas bloquer la réponse
        }
    }
}
