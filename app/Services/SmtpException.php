<?php

declare(strict_types=1);

namespace App\Services;

/** Échec d'envoi SMTP. Le message ne contient jamais d'identifiant ni de mot de passe : il peut être journalisé tel quel. */
final class SmtpException extends \RuntimeException
{
}
