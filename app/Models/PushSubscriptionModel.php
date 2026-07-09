<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use Core\Logger;

class PushSubscriptionModel
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureTableExists();
    }

    // ─── Créer la table si elle n'existe pas ─────────────────────────────────
    private function ensureTableExists(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS push_subscriptions (
                id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
                user_id      INT UNSIGNED    NOT NULL,
                endpoint     TEXT            NOT NULL,
                p256dh       VARCHAR(255)    NOT NULL DEFAULT '',
                auth         VARCHAR(255)    NOT NULL DEFAULT '',
                user_agent   VARCHAR(255)    NOT NULL DEFAULT '',
                created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_used_at DATETIME        NULL,
                PRIMARY KEY (id),
                KEY idx_user (user_id),
                KEY idx_endpoint (endpoint(200))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // ─── Ajouter ou mettre à jour un abonnement ───────────────────────────────
    public function upsert(int $userId, array $data): bool
    {
        $endpoint  = $data['endpoint'] ?? '';
        $p256dh    = $data['keys']['p256dh'] ?? '';
        $auth      = $data['keys']['auth']   ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (!$endpoint) {
            return false;
        }

        // Vérifier si cet endpoint existe déjà (pour cet utilisateur)
        $existing = $this->findByEndpoint($endpoint);

        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE push_subscriptions
                SET p256dh = :p256dh, auth = :auth, user_agent = :ua,
                    last_used_at = NOW()
                WHERE id = :id
            ");
            return $stmt->execute([
                ':p256dh' => $p256dh,
                ':auth'   => $auth,
                ':ua'     => substr($userAgent, 0, 255),
                ':id'     => $existing->id,
            ]);
        }

        $stmt = $this->db->prepare("
            INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent)
            VALUES (:user_id, :endpoint, :p256dh, :auth, :ua)
        ");

        return $stmt->execute([
            ':user_id'  => $userId,
            ':endpoint' => $endpoint,
            ':p256dh'   => $p256dh,
            ':auth'     => $auth,
            ':ua'       => substr($userAgent, 0, 255),
        ]);
    }

    // ─── Supprimer par endpoint ───────────────────────────────────────────────
    public function deleteByEndpoint(string $endpoint, int $userId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM push_subscriptions WHERE endpoint = :ep AND user_id = :uid
        ");
        return $stmt->execute([':ep' => $endpoint, ':uid' => $userId]);
    }

    // ─── Supprimer tous les abonnements d'un utilisateur ─────────────────────
    public function deleteAllForUser(int $userId): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM push_subscriptions WHERE user_id = :uid"
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->rowCount();
    }

    // ─── Récupérer tous les abonnements (pour broadcast) ─────────────────────
    public function getAll(): array
    {
        return $this->db->query("
            SELECT * FROM push_subscriptions ORDER BY created_at DESC
        ")->fetchAll();
    }

    // ─── Récupérer par rôle ───────────────────────────────────────────────────
    public function getByRole(string $role): array
    {
        $stmt = $this->db->prepare("
            SELECT ps.*
            FROM push_subscriptions ps
            JOIN users u ON u.id = ps.user_id
            WHERE u.role = :role AND u.actif = 1
        ");
        $stmt->execute([':role' => $role]);
        return $stmt->fetchAll();
    }

    // ─── Récupérer pour un utilisateur ───────────────────────────────────────
    public function getForUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM push_subscriptions WHERE user_id = :uid ORDER BY created_at DESC
        ");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    // ─── Rechercher par endpoint ──────────────────────────────────────────────
    private function findByEndpoint(string $endpoint): ?\stdClass
    {
        $stmt = $this->db->prepare("
            SELECT * FROM push_subscriptions WHERE endpoint = :ep LIMIT 1
        ");
        $stmt->execute([':ep' => $endpoint]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ─── Marquer un abonnement comme invalide (endpoint expiré) ──────────────
    public function deleteInvalid(string $endpoint): void
    {
        $stmt = $this->db->prepare(
            "DELETE FROM push_subscriptions WHERE endpoint = :ep"
        );
        $stmt->execute([':ep' => $endpoint]);
        Logger::info("Push subscription supprimé (endpoint invalide) : " . substr($endpoint, 0, 60) . '…');
    }

    // ─── Compter les abonnements ──────────────────────────────────────────────
    public function count(): int
    {
        return (int)$this->db->query(
            "SELECT COUNT(*) FROM push_subscriptions"
        )->fetchColumn();
    }
}
