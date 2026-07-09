<?php

declare(strict_types=1);

namespace App\Modules\Communication\Channels;

class EmailChannel implements ChannelInterface
{
    public function envoyer(array $job): bool
    {
        $to      = $job['destinataire_email'] ?? '';
        $subject = $job['sujet'] ?? '(sans objet)';
        $body    = $job['corps'] ?? '';

        if (empty($to)) {
            return false;
        }

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . (defined('MAIL_FROM') ? MAIL_FROM : 'noreply@scolaris.local') . "\r\n";

        return @mail($to, $subject, $body, $headers);
    }

    public function disponible(): bool
    {
        return function_exists('mail');
    }

    public function canal(): string
    {
        return 'email';
    }
}
