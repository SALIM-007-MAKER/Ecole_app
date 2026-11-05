<?php

declare(strict_types=1);

namespace Core\Platform\WebAuthn;

/** Donnée WebAuthn invalide (format, origine, défi, signature…). Le message est destiné aux journaux, pas à l'utilisateur. */
final class WebAuthnException extends \RuntimeException
{
}
