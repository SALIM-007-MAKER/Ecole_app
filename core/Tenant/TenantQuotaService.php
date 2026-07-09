<?php

declare(strict_types=1);

namespace Core\Tenant;

use Core\Database;
use Core\Logger;
use PDO;

/**
 * Gestion des quotas de stockage et de ressources par établissement —
 * MULTI_TENANT_V2_BLUEPRINT.md §12.4.
 *
 * Dimensions réellement mesurables dans cet environnement (tables
 * existantes) : stockage (ledger `api_uploads`), utilisateurs (`users`),
 * élèves (`eleves`) — toutes deux déjà tenant-scopées depuis la Phase 14.3.
 *
 * Dimensions "documents" / "attachments" : les colonnes de limite existent
 * (`etablissements.max_documents/max_attachments`, extensibles pour les
 * futurs plans d'abonnement) mais aucune table de documents n'est appliquée
 * dans cette base (module Documents désactivé — config/modules.php
 * documents=>false). getUsage() retourne alors `null` pour ces compteurs
 * (distinct de 0, qui signifierait "aucun document") plutôt que d'inventer
 * une donnée — voir TENANT_STORAGE_QUOTA_IMPLEMENTATION_REPORT.md §"Hors périmètre".
 */
final class TenantQuotaService
{
    private const WARNING_THRESHOLD = 0.80; // 80% — seuil d'alerte

    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function make(): self
    {
        return new self(Database::getInstance()->getConnection());
    }

    /**
     * @return array{storage_quota_mb:int, max_users:?int, max_eleves:?int, max_documents:?int, max_attachments:?int}
     */
    public function getLimits(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT storage_quota_mb, max_users, max_eleves, max_documents, max_attachments
             FROM etablissements WHERE id = ?"
        );
        $stmt->execute([$etablissementId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new \InvalidArgumentException("Établissement introuvable : {$etablissementId}");
        }

        return [
            'storage_quota_mb' => (int)$row['storage_quota_mb'],
            'max_users'        => $row['max_users'] !== null ? (int)$row['max_users'] : null,
            'max_eleves'       => $row['max_eleves'] !== null ? (int)$row['max_eleves'] : null,
            'max_documents'    => $row['max_documents'] !== null ? (int)$row['max_documents'] : null,
            'max_attachments'  => $row['max_attachments'] !== null ? (int)$row['max_attachments'] : null,
        ];
    }

    /**
     * @return array{storage_used_bytes:int, users_count:int, eleves_count:int, documents_count:?int, attachments_count:?int}
     */
    public function getUsage(int $etablissementId): array
    {
        $storage = $this->pdo->prepare(
            "SELECT COALESCE(SUM(size_bytes), 0) FROM api_uploads WHERE etablissement_id = ? AND deleted_at IS NULL"
        );
        $storage->execute([$etablissementId]);

        $users = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE etablissement_id = ?");
        $users->execute([$etablissementId]);

        $eleves = $this->pdo->prepare("SELECT COUNT(*) FROM eleves WHERE etablissement_id = ?");
        $eleves->execute([$etablissementId]);

        return [
            'storage_used_bytes' => (int)$storage->fetchColumn(),
            'users_count'        => (int)$users->fetchColumn(),
            'eleves_count'       => (int)$eleves->fetchColumn(),
            'documents_count'    => $this->countIfTableExists('doc_documents', $etablissementId),
            'attachments_count'  => $this->countIfTableExists('doc_attachments', $etablissementId),
        ];
    }

