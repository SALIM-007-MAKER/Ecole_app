<?php

declare(strict_types=1);

namespace Core\Platform;

/**
 * Paiement manuel refusé ou annulé. Le message est destiné à l'opérateur (aucun détail technique) ; la cause réelle éventuelle est chaînée
 * (`getPrevious()`) et journalisée côté serveur. `estAudit()` distingue l'échec de la trace d'audit stricte (opération annulée).
 */
final class PaymentException extends \RuntimeException
{
    private bool $audit = false;

    public static function audit(string $message, ?\Throwable $cause = null): self
    {
        $e = new self($message, 0, $cause);
        $e->audit = true;
        return $e;
    }

    public function estAudit(): bool
    {
        return $this->audit;
    }
}
