<?php

declare(strict_types=1);

namespace App\Modules\RH\Affectations\Models;

class AssignmentModel
{
    public const TYPES = [
        'principale'  => 'Principale',
        'secondaire'  => 'Secondaire',
        'temporaire'  => 'Temporaire',
    ];

    public const STATUTS = [
        'active'    => 'Active',
        'terminee'  => 'Terminée',
        'suspendue' => 'Suspendue',
    ];

    public const TYPE_COLORS = [
        'principale'  => 'bg-violet-100 text-violet-700',
        'secondaire'  => 'bg-blue-100 text-blue-700',
        'temporaire'  => 'bg-amber-100 text-amber-700',
    ];

    public const STATUT_COLORS = [
        'active'    => 'bg-emerald-100 text-emerald-700',
        'terminee'  => 'bg-slate-100 text-slate-500',
        'suspendue' => 'bg-amber-100 text-amber-700',
    ];

    public const CHANGEMENTS = [
        'creation'     => 'Création',
        'modification' => 'Modification',
        'transfert'    => 'Transfert',
        'suspension'   => 'Suspension',
        'reactivation' => 'Réactivation',
        'cloture'      => 'Clôture',
        'archivage'    => 'Archivage',
    ];

    public const CHANGEMENT_COLORS = [
        'creation'     => 'text-emerald-600',
        'modification' => 'text-blue-600',
        'transfert'    => 'text-violet-600',
        'suspension'   => 'text-amber-600',
        'reactivation' => 'text-emerald-600',
        'cloture'      => 'text-slate-500',
        'archivage'    => 'text-red-500',
    ];

    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type] ?? $type;
    }

    public static function typeColor(string $type): string
    {
        return self::TYPE_COLORS[$type] ?? 'bg-slate-100 text-slate-600';
    }

    public static function statutLabel(string $statut): string
    {
        return self::STATUTS[$statut] ?? $statut;
    }

    public static function statutColor(string $statut): string
    {
        return self::STATUT_COLORS[$statut] ?? 'bg-slate-100 text-slate-600';
    }

    public static function changementLabel(string $type): string
    {
        return self::CHANGEMENTS[$type] ?? $type;
    }

    public static function changementColor(string $type): string
    {
        return self::CHANGEMENT_COLORS[$type] ?? 'text-slate-600';
    }

    /** Jours restants avant fin, null si pas de date de fin */
    public static function joursAvantFin(?string $dateFin): ?int
    {
        if ($dateFin === null) return null;
        $today = new \DateTime('today');
        $fin   = new \DateTime($dateFin);
        $diff  = $today->diff($fin);
        return $diff->invert ? -$diff->days : $diff->days;
    }

    public static function isActive(string $statut): bool
    {
        return $statut === 'active';
    }
}