    private function countIfTableExists(string $table, int $etablissementId): ?int
    {
        static $existing = [];
        if (!array_key_exists($table, $existing)) {
            $existing[$table] = (bool)$this->pdo->query("SHOW TABLES LIKE " . $this->pdo->quote($table))->fetchColumn();
        }
        if (!$existing[$table]) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE etablissement_id = ?");
        $stmt->execute([$etablissementId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Vérifie qu'un upload de $sizeBytes reste dans le quota de stockage.
     * Journalise (Logger::security) tout dépassement ou franchissement du
     * seuil d'alerte (80%).
     *
     * @throws StorageQuotaExceededException si le quota serait dépassé
     */
    public function checkUploadAllowed(int $etablissementId, int $sizeBytes): void
    {
        $limits = $this->getLimits($etablissementId);
        $usage  = $this->getUsage($etablissementId);

        $quotaBytes = $limits['storage_quota_mb'] * 1024 * 1024;
        $projected  = $usage['storage_used_bytes'] + $sizeBytes;

        if ($projected > $quotaBytes) {
            Logger::security('QUOTA_STORAGE_EXCEEDED', "etablissement_id={$etablissementId}, usage={$usage['storage_used_bytes']}, taille_demandee={$sizeBytes}, quota={$quotaBytes}");
            throw new StorageQuotaExceededException(
                "Quota de stockage dépassé : {$limits['storage_quota_mb']} Mo maximum pour cet établissement."
            );
        }

        if ($projected > $quotaBytes * self::WARNING_THRESHOLD) {
            Logger::security('QUOTA_STORAGE_WARNING', "etablissement_id={$etablissementId}, usage_projete={$projected}, quota={$quotaBytes}");
        }
    }

    /** Enregistre un fichier dans le ledger d'usage (appelé après un put() réussi). */
    public function recordUpload(int $etablissementId, string $module, string $context, string $path, int $sizeBytes, ?string $mimeType): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO api_uploads (etablissement_id, module, context, path, size_bytes, mime_type)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$etablissementId, $module, $context, $path, $sizeBytes, $mimeType]);
    }

    /** Marque une entrée du ledger comme supprimée (soft) — le fichier n'est plus compté dans l'usage, mais reste dans l'historique. */
    public function recordDeletion(int $etablissementId, string $path): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE api_uploads SET deleted_at = NOW() WHERE etablissement_id = ? AND path = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$etablissementId, $path]);
    }

    /** Historique des écritures (actives et supprimées) — le plus récent en premier. */
    public function history(int $etablissementId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT module, context, path, size_bytes, mime_type, created_at, deleted_at
             FROM api_uploads WHERE etablissement_id = ? ORDER BY created_at DESC LIMIT " . max(1, min(500, $limit))
        );
        $stmt->execute([$etablissementId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Recalcule/retourne l'usage réel courant depuis le ledger (source de
     * vérité — pas de cache dénormalisé à corriger, getUsage() est déjà
     * toujours exact). Fournie comme point d'entrée explicite pour l'action
     * "recalculer" de l'UI/API, qui a une valeur opérationnelle (rassurer
     * l'admin après une purge manuelle) même si le calcul est déjà exact.
     */
    public function recalculate(int $etablissementId): array
    {
        return $this->getUsage($etablissementId);
    }

    /**
     * @return list<array{type:string, level:string, message:string}>
     */
    public function alerts(int $etablissementId): array
    {
        $limits = $this->getLimits($etablissementId);
        $usage  = $this->getUsage($etablissementId);
        $alerts = [];

        $quotaBytes = $limits['storage_quota_mb'] * 1024 * 1024;
        if ($quotaBytes <= 0) {
            // Quota nul = aucune capacité de stockage allouée : tout usage existant est déjà un dépassement.
            if ($usage['storage_used_bytes'] > 0) {
                $alerts[] = ['type' => 'storage', 'level' => 'critical', 'message' => 'Quota de stockage dépassé.'];
            }
        } else {
            $ratio = $usage['storage_used_bytes'] / $quotaBytes;
            if ($ratio >= 1.0) {
                $alerts[] = ['type' => 'storage', 'level' => 'critical', 'message' => 'Quota de stockage dépassé.'];
            } elseif ($ratio >= self::WARNING_THRESHOLD) {
                $alerts[] = ['type' => 'storage', 'level' => 'warning', 'message' => sprintf('Stockage utilisé à %d%%.', (int)round($ratio * 100))];
            }
        }

        foreach (['users' => 'utilisateurs', 'eleves' => 'élèves'] as $key => $label) {
            $max = $limits["max_{$key}"];
            $count = $usage["{$key}_count"];
            if ($max !== null && $count !== null && $max > 0) {
                $ratio = $count / $max;
                if ($ratio >= 1.0) {
                    $alerts[] = ['type' => $key, 'level' => 'critical', 'message' => "Limite de {$label} atteinte ({$count}/{$max})."];
                } elseif ($ratio >= self::WARNING_THRESHOLD) {
                    $alerts[] = ['type' => $key, 'level' => 'warning', 'message' => ucfirst($label) . " : {$count}/{$max}."];
                }
            }
        }

        return $alerts;
    }
}
