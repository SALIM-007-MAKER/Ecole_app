<?php

declare(strict_types=1);

namespace App\Modules\Communication\Repositories;

use Core\Database;
use PDO;

class PreferenceRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function upsert(int $userId, string $type, array $canaux, int $etablissementId = 1): void
    {
        $st = $this->pdo->prepare(
            "INSERT INTO com_preferences
             (user_id, type_notification, canal_internal, canal_email, canal_sms, canal_push, etablissement_id)
             VALUES (:uid, :type, :int, :email, :sms, :push, :etab)
             ON DUPLICATE KEY UPDATE
               canal_internal = :int2, canal_email = :email2, canal_sms = :sms2, canal_push = :push2"
        );
        $st->execute([
            ':uid'    => $userId,
            ':type'   => $type,
            ':int'    => (int) ($canaux['internal'] ?? 1),
            ':email'  => (int) ($canaux['email']    ?? 1),
            ':sms'    => (int) ($canaux['sms']      ?? 0),
            ':push'   => (int) ($canaux['push']     ?? 1),
            ':etab'   => $etablissementId,
            ':int2'   => (int) ($canaux['internal'] ?? 1),
            ':email2' => (int) ($canaux['email']    ?? 1),
            ':sms2'   => (int) ($canaux['sms']      ?? 0),
            ':push2'  => (int) ($canaux['push']     ?? 1),
        ]);
    }

    public function findByUser(int $userId): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM com_preferences WHERE user_id = :uid"
        );
        $st->execute([':uid' => $userId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByUserAndType(int $userId, string $type): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM com_preferences WHERE user_id = :uid AND type_notification = :type LIMIT 1"
        );
        $st->execute([':uid' => $userId, ':type' => $type]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function deleteByUser(int $userId): void
    {
        $st = $this->pdo->prepare("DELETE FROM com_preferences WHERE user_id = :uid");
        $st->execute([':uid' => $userId]);
    }
}
