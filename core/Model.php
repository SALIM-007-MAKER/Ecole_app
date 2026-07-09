<?php

namespace Core;

use Core\Tenant\TenantContext;
use PDO;

abstract class Model
{
    protected PDO $db;
    protected string $table = '';
    protected string $primaryKey = 'id';

    /**
     * Phase 14.3 — Migration des tables métier vers le mode multi-tenant.
     *
     * Opt-in par sous-classe : seuls les modèles couvrant une table qui
     * porte réellement une colonne etablissement_id doivent passer ce flag
     * à true. Pour ces modèles, tenantId() retourne TOUJOURS un id valide
     * (le tenant résolu par TenantContext s'il est positionné, sinon la
     * valeur de repli config/tenant.php['default_id'] — l'unique
     * établissement existant tant que TenantMiddleware n'est pas activé).
     * Cette valeur de repli garantit que les écritures ne violent jamais la
     * contrainte NOT NULL ajoutée en Phase 14.3, et que les lectures
     * filtrent sur un tenant réel — résultat strictement identique à avant
     * Phase 14.3 tant qu'un seul établissement existe en base.
     * $tenantScoped=false (modèles non concernés par cette phase) : aucun
     * changement de comportement, jamais.
     */
    protected bool $tenantScoped = false;
    protected string $tenantColumn = 'etablissement_id';

    private static ?int $tenantDefaultId = null;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /** Retourne l'id du tenant à utiliser pour ce modèle, ou null si non scopé. */
    protected function tenantId(): ?int
    {
        if (!$this->tenantScoped) {
            return null;
        }
        return TenantContext::current() ?? self::tenantDefaultId();
    }

    private static function tenantDefaultId(): int
    {
        if (self::$tenantDefaultId === null) {
            $config = require ROOT_PATH . '/config/tenant.php';
            self::$tenantDefaultId = (int)($config['default_id'] ?? 1);
        }
        return self::$tenantDefaultId;
    }

    // ─── Lecture ────────────────────────────────────────────────────────────

    public function findAll(string $orderBy = '', string $direction = 'ASC'): array
    {
        $tenantId = $this->tenantId();
        $sql = "SELECT * FROM `{$this->table}`";
        $params = [];
        if ($tenantId !== null) {
            $sql .= " WHERE `{$this->tenantColumn}` = ?";
            $params[] = $tenantId;
        }
        if ($orderBy !== '' && preg_match('/^[a-zA-Z0-9_. ,]+$/', $orderBy)) {
            $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
            $isExpression = str_contains($orderBy, ',') || str_contains($orderBy, ' ');
            $order = $isExpression ? $orderBy : "`{$orderBy}` {$direction}";
            $sql .= " ORDER BY {$order}";
        }
        if ($params) {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        }
        return $this->db->query($sql)->fetchAll();
    }

    public function findById(int $id): object|false
    {
        $tenantId = $this->tenantId();
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?";
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= " AND `{$this->tenantColumn}` = ?";
            $params[] = $tenantId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public function findBy(string $column, mixed $value): array
    {
        $tenantId = $this->tenantId();
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$column}` = ?";
        $params = [$value];
        if ($tenantId !== null) {
            $sql .= " AND `{$this->tenantColumn}` = ?";
            $params[] = $tenantId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findOneBy(string $column, mixed $value): object|false
    {
        $tenantId = $this->tenantId();
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$column}` = ?";
        $params = [$value];
        if ($tenantId !== null) {
            $sql .= " AND `{$this->tenantColumn}` = ?";
            $params[] = $tenantId;
        }
        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public function count(string $where = '', array $params = []): int
    {
        $tenantId = $this->tenantId();
        $sql = "SELECT COUNT(*) FROM `{$this->table}`";
        $conditions = $where ? [$where] : [];
        if ($tenantId !== null) {
            $conditions[] = "`{$this->tenantColumn}` = ?";
            $params[] = $tenantId;
        }
        if ($conditions) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    // ─── Pagination ─────────────────────────────────────────────────────────

    public function paginate(int $page, int $perPage = 15, string $where = '', array $params = []): array
    {
        $offset = ($page - 1) * $perPage;
        $total  = $this->count($where, $params);

        $tenantId = $this->tenantId();
        $conditions = $where ? [$where] : [];
        if ($tenantId !== null) {
            $conditions[] = "`{$this->tenantColumn}` = ?";
            $params[] = $tenantId;
        }

        $sql = "SELECT * FROM `{$this->table}`";
        if ($conditions) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return [
            'data'         => $stmt->fetchAll(),
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
        ];
    }

    // ─── Écriture ───────────────────────────────────────────────────────────

    public function insert(array $data): int
    {
        $tenantId = $this->tenantId();
        if ($tenantId !== null && !array_key_exists($this->tenantColumn, $data)) {
            $data[$this->tenantColumn] = $tenantId;
        }

        $columns  = implode('`, `', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $stmt = $this->db->prepare(
            "INSERT INTO `{$this->table}` (`{$columns}`) VALUES ({$placeholders})"
        );
        $stmt->execute(array_values($data));
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $tenantId = $this->tenantId();
        $sets = implode(', ', array_map(fn($col) => "`{$col}` = ?", array_keys($data)));
        $sql = "UPDATE `{$this->table}` SET {$sets} WHERE `{$this->primaryKey}` = ?";
        $params = [...array_values($data), $id];
        if ($tenantId !== null) {
            $sql .= " AND `{$this->tenantColumn}` = ?";
            $params[] = $tenantId;
        }
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $tenantId = $this->tenantId();
        $sql = "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?";
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= " AND `{$this->tenantColumn}` = ?";
            $params[] = $tenantId;
        }
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    // ─── Requête brute ──────────────────────────────────────────────────────

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function queryOne(string $sql, array $params = []): object|false
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}
