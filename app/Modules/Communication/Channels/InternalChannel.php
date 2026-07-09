<?php

declare(strict_types=1);

namespace App\Modules\Communication\Channels;

use Core\Database;

class InternalChannel implements ChannelInterface
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function envoyer(array $job): bool
    {
        $userId = $job['user_id'] ?? null;
        if ($userId === null) {
            return false;
        }

        $st = $this->pdo->prepare(
            "INSERT INTO com_notifications
             (user_id, type, module_source, titre, corps, url_action, icone, priorite, etablissement_id)
             VALUES (:uid, :type, :module, :titre, :corps, :url, :icone, :priorite, :etab)"
        );

        return $st->execute([
            ':uid'      => $userId,
            ':type'     => $job['type_notification'] ?? 'info',
            ':module'   => $job['module_source'] ?? 'communication',
            ':titre'    => $job['sujet'] ?? '',
            ':corps'    => $job['corps'] ?? null,
            ':url'      => $job['url_action'] ?? null,
            ':icone'    => 'bell',
            ':priorite' => $job['priorite'] ?? 'normale',
            ':etab'     => $job['etablissement_id'] ?? 1,
        ]);
    }

    public function disponible(): bool
    {
        return true;
    }

    public function canal(): string
    {
        return 'internal';
    }
}
