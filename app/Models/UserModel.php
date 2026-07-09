<?php

namespace App\Models;

use Core\Database;
use Core\Model;
use PDO;

class UserModel extends Model
{
    protected string $table = 'users';
    protected bool $tenantScoped = true;

    public function findByEmail(string $email): object|false
    {
        return $this->findOneBy('email', $email);
    }

    public function updateLastLogin(int $id): void
    {
        $this->execute(
            "UPDATE `users` SET `derniere_connexion` = NOW() WHERE `id` = ? AND `etablissement_id` = ?",
            [$id, $this->tenantId()]
        );
    }

    /**
     * Charge les permissions pour un utilisateur.
     *
     * Stratégie (Phase 14.4 — RBAC Multi-Tenant) :
     *   1. Si $etablissementId fourni ET que l'utilisateur a un rôle attribué
     *      dans user_roles_etab pour CET établissement → DB-driven tenant
     *      (etab_roles/etab_permissions/etab_role_permissions, Phase 14.2/14.4).
     *   2. Sinon, si les tables RBAC V2 (globales, non tenant) existent ET
     *      que l'utilisateur a des rôles DB → DB-driven V2 legacy.
     *   3. Sinon → config/permissions.php (V1 fallback, toujours disponible).
     * Dans tous les cas, les codes V1 et V2 sont retournés simultanément
     * (compat totale avec les contrôleurs existants).
     */
    public function getPermissions(string $role, int $userId = 0, ?int $etablissementId = null): array
    {
        // ── Tentative RBAC Multi-Tenant (etab_*, Phase 14.4) ─────────────────
        if ($userId > 0 && $etablissementId !== null) {
            try {
                $tenantPerms = \Core\Tenant\TenantAuthContext::make()->permissions($userId, $etablissementId);
                if (!empty($tenantPerms)) {
                    // etab_role_permissions ne reflète que les codes littéraux de
                    // config/permissions.php (Phase 14.4 T007) : on applique le
                    // même mapping d'alias V1→V2 que le fallback ci-dessous pour
                    // ne perdre aucune permission par rapport au comportement actuel.
                    return array_unique(array_merge($tenantPerms, $this->v1ToV2Aliases($tenantPerms)));
                }
            } catch (\Throwable) {
                // Tables absentes ou erreur DB → fallback silencieux vers les tiers suivants
            }
        }

        // ── Tentative RBAC V2 legacy (globale, non tenant) ───────────────────
        if ($userId > 0) {
            try {
                $pdo = Database::getInstance()->getConnection();

                // Vérifier que les tables RBAC V2 existent
                $rbacExists = (bool)$pdo->query(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rbac_user_roles'"
                )->fetchColumn();

                if ($rbacExists) {
                    $stmt = $pdo->prepare(
                        "SELECT DISTINCT p.code
                         FROM rbac_user_roles ur
                         JOIN rbac_role_permissions rp ON rp.role_id = ur.role_id
                         JOIN permissions p ON p.id = rp.permission_id
                         WHERE ur.user_id = ? AND ur.actif = 1
                           AND (ur.expires_at IS NULL OR ur.expires_at > NOW())"
                    );
                    $stmt->execute([$userId]);
                    $v2perms = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    if (!empty($v2perms)) {
                        // V2 actif — retourner avec codes V1 pour compatibilité contrôleurs
                        return array_unique($v2perms);
                    }
                }
            } catch (\Throwable) {
                // Tables absentes ou erreur DB → fallback silencieux
            }
        }

        // ── Fallback V1 : config/permissions.php ─────────────────────────────
        $map   = require ROOT_PATH . '/config/permissions.php';
        $perms = $map[$role] ?? [];

        // Ajouter les codes V2 équivalents pour que les futurs contrôleurs V2 fonctionnent
        return array_unique(array_merge($perms, $this->v1ToV2Aliases($perms)));
    }

    /**
     * Génère les codes V2 équivalents pour chaque code V1.
     * Permet aux nouveaux contrôleurs (*.update, *.view.own) de fonctionner
     * sans modifier config/permissions.php.
     */
    private function v1ToV2Aliases(array $v1perms): array
    {
        static $map = [
            'notes.edit'               => 'notes.update',
            'absences.edit'            => 'absences.update',
            'classes.edit'             => 'classes.update',
            'matieres.edit'            => 'matieres.update',
            'emploi_du_temps.edit'     => 'emploi_du_temps.update',
            'enseignants.edit'         => 'enseignants.update',
            'comptabilite.edit'        => 'comptabilite.update',
            'users.edit'               => 'users.update',
            'annonces.edit'            => 'annonces.update',
            'notes.view_own'           => 'notes.view.own',
            'absences.view_own'        => 'absences.view.own',
            'absences.justify'         => 'absences.approve.own',
            'comptabilite.view_own'    => 'comptabilite.view.own',
            'emploi_du_temps.view_own' => 'emploi_du_temps.view.own',
            'notifications.manage'     => 'notifications.create',
            'bulletins.view'           => 'bulletins.print',   // ancien view inclut print
        ];

        $aliases = [];
        foreach ($v1perms as $perm) {
            if (isset($map[$perm])) {
                $aliases[] = $map[$perm];
            }
        }
        return $aliases;
    }

