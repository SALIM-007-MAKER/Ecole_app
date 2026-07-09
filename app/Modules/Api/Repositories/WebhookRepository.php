<?php
declare(strict_types=1);

namespace App\Modules\Api\Repositories;

use Core\Database;

class WebhookRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function create(array $data): int
    {
        $this->pdo->prepare(
            'INSERT INTO webhook_subscriptions
             (etablissement_id, name, url, secret_hash, events, active, headers, verify_ssl, created_by)
             VALUES (:etab, :name, :url, :hash, :events, 1, :headers, :ssl, :by)'
        )->execute([
            ':etab'    => $data['etablissement_id'],
            ':name'    => $data['name'],
            ':url'     => $data['url'],
            ':hash'    => hash('sha256', $data['secret']),
            ':events'  => json_encode($data['events']),
            ':headers' => isset($data['headers']) ? json_encode($data['headers']) : null,
            ':ssl'     => $data['verify_ssl'] ?? 1,
            ':by'      => $data['created_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findById(int $id, int $etablissementId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, etablissement_id, name, url, events, active, headers, verify_ssl, created_at
             FROM webhook_subscriptions WHERE id = :id AND etablissement_id = :etab'
        );
        $stmt->execute([':id' => $id, ':etab' => $etablissementId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function listForEtab(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, url, events, active, created_at FROM webhook_subscriptions
             WHERE etablissement_id = :etab ORDER BY created_at DESC'
        );
        $stmt->execute([':etab' => $etablissementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function update(int $id, int $etabId, array $data): void
    {
        $sets = [];
        $params = [':id' => $id, ':etab' => $etabId];
        foreach (['name', 'url', 'events', 'active', 'headers', 'verify_ssl'] as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = :$field";
                $params[":$field"] = is_array($data[$field]) ? json_encode($data[$field]) : $data[$field];
            }
        }
        if (empty($sets)) return;
        $this->pdo->prepare('UPDATE webhook_subscriptions SET ' . implode(', ', $sets) . ' WHERE id = :id AND etablissement_id = :etab')
                  ->execute($params);
    }

    public function delete(int $id, int $etabId): void
    {
        $this->pdo->prepare('DELETE FROM webhook_subscriptions WHERE id = :id AND etablissement_id = :etab')
                  ->execute([':id' => $id, ':etab' => $etabId]);
    }

    public function findActiveForEvent(string $eventType, ?int $etablissementId = null): array
    {
        $sql    = "SELECT ws.* FROM webhook_subscriptions ws
                   WHERE ws.active = 1 AND JSON_CONTAINS(ws.events, :event)";
        $params = [':event' => json_encode($eventType)];
        if ($etablissementId !== null) {
            $sql .= ' AND ws.etablissement_id = :etab';
            $params[':etab'] = $etablissementId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function deactivate(int $id): void
    {
        $this->pdo->prepare('UPDATE webhook_subscriptions SET active = 0 WHERE id = :id')
                  ->execute([':id' => $id]);
    }

    public function createDelivery(int $subscriptionId, string $eventType, array $payload): int
    {
        $this->pdo->prepare(
            'INSERT INTO webhook_deliveries (subscription_id, event_type, payload, status, next_attempt_at)
             VALUES (:sub, :type, :payload, "pending", NOW())'
        )->execute([
            ':sub'     => $subscriptionId,
            ':type'    => $eventType,
            ':payload' => json_encode($payload),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateDelivery(int $deliveryId, array $data): void
    {
        $this->pdo->prepare(
            'UPDATE webhook_deliveries
             SET status = :status, attempts = attempts + 1, last_http_status = :http,
                 last_response = :resp, duration_ms = :dur, next_attempt_at = :next,
                 delivered_at = IF(:status = "success", NOW(), NULL)
             WHERE id = :id'
        )->execute([
            ':status' => $data['status'],
            ':http'   => $data['http_status'] ?? null,
            ':resp'   => $data['response'] ?? null,
            ':dur'    => $data['duration_ms'] ?? null,
            ':next'   => $data['next_attempt_at'] ?? null,
            ':id'     => $deliveryId,
        ]);
    }

    public function getPendingDeliveries(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.*, ws.url, ws.secret_hash, ws.verify_ssl, ws.headers
             FROM webhook_deliveries d
             JOIN webhook_subscriptions ws ON ws.id = d.subscription_id
             WHERE d.status = "pending" AND d.next_attempt_at <= NOW()
               AND d.attempts < d.max_attempts
             ORDER BY d.next_attempt_at ASC LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getDeliveriesForSubscription(int $subscriptionId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, event_type, status, attempts, last_http_status, duration_ms, created_at, delivered_at
             FROM webhook_deliveries WHERE subscription_id = :sub ORDER BY created_at DESC LIMIT :lim'
        );
        $stmt->bindValue(':sub', $subscriptionId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
