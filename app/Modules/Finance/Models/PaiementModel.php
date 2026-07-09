<?php

namespace App\Modules\Finance\Models;

use Core\Model;

class PaiementModel extends Model
{
    protected string $table = 'finance_paiements';

    public const STATUTS = [
        'initie'    => 'Initié',
        'valide'    => 'Validé',
        'complete'  => 'Complété',
        'annule'    => 'Annulé',
        'rembourse' => 'Remboursé',
    ];

    public const STATUTS_ANNULABLES  = ['initie', 'valide'];
    public const STATUTS_REMBOURSABLES = ['complete'];
    public const STATUTS_VALIDABLES   = ['initie'];
    public const STATUTS_COMPLETABLES = ['initie', 'valide'];

    public function estAnnulable(): bool
    {
        return in_array($this->statut, self::STATUTS_ANNULABLES, true);
    }

    public function estRemboursable(): bool
    {
        return in_array($this->statut, self::STATUTS_REMBOURSABLES, true);
    }

    public function estComplete(): bool
    {
        return $this->statut === 'complete';
    }

    public function badgeClass(): string
    {
        return match ($this->statut) {
            'initie'    => 'bg-slate-100 text-slate-600',
            'valide'    => 'bg-purple-100 text-purple-700',
            'complete'  => 'bg-emerald-100 text-emerald-700',
            'annule'    => 'bg-rose-100 text-rose-600',
            'rembourse' => 'bg-amber-100 text-amber-700',
            default     => 'bg-slate-100 text-slate-600',
        };
    }
}
