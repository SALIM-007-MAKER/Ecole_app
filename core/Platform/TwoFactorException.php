<?php

declare(strict_types=1);

namespace Core\Platform;

/**
 * Opération de double authentification refusée ou annulée. Le message est destiné à l'opérateur (aucun détail technique) ; la cause réelle
 * éventuelle est chaînée (`getPrevious()`) et journalisée côté serveur.
 */
final class TwoFactorException extends \RuntimeException
{
}
