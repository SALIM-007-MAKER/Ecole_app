<?php

/**
 * Configuration Email
 *
 * Lecture : variable d'environnement RÉELLE d'abord (conteneur, serveur, tests), puis fichier .env.
 *
 *   MAIL_DRIVER=smtp            'smtp' = envoi réel via un serveur SMTP (Gmail…) ; 'mail' = fonction mail() de PHP (inopérante sous WAMP)
 *   MAIL_HOST=smtp.gmail.com
 *   MAIL_PORT=587               587 + MAIL_ENCRYPTION=tls (STARTTLS)  |  465 + MAIL_ENCRYPTION=ssl
 *   MAIL_ENCRYPTION=tls         tls | ssl | none (none : uniquement vers un hôte local, jamais avec Gmail)
 *   MAIL_USERNAME=              adresse Gmail complète
 *   MAIL_PASSWORD=              « mot de passe d'application » Gmail (16 caractères) — JAMAIS le mot de passe du compte
 *   MAIL_FROM_EMAIL=            adresse expéditrice : avec Gmail, la même que MAIL_USERNAME (Gmail réécrit sinon l'expéditeur)
 *   MAIL_FROM_NAME="Ecole App"
 *   MAIL_REPLY_TO=
 *
 * Voir docs/deploiement/EMAIL_GMAIL.md.
 */
$env = static function (string $cle, string $defaut = ''): string {
    $reel = getenv($cle);
    if ($reel !== false && $reel !== '') {
        return $reel;
    }
    return isset($_ENV[$cle]) && $_ENV[$cle] !== '' ? (string)$_ENV[$cle] : $defaut;
};

$username = $env('MAIL_USERNAME');

return [
    'from_name'  => $env('MAIL_FROM_NAME', 'Ecole App'),
    'from_email' => $env('MAIL_FROM_EMAIL', $env('MAIL_FROM', $username !== '' ? $username : 'noreply@ecole-app.local')),
    'reply_to'   => $env('MAIL_REPLY_TO'),
    'driver'     => strtolower($env('MAIL_DRIVER', 'mail')),   // 'smtp' | 'mail'

    // SMTP (utilisé seulement si driver = smtp)
    'host'       => $env('MAIL_HOST', 'smtp.gmail.com'),
    'port'       => (int)$env('MAIL_PORT', '587'),
    'encryption' => strtolower($env('MAIL_ENCRYPTION', 'tls')),
    'username'   => $username,
    'password'   => $env('MAIL_PASSWORD'),
    'timeout'    => (int)$env('MAIL_TIMEOUT', '15'),
    'ehlo_host'  => $env('MAIL_EHLO_HOST', 'localhost'),
];
