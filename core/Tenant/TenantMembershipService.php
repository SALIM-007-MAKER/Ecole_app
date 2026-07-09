<?php

declare(strict_types=1);

namespace Core\Tenant;

use Core\Database;
use PDO;

/**
 * Appartenance d'un utilisateur à un ou plusieurs établissements
 * (blueprint §7). Source de vérité : `user_etablissements` (et non plus
 * `users.etablissement_id`, colonne de transition conservée pour
 * compatibilité V1 uniquement — §7.3 du blueprint).
 *
 * Isolation stricte : `isMember()` est le seul point de validation qu'un
 * changement d'établissement (login multi-école ou switch-school) doit
 * consulter avant d'accorder un contexte — jamais de confiance aveugle
 * dans un `etablissement_id` fourni par la requête.
 */
final class TenantMembershipService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function make(): self
    {
        return new self(Database::getInstance()->getConnection());
    }

    /**
     * Liste les établissements actifs d'un utilisateur (left_at IS NULL),
     * établissement principal en premier.
     *
     * @return array<int, array{id:int, slug:string, nom:string, nom_court:string, is_primary:bool}>
     */
    public function listForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.id, e.slug, e.nom, e.nom_court, ue.is_primary
             FROM user_etablissements ue
             JOIN etablissements e ON e.id = ue.etablissement_id
             WHERE ue.user_id = ? AND ue.left_at IS NULL AND e.deleted_at IS NULL
             ORDER BY ue.is_primary DESC, e.nom_court ASC"
        );
        $stmt->execute([$userId]);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                'id'         => (int)$row['id'],
                'slug'       => $row['slug'],
                'nom'        => $row['nom'],
                'nom_court'  => $row['nom_court'],
                'is_primary' => (bool)$row['is_primary'],
            ];
        }
        return $rows;
    }

    /** Vérifie qu'un utilisateur appartient réellement à un établissement — TOUJOURS consulté avant d'accorder un contexte. */
    public function isMember(int $userId, int $etablissementId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM user_etablissements
             WHERE user_id = ? AND etablissement_id = ? AND left_at IS NULL"
        );
        $stmt->execute([$userId, $etablissementId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /** Rôle (code) d'un utilisateur DANS un établissement donné — délègue à TenantAuthContext (Phase 14.4). */
    public function roleForUser(int $userId, int $etablissementId): ?string
    {
        $roles = TenantAuthContext::make()->roles($userId, $etablissementId);
        return $roles[0] ?? null;
    }

    public function getLastUsed(int $userId): ?int
    {
        $stmt = $this->pdo->prepare("SELECT dernier_etablissement_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $val = $stmt->fetchColumn();
        return $val !== null && $val !== false ? (int)$val : null;
    }

    public function setLastUsed(int $userId, int $etablissementId): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET dernier_etablissement_id = ? WHERE id = ?");
        $stmt->execute([$etablissementId, $userId]);
    }
}
