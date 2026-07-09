<?php
declare(strict_types=1);

namespace App\Shared\Api;

use Core\Request;

/**
 * Parse le paramètre sort=field,-field2.
 * Retourne une clause ORDER BY sûre (whitelist-only).
 */
class SortService
{
    /**
     * @param array  $allowedFields Liste blanche
     * @param string $defaultSort   Champ de tri par défaut (ex: 'created_at')
     * @param string $tableAlias    Alias de table (optionnel)
     */
    public function parse(Request $request, array $allowedFields, string $defaultSort = 'created_at', string $tableAlias = ''): string
    {
        $sortParam = trim($request->get('sort') ?? '');

        $colExpr = fn(string $field): string => $tableAlias !== ''
            ? "`$tableAlias`.`$field`"
            : "`$field`";

        if ($sortParam === '') {
            return $colExpr($defaultSort) . ' ASC';
        }

        $parts   = array_filter(array_map('trim', explode(',', $sortParam)));
        $clauses = [];

        foreach ($parts as $part) {
            $dir   = str_starts_with($part, '-') ? 'DESC' : 'ASC';
            $field = ltrim($part, '-+');

            if (!in_array($field, $allowedFields, true)) {
                continue;
            }

            $clauses[] = $colExpr($field) . ' ' . $dir;
        }

        return !empty($clauses) ? implode(', ', $clauses) : $colExpr($defaultSort) . ' ASC';
    }
}
