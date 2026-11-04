<?php

declare(strict_types=1);

namespace Core\Platform;

use Core\Database;
use DateTimeImmutable;
use PDO;

/**
 * Revenus de la plateforme (étape 5) : agrégats des encaissements manuels, SANS jamais additionner deux devises (aucune conversion) : chaque
 * total est donné par devise. Seuls les paiements `recorded` comptent ; les annulés sont comptés à part.
 *
 * Lecture seule. Le périmètre (dates) porte sur `date_paiement`, bornes incluses.
 */
final class PlatformRevenueService
{
    public const EXPORT_MAX = 50000;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function make(): self
    {
        return new self(Database::getInstance()->getConnection());
    }

    /** Normalise une période ; bornes invalides ou inversées : repli sur l'année en cours (début -> aujourd'hui). */
    public static function periode(?string $du, ?string $au, ?DateTimeImmutable $aujourdhui = null): array
    {
        $jour = SubscriptionRules::aujourdhui($aujourdhui);
        $valide = static function (?string $d): ?string {
            $d = trim((string)$d);
            $x = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
            return $x !== false && $x->format('Y-m-d') === $d && (int)$x->format('Y') >= 2000 ? $d : null;
        };
        $du = $valide($du) ?? $jour->format('Y-01-01');
        $au = $valide($au) ?? $jour->format('Y-m-d');
        if ($du > $au) {
            [$du, $au] = [$au, $du];
        }
        return ['du' => $du, 'au' => $au];
    }

    /**
     * @return array{
     *   totaux: array<string,array{total:string,nb:int}>,
     *   mensuel: array<string,array<string,string>>,
     *   par_mode: array<string,array<string,string>>,
     *   par_plan: array<string,array<string,string>>,
     *   annules: array<string,array{total:string,nb:int}>,
     *   a_encaisser: array<string,string>
     * } clés des sous-tableaux : devise, puis mois (AAAA-MM) / mode / plan
     */
    public function synthese(string $du, string $au): array
    {
        $totaux = [];
        $q = $this->pdo->prepare("SELECT devise, SUM(montant) AS total, COUNT(*) AS nb FROM platform_payments WHERE statut = 'recorded' AND date_paiement BETWEEN ? AND ? GROUP BY devise ORDER BY devise");
        $q->execute([$du, $au]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $totaux[$l['devise']] = ['total' => $this->dec($l['total']), 'nb' => (int)$l['nb']];
        }

        $mensuel = [];
        $q = $this->pdo->prepare("SELECT devise, DATE_FORMAT(date_paiement, '%Y-%m') AS mois, SUM(montant) AS total FROM platform_payments
                                   WHERE statut = 'recorded' AND date_paiement BETWEEN ? AND ? GROUP BY devise, mois ORDER BY mois");
        $q->execute([$du, $au]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $mensuel[$l['devise']][$l['mois']] = $this->dec($l['total']);
        }

        $parMode = [];
        $q = $this->pdo->prepare("SELECT devise, mode, SUM(montant) AS total FROM platform_payments
                                   WHERE statut = 'recorded' AND date_paiement BETWEEN ? AND ? GROUP BY devise, mode ORDER BY total DESC");
        $q->execute([$du, $au]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $parMode[$l['devise']][$l['mode']] = $this->dec($l['total']);
        }

        $parPlan = [];
        $q = $this->pdo->prepare("SELECT p.devise, pl.nom AS plan, SUM(p.montant) AS total FROM platform_payments p
                                    JOIN platform_subscriptions sb ON sb.id = p.subscription_id JOIN platform_plans pl ON pl.id = sb.plan_id
                                   WHERE p.statut = 'recorded' AND p.date_paiement BETWEEN ? AND ? GROUP BY p.devise, pl.id, pl.nom ORDER BY total DESC");
        $q->execute([$du, $au]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $parPlan[$l['devise']][$l['plan']] = $this->dec($l['total']);
        }

        $annules = [];
        $q = $this->pdo->prepare("SELECT devise, SUM(montant) AS total, COUNT(*) AS nb FROM platform_payments WHERE statut = 'cancelled' AND date_paiement BETWEEN ? AND ? GROUP BY devise");
        $q->execute([$du, $au]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $annules[$l['devise']] = ['total' => $this->dec($l['total']), 'nb' => (int)$l['nb']];
        }

        // Reste à encaisser sur les périodes en cours (prix figé connu, essai exclu). Indépendant de la période choisie.
        $aEncaisser = [];
        $q = $this->pdo->query(
            "SELECT sb.devise, SUM(GREATEST(sb.prix_applique - COALESCE(x.paye, 0), 0)) AS reste
               FROM platform_subscriptions sb
          LEFT JOIN (SELECT subscription_id, SUM(montant) AS paye FROM platform_payments WHERE statut = 'recorded' GROUP BY subscription_id) x ON x.subscription_id = sb.id
              WHERE sb.statut IN ('active','grace','cancelled') AND sb.cycle <> 'trial' AND sb.prix_applique IS NOT NULL
              GROUP BY sb.devise HAVING reste > 0"
        );
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $aEncaisser[$l['devise']] = $this->dec($l['reste']);
        }

        return ['totaux' => $totaux, 'mensuel' => $mensuel, 'par_mode' => $parMode, 'par_plan' => $parPlan, 'annules' => $annules, 'a_encaisser' => $aEncaisser];
    }

    /** @return list<array<string,mixed>> derniers paiements de la période (tous statuts) */
    public function paiements(string $du, string $au, int $limite = 200): array
    {
        $q = $this->pdo->prepare(
            'SELECT p.id, p.reference, p.date_paiement, p.montant, p.devise, p.mode, p.reference_externe, p.statut, e.nom AS etablissement_nom, p.etablissement_id, pl.nom AS plan_nom
               FROM platform_payments p JOIN etablissements e ON e.id = p.etablissement_id
               JOIN platform_subscriptions sb ON sb.id = p.subscription_id JOIN platform_plans pl ON pl.id = sb.plan_id
              WHERE p.date_paiement BETWEEN ? AND ? ORDER BY p.date_paiement DESC, p.id DESC LIMIT ' . max(1, min($limite, 1000))
        );
        $q->execute([$du, $au]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return \Generator<int,array<string,mixed>> lignes d'export (tous statuts, l'annulation est visible), plafonnées */
    public function iterer(string $du, string $au): \Generator
    {
        $q = $this->pdo->prepare(
            'SELECT p.reference, p.date_paiement, e.nom AS etablissement, pl.nom AS plan, sb.cycle, p.montant, p.devise, p.mode, p.reference_externe,
                    p.statut, p.cancel_reason, p.note
               FROM platform_payments p JOIN etablissements e ON e.id = p.etablissement_id
               JOIN platform_subscriptions sb ON sb.id = p.subscription_id JOIN platform_plans pl ON pl.id = sb.plan_id
              WHERE p.date_paiement BETWEEN ? AND ? ORDER BY p.date_paiement, p.id LIMIT ' . self::EXPORT_MAX
        );
        $q->execute([$du, $au]);
        while (($l = $q->fetch(PDO::FETCH_ASSOC)) !== false) {
            yield $l;
        }
    }

    private function dec(mixed $v): string
    {
        return number_format((float)$v, 2, '.', '');
    }
}
