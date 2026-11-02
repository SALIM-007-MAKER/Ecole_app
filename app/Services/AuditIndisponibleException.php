<?php

namespace App\Services;

/**
 * L'écriture d'audit d'une opération CRITIQUE (paiement, annulation, remboursement, extourne, clôture comptable, publication de bulletin) a échoué.
 *
 * Levée dans la transaction de l'opération : l'appelant annule alors l'opération (rollBack) — une opération critique n'est jamais validée sans sa
 * trace confirmée. Le message est destiné à l'utilisateur et ne contient AUCUN détail technique ; la cause réelle est dans le journal technique.
 */
final class AuditIndisponibleException extends \RuntimeException
{
    public function __construct(?\Throwable $cause = null)
    {
        parent::__construct('Opération annulée : sa traçabilité n\'a pas pu être enregistrée. Réessayez ; si le problème persiste, contactez l\'administrateur.', 0, $cause);
    }
}
