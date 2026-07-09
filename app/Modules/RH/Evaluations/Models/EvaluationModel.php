<?php

declare(strict_types=1);

namespace App\Modules\RH\Evaluations\Models;

class EvaluationModel
{
    const STATUTS = [
        'brouillon'          => 'Brouillon',
        'en_auto_evaluation' => 'Auto-évaluation',
        'en_evaluation'      => 'Évaluation',
        'soumise'            => 'Soumise',
        'validee'            => 'Validée',
        'publiee'            => 'Publiée',
        'archivee'           => 'Archivée',
    ];

    const STATUT_COLORS = [
        'brouillon'          => 'bg-slate-100 text-slate-600',
        'en_auto_evaluation' => 'bg-blue-100 text-blue-700',
        'en_evaluation'      => 'bg-amber-100 text-amber-700',
        'soumise'            => 'bg-violet-100 text-violet-700',
        'validee'            => 'bg-emerald-100 text-emerald-700',
        'publiee'            => 'bg-green-100 text-green-700',
        'archivee'           => 'bg-slate-100 text-slate-400',
    ];

    const TRANSITIONS = [
        'brouillon'          => ['demarrer_auto_eval'],
        'en_auto_evaluation' => ['soumettre_auto_eval'],
        'en_evaluation'      => ['soumettre'],
        'soumise'            => ['valider'],
        'validee'            => ['publier'],
        'publiee'            => ['archiver'],
        'archivee'           => [],
    ];

    const CAMPAGNE_STATUTS = [
        'brouillon' => 'Brouillon',
        'active'    => 'Active',
        'cloturee'  => 'Clôturée',
        'archivee'  => 'Archivée',
    ];

    const CAMPAGNE_COLORS = [
        'brouillon' => 'bg-slate-100 text-slate-600',
        'active'    => 'bg-emerald-100 text-emerald-700',
        'cloturee'  => 'bg-amber-100 text-amber-700',
        'archivee'  => 'bg-slate-100 text-slate-400',
    ];

    const MENTIONS = [
        'Excellent',
        'Très bien',
        'Bien',
        'Satisfaisant',
        'Insuffisant',
    ];

    const MENTION_COLORS = [
        'Excellent'    => 'bg-emerald-100 text-emerald-800',
        'Très bien'    => 'bg-green-100 text-green-700',
        'Bien'         => 'bg-blue-100 text-blue-700',
        'Satisfaisant' => 'bg-amber-100 text-amber-700',
        'Insuffisant'  => 'bg-red-100 text-red-700',
    ];

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

    public static function isFinal(string $statut): bool
    {
        return in_array($statut, ['archivee'], true);
    }

    public static function isEditable(string $statut): bool
    {
        return in_array($statut, ['brouillon', 'en_auto_evaluation', 'en_evaluation'], true);
    }

    public static function getMentionFromScore(float $score): string
    {
        return match (true) {
            $score >= 4.5 => 'Excellent',
            $score >= 3.5 => 'Très bien',
            $score >= 2.5 => 'Bien',
            $score >= 1.5 => 'Satisfaisant',
            default       => 'Insuffisant',
        };
    }

    public static function mentionColor(string $mention): string
    {
        return self::MENTION_COLORS[$mention] ?? 'bg-slate-100 text-slate-600';
    }

    public static function scoreColor(float $score): string
    {
        return match (true) {
            $score >= 4.0 => 'text-emerald-600',
            $score >= 2.5 => 'text-amber-600',
            default       => 'text-red-600',
        };
    }

    public static function formatScore(float $score): string
    {
        return number_format($score, 2) . ' / 5';
    }

    public static function campagneStatutLabel(string $statut): string
    {
        return self::CAMPAGNE_STATUTS[$statut] ?? $statut;
    }

    public static function campagneStatutColor(string $statut): string
    {
        return self::CAMPAGNE_COLORS[$statut] ?? 'bg-slate-100 text-slate-600';
    }

    public static function periodeLabel(string $p): string
    {
        return match ($p) {
            'S1'            => 'Semestre 1',
            'S2'            => 'Semestre 2',
            'annuelle'      => 'Annuelle',
            'trimestrielle' => 'Trimestrielle',
            'ad_hoc'        => 'Ponctuelle',
            default         => $p,
        };
    }
}
