<?php

declare(strict_types=1);

namespace Core\Platform;

/**
 * Pays d'un établissement (code ISO 3166-1 alpha-2, colonne etablissements.pays).
 *
 * Le pays par défaut est le Niger : EduNova suit le système éducatif nigérien. Le code historique « DZ » (Algérie, héritage de la
 * conception multi-établissements initiale) reste VALIDE pour ne casser aucun établissement existant. Liste volontairement courte
 * (Niger, voisins, Afrique francophone, Maghreb, partenaires) : en ajouter un se fait ici, sans migration.
 */
final class Pays
{
    public const PAR_DEFAUT = 'NE';

    /** @var array<string,string> code ISO → nom français */
    public const LISTE = [
        'NE' => 'Niger',
        'BF' => 'Burkina Faso',
        'ML' => 'Mali',
        'TD' => 'Tchad',
        'NG' => 'Nigeria',
        'BJ' => 'Bénin',
        'TG' => 'Togo',
        'GH' => 'Ghana',
        'CI' => 'Côte d\'Ivoire',
        'SN' => 'Sénégal',
        'GN' => 'Guinée',
        'MR' => 'Mauritanie',
        'CM' => 'Cameroun',
        'GA' => 'Gabon',
        'CG' => 'Congo',
        'CD' => 'RD Congo',
        'DZ' => 'Algérie',
        'MA' => 'Maroc',
        'TN' => 'Tunisie',
        'LY' => 'Libye',
        'EG' => 'Égypte',
        'FR' => 'France',
        'BE' => 'Belgique',
        'CH' => 'Suisse',
        'CA' => 'Canada',
    ];

    /**
     * Code normalisé (majuscules, espaces retirés). Vide ou absent : le pays par défaut. Code inconnu : null.
     */
    public static function normaliser(?string $code): ?string
    {
        $code = strtoupper(trim((string)$code));
        if ($code === '') {
            return self::PAR_DEFAUT;
        }
        return array_key_exists($code, self::LISTE) ? $code : null;
    }

    /** Nom lisible d'un code ; le code lui-même s'il n'est pas dans la liste (établissement existant, pays hors liste). */
    public static function nom(string $code): string
    {
        return self::LISTE[strtoupper($code)] ?? $code;
    }
}
