<?php

declare(strict_types=1);

namespace Core\Platform;

/**
 * Politique de mot de passe des opérateurs de la plateforme (plus stricte que celle des établissements).
 *
 * Règles : 14 caractères minimum, au moins 3 familles parmi (minuscules, majuscules, chiffres, symboles), ne contient ni l'adresse e-mail,
 * ni la partie locale de l'adresse, ni le nom, n'est pas un mot de passe courant ni une suite triviale. Le générateur produit des mots de
 * passe aléatoires (CSPRNG) qui respectent toujours cette politique.
 */
final class PlatformPasswordPolicy
{
    public const LONGUEUR_MIN = 14;

    /** Mots de passe courants / prévisibles refusés (comparaison insensible à la casse, après retrait des séparateurs). */
    private const COURANTS = [
        'password', 'motdepasse', 'azerty', 'qwerty', 'admin', 'administrateur', 'edunova', 'scolaris', 'ecoleapp',
        'changeme', 'changemenow', 'letmein', 'welcome', 'bienvenue', 'superadmin', 'operateur', 'platform', 'plateforme',
        '123456', '12345678', '0123456789', 'iloveyou', 'abc123',
    ];

    /** @return list<string> messages d'erreur (vide = mot de passe accepté) */
    public static function valider(string $motDePasse, string $email = '', string $nom = ''): array
    {
        $erreurs = [];
        if (mb_strlen($motDePasse) < self::LONGUEUR_MIN) {
            $erreurs[] = 'Le mot de passe doit contenir au moins ' . self::LONGUEUR_MIN . ' caractères.';
        }
        if (mb_strlen($motDePasse) > 200) {
            $erreurs[] = 'Le mot de passe est trop long (200 caractères maximum).';
        }

        $familles = (int)(preg_match('/[a-z]/', $motDePasse) === 1)
                  + (int)(preg_match('/[A-Z]/', $motDePasse) === 1)
                  + (int)(preg_match('/\d/', $motDePasse) === 1)
                  + (int)(preg_match('/[^a-zA-Z0-9]/', $motDePasse) === 1);
        if ($familles < 3) {
            $erreurs[] = 'Le mot de passe doit mélanger au moins 3 types de caractères (minuscules, majuscules, chiffres, symboles).';
        }

        $bas = mb_strtolower($motDePasse);
        $compact = preg_replace('/[^a-z0-9]/', '', $bas) ?? '';
        foreach (self::COURANTS as $commun) {
            if ($compact === $commun || str_contains($compact, $commun)) {
                $erreurs[] = 'Le mot de passe contient un mot courant ou prévisible.';
                break;
            }
        }
        if (preg_match('/(.)\1{3,}/u', $motDePasse) === 1) {
            $erreurs[] = 'Le mot de passe ne doit pas répéter le même caractère plus de trois fois de suite.';
        }
        if (self::estSuiteTriviale($compact)) {
            $erreurs[] = 'Le mot de passe ne doit pas être une suite triviale.';
        }

        $local = mb_strtolower(explode('@', $email)[0] ?? '');
        if ($email !== '' && (str_contains($bas, mb_strtolower($email)) || (mb_strlen($local) >= 3 && str_contains($bas, $local)))) {
            $erreurs[] = 'Le mot de passe ne doit pas contenir votre adresse e-mail.';
        }
        foreach (preg_split('/\s+/', mb_strtolower(trim($nom))) ?: [] as $mot) {
            if (mb_strlen($mot) >= 4 && str_contains($bas, $mot)) {
                $erreurs[] = 'Le mot de passe ne doit pas contenir votre nom.';
                break;
            }
        }
        return array_values(array_unique($erreurs));
    }

    /** Mot de passe aléatoire (CSPRNG) qui respecte toujours la politique. */
    public static function generer(int $longueur = 24): string
    {
        $longueur = max(self::LONGUEUR_MIN + 4, $longueur);
        $jeux = ['abcdefghjkmnpqrstuvwxyz', 'ABCDEFGHJKMNPQRSTUVWXYZ', '23456789', '!#$%*+-=?@_'];
        do {
            $caracteres = [];
            foreach ($jeux as $jeu) {                       // au moins un caractère de chaque famille
                $caracteres[] = $jeu[random_int(0, strlen($jeu) - 1)];
            }
            $tout = implode('', $jeux);
            while (count($caracteres) < $longueur) {
                $caracteres[] = $tout[random_int(0, strlen($tout) - 1)];
            }
            for ($i = count($caracteres) - 1; $i > 0; $i--) { // mélange de Fisher-Yates (CSPRNG)
                $j = random_int(0, $i);
                [$caracteres[$i], $caracteres[$j]] = [$caracteres[$j], $caracteres[$i]];
            }
            $mdp = implode('', $caracteres);
        } while (self::valider($mdp) !== []);
        return $mdp;
    }

    private static function estSuiteTriviale(string $compact): bool
    {
        if ($compact === '') {
            return false;
        }
        foreach (['0123456789', 'abcdefghijklmnopqrstuvwxyz', 'azertyuiop', 'qwertyuiop'] as $suite) {
            if (str_contains($suite, $compact) && strlen($compact) >= 8) {
                return true;
            }
        }
        return false;
    }
}
