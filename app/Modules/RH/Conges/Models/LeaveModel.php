<?php

declare(strict_types=1);

namespace App\Modules\RH\Conges\Models;

class LeaveModel
{
    public const STATUTS = [
        'brouillon' => 'Brouillon',
        'soumis'    => 'Soumis',
        'approuve'  => 'Approuvé',
        'rejete'    => 'Rejeté',
        'annule'    => 'Annulé',
        'en_cours'  => 'En cours',
        'termine'   => 'Terminé',
    ];

    public const STATUT_COLORS = [
        'brouillon' => 'bg-slate-100 text-slate-600',
        'soumis'    => 'bg-amber-100 text-amber-700',
        'approuve'  => 'bg-emerald-100 text-emerald-700',
        'rejete'    => 'bg-red-100 text-red-700',
        'annule'    => 'bg-slate-100 text-slate-500',
        'en_cours'  => 'bg-violet-100 text-violet-700',
        'termine'   => 'bg-sky-100 text-sky-700',
    ];

    // Transitions autorisées par statut
    public const TRANSITIONS = [
        'brouillon' => ['soumettre'],
        'soumis'    => ['approuver', 'rejeter', 'annuler'],
        'approuve'  => ['demarrer', 'annuler'],
        'en_cours'  => ['terminer', 'annuler'],
        'rejete'    => [],
        'annule'    => [],
        'termine'   => [],
    ];

    // ── Helpers statiques ─────────────────────────────────────────────────────

    public static function statutLabel(string $statut): string
    {
        return self::STATUTS[$statut] ?? $statut;
    }

    public static function statutColor(string $statut): string
    {
        return self::STATUT_COLORS[$statut] ?? 'bg-slate-100 text-slate-600';
    }

    public static function canTransition(string $statut, string $action): bool
    {
        return in_array($action, self::TRANSITIONS[$statut] ?? [], true);
    }

    public static function formatDuree(float $jours, ?float $heures = null): string
    {
        if ($heures !== null && $heures > 0) {
            return number_format($heures, 1) . 'h';
        }
        if ($jours <= 0) {
            return '—';
        }
        $entier  = (int)$jours;
        $demi    = ($jours - $entier) >= 0.4;
        if ($demi && $entier > 0) {
            return $entier . 'j½';
        }
        if ($demi) {
            return '½ j';
        }
        return $entier . ($entier > 1 ? ' jours' : ' jour');
    }

    public static function isEditable(string $statut): bool
    {
        return $statut === 'brouillon';
    }

    public static function isFinal(string $statut): bool
    {
        return in_array($statut, ['rejete', 'annule', 'termine'], true);
    }

    public static function soldeRestant(array $solde): float
    {
        return max(0.0,
            (float)($solde['solde_initial']    ?? 0)
            - (float)($solde['solde_pris']     ?? 0)
            - (float)($solde['solde_en_attente'] ?? 0)
        );
    }

    public static function soldeColor(float $restant, float $initial): string
    {
        if ($initial <= 0) return 'text-slate-400';
        $pct = $restant / $initial;
        if ($pct > 0.5) return 'text-emerald-600';
        if ($pct > 0.2) return 'text-amber-600';
        return 'text-red-600';
    }
}
