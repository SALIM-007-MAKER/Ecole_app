<?php

namespace App\Modules\Academique\DTO;

/**
 * Version allégée de BulletinData pour les listings et tableaux de bord.
 */
final class BulletinSummary
{
    public function __construct(
        public readonly int    $eleveId,
        public readonly string $eleveNom,
        public readonly string $elevePrenom,
        public readonly string $eleveMatricule,
        public readonly int    $classeId,
        public readonly string $classeNom,
        public readonly int    $periodeId,
        public readonly string $periodeNom,
        public readonly float  $moyenne,
        public readonly string $mentionCode,
        public readonly string $mentionLabel,
        public readonly int    $rang,
        public readonly int    $nbEleves,
        public readonly string $decision,
        public readonly string $statut,
        public readonly string $verificationToken,
        public readonly string $generatedAt,
    ) {}

    public function eleveNomComplet(): string
    {
        return $this->eleveNom . ' ' . $this->elevePrenom;
    }

    public function estAdmis(): bool
    {
        return $this->decision === 'admis';
    }

    public function estPublie(): bool
    {
        return $this->statut === 'publie';
    }

    public function toArray(): array
    {
        return [
            'eleve_id'          => $this->eleveId,
            'eleve_nom'         => $this->eleveNom,
            'eleve_prenom'      => $this->elevePrenom,
            'eleve_matricule'   => $this->eleveMatricule,
            'classe_id'         => $this->classeId,
            'classe_nom'        => $this->classeNom,
            'periode_id'        => $this->periodeId,
            'periode_nom'       => $this->periodeNom,
            'moyenne'           => $this->moyenne,
            'mention_code'      => $this->mentionCode,
            'mention_label'     => $this->mentionLabel,
            'rang'              => $this->rang,
            'nb_eleves'         => $this->nbEleves,
            'decision'          => $this->decision,
            'statut'            => $this->statut,
            'verification_token'=> $this->verificationToken,
            'generated_at'      => $this->generatedAt,
        ];
    }
}
