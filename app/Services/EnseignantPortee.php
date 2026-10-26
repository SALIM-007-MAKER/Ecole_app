<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\Academique\Services\TeacherScope;
use Core\Session;

/**
 * Périmètre de consultation d'un ENSEIGNANT sur les pages générales (liste/fiches élèves, liste/fiches classes) :
 * uniquement les classes où il a une affectation réelle (table `enseignements`) pour l'année scolaire en cours,
 * calculée côté serveur. Les autres rôles ne sont pas restreints ici (null).
 *
 * Deny-by-default : un compte enseignant sans fiche professeur ou sans affectation ne voit aucune classe.
 */
final class EnseignantPortee
{
    /** Année scolaire en cours (septembre → août), ex. « 2026-2027 ». */
    public static function anneeScolaire(?int $timestamp = null): string
    {
        $t = $timestamp ?? time();
        $annee = (int)date('Y', $t);
        $debut = (int)date('n', $t) >= 9 ? $annee : $annee - 1;
        return $debut . '-' . ($debut + 1);
    }

    /**
     * @return int[]|null null = aucune restriction (rôle autre qu'enseignant) ; sinon les classes autorisées (éventuellement vide)
     */
    public static function classeIds(?TeacherScope $scope = null): ?array
    {
        $user = Session::getUser();
        if (($user['role'] ?? '') !== 'enseignant') {
            return null;
        }
        $etab = (int)($user['etablissement_id'] ?? 0);
        if ($etab <= 0) {
            return [];
        }
        return ($scope ?? new TeacherScope())->classeIds((int)$user['id'], $etab, self::anneeScolaire());
    }

    /** La classe est-elle consultable par l'utilisateur courant ? */
    public static function peutVoirClasse(?int $classeId): bool
    {
        $ids = self::classeIds();
        return $ids === null || ($classeId !== null && in_array($classeId, $ids, true));
    }
}
