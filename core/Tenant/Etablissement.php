<?php

declare(strict_types=1);

namespace Core\Tenant;

/**
 * DTO représentant une ligne de la table `etablissements` (registre des tenants).
 * Voir MULTI_TENANT_V2_BLUEPRINT.md §4.2.
 */
final class Etablissement
{
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $nom,
        public readonly string $nomCourt,
        public readonly ?string $codeEtablissement,
        public readonly string $type,
        public readonly string $pays,
        public readonly string $statut,
        public readonly ?int $planId,
        public readonly ?string $planExpiresAt,
        public readonly int $storageQuotaMb,
        public readonly int $maxUsers,
        public readonly int $maxEleves,
        public readonly ?string $trialEndsAt,
        public readonly ?string $suspendedAt,
        public readonly ?string $suspensionReason,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            slug: (string)$row['slug'],
            nom: (string)$row['nom'],
            nomCourt: (string)$row['nom_court'],
            codeEtablissement: $row['code_etablissement'] ?? null,
            type: (string)$row['type'],
            pays: (string)$row['pays'],
            statut: (string)$row['statut'],
            planId: isset($row['plan_id']) ? (int)$row['plan_id'] : null,
            planExpiresAt: $row['plan_expires_at'] ?? null,
            storageQuotaMb: (int)($row['storage_quota_mb'] ?? 1024),
            maxUsers: (int)($row['max_users'] ?? 50),
            maxEleves: (int)($row['max_eleves'] ?? 500),
            trialEndsAt: $row['trial_ends_at'] ?? null,
            suspendedAt: $row['suspended_at'] ?? null,
            suspensionReason: $row['suspension_reason'] ?? null,
        );
    }

    public function isActive(): bool
    {
        return in_array($this->statut, ['trial', 'active'], true);
    }

    public function isSuspended(): bool
    {
        return $this->statut === 'suspended';
    }

    public function isCancelled(): bool
    {
        return $this->statut === 'cancelled';
    }

    public function isPlanExpired(): bool
    {
        if ($this->planExpiresAt === null) {
            return false;
        }
        return strtotime($this->planExpiresAt) < strtotime('today');
    }
}