    // ─── Réinitialisation de mot de passe ────────────────────────────────────

    public function createResetToken(string $email, string $token): void
    {
        // Invalider les anciens tokens
        $this->execute(
            "DELETE FROM `password_resets` WHERE `email` = ?",
            [$email]
        );

        $this->execute(
            "INSERT INTO `password_resets` (`email`, `token`, `expires_at`)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))",
            [$email, $token]
        );
    }

    public function findByResetToken(string $token): object|false
    {
        return $this->queryOne(
            "SELECT * FROM `password_resets`
             WHERE `token` = ? AND `used` = 0 AND `expires_at` > NOW()
             LIMIT 1",
            [$token]
        );
    }

    public function markResetTokenUsed(string $token): void
    {
        $this->execute(
            "UPDATE `password_resets` SET `used` = 1 WHERE `token` = ?",
            [$token]
        );
    }

    // ─── Gestion du profil ────────────────────────────────────────────────────

    public function updatePassword(int $id, string $hash): void
    {
        $this->execute(
            "UPDATE `users` SET `password` = ?, `updated_at` = NOW() WHERE `id` = ? AND `etablissement_id` = ?",
            [$hash, $id, $this->tenantId()]
        );
    }

    public function updateProfile(int $id, array $data): bool
    {
        return $this->update($id, array_merge($data, ['updated_at' => date('Y-m-d H:i:s')]));
    }

    public function emailExistsForOther(string $email, int $excludeId): bool
    {
        $count = $this->queryOne(
            "SELECT COUNT(*) AS n FROM `users` WHERE `email` = ? AND `id` != ?",
            [$email, $excludeId]
        );
        return $count && (int)$count->n > 0;
    }

    // ─── Pagination ordonnée ─────────────────────────────────────────────────

    public function paginateOrdered(int $page, int $perPage = 20, string $where = '', array $params = []): array
    {
        $offset = ($page - 1) * $perPage;
        $total  = $this->count($where, $params);

        $conditions = $where ? [$where] : [];
        $conditions[] = "`etablissement_id` = ?";
        $params[] = $this->tenantId();

        $sql = "SELECT * FROM `{$this->table}` WHERE " . implode(' AND ', $conditions);
        $sql .= " ORDER BY nom ASC, prenom ASC LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return [
            'data'         => $stmt->fetchAll(),
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int)ceil($total / $perPage),
        ];
    }

    // ─── Administration ───────────────────────────────────────────────────────

    public function countByRole(string $role): int
    {
        return $this->count('role = ?', [$role]);
    }

    public function findAllWithRole(string $role): array
    {
        return $this->findBy('role', $role);
    }

    public function findAllWithRoles(array $roles): array
    {
        if (empty($roles)) {
            return [];
        }
        $in = implode(',', array_fill(0, count($roles), '?'));
        return $this->query(
            "SELECT * FROM `users` WHERE `role` IN ({$in}) AND `actif` = 1 AND `etablissement_id` = ?",
            [...$roles, $this->tenantId()]
        );
    }

    public static function allRoles(): array
    {
        return ['admin', 'directeur', 'secretaire', 'comptable', 'enseignant', 'parent', 'eleve'];
    }

    public static function roleLabel(string $role): string
    {
        return match($role) {
            'admin'      => 'Administrateur',
            'directeur'  => 'Directeur',
            'secretaire' => 'Secrétaire',
            'comptable'  => 'Comptable',
            'enseignant' => 'Enseignant',
            'parent'     => 'Parent',
            'eleve'      => 'Élève',
            default      => ucfirst($role),
        };
    }

    public static function roleBadgeColor(string $role): string
    {
        return match($role) {
            'admin'      => 'danger',
            'directeur'  => 'primary',
            'secretaire' => 'info',
            'comptable'  => 'success',
            'enseignant' => 'warning',
            'parent'     => 'secondary',
            'eleve'      => 'light text-dark',
            default      => 'secondary',
        };
    }
}
