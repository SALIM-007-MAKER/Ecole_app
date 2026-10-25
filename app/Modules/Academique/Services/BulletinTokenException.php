<?php

declare(strict_types=1);

namespace App\Modules\Academique\Services;

/**
 * Erreur de configuration ou d'utilisation du service de jetons de vérification des bulletins.
 *
 * Les messages ne contiennent JAMAIS la valeur du secret ni un jeton : ils peuvent être journalisés
 * ou affichés à un administrateur sans risque.
 */
final class BulletinTokenException extends \RuntimeException
{
    public const MISSING     = 1;   // BULLETIN_TOKEN_SECRET absent ou vide
    public const FORMAT      = 2;   // pas assez long ou pas hexadécimal
    public const LOW_ENTROPY = 3;   // valeur évidente (répétitions, trop peu de caractères distincts)
    public const REUSED      = 4;   // identique à un autre secret de l'application
    public const INPUT       = 5;   // identifiants (établissement, élève, période) invalides

    public static function missing(): self
    {
        return new self(
            'BULLETIN_TOKEN_SECRET n\'est pas défini. Générez-le avec : php -r "echo bin2hex(random_bytes(32));" '
            . 'puis ajoutez-le à .env (ou aux variables d\'environnement du serveur). Aucune valeur par défaut n\'existe.',
            self::MISSING
        );
    }

    public static function invalid(int $code, string $detail): self
    {
        return new self('BULLETIN_TOKEN_SECRET invalide : ' . $detail, $code);
    }
}
