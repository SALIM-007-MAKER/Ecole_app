<?php

declare(strict_types=1);

namespace App\Modules\RH\Presences\Models;

class AttendanceModel
{
    public const STATUTS = [
        'present'          => 'Présent',
        'absent'           => 'Absent',
        'retard'           => 'Retard',
        'sortie_anticipee' => 'Sortie anticipée',
        'mi_temps'         => 'Mi-temps',
        'mission'          => 'Mission',
        'heure_sup'        => 'Heures supplémentaires',
    ];

    public const STATUT_COLORS = [
        'present'          => 'bg-emerald-100 text-emerald-700',
        'absent'           => 'bg-red-100 text-red-700',
        'retard'           => 'bg-amber-100 text-amber-700',
        'sortie_anticipee' => 'bg-orange-100 text-orange-700',
        'mi_temps'         => 'bg-sky-100 text-sky-700',
        'mission'          => 'bg-indigo-100 text-indigo-700',
        'heure_sup'        => 'bg-violet-100 text-violet-700',
    ];

    public const MODES = [
        'manuel'       => 'Manuel',
        'badge'        => 'Badge',
        'qr_code'      => 'QR Code',
        'biometrie'    => 'Biométrie',
        'api_externe'  => 'API externe',
    ];

    public const MODE_COLORS = [
        'manuel'       => 'bg-slate-100 text-slate-600',
        'badge'        => 'bg-blue-100 text-blue-700',
        'qr_code'      => 'bg-violet-100 text-violet-700',
        'biometrie'    => 'bg-emerald-100 text-emerald-700',
        'api_externe'  => 'bg-amber-100 text-amber-700',
    ];

    public const VALIDATION = [
        'en_attente' => 'En attente',
        'valide'     => 'Validé',
        'rejete'     => 'Rejeté',
    ];

    public const VALIDATION_COLORS = [
        'en_attente' => 'bg-amber-100 text-amber-700',
        'valide'     => 'bg-emerald-100 text-emerald-700',
        'rejete'     => 'bg-red-100 text-red-700',
    ];

    public const REG_TYPES = [
        'correction_heure'  => 'Correction d\'horaire',
        'correction_statut' => 'Correction de statut',
        'justification'     => 'Justification',
        'annulation'        => 'Annulation',
    ];

    public const REG_COLORS = [
        'correction_heure'  => 'bg-blue-100 text-blue-700',
        'correction_statut' => 'bg-violet-100 text-violet-700',
        'justification'     => 'bg-emerald-100 text-emerald-700',
        'annulation'        => 'bg-red-100 text-red-700',
    ];

    public static function statutLabel(string $s): string
    {
        return self::STATUTS[$s] ?? ucfirst($s);
    }

    public static function statutColor(string $s): string
    {
        return self::STATUT_COLORS[$s] ?? 'bg-slate-100 text-slate-600';
    }

    public static function modeLabel(string $m): string
    {
        return self::MODES[$m] ?? ucfirst($m);
    }

    public static function modeColor(string $m): string
    {
        return self::MODE_COLORS[$m] ?? 'bg-slate-100 text-slate-600';
    }

    public static function validationLabel(string $v): string
    {
        return self::VALIDATION[$v] ?? ucfirst($v);
    }

    public static function validationColor(string $v): string
    {
        return self::VALIDATION_COLORS[$v] ?? 'bg-slate-100 text-slate-600';
    }

    public static function regTypeLabel(string $t): string
    {
        return self::REG_TYPES[$t] ?? ucfirst($t);
    }

    public static function regTypeColor(string $t): string
    {
        return self::REG_COLORS[$t] ?? 'bg-slate-100 text-slate-600';
    }

    /** Formate une durée en minutes → "8h30", "45min", etc. */
    public static function formatDuree(?int $minutes): string
    {
        if ($minutes === null || $minutes <= 0) {
            return '—';
        }
        $h   = intdiv($minutes, 60);
        $min = $minutes % 60;

        if ($h === 0) {
            return "{$min}min";
        }
        return $min === 0 ? "{$h}h" : "{$h}h{$min}";
    }

    /** Classe CSS d'alerte selon le nombre de minutes de retard */
    public static function alerteRetard(int $minutes): string
    {
        if ($minutes >= 60) return 'text-red-700 font-semibold';
        if ($minutes >= 30) return 'text-orange-600 font-medium';
        if ($minutes >= 15) return 'text-amber-600';
        return 'text-amber-500';
    }
}
