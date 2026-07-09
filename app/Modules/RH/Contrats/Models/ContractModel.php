<?php

declare(strict_types=1);

namespace App\Modules\RH\Contrats\Models;

class ContractModel
{
    public const TYPES = [
        'cdi'          => 'CDI',
        'cdd'          => 'CDD',
        'vacataire'    => 'Vacataire',
        'stage'        => 'Stage',
        'apprentissage'=> 'Apprentissage',
        'consultant'   => 'Consultant',
        'autre'        => 'Autre',
    ];

    public const STATUTS = [
        'brouillon' => 'Brouillon',
        'actif'     => 'Actif',
        'suspendu'  => 'Suspendu',
        'expire'    => 'Expiré',
        'resilie'   => 'Résilié',
    ];

    // Classes Tailwind par statut
    public const STATUT_COLORS = [
        'brouillon' => 'bg-slate-100 text-slate-600',
        'actif'     => 'bg-emerald-100 text-emerald-700',
        'suspendu'  => 'bg-amber-100 text-amber-700',
        'expire'    => 'bg-red-100 text-red-700',
        'resilie'   => 'bg-gray-100 text-gray-600',
    ];

    public const TYPE_COLORS = [
        'cdi'          => 'bg-violet-100 text-violet-800',
        'cdd'          => 'bg-blue-100 text-blue-800',
        'vacataire'    => 'bg-cyan-100 text-cyan-800',
        'stage'        => 'bg-amber-100 text-amber-800',
        'apprentissage'=> 'bg-green-100 text-green-800',
        'consultant'   => 'bg-purple-100 text-purple-800',
        'autre'        => 'bg-gray-100 text-gray-700',
    ];

    // Transitions autorisées : statut_actuel → [statuts_cibles]
    public const TRANSITIONS = [
        'brouillon' => ['actif'],
        'actif'     => ['suspendu', 'expire', 'resilie'],
        'suspendu'  => ['actif', 'resilie'],
        'expire'    => [],
        'resilie'   => [],
    ];

    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type] ?? ucfirst($type);
    }

    public static function statutLabel(string $statut): string
    {
        return self::STATUTS[$statut] ?? ucfirst($statut);
    }

    public static function statutColor(string $statut): string
    {
        return self::STATUT_COLORS[$statut] ?? 'bg-gray-100 text-gray-600';
    }

    public static function typeColor(string $type): string
    {
        return self::TYPE_COLORS[$type] ?? 'bg-gray-100 text-gray-600';
    }

    /** Calcule le nombre de jours avant la date de fin */
    public static function joursAvantExpiration(?string $dateFin): ?int
    {
        if ($dateFin === null) {
            return null;
        }
        $diff = (new \DateTime())->diff(new \DateTime($dateFin));
        return $diff->invert ? -$diff->days : $diff->days;
    }

    /** Retourne la classe d'alerte selon les jours restants */
    public static function alerteColor(?int $jours): string
    {
        if ($jours === null) return '';
        if ($jours < 0)   return 'text-red-700 bg-red-50';
        if ($jours <= 30) return 'text-red-600 bg-red-50';
        if ($jours <= 60) return 'text-amber-600 bg-amber-50';
        if ($jours <= 90) return 'text-yellow-600 bg-yellow-50';
        return '';
    }
}
