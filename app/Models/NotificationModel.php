<?php

namespace App\Models;

use Core\Model;

class NotificationModel extends Model
{
    protected string $table = 'notifications';

    const TYPES = [
        'note'     => ['label' => 'Note',      'icon' => 'pencil-square',   'color' => 'primary'],
        'absence'  => ['label' => 'Absence',   'icon' => 'calendar-x',      'color' => 'danger'],
        'paiement' => ['label' => 'Paiement',  'icon' => 'cash-stack',      'color' => 'success'],
        'annonce'  => ['label' => 'Annonce',   'icon' => 'megaphone',        'color' => 'warning'],
        'info'     => ['label' => 'Info',      'icon' => 'info-circle',      'color' => 'secondary'],
    ];

    public function findForUser(int $userId, int $limit = 30): array
    {
        return $this->query(
            'SELECT * FROM `notifications` WHERE `user_id` = ? ORDER BY `created_at` DESC LIMIT ?',
            [$userId, $limit]
        );
    }

    public function findUnreadForUser(int $userId, int $limit = 10): array
    {
        return $this->query(
            'SELECT * FROM `notifications` WHERE `user_id` = ? AND `lu` = 0
             ORDER BY `created_at` DESC LIMIT ?',
            [$userId, $limit]
        );
    }

    public function countUnread(int $userId): int
    {
        $r = $this->queryOne(
            'SELECT COUNT(*) AS n FROM `notifications` WHERE `user_id` = ? AND `lu` = 0',
            [$userId]
        );
        return (int)($r?->n ?? 0);
    }

    public function markAllRead(int $userId): void
    {
        $this->execute(
            'UPDATE `notifications` SET `lu` = 1 WHERE `user_id` = ?',
            [$userId]
        );
    }

    public function markRead(int $id, int $userId): void
    {
        $this->execute(
            'UPDATE `notifications` SET `lu` = 1 WHERE `id` = ? AND `user_id` = ?',
            [$id, $userId]
        );
    }

    public function createForUser(
        int $userId,
        string $type,
        string $titre,
        string $message = '',
        string $lien = ''
    ): void {
        $this->insert([
            'user_id'    => $userId,
            'type'       => $type,
            'titre'      => $titre,
            'message'    => $message,
            'lien'       => $lien,
            'lu'         => 0,
        ]);
    }

    public function createBulk(
        array $userIds,
        string $type,
        string $titre,
        string $message = '',
        string $lien = ''
    ): void {
        foreach ($userIds as $uid) {
            $this->createForUser((int)$uid, $type, $titre, $message, $lien);
        }
    }

    public function deleteOld(int $days = 90): void
    {
        $this->execute(
            'DELETE FROM `notifications` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL ? DAY)',
            [$days]
        );
    }
}
