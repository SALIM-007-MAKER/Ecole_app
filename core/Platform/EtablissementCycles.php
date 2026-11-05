<?php

declare(strict_types=1);

namespace Core\Platform;

/**
 * Cycles proposés par un établissement et déduction de son « type » (colonne etablissements.type).
 *
 * - Un établissement SCOLAIRE propose un ou plusieurs cycles (Préscolaire, Primaire, Collège, Lycée) ;
 *   ils alimentent le paramètre `academique.cycles_actifs`, et le type en est déduit.
 * - Une UNIVERSITÉ ou une FORMATION PROFESSIONNELLE n'est pas un cycle scolaire : elle n'a aucun cycle
 *   (`cycles_actifs` = []), la règle « au moins un cycle » ne lui est pas appliquée.
 *
 * Classe pure (aucun accès base) : les règles sont testables isolément. La liste des cycles reprend
 * les clés de App\Models\ClasseModel::NIVEAUX ; un test vérifie qu'elles ne divergent pas.
 */
final class EtablissementCycles
{
    /** Cycles scolaires proposables, dans l'ordre pédagogique. */
    public const CYCLES = ['Préscolaire', 'Primaire', 'Collège', 'Lycée'];

    /** Types sans cycle scolaire. */
    public const TYPES_HORS_CYCLE = ['universite', 'formation_pro'];

    /** Famille de type déduite de chaque cycle (le préscolaire se rattache à l'école primaire). */
    private const FAMILLE = [
        'Préscolaire' => 'ecole_primaire',
        'Primaire'    => 'ecole_primaire',
        'Collège'     => 'college',
        'Lycée'       => 'lycee',
    ];

    /** Vrai si le type d'établissement n'est pas un établissement à cycles scolaires. */
    public static function estHorsCycle(?string $type): bool
    {
        return in_array((string)$type, self::TYPES_HORS_CYCLE, true);
    }

    /**
     * Normalise la saisie du formulaire de création.
     *
     * @param  mixed       $cycles       valeur brute de cycles[] (tableau attendu)
     * @param  string|null $typeHorsCycle '' / null (établissement scolaire), 'universite' ou 'formation_pro'
     * @return array{type:string, cycles:list<string>}
     * @throws \InvalidArgumentException saisie invalide ou incohérente
     */
    public static function depuisSaisie(mixed $cycles, ?string $typeHorsCycle): array
    {
        $typeHorsCycle = trim((string)$typeHorsCycle);
        if ($cycles !== null && !is_array($cycles)) {
            throw new \InvalidArgumentException('Liste de cycles invalide.');
        }
        $cycles = $cycles ?? [];
        foreach ($cycles as $c) {
            if (!is_string($c) || !in_array($c, self::CYCLES, true)) {
                throw new \InvalidArgumentException('Cycle inconnu : ' . (is_string($c) ? "« {$c} »" : '(valeur invalide)') . '.');
            }
        }
        // Ordre pédagogique, sans doublon, quel que soit l'ordre posté.
        $cycles = array_values(array_intersect(self::CYCLES, $cycles));

        if ($typeHorsCycle !== '') {
            if (!self::estHorsCycle($typeHorsCycle)) {
                throw new \InvalidArgumentException('Type d\'établissement inconnu : « ' . $typeHorsCycle . ' ».');
            }
            if ($cycles !== []) {
                throw new \InvalidArgumentException('Une université ou une formation professionnelle ne propose pas de cycles scolaires : décochez les cycles ou choisissez « Établissement scolaire ».');
            }
            return ['type' => $typeHorsCycle, 'cycles' => []];
        }

        if ($cycles === []) {
            throw new \InvalidArgumentException('Sélectionnez au moins un cycle scolaire, ou choisissez Université / Formation professionnelle.');
        }
        return ['type' => self::typeDepuisCycles($cycles), 'cycles' => $cycles];
    }

    /**
     * Type déduit de cycles valides : une seule famille → son type, plusieurs familles → groupe scolaire.
     *
     * @param list<string> $cycles
     */
    public static function typeDepuisCycles(array $cycles): string
    {
        $familles = array_values(array_unique(array_map(fn(string $c) => self::FAMILLE[$c], $cycles)));
        return count($familles) === 1 ? $familles[0] : 'groupe_scolaire';
    }
}
