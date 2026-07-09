<?php

namespace App\Modules\Finance\Models;

use Core\Model;

class MouvementCaisseModel extends Model
{
    protected string $table = 'finance_mouvements_caisse';

    public const TYPES = [
        'recette'       => 'Recette',
        'decaissement'  => 'Décaissement',
        'correction'    => 'Correction',
        'ouverture'     => 'Ouverture',
        'fermeture'     => 'Fermeture',
        'annulation'    => 'Annulation',
    ];

    public const TYPES_MANUELS = ['recette', 'decaissement', 'correction'];

    public function estActif(): bool
    {
        return $this->statut === 'actif';
    }

    public function estAnnulable(): bool
    {
        return $this->statut === 'actif'
            && in_array($this->type, ['recette', 'decaissement', 'correction'], true);
    }

    public function sensLabel(): string
    {
        return $this->sens === 'credit' ? 'Entrée' : 'Sortie';
    }

    public function badgeClass(): string
    {
        if ($this->statut === 'annule') {
            return 'bg-slate-100 text-slate-400 line-through';
        }
        return match ($this->sens) {
            'credit' => 'bg-emerald-100 text-emerald-700',
            'debit'  => 'bg-rose-100 text-rose-600',
            default  => 'bg-slate-100 text-slate-600',
        };
    }

    public function typeBadgeClass(): string
    {
        return match ($this->type) {
            'recette'      => 'bg-emerald-50 text-emerald-700',
            'decaissement' => 'bg-rose-50 text-rose-700',
            'correction'   => 'bg-amber-50 text-amber-700',
            'ouverture'    => 'bg-blue-50 text-blue-700',
            'annulation'   => 'bg-slate-50 text-slate-500',
            default        => 'bg-slate-50 text-slate-600',
        };
    }
}
