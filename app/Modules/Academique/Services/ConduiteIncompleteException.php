<?php

namespace App\Modules\Academique\Services;

/**
 * La génération pour publication ou la publication d'un bulletin est refusée parce que la Conduite de la classe
 * est incomplète, absente ou non publiée (règle stricte). Porte l'état détaillé (élèves manquants) pour l'affichage.
 */
class ConduiteIncompleteException extends \RuntimeException
{
    public function __construct(string $message, public readonly array $etat = [])
    {
        parent::__construct($message);
    }
}
