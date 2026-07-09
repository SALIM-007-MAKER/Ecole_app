<?php

namespace App\Modules\Academique\DTO;

/**
 * Résultat immuable d'une opération de classement.
 */
final class RankingResultDTO
{
    /**
     * @param string  $type          'classe' | 'niveau' | 'matiere' | 'general'
     * @param int     $periodeId     0 pour classement général multi-périodes
     * @param array   $rankings      Entrées triées par rang croissant
     * @param array   $statistiques  Retour de AcademicCalculationService::statistiquesClasse
     */
    public function __construct(
        public readonly string  $type,
        public readonly int     $periodeId,
        public readonly ?int    $classeId,
        public readonly ?int    $matiereId,
        public readonly ?string $niveau,
        public readonly array   $rankings,
        public readonly array   $statistiques,
        public readonly int     $nbTotal,
        public readonly int     $nbAdmis,
        public readonly string  $generatedAt,
    ) {}

    public function getTauxReussite(): float
    {
        if ($this->nbTotal === 0) return 0.0;
        return round(($this->nbAdmis / $this->nbTotal) * 100, 2);
    }

    public function getMoyenneClasse(): ?float
    {
        return $this->statistiques['moyenne'] ?? null;
    }

    public function isEmpty(): bool
    {
        return empty($this->rankings);
    }

    public function toArray(): array
    {
        return [
            'type'         => $this->type,
            'periode_id'   => $this->periodeId,
            'classe_id'    => $this->classeId,
            'matiere_id'   => $this->matiereId,
            'niveau'       => $this->niveau,
            'nb_total'     => $this->nbTotal,
            'nb_admis'     => $this->nbAdmis,
            'taux_reussite'=> $this->getTauxReussite(),
            'moyenne'      => $this->getMoyenneClasse(),
            'rankings'     => array_map(fn($r) => array_merge($r, [
                'moyenne' => $r['moyenne']->getValue(),
            ]), $this->rankings),
            'statistiques' => $this->statistiques,
            'generated_at' => $this->generatedAt,
        ];
    }
}
