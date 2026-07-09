<?php
declare(strict_types=1);

namespace App\Shared\Api;

use Core\Request;

/**
 * Parse les paramètres filter[field]=val et filter[field][op]=val.
 * Produit un tableau de clauses WHERE sûres (whitelist-only).
 */
class FilterService
{
    private const OPERATORS = [
        'eq'       => '= ?',
        'neq'      => '!= ?',
        'gt'       => '> ?',
        'gte'      => '>= ?',
        'lt'       => '< ?',
        'lte'      => '<= ?',
        'like'     => 'LIKE ?',
        'starts'   => 'LIKE ?',
        'in'       => 'IN (?)',      // valeur: "a,b,c"
        'null'     => 'IS NULL',
        'not_null' => 'IS NOT NULL',
    ];

    /**
     * @param array  $allowedFields Liste blanche des champs filtrables
     * @return array ['conditions' => string[], 'bindings' => array]
     */
    public function parse(Request $request, array $allowedFields, string $tableAlias = ''): array
    {
        $raw = $_GET['filter'] ?? [];
        if (!is_array($raw)) {
            return ['conditions' => [], 'bindings' => []];
        }

        $conditions = [];
        $bindings   = [];
        $prefix     = $tableAlias !== '' ? "$tableAlias." : '';

        foreach ($raw as $field => $value) {
            if (!in_array($field, $allowedFields, true)) {
                continue;
            }

            $col = $tableAlias !== ''
                ? "`$tableAlias`.`$field`"
                : "`$field`";

            if (is_array($value)) {
                // filter[field][op]=val
                foreach ($value as $op => $val) {
                    $op = strtolower($op);
                    if (!isset(self::OPERATORS[$op])) continue;
                    [$cond, $bind] = $this->buildClause($col, $op, $val);
                    if ($cond !== null) {
                        $conditions[] = $cond;
                        array_push($bindings, ...$bind);
                    }
                }
            } else {
                // filter[field]=val → eq par défaut
                [$cond, $bind] = $this->buildClause($col, 'eq', (string)$value);
                if ($cond !== null) {
                    $conditions[] = $cond;
                    array_push($bindings, ...$bind);
                }
            }
        }

        return ['conditions' => $conditions, 'bindings' => $bindings];
    }

    private function buildClause(string $col, string $op, string $val): array
    {
        // $col is already backtick-quoted by the caller
        return match($op) {
            'null'     => ["$col IS NULL", []],
            'not_null' => ["$col IS NOT NULL", []],
            'like'     => ["$col LIKE ?", ["%$val%"]],
            'starts'   => ["$col LIKE ?", ["$val%"]],
            'in'       => $this->buildIn($col, $val),
            default    => ["$col " . (self::OPERATORS[$op] ?? '= ?'), [$val]],
        };
    }

    private function buildIn(string $col, string $val): array
    {
        $values = array_filter(array_map('trim', explode(',', $val)));
        if (empty($values)) return [null, []];
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        return ["$col IN ($placeholders)", array_values($values)];
    }
}
