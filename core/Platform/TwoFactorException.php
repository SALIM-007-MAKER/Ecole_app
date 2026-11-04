<?php

declare(strict_types=1);

namespace Core\Platform;

/**
 * Opération de double authentification refusée ou annulée. Le message est destiné à l'opérateur (aucun détail technique) ; la cause réelle
 * éventuelle est chaînée (`getPrevious()`) et journalisée côté serveur.
 */
final class TwoFactorException extends \RuntimeException
{
    private bool $echecIdentifiants = false;

    /** Mot de passe ou code faux : l'essai compte dans la limitation des tentatives (ne dépend jamais du texte du message). */
    public static function identifiantsIncorrects(string $message): self
    {
        $e = new self($message);
        $e->echecIdentifiants = true;
        return $e;
    }

    public function estEchecIdentifiants(): bool
    {
        return $this->echecIdentifiants;
    }
}
