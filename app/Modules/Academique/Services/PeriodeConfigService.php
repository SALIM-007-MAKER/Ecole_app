<?php

namespace App\Modules\Academique\Services;

use App\Modules\Academique\DTO\PeriodeConfigDTO;
use App\Modules\Academique\Repositories\PeriodeConfigRepository;

/**
 * Modèle par défaut (configurable) pour la génération automatique des
 * périodes scolaires. Préconfiguré au calendrier semestriel du Complexe
 * Scolaire Privé La Persévérance, mais aucune date n'est codée en dur dans
 * la logique applicative : tout est lu depuis `periodes_scolaires_config`
 * (voir migration_periodes_scolaires.sql et T023_periodes_config_semestre.php).
 */
class PeriodeConfigService
{
    /**
     * Valeurs de secours (La Persévérance) utilisées UNIQUEMENT pour amorcer
     * la table si elle est vide (ex: base fraîchement installée sans
     * migration exécutée). Une fois la table peuplée, ces constantes ne sont
     * plus consultées — seule la configuration en base fait foi.
     */
    private const SEED = [
        1 => ['nom' => 'Premier semestre',  'mois_debut' => 10, 'jour_debut' => 1, 'mois_fin' => 1, 'jour_fin' => 31],
        2 => ['nom' => 'Deuxième semestre', 'mois_debut' => 2,  'jour_debut' => 1, 'mois_fin' => 6, 'jour_fin' => 30],
    ];

    private PeriodeConfigRepository $repo;

    public function __construct()
    {
        $this->repo = new PeriodeConfigRepository();
    }

    public function getTemplate(): array
    {
        $this->seedIfEmpty();
        return $this->repo->all();
    }

    public function mettreAJour(int $numero, PeriodeConfigDTO $dto, int $userId): void
    {
        $this->seedIfEmpty();
        $this->repo->update($numero, $dto->toArray(), $userId);
    }

    private function seedIfEmpty(): void
    {
        if ($this->repo->count() > 0) {
            return;
        }
        foreach (self::SEED as $numero => $seed) {
            $this->repo->update($numero, [
                'nom_defaut' => $seed['nom'],
                'mois_debut' => $seed['mois_debut'],
                'jour_debut' => $seed['jour_debut'],
                'mois_fin'   => $seed['mois_fin'],
                'jour_fin'   => $seed['jour_fin'],
                'ordre'      => $numero,
            ], 0);
        }
    }

    /**
     * Calcule, pour une année scolaire donnée (format 'YYYY-YYYY'), les
     * dates concrètes de chaque période du modèle par défaut.
     *
     * Règle de rattachement à l'année civile : un mois ≥ 8 (août-décembre)
     * appartient à l'année de DÉBUT (Y1) de l'année scolaire ; un mois ≤ 7
     * (janvier-juillet) appartient à l'année de FIN (Y2). Cette règle
     * couvre correctement le calendrier semestriel par défaut (S1 : oct → Y1,
     * fin en janv → Y2 ; S2 : fév-juin → Y2) et reste cohérente pour toute
     * configuration personnalisée respectant un cycle août→juillet.
     *
     * @return array<int, array{numero:int, nom:string, date_debut:string, date_fin:string, ordre:int}>
     */
    public function calculerDates(string $anneeScolaire): array
    {
        if (!preg_match('/^(\d{4})-(\d{4})$/', $anneeScolaire, $m)) {
            throw new \InvalidArgumentException("Format d'année scolaire invalide : {$anneeScolaire}");
        }
        [$y1, $y2] = [(int)$m[1], (int)$m[2]];

        $resultat = [];
        foreach ($this->getTemplate() as $config) {
            $anneeDebut = $config->mois_debut >= 8 ? $y1 : $y2;
            $anneeFin   = $config->mois_fin   >= 8 ? $y1 : $y2;

            $resultat[] = [
                'numero'     => (int)$config->numero,
                'nom'        => $config->nom_defaut,
                'date_debut' => sprintf('%04d-%02d-%02d', $anneeDebut, $config->mois_debut, $config->jour_debut),
                'date_fin'   => sprintf('%04d-%02d-%02d', $anneeFin,   $config->mois_fin,   $config->jour_fin),
                'ordre'      => (int)$config->ordre,
            ];
        }
        return $resultat;
    }

    /**
     * Bornes de l'année scolaire (format 'YYYY-YYYY') utilisées pour
     * valider qu'une période reste dans l'année scolaire à laquelle elle
     * est rattachée : du 1er août Y1 au 31 juillet Y2.
     */
    public function bornesAnneeScolaire(string $anneeScolaire): array
    {
        if (!preg_match('/^(\d{4})-(\d{4})$/', $anneeScolaire, $m)) {
            throw new \InvalidArgumentException("Format d'année scolaire invalide : {$anneeScolaire}");
        }
        [$y1, $y2] = [(int)$m[1], (int)$m[2]];

        return [
            'min' => sprintf('%04d-08-01', $y1),
            'max' => sprintf('%04d-07-31', $y2),
        ];
    }
}
