<?php

namespace App\Services;

use Core\Database;
use PDO;

class AuditService
{
    private static array $sensitiveKeys = [
        'password', 'mot_de_passe', 'mdp', 'token', 'reset_token',
        'api_key', 'vapid_private_key', 'smtp_password', 'sms_api_key',
        '_csrf_token', 'secret',
    ];

    // ── API publique ──────────────────────────────────────────────────────────

    public function log(
        ?int   $userId,
        string $action,
        string $module,
        string $entite    = null,
        int    $entiteId  = null,
        array  $avant     = null,
        array  $apres     = null
    ): void {
        $this->insert([
            'user_id'    => $userId,
            'action'     => $action,
            'module'     => $module,
            'entite'     => $entite,
            'entite_id'  => $entiteId,
            'avant'      => $avant  !== null ? json_encode($this->sanitize($avant),  JSON_UNESCAPED_UNICODE) : null,
            'apres'      => $apres  !== null ? json_encode($this->sanitize($apres),  JSON_UNESCAPED_UNICODE) : null,
            'ip'         => $this->getIp(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT'])
                            ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 500)
                            : null,
        ]);
    }

    public function logCreate(int $userId, string $module, string $entite, ?int $newId, array $data): void
    {
        $this->log($userId, 'create', $module, $entite, $newId, null, $data);
    }

    public function logUpdate(int $userId, string $module, string $entite, int $id, array $avant, array $apres): void
    {
        $diff = $this->diff($avant, $apres);
        if (empty($diff['avant']) && empty($diff['apres'])) {
            return; // Aucun changement réel — ne pas polluer les logs
        }
        $this->log($userId, 'update', $module, $entite, $id, $diff['avant'], $diff['apres']);
    }

    public function logDelete(int $userId, string $module, string $entite, int $id, array $snapshot): void
    {
        $this->log($userId, 'delete', $module, $entite, $id, $snapshot, null);
    }

    public function logLogin(?int $userId, string $email, bool $success, string $ip): void
    {
        $this->insert([
            'user_id'    => $userId,
            'action'     => $success ? 'login' : 'login_failed',
            'module'     => 'auth',
            'entite'     => 'users',
            'entite_id'  => $userId,
            'avant'      => null,
            'apres'      => json_encode(['email' => $email], JSON_UNESCAPED_UNICODE),
            'ip'         => $ip,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT'])
                            ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 500)
                            : null,
        ]);
    }

    public function logPermissionDenied(int $userId, string $permission, string $route): void
    {
        $this->log($userId, 'permission_denied', 'auth', null, null, null, [
            'permission' => $permission,
            'route'      => $route,
        ]);
    }

    // ── Consultation ─────────────────────────────────────────────────────────

    public function getEntityHistory(string $entite, int $id, int $limit = 50): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT al.*, u.nom, u.prenom FROM audit_logs al
                 LEFT JOIN users u ON u.id = al.user_id
                 WHERE al.entite = ? AND al.entite_id = ?
                 ORDER BY al.created_at DESC LIMIT ?'
            );
            $stmt->execute([$entite, $id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Throwable) {
            return [];
        }
    }

    public function getUserHistory(int $userId, int $limit = 100): array
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'SELECT * FROM audit_logs WHERE user_id = ?
                 ORDER BY created_at DESC LIMIT ?'
            );
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\Throwable) {
            return [];
        }
    }

    public function search(array $filters, int $page = 1, int $perPage = 50): array
    {
        try {
            $pdo    = Database::getInstance()->getConnection();
            $where  = ['1=1'];
            $params = [];

            if (!empty($filters['user_id'])) {
                $where[]  = 'al.user_id = ?';
                $params[] = (int)$filters['user_id'];
            }
            if (!empty($filters['module'])) {
                $where[]  = 'al.module = ?';
                $params[] = $filters['module'];
            }
            if (!empty($filters['action'])) {
                $where[]  = 'al.action = ?';
                $params[] = $filters['action'];
            }
            if (!empty($filters['date_debut'])) {
                $where[]  = 'al.created_at >= ?';
                $params[] = $filters['date_debut'] . ' 00:00:00';
            }
            if (!empty($filters['date_fin'])) {
                $where[]  = 'al.created_at <= ?';
                $params[] = $filters['date_fin'] . ' 23:59:59';
            }

            $whereStr = implode(' AND ', $where);
            $offset   = ($page - 1) * $perPage;

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs al WHERE {$whereStr}");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $params[] = $perPage;
            $params[] = $offset;
            $stmt = $pdo->prepare(
                "SELECT al.*, u.nom, u.prenom FROM audit_logs al
                 LEFT JOIN users u ON u.id = al.user_id
                 WHERE {$whereStr}
                 ORDER BY al.created_at DESC LIMIT ? OFFSET ?"
            );
            $stmt->execute($params);

            return ['data' => $stmt->fetchAll(PDO::FETCH_OBJ), 'total' => $total];
        } catch (\Throwable) {
            return ['data' => [], 'total' => 0];
        }
    }

    // ── Privé ─────────────────────────────────────────────────────────────────

    private function insert(array $row): void
    {
        try {
            $pdo  = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                'INSERT INTO audit_logs
                 (user_id, action, module, entite, entite_id, avant, apres, ip, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $row['user_id'],
                $row['action'],
                $row['module'],
                $row['entite']     ?? null,
                $row['entite_id']  ?? null,
                $row['avant']      ?? null,
                $row['apres']      ?? null,
                $row['ip']         ?? null,
                $row['user_agent'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Table absente ou erreur DB → dégradation silencieuse.
            // L'application continue sans bloquer la requête HTTP.
            error_log('[AuditService] INSERT échoué : ' . $e->getMessage());
        }
    }

    private function sanitize(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            $lower = strtolower((string)$key);
            $masked = false;
            foreach (self::$sensitiveKeys as $sensitive) {
                if (str_contains($lower, $sensitive)) {
                    $clean[$key] = '***';
                    $masked = true;
                    break;
                }
            }
            if (!$masked) {
                $clean[$key] = is_array($value) ? $this->sanitize($value) : $value;
            }
        }
        return $clean;
    }

    public function diff(array $avant, array $apres): array
    {
        $changedAvant = [];
        $changedApres = [];
        $allKeys = array_unique(array_merge(array_keys($avant), array_keys($apres)));

        foreach ($allKeys as $key) {
            $a = $avant[$key] ?? null;
            $b = $apres[$key] ?? null;
            if ($a !== $b) {
                $changedAvant[$key] = $a;
                $changedApres[$key] = $b;
            }
        }

        return [
            'avant' => $this->sanitize($changedAvant),
            'apres' => $this->sanitize($changedApres),
        ];
    }

    private function getIp(): string
    {
        // Respecte les proxies de confiance via X-Forwarded-For
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($forwarded !== '') {
            $ips = array_map('trim', explode(',', $forwarded));
            $ip  = filter_var($ips[0], FILTER_VALIDATE_IP);
            if ($ip !== false) {
                return $ip;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
