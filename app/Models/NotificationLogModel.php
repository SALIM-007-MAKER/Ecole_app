<?php

namespace App\Models;

use Core\Model;

class NotificationLogModel extends Model
{
    protected string $table = 'notification_logs';

    public function log(
        int     $userId,
        string  $trigger,
        string  $canal,
        string  $titre,
        string  $message,
        ?string $destinataire,
        string  $statut = 'envoye',
        ?string $erreur = null
    ): void {
        try {
            $this->insert([
                'user_id'      => $userId,
                'trigger_type' => $trigger,
                'canal'        => $canal,
                'titre'        => mb_substr($titre,   0, 255),
                'message'      => mb_substr($message,  0, 1000),
                'destinataire' => $destinataire ? mb_substr($destinataire, 0, 255) : null,
                'statut'       => $statut,
                'erreur'       => $erreur ? mb_substr($erreur, 0, 500) : null,
            ]);
        } catch (\Throwable) {
            // Le log ne doit jamais faire planter l'application
        }
    }

    public function paginateFiltered(int $page, int $perPage, array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $total = (int)($this->queryOne(
            "SELECT COUNT(*) AS n
             FROM `notification_logs` l
             LEFT JOIN `users` u ON u.id = l.user_id
             {$where}",
            $params
        )->n ?? 0);

        $offset = ($page - 1) * $perPage;
        $rows   = $this->query(
            "SELECT l.*,
                    CONCAT(COALESCE(u.prenom,''), ' ', COALESCE(u.nom,'')) AS user_nom,
                    u.email AS user_email,
                    u.role  AS user_role
             FROM `notification_logs` l
             LEFT JOIN `users` u ON u.id = l.user_id
             {$where}
             ORDER BY l.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items'       => $rows,
            'total'       => $total,
            'pages'       => (int)ceil($total / $perPage),
            'currentPage' => $page,
        ];
    }

    public function getStats(): array
    {
        $global = $this->queryOne(
            "SELECT COUNT(*) AS total,
                    SUM(statut = 'envoye') AS ok,
                    SUM(statut = 'echoue') AS ko,
                    SUM(DATE(created_at) = CURDATE()) AS today
             FROM `notification_logs`"
        );

        $byCanal = $this->query(
            "SELECT canal,
                    COUNT(*) AS total,
                    SUM(statut = 'envoye') AS ok,
                    SUM(statut = 'echoue') AS ko
             FROM `notification_logs`
             GROUP BY canal"
        );

        $byTrigger = $this->query(
            "SELECT trigger_type, COUNT(*) AS total
             FROM `notification_logs`
             GROUP BY trigger_type ORDER BY total DESC"
        );

        return [
            'total'     => (int)($global?->total ?? 0),
            'ok'        => (int)($global?->ok    ?? 0),
            'ko'        => (int)($global?->ko    ?? 0),
            'today'     => (int)($global?->today ?? 0),
            'byCanal'   => $byCanal,
            'byTrigger' => $byTrigger,
        ];
    }

    public function deleteOld(int $days = 90): void
    {
        $this->execute(
            "DELETE FROM `notification_logs` WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
    }

    private function buildWhere(array $f): array
    {
        $c = []; $p = [];

        if (!empty($f['q'])) {
            $t = '%' . $f['q'] . '%';
            $c[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR l.titre LIKE ?)";
            array_push($p, $t, $t, $t, $t);
        }
        if (!empty($f['canal']))      { $c[] = "l.canal = ?";           $p[] = $f['canal']; }
        if (!empty($f['trigger']))    { $c[] = "l.trigger_type = ?";    $p[] = $f['trigger']; }
        if (!empty($f['statut']))     { $c[] = "l.statut = ?";          $p[] = $f['statut']; }
        if (!empty($f['date_debut'])) { $c[] = "DATE(l.created_at) >= ?"; $p[] = $f['date_debut']; }
        if (!empty($f['date_fin']))   { $c[] = "DATE(l.created_at) <= ?"; $p[] = $f['date_fin']; }

        $where = $c ? "WHERE " . implode(" AND ", $c) : "";
        return [$where, $p];
    }
}
