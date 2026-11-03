<?php

declare(strict_types=1);

namespace Core\Platform;

/** Erreurs de validation d'un plan SaaS : la liste complète est disponible via getErrors() (le message les joint). */
final class PlanValidationException extends \InvalidArgumentException
{
    /** @param list<string> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }

    /** @return list<string> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
