<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

class DocumentModel
{
    const STATUTS          = ['brouillon', 'actif', 'expire', 'archive', 'corbeille'];
    const CONFIDENTIALITES = ['public', 'interne', 'confidentiel', 'secret'];
    const MODULES_SOURCE   = [
        'rh', 'finance', 'academique', 'vie_scolaire',
        'scolarite', 'bibliotheque', 'inventaire', 'communication', 'global',
    ];

    const STATUT_LABELS = [
        'brouillon' => 'Brouillon',
        'actif'     => 'Actif',
        'expire'    => 'Expiré',
        'archive'   => 'Archivé',
        'corbeille' => 'Corbeille',
    ];

    const CONFIDENTIALITE_LABELS = [
        'public'       => 'Public',
        'interne'      => 'Interne',
        'confidentiel' => 'Confidentiel',
        'secret'       => 'Secret',
    ];

    const STATUT_COLORS = [
        'brouillon' => 'slate',
        'actif'     => 'green',
        'expire'    => 'red',
        'archive'   => 'gray',
        'corbeille' => 'orange',
    ];

    const CONF_COLORS = [
        'public'       => 'green',
        'interne'      => 'blue',
        'confidentiel' => 'amber',
        'secret'       => 'red',
    ];

    const PURGE_CORBEILLE_JOURS = 30;

    // MIME autorisés par module
    const MIME_PAR_MODULE = [
        'rh' => [
            'application/pdf',
            'image/jpeg', 'image/png', 'image/webp',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        'finance' => [
            'application/pdf',
            'image/jpeg', 'image/png',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
        ],
        'academique'    => ['application/pdf', 'image/jpeg', 'image/png'],
        'vie_scolaire'  => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
        'scolarite'     => ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
        'global'        => ['*'],
    ];

    const TAILLE_MAX_OCTETS = 20971520; // 20 Mo

    public static function isExpired(?string $dateExpiration): bool
    {
        if (!$dateExpiration) return false;
        return $dateExpiration < date('Y-m-d');
    }

    public static function isExpiringSoon(?string $dateExpiration, int $alerteJours = 30): bool
    {
        if (!$dateExpiration) return false;
        $jours = self::joursAvantExpiration($dateExpiration);
        return $jours !== null && $jours >= 0 && $jours <= $alerteJours;
    }

    public static function joursAvantExpiration(?string $dateExpiration): ?int
    {
        if (!$dateExpiration) return null;
        return (int)ceil((strtotime($dateExpiration) - time()) / 86400);
    }

    public static function confidentialiteLabel(string $conf): string
    {
        return self::CONFIDENTIALITE_LABELS[$conf] ?? ucfirst($conf);
    }

    public static function statutLabel(string $statut): string
    {
        return self::STATUT_LABELS[$statut] ?? ucfirst($statut);
    }

    public static function isMimeAllowed(string $mime, string $moduleSource): bool
    {
        $allowed = self::MIME_PAR_MODULE[$moduleSource] ?? self::MIME_PAR_MODULE['global'];
        if ($allowed === ['*']) return true;
        return in_array($mime, $allowed, true);
    }
}
