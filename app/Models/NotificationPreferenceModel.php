<?php

namespace App\Models;

use Core\Model;

class NotificationPreferenceModel extends Model
{
    protected string $table = 'notification_preferences';

    public const TRIGGERS = ['note', 'absence', 'paiement', 'annonce'];

    private const DEFAULTS = ['interne' => true, 'email' => false, 'sms' => false];

    public function getForUser(int $userId, string $trigger): array
    {
        $row = $this->queryOne(
            "SELECT * FROM `notification_preferences`
             WHERE user_id = ? AND trigger_type = ? LIMIT 1",
            [$userId, $trigger]
        );

        if (!$row) {
            return self::DEFAULTS;
        }

        return [
            'interne' => (bool)$row->canal_interne,
            'email'   => (bool)$row->canal_email,
            'sms'     => (bool)$row->canal_sms,
        ];
    }

    public function getAllForUser(int $userId): array
    {
        $rows  = $this->findBy('user_id', $userId);
        $prefs = [];
        foreach ($rows as $r) {
            $prefs[$r->trigger_type] = [
                'interne' => (bool)$r->canal_interne,
                'email'   => (bool)$r->canal_email,
                'sms'     => (bool)$r->canal_sms,
            ];
        }
        foreach (self::TRIGGERS as $t) {
            if (!isset($prefs[$t])) {
                $prefs[$t] = self::DEFAULTS;
            }
        }
        return $prefs;
    }

    public function saveForUser(int $userId, array $prefs): void
    {
        foreach (self::TRIGGERS as $trigger) {
            $p = $prefs[$trigger] ?? self::DEFAULTS;
            $this->execute(
                "INSERT INTO `notification_preferences`
                    (user_id, trigger_type, canal_interne, canal_email, canal_sms)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    canal_interne = VALUES(canal_interne),
                    canal_email   = VALUES(canal_email),
                    canal_sms     = VALUES(canal_sms),
                    updated_at    = NOW()",
                [$userId, $trigger, (int)($p['interne'] ?? 1), (int)($p['email'] ?? 0), (int)($p['sms'] ?? 0)]
            );
        }
    }
}
