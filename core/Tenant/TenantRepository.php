<?php

declare(strict_types=1);

namespace Core\Tenant;

use Core\Database;
use PDO;

/**
 * Accès en lecture au registre des tenants (`etablissements`,
 * `etablissement_domains`). Utilisé exclusivement par TenantResolver.
 *
 * Volontairement minimal pour la Phase 14.2 (fondation) : pas de cache,
 * pas d'écriture. La mise en cache (Redis namespacé) est prévue Phase 14.9.
 */
class TenantRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    public function findBySlug(string $slug): ?Etablissement
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM etablissements WHERE slug = :slug AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Etablissement::fromRow($row) : null;
    }

    public function findByDomain(string $domain): ?Etablissement
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.* FROM etablissements e
             INNER JOIN etablissement_domains d ON d.etablissement_id = e.id
             WHERE d.domain = :domain AND d.verified = 1 AND e.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([':domain' => $domain]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Etablissement::fromRow($row) : null;
    }

    public function findById(int $id): ?Etablissement
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM etablissements WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Etablissement::fromRow($row) : null;
    }
}
