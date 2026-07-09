<?php

declare(strict_types=1);

namespace Core\Tenant;

use Core\Database;
use PDO;

/**
 * Résolution des permissions d'un utilisateur DANS un établissement donné,
 * via les tables RBAC tenant-aware créées en Phase 14.2 et peuplées en
 * Phase 14.4 (etab_roles, etab_permissions, etab_role_permissions,
 * user_roles_etab). Voir MULTI_TENANT_V2_BLUEPRINT.md §8.3.
 *
 * Contrairement à TenantContext (qui exige un tenant résolu par le
 * pipeline HTTP, non actif tant que Phase 14.4+ n'active pas
 * TenantMiddleware), ce résolveur reçoit l'établissement explicitement en
 * paramètre — dérivé directement de l'enregistrement de l'utilisateur
 * (users.etablissement_id, Phase 14.3). Il n'a donc besoin d'aucune
 * activation de middleware pour fonctionner correctement dès aujourd'hui.
 */
final class TenantAuthContext
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function make(): self
    {
        return new self(Database::getInstance()->getConnection());
    }

    /**
     * Retourne les codes de permission d'un utilisateur dans un
     * établissement donné, ou [] si l'utilisateur n'a aucun rôle attribué
     * dans cet établissement (via user_roles_etab).
     *
     * @return string[]
     */
    public function permissions(int $userId, int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT p.code
             FROM user_roles_etab ur
             JOIN etab_role_permissions rp ON rp.role_id = ur.role_id
             JOIN etab_permissions p ON p.id = rp.permission_id
             WHERE ur.user_id = ? AND ur.etablissement_id = ?"
        );
        $stmt->execute([$userId, $etablissementId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Retourne les rôles (codes) d'un utilisateur dans un établissement donné.
     *
     * @return string[]
     */
    public function roles(int $userId, int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT r.code
             FROM user_roles_etab ur
             JOIN etab_roles r ON r.id = ur.role_id
             WHERE ur.user_id = ? AND ur.etablissement_id = ?"
        );
        $stmt->execute([$userId, $etablissementId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Vrai si l'utilisateur a au moins un rôle attribué dans cet
     * établissement (condition pour que la résolution DB-driven tenant
     * soit considérée "active" pour cet utilisateur).
     */
    public function hasAnyRole(int $userId, int $etablissementId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM user_roles_etab WHERE user_id = ? AND etablissement_id = ?"
        );
        $stmt->execute([$userId, $etablissementId]);

        return (int)$stmt->fetchColumn() > 0;
    }
}
