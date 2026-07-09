<?php

declare(strict_types=1);

namespace App\Modules\RH\Documents\Models;

class HRDocumentModel
{
    const TYPES = [
        'contrat_signe', 'avenant', 'diplome', 'certificat',
        'attestation', 'piece_identite', 'certificat_medical',
        'autorisation', 'sanction', 'recompense',
        'evaluation_signee', 'certificat_formation',
    ];

    const TYPE_LABELS = [
        'contrat_signe'       => 'Contrat signé',
        'avenant'             => 'Avenant',
        'diplome'             => 'Diplôme',
        'certificat'          => 'Certificat',
        'attestation'         => 'Attestation',
        'piece_identite'      => 'Pièce d\'identité',
        'certificat_medical'  => 'Certificat médical',
        'autorisation'        => 'Autorisation',
        'sanction'            => 'Sanction',
        'recompense'          => 'Récompense',
        'evaluation_signee'   => 'Évaluation signée',
        'certificat_formation'=> 'Certificat de formation',
    ];

    const TYPE_COLORS = [
        'contrat_signe'       => 'violet',
        'avenant'             => 'purple',
        'diplome'             => 'blue',
        'certificat'          => 'cyan',
        'attestation'         => 'teal',
        'piece_identite'      => 'slate',
        'certificat_medical'  => 'rose',
        'autorisation'        => 'amber',
        'sanction'            => 'red',
        'recompense'          => 'green',
        'evaluation_signee'   => 'indigo',
        'certificat_formation'=> 'orange',
    ];

    const STATUTS = ['actif', 'expire', 'archive', 'en_attente', 'refuse'];

    const STATUT_COLORS = [
        'actif'      => 'green',
        'expire'     => 'red',
        'archive'    => 'slate',
        'en_attente' => 'amber',
        'refuse'     => 'rose',
    ];

    const CONFIDENTIALITES = ['public', 'confidentiel', 'secret'];

    const CONFIDENTIALITE_COLORS = [
        'public'       => 'green',
        'confidentiel' => 'amber',
        'secret'       => 'red',
    ];

    const CONFIDENTIALITE_LABELS = [
        'public'       => 'Public',
        'confidentiel' => 'Confidentiel',
        'secret'       => 'Secret',
    ];

    public static function typeLabel(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    public static function joursAvantExpiration(?string $dateExpiration): ?int
    {
        if (!$dateExpiration) return null;
        $diff = (int)ceil((strtotime($dateExpiration) - time()) / 86400);
        return $diff;
    }

    public static function isExpiringSoon(?string $dateExpiration, int $alerteJours = 30): bool
    {
        if (!$dateExpiration) return false;
        $jours = self::joursAvantExpiration($dateExpiration);
        return $jours !== null && $jours >= 0 && $jours <= $alerteJours;
    }

    public static function isExpired(?string $dateExpiration): bool
    {
        if (!$dateExpiration) return false;
        return $dateExpiration < date('Y-m-d');
    }
}
