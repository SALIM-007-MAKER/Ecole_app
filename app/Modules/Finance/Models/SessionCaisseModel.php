<?php

namespace App\Modules\Finance\Models;

use Core\Model;

class SessionCaisseModel extends Model
{
    protected string $table = 'finance_sessions_caisse';

    public const STATUTS = [
        'ouverte'      => 'Ouverte',
        'en_activite'  => 'En activité',
        'fermee'       => 'Fermée',
        'annulee'      => 'Annulée',
    ];

    public const STATUTS_ACTIFS = ['ouverte', 'en_activite'];

    public function estActive(): bool
    {
        return in_array($this->statut, self::STATUTS_ACTIFS, true);
    }

    public function estFermee(): bool
    {
        return $this->statut === 'fermee';
    }

    public function estAnnulable(): bool
    {
        return $this->statut === 'ouverte';
    }

    public function soldeTheorique(): float
    {
        return round((float)$this->solde_initial + (float)$this->total_recettes - (float)$this->total_decaissements, 2);
    }

    public function ecartFormate(): string
    {
        if ($this->ecart === null) {
            return '—';
        }
        $ecart = (float)$this->ecart;
        $signe = $ecart >= 0 ? '+' : '';
        return $signe . number_format($ecart, 0, ',', ' ') . ' XOF';
    }

    public function badgeClass(): string
    {
        return match ($this->statut) {
            'ouverte'     => 'bg-blue-100 text-blue-700',
            'en_activite' => 'bg-emerald-100 text-emerald-700',
            'fermee'      => 'bg-slate-100 text-slate-600',
            'annulee'     => 'bg-rose-100 text-rose-600',
            default       => 'bg-slate-100 text-slate-600',
        };
    }
}
