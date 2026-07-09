<?php

declare(strict_types=1);

namespace Core\Platform;

use Core\Cache\CacheManager;
use Core\Database;
use Core\Queue\JobQueue;
use Core\Tenant\TenantQuotaService;
use PDO;

/**
 * Statistiques et supervision globales de la plateforme — Phase 14.10.
 * Seule classe de l'application dont c'est le RÔLE explicite d'agréger
 * across-tenant (aucune fuite de valeurs métier : uniquement des comptages
 * et totaux, jamais le contenu des données d'un établissement précis).
 */
final class PlatformStatsService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function make(): self
    {
        return new self(Database::getInstance()->getConnection());
    }

    /**
     * @return array{
     *   total_tenants:int, active_tenants:int, trial_tenants:int, suspended_tenants:int, archived_tenants:int,
     *   total_users:int, total_eleves:int, total_professeurs:int,
     *   storage_used_gb:float, total_api_calls:?int
     * }
     */
    public function globalKpis(): array
    {
        $statutCounts = $this->pdo->query(
            "SELECT statut, COUNT(*) AS n FROM etablissements WHERE deleted_at IS NULL GROUP BY statut"
        )->fetchAll(PDO::FETCH_ASSOC);

        $byStatut = ['trial' => 0, 'active' => 0, 'suspended' => 0, 'cancelled' => 0, 'archived' => 0];
        $total = 0;
        foreach ($statutCounts as $row) {
            $byStatut[$row['statut']] = (int)$row['n'];
            $total += (int)$row['n'];
        }

        $storageBytes = (int)$this->pdo->query(
            "SELECT COALESCE(SUM(size_bytes), 0) FROM api_uploads WHERE deleted_at IS NULL"
        )->fetchColumn();

        return [
            'total_tenants'     => $total,
            'active_tenants'    => $byStatut['active'],
            'trial_tenants'     => $byStatut['trial'],
            'suspended_tenants' => $byStatut['suspended'],
            'archived_tenants'  => $byStatut['archived'],
            'total_users'       => (int)$this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'total_eleves'      => (int)$this->pdo->query("SELECT COUNT(*) FROM eleves")->fetchColumn(),
            'total_professeurs' => (int)$this->pdo->query("SELECT COUNT(*) FROM professeurs")->fetchColumn(),
            'storage_used_gb'   => round($storageBytes / 1024 / 1024 / 1024, 4),
            // Aucune table de suivi des appels API n'est appliquée dans cette base
            // (module API Platform activé en config mais ses tables jamais migrées
            // ici, constat déjà documenté en Phase 14.4) — distinct de 0, "non mesuré".
            'total_api_calls'   => null,
        ];
    }

    /** Files d'attente — vue plateforme = JobQueue::stats(null), déjà agrégée tous tenants (Phase 14.9). */
    public function queueStats(): array
    {
        return JobQueue::make()->stats(null);
    }

    /** @return array{keys:int} nombre total de clés de cache actives, tous tenants confondus */
    public function cacheStats(): array
    {
        return CacheManager::driver()->stats('tenant:');
    }

    /** @return array<string, bool> état enabled/disabled de chaque module (config/modules.php) */
    public function moduleHealth(): array
    {
        $modules = require ROOT_PATH . '/config/modules.php';
        $health = [];
        foreach ($modules as $key => $m) {
            if (is_array($m) && array_key_exists('enabled', $m)) {
                $health[$key] = (bool)$m['enabled'];
            }
        }
        return $health;
    }

    /**
     * Établissements approchant ou dépassant un quota (stockage/utilisateurs/élèves)
     * — agrège TenantQuotaService::alerts() sur chaque établissement actif.
     * @return list<array{etablissement_id:int, slug:string, nom:string, alerts:list<array{type:string,level:string,message:string}>}>
     */
    public function tenantsNearQuota(): array
    {
        $quotaService = new TenantQuotaService($this->pdo);
        $etabs = $this->pdo->query(
            "SELECT id, slug, nom FROM etablissements WHERE deleted_at IS NULL AND statut IN ('active','trial')"
        )->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($etabs as $etab) {
            $alerts = $quotaService->alerts((int)$etab['id']);
            if (!empty($alerts)) {
                $result[] = [
                    'etablissement_id' => (int)$etab['id'],
                    'slug'             => $etab['slug'],
                    'nom'              => $etab['nom'],
                    'alerts'           => $alerts,
                ];
            }
        }
        return $result;
    }

    /** Enregistre un instantané dans platform_analytics_snapshots (point d'entrée pour un futur snapshot planifié — Phase 14.9/14.11 queue+cron). */
    public function recordSnapshot(string $period = 'manual'): int
    {
        $kpis = $this->globalKpis();

        $topTenants = $this->pdo->query(
            "SELECT e.slug, COALESCE(SUM(u.size_bytes), 0) AS storage_bytes
             FROM etablissements e
             LEFT JOIN api_uploads u ON u.etablissement_id = e.id AND u.deleted_at IS NULL
             WHERE e.deleted_at IS NULL
             GROUP BY e.id, e.slug
             ORDER BY storage_bytes DESC
             LIMIT 10"
        )->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare(
            "INSERT INTO platform_analytics_snapshots
                (period, total_tenants, active_tenants, total_users, total_eleves, total_api_calls, storage_used_gb, top_tenants, errors_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $period,
            $kpis['total_tenants'],
            $kpis['active_tenants'],
            $kpis['total_users'],
            $kpis['total_eleves'],
            $kpis['total_api_calls'],
            $kpis['storage_used_gb'],
            json_encode($topTenants, JSON_THROW_ON_ERROR),
            $this->queueStats()['failed'] ?? 0,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /** @return list<array<string,mixed>> derniers instantanés, plus récent en premier */
    public function recentSnapshots(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM platform_analytics_snapshots ORDER BY snapshot_at DESC LIMIT " . max(1, min(200, $limit))
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
