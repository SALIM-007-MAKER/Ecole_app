<?php

namespace App\Modules\Finance\Models;

use Core\Model;

#[\AllowDynamicProperties]
class DecaissementModel extends Model
{
    protected string $table = 'finance_decaissements';

    public const STATUTS = [
        'brouillon' => 'Brouillon',
        'soumis'    => 'Soumis',
        'valide'    => 'Validé',
        'approuve'  => 'Approuvé',
        'paye'      => 'Payé',
        'rejete'    => 'Rejeté',
        'annule'    => 'Annulé',
    ];

    public const STATUTS_MODIFIABLES  = ['brouillon'];
    public const STATUTS_SUPPRIMABLES = ['brouillon'];
    public const STATUTS_ANNULABLES   = ['soumis', 'valide', 'approuve'];

    /** Transitions autorisées depuis chaque statut (workflow séquentiel + branches rejet/annulation). */
    public const TRANSITIONS = [
        'brouillon' => ['soumis', 'annule'],
        'soumis'    => ['valide', 'rejete', 'annule'],
        'valide'    => ['approuve', 'rejete', 'annule'],
        'approuve'  => ['paye', 'annule'],
        'paye'      => [],
        'rejete'    => [],
        'annule'    => [],
    ];

    public function estModifiable(): bool
    {
        return in_array($this->statut, self::STATUTS_MODIFIABLES, true);
    }

    public function estAnnulable(): bool
    {
        return in_array($this->statut, self::STATUTS_ANNULABLES, true);
    }

    public function montantFormate(): string
    {
        return number_format((float)$this->montant, 0, ',', ' ') . ' ' . ($this->devise ?? 'XOF');
    }

    public static function statutLabel(string $statut): string
    {
        return self::STATUTS[$statut] ?? $statut;
    }

    public static function statutColor(string $statut): string
    {
        return match ($statut) {
            'brouillon' => 'bg-slate-100 text-slate-500',
            'soumis'    => 'bg-blue-100 text-blue-700',
            'valide'    => 'bg-violet-100 text-violet-700',
            'approuve'  => 'bg-amber-100 text-amber-700',
            'paye'      => 'bg-emerald-100 text-emerald-700',
            'rejete'    => 'bg-red-100 text-red-700',
            'annule'    => 'bg-rose-100 text-rose-500',
            default     => 'bg-slate-100 text-slate-500',
        };
    }
}
