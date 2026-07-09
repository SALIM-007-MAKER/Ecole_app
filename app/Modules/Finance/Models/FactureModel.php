<?php

namespace App\Modules\Finance\Models;

use Core\Model;

class FactureModel extends Model
{
    protected string $table = 'finance_factures';

    public const STATUTS = [
        'brouillon'          => 'Brouillon',
        'emise'              => 'Émise',
        'partiellement_payee'=> 'Partiellement payée',
        'payee'              => 'Payée',
        'en_retard'          => 'En retard',
        'annulee'            => 'Annulée',
        'archive'            => 'Archivée',
    ];

    public const STATUTS_MODIFIABLES = ['brouillon'];
    public const STATUTS_SUPPRIMABLES = ['brouillon'];
    public const STATUTS_EMETTABLES  = ['brouillon'];
    public const STATUTS_ANNULABLES  = ['emise', 'partiellement_payee', 'payee', 'en_retard'];

    public function estBrouillon(): bool
    {
        return $this->statut === 'brouillon';
    }

    public function estAnnulable(): bool
    {
        return in_array($this->statut, self::STATUTS_ANNULABLES, true);
    }

    public function estEditable(): bool
    {
        return in_array($this->statut, self::STATUTS_MODIFIABLES, true);
    }

    public function montantRestant(): float
    {
        return max(0.0, (float)$this->montant_total - (float)$this->montant_paye);
    }

    public function badgeClass(): string
    {
        return match ($this->statut) {
            'brouillon'           => 'bg-slate-100 text-slate-600',
            'emise'               => 'bg-blue-100 text-blue-700',
            'partiellement_payee' => 'bg-amber-100 text-amber-700',
            'payee'               => 'bg-emerald-100 text-emerald-700',
            'en_retard'           => 'bg-red-100 text-red-700',
            'annulee'             => 'bg-rose-100 text-rose-600',
            'archive'             => 'bg-slate-100 text-slate-400',
            default               => 'bg-slate-100 text-slate-600',
        };
    }
}
