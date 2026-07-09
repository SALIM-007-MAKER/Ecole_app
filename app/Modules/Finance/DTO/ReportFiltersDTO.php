<?php

namespace App\Modules\Finance\DTO;

class ReportFiltersDTO
{
    public const FORMATS = ['html', 'pdf', 'csv', 'excel'];
    public const TYPES   = ['dashboard','paiements','factures','impayes','caisse','analytique','comptable'];

    public function __construct(
        public readonly string $type         = 'dashboard',
        public readonly string $format       = 'html',
        public readonly string $dateDebut    = '',
        public readonly string $dateFin      = '',
        public readonly string $anneeScolaire = '',
        public readonly int    $annee        = 0,
        public readonly int    $mois         = 0,
        public readonly int    $exerciceId   = 0,
        public readonly int    $classeId     = 0,
        public readonly string $niveau       = '',
        public readonly int    $eleveId      = 0,
        public readonly string $statut       = '',
        public readonly string $modePaiement = '',
        public readonly string $q            = '',
        public readonly string $tranche      = '',
        public readonly int    $page         = 1,
        public readonly int    $perPage      = 50,
        public readonly string $sortBy       = '',
        public readonly string $sortDir      = 'DESC',
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            type:          in_array($data['type'] ?? '', self::TYPES, true) ? $data['type'] : 'dashboard',
            format:        in_array($data['format'] ?? '', self::FORMATS, true) ? $data['format'] : 'html',
            dateDebut:     trim($data['date_debut']    ?? ''),
            dateFin:       trim($data['date_fin']      ?? ''),
            anneeScolaire: trim($data['annee_scolaire'] ?? ''),
            annee:         (int)($data['annee']        ?? date('Y')),
            mois:          (int)($data['mois']         ?? 0),
            exerciceId:    (int)($data['exercice_id']  ?? 0),
            classeId:      (int)($data['classe_id']    ?? 0),
            niveau:        trim($data['niveau']        ?? ''),
            eleveId:       (int)($data['eleve_id']     ?? 0),
            statut:        trim($data['statut']        ?? ''),
            modePaiement:  trim($data['mode_paiement'] ?? ''),
            q:             trim($data['q']             ?? ''),
            tranche:       trim($data['tranche']       ?? ''),
            page:          max(1, (int)($data['page']     ?? 1)),
            perPage:       min(500, max(10, (int)($data['per_page'] ?? 50))),
            sortBy:        trim($data['sort_by']       ?? ''),
            sortDir:       ($data['sort_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC',
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'type'          => $this->type,
            'date_debut'    => $this->dateDebut,
            'date_fin'      => $this->dateFin,
            'annee_scolaire'=> $this->anneeScolaire,
            'annee'         => $this->annee ?: null,
            'mois'          => $this->mois ?: null,
            'classe_id'     => $this->classeId ?: null,
            'niveau'        => $this->niveau,
            'eleve_id'      => $this->eleveId ?: null,
            'statut'        => $this->statut,
            'mode_paiement' => $this->modePaiement,
            'q'             => $this->q,
        ]);
    }
}
