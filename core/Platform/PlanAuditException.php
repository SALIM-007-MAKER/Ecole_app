<?php

declare(strict_types=1);

namespace Core\Platform;

/**
 * La trace d'audit STRICTE d'une opération sur un plan n'a pas pu être écrite : l'opération a été annulée avec sa transaction.
 *
 * Le message est destiné à l'opérateur (aucun détail technique) ; la cause réelle est chaînée (`getPrevious()`) et journalisée côté serveur.
 * Exception volontairement distincte de \RuntimeException « nue » : \PDOException en hérite et ne doit jamais être affichée telle quelle.
 */
final class PlanAuditException extends \RuntimeException
{
}
