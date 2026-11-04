<?php

declare(strict_types=1);

namespace Core\Platform;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Règles pures des abonnements (aucun accès base) : fuseau, calcul des dates, évaluation de l'état à une date donnée.
 *
 * Fuseau : `PLATFORM_TIMEZONE` (défaut Africa/Niamey). Les dates sont des jours calendaires locaux ; `date_fin` est INCLUSIVE : l'abonnement
 * est valide jusqu'à la fin de ce jour, la bascule se fait à minuit local, le lendemain.
 *
 * États dérivés d'un abonnement non terminal, au jour D (date_fin = F, grâce = G jours) :
 *   essai  : D <= F -> trial ; sinon expired (pas de grâce pour un essai)
 *   payant : D <= F -> active ; D <= F + G -> grace (accès maintenu) ; sinon expired
 *   annulé : accès jusqu'à F inclus, puis plus d'accès (le statut reste 'cancelled')
 *   F NULL : sans échéance, jamais expiré (sauf annulé sans date : aucun accès)
 */
final class SubscriptionRules
{
    public const ENV_FUSEAU = 'PLATFORM_TIMEZONE';
    public const FUSEAU_DEFAUT = 'Africa/Niamey';
    /** Devises dont la souscription payante est refusée (aucune conversion automatique). */
    public const DEVISES_REFUSEES = ['DZD'];

    public static function fuseau(): DateTimeZone
    {
        $nom = trim((string)($_ENV[self::ENV_FUSEAU] ?? getenv(self::ENV_FUSEAU) ?: ''));
        try {
            return new DateTimeZone($nom !== '' ? $nom : self::FUSEAU_DEFAUT);
        } catch (\Throwable) {
            return new DateTimeZone(self::FUSEAU_DEFAUT);
        }
    }

    /** Jour calendaire local (minuit) correspondant à $instant, ou à maintenant. */
    public static function aujourdhui(?DateTimeImmutable $instant = null): DateTimeImmutable
    {
        return ($instant ?? new DateTimeImmutable('now'))->setTimezone(self::fuseau())->setTime(0, 0, 0);
    }

    public static function date(string $ymd): DateTimeImmutable
    {
        return new DateTimeImmutable($ymd . ' 00:00:00', self::fuseau());
    }

    /** Ajoute des mois en ramenant le jour à la fin du mois d'arrivée si nécessaire (31 janvier + 1 mois = 28/29 février). */
    public static function ajouterMois(DateTimeImmutable $d, int $mois): DateTimeImmutable
    {
        $jour = (int)$d->format('j');
        $premier = $d->modify('first day of this month')->modify(($mois >= 0 ? '+' : '') . $mois . ' months');
        $dernier = (int)$premier->format('t');
        return $premier->setDate((int)$premier->format('Y'), (int)$premier->format('n'), min($jour, $dernier));
    }

    /** Dernier jour (inclus) d'une période commençant à $debut : mensuel = +1 mois - 1 jour ; annuel = +12 mois - 1 jour. */
    public static function finDePeriode(DateTimeImmutable $debut, string $cycle): DateTimeImmutable
    {
        return match ($cycle) {
            'monthly' => self::ajouterMois($debut, 1)->modify('-1 day'),
            'yearly'  => self::ajouterMois($debut, 12)->modify('-1 day'),
            default   => throw new \InvalidArgumentException("Cycle de facturation inconnu : {$cycle}"),
        };
    }

    /** Dernier jour d'un essai de $jours jours démarrant à $debut (le jour de départ compte : 14 jours = débute D, finit D+13). */
    public static function finDEssai(DateTimeImmutable $debut, int $jours): DateTimeImmutable
    {
        return $debut->modify('+' . max(0, $jours - 1) . ' days');
    }

    public static function finDeGrace(string $dateFin, int $joursGrace): string
    {
        return self::date($dateFin)->modify('+' . max(0, $joursGrace) . ' days')->format('Y-m-d');
    }

    /**
     * État d'un abonnement à la date $aujourdhui.
     *
     * @param array<string,mixed> $sub ligne de platform_subscriptions (statut, cycle, date_fin, jours_grace_appliques)
     * @return array{etat:string, acces:bool, acces_jusqua:?string, en_grace:bool, jours_restants:?int, sans_echeance:bool}
     */
    public static function evaluer(array $sub, DateTimeImmutable $aujourdhui): array
    {
        $statut = (string)$sub['statut'];
        $fin = isset($sub['date_fin']) && $sub['date_fin'] !== '' ? (string)$sub['date_fin'] : null;
        $j = $aujourdhui->format('Y-m-d');
        $res = ['etat' => $statut, 'acces' => false, 'acces_jusqua' => null, 'en_grace' => false, 'jours_restants' => null, 'sans_echeance' => $fin === null];

        if ($statut === 'superseded' || $statut === 'expired') {
            return $res;
        }
        if ($statut === 'cancelled') {
            $res['acces_jusqua'] = $fin;
            $res['acces'] = $fin !== null && $j <= $fin;
            $res['jours_restants'] = $fin !== null ? self::joursEntre($aujourdhui, $fin) : null;
            return $res;
        }

        $essai = $statut === 'trial' || ($sub['cycle'] ?? '') === 'trial';
        if ($fin === null) {
            return ['etat' => $essai ? 'trial' : 'active', 'acces' => true] + $res;
        }
        $grace = $essai ? $fin : self::finDeGrace($fin, (int)($sub['jours_grace_appliques'] ?? 0));
        $res['acces_jusqua'] = $grace;
        if ($j <= $fin) {
            $res['etat'] = $essai ? 'trial' : 'active';
            $res['acces'] = true;
            $res['jours_restants'] = self::joursEntre($aujourdhui, $fin);
        } elseif ($j <= $grace) {
            $res['etat'] = 'grace';
            $res['acces'] = true;
            $res['en_grace'] = true;
            $res['jours_restants'] = self::joursEntre($aujourdhui, $grace);
        } else {
            $res['etat'] = 'expired';
        }
        return $res;
    }

    private static function joursEntre(DateTimeImmutable $de, string $ymd): int
    {
        return (int)$de->diff(self::date($ymd))->format('%r%a');
    }
}
