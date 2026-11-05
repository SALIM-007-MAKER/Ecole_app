<?php

declare(strict_types=1);

namespace Core\Platform\WebAuthn;

/**
 * Décodeur CBOR (RFC 8949) minimal, limité à ce que WebAuthn utilise : entiers, chaînes d'octets et de texte, tableaux, tables, booléens et null.
 *
 * Il lit des données venant du navigateur : tout est borné (profondeur, longueurs vérifiées contre les octets restants) et toute anomalie
 * lève une WebAuthnException. Les flottants, les étiquettes et les longueurs indéfinies sont refusés.
 */
final class Cbor
{
    private const PROFONDEUR_MAX = 8;

    /**
     * Décode un élément CBOR à partir de $offset ; $offset est avancé après l'élément.
     *
     * @throws WebAuthnException
     */
    public static function decode(string $donnees, int &$offset = 0, int $profondeur = 0): mixed
    {
        if ($profondeur > self::PROFONDEUR_MAX) {
            throw new WebAuthnException('CBOR trop imbriqué.');
        }
        if ($offset >= strlen($donnees)) {
            throw new WebAuthnException('CBOR tronqué.');
        }
        $octet = ord($donnees[$offset++]);
        $type = $octet >> 5;
        $info = $octet & 0x1F;

        if ($type === 7) {
            return match ($info) {
                20 => false,
                21 => true,
                22 => null,
                default => throw new WebAuthnException('Valeur simple CBOR non prise en charge.'),
            };
        }
        $valeur = self::longueur($donnees, $offset, $info);

        switch ($type) {
            case 0:
                return $valeur;
            case 1:
                return -1 - $valeur;
            case 2:
            case 3:
                if ($valeur > strlen($donnees) - $offset) {
                    throw new WebAuthnException('CBOR tronqué.');
                }
                $texte = substr($donnees, $offset, $valeur);
                $offset += $valeur;
                return $texte;
            case 4:
                if ($valeur > strlen($donnees) - $offset) {      // chaque élément occupe au moins un octet
                    throw new WebAuthnException('CBOR tronqué.');
                }
                $liste = [];
                for ($i = 0; $i < $valeur; $i++) {
                    $liste[] = self::decode($donnees, $offset, $profondeur + 1);
                }
                return $liste;
            case 5:
                if ($valeur * 2 > strlen($donnees) - $offset) {
                    throw new WebAuthnException('CBOR tronqué.');
                }
                $table = [];
                for ($i = 0; $i < $valeur; $i++) {
                    $cle = self::decode($donnees, $offset, $profondeur + 1);
                    if (!is_int($cle) && !is_string($cle)) {
                        throw new WebAuthnException('Clé CBOR non prise en charge.');
                    }
                    if (array_key_exists($cle, $table)) {
                        throw new WebAuthnException('Clé CBOR en double.');
                    }
                    $table[$cle] = self::decode($donnees, $offset, $profondeur + 1);
                }
                return $table;
            default:
                throw new WebAuthnException('Type CBOR non pris en charge.');
        }
    }

    /** Décode un document complet : aucun octet ne doit rester après l'élément. */
    public static function decodeComplet(string $donnees): mixed
    {
        $offset = 0;
        $v = self::decode($donnees, $offset);
        if ($offset !== strlen($donnees)) {
            throw new WebAuthnException('Données CBOR en trop.');
        }
        return $v;
    }

    private static function longueur(string $d, int &$offset, int $info): int
    {
        if ($info < 24) {
            return $info;
        }
        $taille = match ($info) {
            24 => 1, 25 => 2, 26 => 4, 27 => 8,
            default => throw new WebAuthnException('Longueur CBOR non prise en charge.'),
        };
        if ($taille > strlen($d) - $offset) {
            throw new WebAuthnException('CBOR tronqué.');
        }
        $v = 0;
        for ($i = 0; $i < $taille; $i++) {
            $v = ($v << 8) | ord($d[$offset + $i]);
        }
        $offset += $taille;
        if ($v < 0) {                           // dépassement des entiers PHP sur 64 bits
            throw new WebAuthnException('Entier CBOR trop grand.');
        }
        return $v;
    }
}
