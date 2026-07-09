<?php

declare(strict_types=1);

namespace Core\Platform;

use Core\Database;
use Core\Logger;
use PDO;

/**
 * CRUD des plans SaaS (`platform_plans`, existe depuis la Phase 14.2) —
 * Phase 14.10. Architecture pour licences/essais/renouvellements : les
 * dates d'expiration/essai vivent sur `etablissements`
 * (plan_expires_at, trial_ends_at — Phase 14.2) ; les LIMITES (quotas)
 * vivent sur le plan et sont copiées sur l'établissement à l'assignation
 * (PlatformEtablissementService::assignPlan()) — un changement de plan ne
 * modifie donc jamais rétroactivement les établissements déjà assignés à
 * un plan précédent tant qu'on ne réassigne pas explicitement.
 */
final class PlatformPlanService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function make(): self
    {
        return new self(Database::getInstance()->getConnection());
    }

    public function all(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM platform_plans ORDER BY prix_mensuel ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM platform_plans WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array{tenants_count:int} nombre d'établissements actuellement sur ce plan */
    public function usageCount(int $planId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM etablissements WHERE plan_id = ? AND deleted_at IS NULL");
        $stmt->execute([$planId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * @param array{code:string, nom:string, max_users:int, max_eleves:int, storage_quota_mb:int, api_calls_per_day:int, prix_mensuel:float, prix_annuel:float} $data
     * @throws \InvalidArgumentException
     */
    public function create(array $data, int $operatorUserId): int
    {
        $code = strtolower(trim($data['code'] ?? ''));
        if (!preg_match('/^[a-z0-9_-]{2,30}$/', $code)) {
            throw new \InvalidArgumentException('Code de plan invalide.');
        }
        $existing = $this->pdo->prepare("SELECT id FROM platform_plans WHERE code = ?");
        $existing->execute([$code]);
        if ($existing->fetchColumn() !== false) {
            throw new \InvalidArgumentException("Le code de plan « {$code} » existe déjà.");
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO platform_plans (code, nom, max_users, max_eleves, storage_quota_mb, api_calls_per_day, prix_mensuel, prix_annuel, actif)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)"
        );
        $stmt->execute([
            $code,
            trim($data['nom'] ?? $code),
            max(1, (int)($data['max_users'] ?? 50)),
            max(1, (int)($data['max_eleves'] ?? 500)),
            max(1, (int)($data['storage_quota_mb'] ?? 1024)),
            max(1, (int)($data['api_calls_per_day'] ?? 10000)),
            (float)($data['prix_mensuel'] ?? 0),
            (float)($data['prix_annuel'] ?? 0),
        ]);
        $id = (int)$this->pdo->lastInsertId();

        Logger::security('PLATFORM_PLAN_CREATED', "plan_id={$id} code={$code} par operator_user_id={$operatorUserId}");
        return $id;
    }

    public function update(int $id, array $data, int $operatorUserId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE platform_plans SET nom = ?, max_users = ?, max_eleves = ?, storage_quota_mb = ?,
                api_calls_per_day = ?, prix_mensuel = ?, prix_annuel = ? WHERE id = ?"
        );
        $stmt->execute([
            trim($data['nom'] ?? ''),
            max(1, (int)($data['max_users'] ?? 50)),
            max(1, (int)($data['max_eleves'] ?? 500)),
            max(1, (int)($data['storage_quota_mb'] ?? 1024)),
            max(1, (int)($data['api_calls_per_day'] ?? 10000)),
            (float)($data['prix_mensuel'] ?? 0),
            (float)($data['prix_annuel'] ?? 0),
            $id,
        ]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            Logger::security('PLATFORM_PLAN_UPDATED', "plan_id={$id} par operator_user_id={$operatorUserId}");
        }
        return $ok;
    }

    public function toggleActive(int $id, bool $active, int $operatorUserId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE platform_plans SET actif = ? WHERE id = ?");
        $stmt->execute([$active ? 1 : 0, $id]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            Logger::security('PLATFORM_PLAN_TOGGLED', "plan_id={$id} actif=" . ($active ? '1' : '0') . " par operator_user_id={$operatorUserId}");
        }
        return $ok;
    }
}
