<?php

declare(strict_types=1);

namespace App\Modules\Communication\Channels;

class SmsChannel implements ChannelInterface
{
    public function envoyer(array $job): bool
    {
        // V2 : stub — log uniquement, pas de provider SMS configuré
        $tel  = $job['destinataire_tel'] ?? '';
        $corps = $job['corps'] ?? '';

        if (empty($tel)) {
            return false;
        }

        $logDir  = ROOT_PATH . '/storage/logs';
        $logFile = $logDir . '/sms_' . date('Y-m-d') . '.log';
        $line    = date('Y-m-d H:i:s') . " | TO:{$tel} | MSG:" . substr($corps, 0, 160) . PHP_EOL;

        if (is_dir($logDir)) {
            file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        }

        return true;
    }

    public function disponible(): bool
    {
        return false; // Provider non configuré en V2
    }

    public function canal(): string
    {
        return 'sms';
    }
}
