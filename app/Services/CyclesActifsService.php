<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClasseModel;
use Core\Tenant\SettingsService;
use Core\Tenant\TenantContext;

/**
 * Cycles scolaires ACTIVÉS par l'établissement courant (réglage `academique.cycles_actifs`) et niveaux qui en découlent.
 *
 * Source unique pour tous les formulaires qui PROPOSENT des niveaux (création/modification de classe, frais, génération de
 * factures). Règles de cohérence :
 *  - Réglage absent ou vide (établissement créé avant le réglage, université / formation pro sans cycle scolaire) : aucune
 *    restriction, tous les niveaux restent proposés. Rien ne devient inaccessible.
 *  - Seules les PROPOSITIONS sont filtrées. Les données existantes ne sont jamais masquées ni modifiées : un niveau déjà utilisé
 *    (classe existante, frais déjà ciblé) reste proposé et valide même si son cycle a été désactivé depuis ($conserver).
 */
final class CyclesActifsService
{
    public function __construct(private readonly SettingsService $settings, private readonly int $etablissementId) {}

    /** Service de l'établissement courant (même résolution que les modèles : contexte tenant, sinon établissement par défaut). */
    public static function pour(?int $etablissementId = null): self
    {
        $id = $etablissementId
            ?? TenantContext::current()
            ?? (int)((require ROOT_PATH . '/config/tenant.php')['default_id'] ?? 1);
        return new self(SettingsService::make(), $id);
    }

    /** Cycles actifs, dans l'ordre de la nomenclature. Tous les cycles si le réglage est absent, vide ou illisible. */
    public function cycles(): array
    {
        $tous = array_keys(ClasseModel::NIVEAUX);
        $stocke = $this->settings->get($this->etablissementId, 'academique', 'cycles_actifs', null);
        if (!is_array($stocke)) {
            return $tous;
        }
        $actifs = array_values(array_intersect($tous, $stocke));
        return $actifs === [] ? $tous : $actifs;
    }

    /**
     * Nomenclature restreinte aux cycles actifs, groupée par cycle (même forme que ClasseModel::NIVEAUX).
     *
     * @param  list<string> $conserver niveaux déjà utilisés à garder proposés (leur cycle est réintégré)
     * @return array<string, list<string>>
     */
    public function niveauxParCycle(array $conserver = []): array
    {
        $actifs = $this->cycles();
        $out = [];
        foreach (ClasseModel::NIVEAUX as $cycle => $niveaux) {
            if (in_array($cycle, $actifs, true) || array_intersect($niveaux, $conserver) !== []) {
                $out[$cycle] = $niveaux;
            }
        }
        return $out;
    }

    /**
     * Liste à plat des niveaux proposés (ordre pédagogique).
     *
     * @param  list<string> $conserver
     * @return list<string>
     */
    public function niveaux(array $conserver = []): array
    {
        return array_merge([], ...array_values($this->niveauxParCycle($conserver)));
    }

    /** Un niveau peut-il être attribué ? Cycle actif, ou niveau inchangé d'un enregistrement existant ($actuel). */
    public function niveauAutorise(string $niveau, ?string $actuel = null): bool
    {
        if ($actuel !== null && $niveau === $actuel) {
            return true;
        }
        return in_array($niveau, $this->niveaux(), true);
    }
}
