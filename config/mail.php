<?php

/**
 * Configuration Email
 * Surcharger via le fichier .env :
 *   MAIL_FROM_NAME="Ecole App"
 *   MAIL_FROM_EMAIL=noreply@monecole.com
 *   MAIL_DRIVER=mail
 */
return [
    'from_name'  => $_ENV['MAIL_FROM_NAME']  ?? 'Ecole App',
    'from_email' => $_ENV['MAIL_FROM_EMAIL'] ?? 'noreply@ecole-app.local',
    'reply_to'   => $_ENV['MAIL_REPLY_TO']   ?? '',
    'driver'     => $_ENV['MAIL_DRIVER']     ?? 'mail', // 'mail' = php mail()
];
