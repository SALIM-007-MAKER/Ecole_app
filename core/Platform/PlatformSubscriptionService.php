<?php

declare(strict_types=1);

namespace Core\Platform;

use Core\Database;
use Core\Logger;
use DateTimeImmutable;
use PDO;

/**
 * Abonnements SaaS (étape 4) : établissement <-> plan, essai, périodes, renouvellement, annulation, échéances.
 *
 * Principes :
 *  - `platform_subscriptions` garde l'historique ; les conditions commerciales (prix, devise, essai, grâce) sont FIGÉES à la création d'une
 *    ligne : modifier un plan ne touche jamais un abonnement existant ;
 *  - `etablissements.statut` reste le verrou d'accès. Une suspension pour expiration est marquée `suspension_origine = 'subscription'` ;
 *    une suspension d'opérateur ('manual', ou NULL pour l'historique) n'est JAMAIS levée ni écrasée par un abonnement ;
 *  - toute opération est exécutée dans UNE transaction (point de sauvegarde si l'appelant en a une) qui contient la trace d'audit globale
 *    en mode strict : audit impossible = opération annulée ;
 *  - aucune conversion de devise ; les souscriptions payantes aux plans en DZD sont refusées ;
 *  - changement de plan immédiat, sans prorata ni remboursement.
 */
final class PlatformSubscriptionService
{
    /** @var array<int,bool> disponibilité (table + colonne) par connexion, uniquement si positive */
    private static array $disponible = [];

    /** @var callable():DateTimeImmutable */
    private $horloge;

    public function __construct(private readonly PDO $pdo, ?callable $horloge = null)
    {
        $this->horloge = $horloge ?? static fn(): DateTimeImmutable => new DateTimeImmutable('now');
    }

    public static function make(): self
    {
        return new self(Database::getInstance()->getConnection());
    }

    /** Migration T067 appliquée (table + colonne) ? Avant elle, le code retombe sur le comportement historique. */
    public static function disponible(PDO $pdo): bool
    {
        $cle = spl_object_id($pdo);
        if (self::$disponible[$cle] ?? false) {
            return true;
        }
        try {
            $n = (int)$pdo->query(
                "SELECT (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_subscriptions')
                      + (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'etablissements' AND COLUMN_NAME = 'suspension_origine')"
            )->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
        return self::$disponible[$cle] = ($n === 2);
    }

    /**
     * Garde de session : l'abonnement courant de l'établissement interdit-il l'accès à cette date ? Sans abonnement, ou avant T067,
     * ou en cas d'erreur technique : false (on n'interdit jamais sur une panne).
     */
    public static function accesInterdit(PDO $pdo, int $etablissementId, ?DateTimeImmutable $maintenant = null): bool
    {
        try {
            if (!self::disponible($pdo)) {
                return false;
            }
            $s = $pdo->prepare("SELECT * FROM platform_subscriptions WHERE etablissement_id = ? AND statut <> 'superseded' ORDER BY id DESC LIMIT 1");
            $s->execute([$etablissementId]);
            $sub = $s->fetch(PDO::FETCH_ASSOC);
            if ($sub === false) {
                return false;
            }
            return !SubscriptionRules::evaluer($sub, SubscriptionRules::aujourdhui($maintenant))['acces'];
        } catch (\Throwable $e) {
            Logger::error('Contrôle d\'abonnement impossible : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Période de grâce en cours ? (bandeau côté école). Lecture seule, jamais d'exception : null si pas en grâce, sans abonnement, base non
     * migrée ou panne.
     *
     * @return ?array{date_fin:string, acces_jusqua:string, jours_restants:int}
     */
    public static function graceEnCours(PDO $pdo, int $etablissementId, ?DateTimeImmutable $maintenant = null): ?array
    {
        try {
            if (!self::disponible($pdo)) {
                return null;
            }
            $s = $pdo->prepare("SELECT * FROM platform_subscriptions WHERE etablissement_id = ? AND statut <> 'superseded' ORDER BY id DESC LIMIT 1");
            $s->execute([$etablissementId]);
            $sub = $s->fetch(PDO::FETCH_ASSOC);
            if ($sub === false || $sub['date_fin'] === null) {
                return null;
            }
            $etat = SubscriptionRules::evaluer($sub, SubscriptionRules::aujourdhui($maintenant));
            if ($etat['etat'] !== 'grace' || !$etat['acces'] || $etat['acces_jusqua'] === null) {
                return null;
            }
            return ['date_fin' => (string)$sub['date_fin'], 'acces_jusqua' => (string)$etat['acces_jusqua'], 'jours_restants' => (int)$etat['jours_restants']];
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Lecture ───────────────────────────────────────────────────────────────────────────────────────────────────────────────

    /** Dernier abonnement non remplacé (courant, ou expiré le plus récent). */
    public function courant(int $etablissementId): ?array
    {
        $s = $this->pdo->prepare("SELECT * FROM platform_subscriptions WHERE etablissement_id = ? AND statut <> 'superseded' ORDER BY id DESC LIMIT 1");
        $s->execute([$etablissementId]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** @return list<array<string,mixed>> du plus récent au plus ancien, avec le nom du plan */
    public function historique(int $etablissementId): array
    {
        $s = $this->pdo->prepare(
            'SELECT s.*, p.nom AS plan_nom, p.code AS plan_code FROM platform_subscriptions s
             JOIN platform_plans p ON p.id = s.plan_id WHERE s.etablissement_id = ? ORDER BY s.id DESC'
        );
        $s->execute([$etablissementId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /** État d'un abonnement à la date de l'horloge (voir SubscriptionRules::evaluer). */
    public function etat(array $sub, ?DateTimeImmutable $jour = null): array
    {
        return SubscriptionRules::evaluer($sub, $jour ?? $this->aujourdhui());
    }

    // ── Opérations ────────────────────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Point d'entrée de l'assignation d'un plan (création d'établissement, formulaire « Assigner un plan »).
     *  - abonnement en cours (essai, actif, grâce) : changement de plan immédiat ;
     *  - aucun abonnement et établissement en essai sans essai consommé : démarrage de l'essai du plan ;
     *  - aucun abonnement sinon (établissement déjà actif avant T067) : abonnement SANS ÉCHÉANCE, aucune date inventée.
     *
     * @return int identifiant de l'abonnement créé
     */
    public function assigner(int $etablissementId, int $planId, int $operatorId): int
    {
        $etab = $this->etablissement($etablissementId);
        $courant = $this->courant($etablissementId);
        if ($courant !== null && in_array($courant['statut'], ['trial', 'active', 'grace'], true)) {
            return $this->changerPlan($etablissementId, $planId, $operatorId);
        }
        if ($courant !== null) {
            throw new SubscriptionException('Cet établissement a un abonnement ' . ($courant['statut'] === 'cancelled' ? 'annulé' : 'expiré') . ' : utilisez « Souscrire / réactiver ».');
        }
        if ($etab['statut'] === 'trial' && !$this->essaiDejaConsomme($etablissementId)) {
            return $this->demarrerEssai($etablissementId, $planId, $operatorId);
        }
        return $this->transaction(function () use ($etablissementId, $planId, $operatorId): int {
            $this->verrouiller($etablissementId);
            $plan = $this->planUtilisable($planId, false);
            $id = $this->inserer([
                'etablissement_id' => $etablissementId, 'plan_id' => $planId, 'statut' => 'active', 'cycle' => 'legacy',
                'date_debut' => $this->aujourdhui()->format('Y-m-d'), 'date_fin' => null, 'trial_ends_at' => null, 'grace_ends_at' => null,
                'prix_applique' => null, 'devise' => $plan['devise'], 'duree_essai_appliquee' => (int)$plan['duree_essai_jours'],
                'jours_grace_appliques' => (int)$plan['jours_grace'], 'precedent_id' => null, 'created_by' => $operatorId ?: null,
            ]);
            $this->copierLimites($etablissementId, $plan);
            $this->finaliser($etablissementId, $id, 'subscription.created', $operatorId, null, ['mode' => 'sans_echeance']);
            return $id;
        });
    }

    /** Démarre l'essai du plan (une seule fois par établissement). Durée 0 : abonnement mensuel actif d'emblée. */
    public function demarrerEssai(int $etablissementId, int $planId, int $operatorId): int
    {
        return $this->transaction(function () use ($etablissementId, $planId, $operatorId): int {
            $this->verrouiller($etablissementId);
            $plan = $this->planUtilisable($planId, true);
            if ($this->essaiDejaConsomme($etablissementId)) {
                throw new SubscriptionException('Cet établissement a déjà bénéficié de sa période d\'essai (une seule par établissement) : souscrivez ou prolongez l\'essai.');
            }
            $courant = $this->courant($etablissementId);
            if ($courant !== null && in_array($courant['statut'], ['trial', 'active', 'grace', 'cancelled'], true)) {
                throw new SubscriptionException('Cet établissement a déjà un abonnement en cours.');
            }
            $jours = (int)$plan['duree_essai_jours'];
            $debut = $this->aujourdhui();
            if ($jours > 0) {
                $fin = SubscriptionRules::finDEssai($debut, $jours)->format('Y-m-d');
                $ligne = ['statut' => 'trial', 'cycle' => 'trial', 'date_fin' => $fin, 'trial_ends_at' => $fin, 'grace_ends_at' => $fin, 'prix_applique' => '0.00'];
            } else {
                $this->devisePayanteAcceptee($plan);
                $fin = SubscriptionRules::finDePeriode($debut, 'monthly')->format('Y-m-d');
                $ligne = ['statut' => 'active', 'cycle' => 'monthly', 'date_fin' => $fin, 'trial_ends_at' => null,
                    'grace_ends_at' => SubscriptionRules::finDeGrace($fin, (int)$plan['jours_grace']), 'prix_applique' => $plan['prix_mensuel']];
            }
            $id = $this->inserer($ligne + [
                'etablissement_id' => $etablissementId, 'plan_id' => $planId, 'date_debut' => $debut->format('Y-m-d'), 'devise' => $plan['devise'],
                'duree_essai_appliquee' => $jours, 'jours_grace_appliques' => (int)$plan['jours_grace'],
                'precedent_id' => $courant['id'] ?? null, 'created_by' => $operatorId ?: null,
            ]);
            $this->copierLimites($etablissementId, $plan);
            $this->finaliser($etablissementId, $id, 'subscription.created', $operatorId, $courant, ['mode' => $jours > 0 ? 'essai' : 'sans_essai']);
            return $id;
        });
    }

    /**
     * Souscription payante (aussi : conversion d'un essai, réactivation après expiration ou annulation). La période démarre aujourd'hui.
     * Refusée si un abonnement payant est déjà en cours (utiliser renouveler / changerPlan).
     */
    public function souscrire(int $etablissementId, int $planId, string $cycle, int $operatorId): int
    {
        if (!in_array($cycle, ['monthly', 'yearly'], true)) {
            throw new SubscriptionException('Cycle de facturation invalide (mensuel ou annuel).');
        }
        return $this->transaction(function () use ($etablissementId, $planId, $cycle, $operatorId): int {
            $this->verrouiller($etablissementId);
            $plan = $this->planUtilisable($planId, false);
            $courant = $this->courant($etablissementId);
            if ($courant !== null && in_array($courant['statut'], ['active', 'grace'], true) && $this->etat($courant)['acces']) {
                throw new SubscriptionException('Cet établissement a déjà un abonnement payant en cours : utilisez « Renouveler » ou « Changer de plan ».');
            }
            $debut = $this->aujourdhui();
            $fin = SubscriptionRules::finDePeriode($debut, $cycle)->format('Y-m-d');
            $id = $this->inserer([
                'etablissement_id' => $etablissementId, 'plan_id' => $planId, 'statut' => 'active', 'cycle' => $cycle,
                'date_debut' => $debut->format('Y-m-d'), 'date_fin' => $fin, 'trial_ends_at' => null,
                'grace_ends_at' => SubscriptionRules::finDeGrace($fin, (int)$plan['jours_grace']),
                'prix_applique' => $cycle === 'yearly' ? $plan['prix_annuel'] : $plan['prix_mensuel'], 'devise' => $plan['devise'],
                'duree_essai_appliquee' => (int)$plan['duree_essai_jours'], 'jours_grace_appliques' => (int)$plan['jours_grace'],
                'precedent_id' => $courant['id'] ?? null, 'created_by' => $operatorId ?: null,
            ]);
            $this->copierLimites($etablissementId, $plan);
            $this->finaliser($etablissementId, $id, 'subscription.created', $operatorId, $courant, ['mode' => 'souscription']);
            return $id;
        });
    }

    /**
     * Renouvelle un abonnement payant en cours (actif ou en grâce) : la nouvelle période commence le lendemain de `date_fin` (aucun trou,
     * aucun chevauchement). Par défaut les conditions ACTUELLES du plan s'appliquent ; `$conserverConditions` garde celles de la période
     * précédente (prix et devise figés, grâce comprise). Abonnement sans échéance : la période démarre aujourd'hui.
     */
    public function renouveler(int $etablissementId, int $operatorId, ?string $cycle = null, bool $conserverConditions = false): int
    {
        return $this->transaction(function () use ($etablissementId, $operatorId, $cycle, $conserverConditions): int {
            $this->verrouiller($etablissementId);
            $courant = $this->courant($etablissementId);
            if ($courant === null) {
                throw new SubscriptionException('Cet établissement n\'a pas d\'abonnement à renouveler.');
            }
            $etat = $this->etat($courant);
            if ($courant['statut'] === 'trial' || $courant['cycle'] === 'trial') {
                throw new SubscriptionException('Un essai ne se renouvelle pas : utilisez « Souscrire ».');
            }
            if (!in_array($courant['statut'], ['active', 'grace'], true) || !in_array($etat['etat'], ['active', 'grace'], true)) {
                throw new SubscriptionException('Cet abonnement est expiré ou annulé : utilisez « Souscrire / réactiver ».');
            }
            $cycle ??= (string)$courant['cycle'];
            if (!in_array($cycle, ['monthly', 'yearly'], true)) {
                throw new SubscriptionException('Précisez le cycle de facturation (mensuel ou annuel) : le cycle précédent est inconnu.');
            }
            $plan = $this->plan((int)$courant['plan_id']);
            if ($plan === null) {
                throw new SubscriptionException('Plan introuvable.');
            }
            if ($conserverConditions) {
                if ($courant['prix_applique'] === null) {
                    throw new SubscriptionException('Les conditions de la période précédente sont inconnues (abonnement repris de l\'existant) : renouvelez avec les conditions actuelles du plan.');
                }
                $prix = (string)$courant['prix_applique'];
                $devise = (string)$courant['devise'];
                $grace = (int)$courant['jours_grace_appliques'];
                if ($courant['cycle'] !== $cycle) {
                    throw new SubscriptionException('Conserver les conditions suppose de garder le même cycle de facturation.');
                }
            } else {
                if ((int)$plan['actif'] !== 1) {
                    throw new SubscriptionException('Le plan est archivé : changez de plan ou conservez les conditions de la période précédente.');
                }
                $prix = $cycle === 'yearly' ? (string)$plan['prix_annuel'] : (string)$plan['prix_mensuel'];
                $devise = (string)$plan['devise'];
                $grace = (int)$plan['jours_grace'];
            }
            $this->devisePayanteAcceptee(['devise' => $devise, 'nom' => $plan['nom']]);

            $debut = $courant['date_fin'] !== null ? SubscriptionRules::date((string)$courant['date_fin'])->modify('+1 day') : $this->aujourdhui();
            $fin = SubscriptionRules::finDePeriode($debut, $cycle)->format('Y-m-d');
            $id = $this->inserer([
                'etablissement_id' => $etablissementId, 'plan_id' => (int)$courant['plan_id'], 'statut' => 'active', 'cycle' => $cycle,
                'date_debut' => $debut->format('Y-m-d'), 'date_fin' => $fin, 'trial_ends_at' => null,
                'grace_ends_at' => SubscriptionRules::finDeGrace($fin, $grace), 'prix_applique' => $prix, 'devise' => $devise,
                'duree_essai_appliquee' => (int)$courant['duree_essai_appliquee'], 'jours_grace_appliques' => $grace,
                'precedent_id' => (int)$courant['id'], 'created_by' => $operatorId ?: null,
            ]);
            $this->finaliser($etablissementId, $id, 'subscription.renewed', $operatorId, $courant, ['conditions_conservees' => $conserverConditions]);
            return $id;
        });
    }

    /**
     * Changement de plan IMMÉDIAT, sans prorata ni remboursement : la nouvelle ligne reprend le statut, le cycle et l'échéance de la période
     * en cours ; prix, devise et grâce viennent du nouveau plan. Hors essai, un plan en DZD est refusé.
     */
    public function changerPlan(int $etablissementId, int $planId, int $operatorId): int
    {
        return $this->transaction(function () use ($etablissementId, $planId, $operatorId): int {
            $this->verrouiller($etablissementId);
            $courant = $this->courant($etablissementId);
            if ($courant === null || !in_array($courant['statut'], ['trial', 'active', 'grace'], true) || !$this->etat($courant)['acces']) {
                throw new SubscriptionException('Aucun abonnement en cours : utilisez « Souscrire / réactiver ».');
            }
            if ((int)$courant['plan_id'] === $planId) {
                throw new SubscriptionException('L\'établissement est déjà sur ce plan.');
            }
            $essai = $courant['cycle'] === 'trial';
            $plan = $this->planUtilisable($planId, $essai);
            $legacy = $courant['cycle'] === 'legacy';
            $cycle = (string)$courant['cycle'];
            $prix = $essai ? '0.00' : ($legacy ? null : ($cycle === 'yearly' ? (string)$plan['prix_annuel'] : (string)$plan['prix_mensuel']));
            $fin = $courant['date_fin'];
            $id = $this->inserer([
                'etablissement_id' => $etablissementId, 'plan_id' => $planId, 'statut' => $courant['statut'], 'cycle' => $cycle,
                'date_debut' => $this->aujourdhui()->format('Y-m-d'), 'date_fin' => $fin, 'trial_ends_at' => $courant['trial_ends_at'],
                'grace_ends_at' => $fin !== null ? ($essai ? $fin : SubscriptionRules::finDeGrace((string)$fin, (int)$plan['jours_grace'])) : null,
                'prix_applique' => $prix, 'devise' => $plan['devise'],
                'duree_essai_appliquee' => (int)$plan['duree_essai_jours'], 'jours_grace_appliques' => $essai ? 0 : (int)$plan['jours_grace'],
                'precedent_id' => (int)$courant['id'], 'created_by' => $operatorId ?: null,
            ]);
            $this->copierLimites($etablissementId, $plan);
            $this->finaliser($etablissementId, $id, 'subscription.plan_changed', $operatorId, $courant, ['prorata' => false]);
            return $id;
        });
    }

    /** Annule l'abonnement en cours : l'accès est maintenu jusqu'à `date_fin` (sans échéance : jusqu'à aujourd'hui inclus), sans renouvellement. */
    public function annuler(int $etablissementId, string $motif, int $operatorId): bool
    {
        $motif = trim($motif);
        if (mb_strlen($motif) < 3 || mb_strlen($motif) > 255) {
            throw new SubscriptionException('Le motif d\'annulation est obligatoire (3 à 255 caractères).');
        }
        return $this->transaction(function () use ($etablissementId, $motif, $operatorId): bool {
            $this->verrouiller($etablissementId);
            $courant = $this->courant($etablissementId);
            if ($courant === null || !in_array($courant['statut'], ['trial', 'active', 'grace'], true)) {
                throw new SubscriptionException('Aucun abonnement en cours à annuler.');
            }
            $fin = $courant['date_fin'] ?? $this->aujourdhui()->format('Y-m-d');
            $this->pdo->prepare("UPDATE platform_subscriptions SET statut = 'cancelled', cancelled_at = NOW(), cancel_reason = ?, date_fin = ? WHERE id = ?")
                ->execute([$motif, $fin, (int)$courant['id']]);
            $this->finaliser($etablissementId, (int)$courant['id'], 'subscription.cancelled', $operatorId, $courant, ['motif' => $motif]);
            return true;
        });
    }

    /**
     * Prolongation EXCEPTIONNELLE d'un essai (y compris expiré). Motif obligatoire, 1 à 90 jours, comptés à partir de la fin d'essai ou
     * d'aujourd'hui si elle est dépassée. Lève la suspension automatique éventuelle, jamais une suspension manuelle.
     */
    public function prolongerEssai(int $etablissementId, int $jours, string $motif, int $operatorId): bool
    {
        $motif = trim($motif);
        if ($jours < 1 || $jours > 90) {
            throw new SubscriptionException('La prolongation doit durer de 1 à 90 jours.');
        }
        if (mb_strlen($motif) < 3 || mb_strlen($motif) > 255) {
            throw new SubscriptionException('Le motif de prolongation est obligatoire (3 à 255 caractères).');
        }
        return $this->transaction(function () use ($etablissementId, $jours, $motif, $operatorId): bool {
            $this->verrouiller($etablissementId);
            $courant = $this->courant($etablissementId);
            if ($courant === null || $courant['cycle'] !== 'trial' || !in_array($courant['statut'], ['trial', 'expired'], true)) {
                throw new SubscriptionException('Seul un essai (en cours ou expiré) peut être prolongé.');
            }
            if ($courant['date_fin'] === null) {
                throw new SubscriptionException('Cet essai n\'a pas d\'échéance : rien à prolonger.');
            }
            $base = max($courant['date_fin'], $this->aujourdhui()->format('Y-m-d'));
            $fin = SubscriptionRules::date($base)->modify("+{$jours} days")->format('Y-m-d');
            $this->pdo->prepare("UPDATE platform_subscriptions SET statut = 'trial', date_fin = ?, trial_ends_at = ?, grace_ends_at = ? WHERE id = ?")
                ->execute([$fin, $fin, $fin, (int)$courant['id']]);
            $this->finaliser($etablissementId, (int)$courant['id'], 'subscription.trial_extended', $operatorId, $courant, ['jours' => $jours, 'motif' => $motif]);
            return true;
        });
    }

    // ── Échéances ─────────────────────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * Applique les transitions dues à la date de l'horloge : actif -> grâce -> expiré, essai -> expiré, annulation arrivée à échéance, avec
     * suspension automatique de l'établissement quand l'accès cesse. Idempotent : une seconde exécution ne change plus rien.
     * Chaque abonnement est traité dans sa propre transaction ; l'échec de l'un n'empêche pas les autres.
     *
     * @return array{transitions:list<array<string,mixed>>, erreurs:int, dry_run:bool}
     */
    public function appliquerEcheances(bool $dryRun = false): array
    {
        $jour = $this->aujourdhui();
        // Dernière ligne non remplacée de chaque établissement seulement (une période expirée ANCIENNE ne doit jamais suspendre un établissement
        // réabonné). Les abonnements déjà « expired » sont inclus : rattrapage d'un établissement resté actif (essai échu repris par T067, script
        // arrêté, suspension levée à tort…). La suspension n'est posée que sur un établissement trial/active : toute suspension existante,
        // manuelle ou historique, est laissée telle quelle.
        $sql = "SELECT s.* FROM platform_subscriptions s JOIN etablissements e ON e.id = s.etablissement_id
                 WHERE e.deleted_at IS NULL
                   AND s.id = (SELECT MAX(x.id) FROM platform_subscriptions x WHERE x.etablissement_id = s.etablissement_id AND x.statut <> 'superseded')
                   AND ((s.statut IN ('trial','active','grace','cancelled') AND s.date_fin IS NOT NULL) OR s.statut = 'expired')
                 ORDER BY s.id";
        $lignes = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $res = ['transitions' => [], 'erreurs' => 0, 'dry_run' => $dryRun];
        foreach ($lignes as $sub) {
            $etat = SubscriptionRules::evaluer($sub, $jour);
            $cible = $etat['etat'];
            $enCours = $sub['statut'] === 'cancelled';
            $etab = $this->pdo->query('SELECT statut, suspension_origine FROM etablissements WHERE id = ' . (int)$sub['etablissement_id'])->fetch(PDO::FETCH_ASSOC);
            $changeStatut = !$enCours && $cible !== $sub['statut'];
            $doitSuspendre = !$etat['acces'] && in_array($etab['statut'] ?? '', ['trial', 'active'], true);
            if (!$changeStatut && !$doitSuspendre) {
                continue;
            }
            $res['transitions'][] = [
                'abonnement_id' => (int)$sub['id'], 'etablissement_id' => (int)$sub['etablissement_id'], 'de' => $sub['statut'], 'vers' => $changeStatut ? $cible : $sub['statut'],
                'suspension_automatique' => $doitSuspendre, 'rattrapage' => !$changeStatut,
            ];
            if ($dryRun) {
                continue;
            }
            try {
                $this->transaction(function () use ($sub, $cible, $changeStatut, $enCours): void {
                    $this->verrouiller((int)$sub['etablissement_id']);
                    $frais = $this->pdo->prepare('SELECT * FROM platform_subscriptions WHERE id = ? FOR UPDATE');
                    $frais->execute([(int)$sub['id']]);
                    $courante = $frais->fetch(PDO::FETCH_ASSOC);
                    if ($courante === false || $courante['statut'] !== $sub['statut']) {
                        return;   // modifiée entre-temps par un opérateur
                    }
                    if ($changeStatut) {
                        $this->pdo->prepare('UPDATE platform_subscriptions SET statut = ? WHERE id = ?')->execute([$cible, (int)$sub['id']]);
                    }
                    $action = $enCours ? 'subscription.cancel_effective' : ($cible === 'grace' ? 'subscription.grace_started' : 'subscription.expired');
                    $this->finaliser((int)$sub['etablissement_id'], (int)$sub['id'], $action, 0, $courante, ['automatique' => true, 'rattrapage' => !$changeStatut]);
                });
            } catch (\Throwable $e) {
                $res['erreurs']++;
                Logger::error("Échéance d'abonnement {$sub['id']} non appliquée : " . get_class($e) . ' ' . $e->getMessage());
            }
        }
        return $res;
    }

    // ── Interne ───────────────────────────────────────────────────────────────────────────────────────────────────────────────

    private function aujourdhui(): DateTimeImmutable
    {
        return SubscriptionRules::aujourdhui(($this->horloge)());
    }

    /** @return array<string,mixed> */
    private function etablissement(int $id): array
    {
        $s = $this->pdo->prepare('SELECT * FROM etablissements WHERE id = ? AND deleted_at IS NULL');
        $s->execute([$id]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if ($r === false) {
            throw new SubscriptionException('Établissement introuvable.');
        }
        return $r;
    }

    /** Verrou de ligne sur l'établissement : sérialise les opérations concurrentes d'un même établissement. */
    private function verrouiller(int $id): void
    {
        $s = $this->pdo->prepare('SELECT id FROM etablissements WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
        $s->execute([$id]);
        if ($s->fetchColumn() === false) {
            throw new SubscriptionException('Établissement introuvable.');
        }
    }

    private function plan(int $id): ?array
    {
        $s = $this->pdo->prepare('SELECT * FROM platform_plans WHERE id = ?');
        $s->execute([$id]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** Plan assignable (existant, non archivé) ; hors essai, devise acceptée pour une souscription payante. */
    private function planUtilisable(int $planId, bool $essai): array
    {
        $plan = $this->plan($planId);
        if ($plan === null || (int)$plan['actif'] !== 1) {
            throw new SubscriptionException('Plan introuvable ou archivé : un plan archivé ne peut plus être assigné.');
        }
        if (!$essai) {
            $this->devisePayanteAcceptee($plan);
        }
        return $plan;
    }

    private function devisePayanteAcceptee(array $plan): void
    {
        if (in_array(strtoupper((string)$plan['devise']), SubscriptionRules::DEVISES_REFUSEES, true)) {
            throw new SubscriptionException('Le plan « ' . $plan['nom'] . ' » est en ' . $plan['devise'] . ' : aucune souscription payante n\'est acceptée dans cette devise. Corrigez la devise et les prix du plan (aucune conversion automatique).');
        }
    }

    private function essaiDejaConsomme(int $etablissementId): bool
    {
        $s = $this->pdo->prepare("SELECT COUNT(*) FROM platform_subscriptions WHERE etablissement_id = ? AND cycle = 'trial'");
        $s->execute([$etablissementId]);
        return (int)$s->fetchColumn() > 0;
    }

    /** Insère une période ; le courant éventuel est d'abord marqué « superseded » (unicité du courant), son id reste dans precedent_id. @param array<string,mixed> $v */
    private function inserer(array $v): int
    {
        $this->pdo->prepare("UPDATE platform_subscriptions SET statut = 'superseded' WHERE etablissement_id = ? AND statut IN ('trial','active','grace','cancelled')")
            ->execute([(int)$v['etablissement_id']]);
        $colonnes = ['etablissement_id', 'plan_id', 'statut', 'cycle', 'date_debut', 'date_fin', 'trial_ends_at', 'grace_ends_at', 'prix_applique', 'devise',
            'duree_essai_appliquee', 'jours_grace_appliques', 'precedent_id', 'created_by'];
        $sql = 'INSERT INTO platform_subscriptions (' . implode(',', $colonnes) . ') VALUES (' . implode(',', array_fill(0, count($colonnes), '?')) . ')';
        $this->pdo->prepare($sql)->execute(array_map(static fn(string $c) => $v[$c] ?? null, $colonnes));
        return (int)$this->pdo->lastInsertId();
    }

    private function copierLimites(int $etablissementId, array $plan): void
    {
        $this->pdo->prepare('UPDATE etablissements SET storage_quota_mb = ?, max_users = ?, max_eleves = ? WHERE id = ?')
            ->execute([(int)$plan['storage_quota_mb'], (int)$plan['max_users'], (int)$plan['max_eleves'], $etablissementId]);
    }

    /**
     * Fin commune de toute opération : recopie l'abonnement sur l'établissement (miroir), suspend ou réactive AUTOMATIQUEMENT selon l'accès,
     * puis écrit la trace d'audit stricte dans la transaction.
     *
     * @param ?array<string,mixed> $avant abonnement avant l'opération
     * @param array<string,mixed> $extra
     */
    private function finaliser(int $etablissementId, int $subId, string $action, int $operatorId, ?array $avant, array $extra = []): void
    {
        $s = $this->pdo->prepare('SELECT * FROM platform_subscriptions WHERE id = ?');
        $s->execute([$subId]);
        $sub = $s->fetch(PDO::FETCH_ASSOC);
        $etab = $this->etablissement($etablissementId);
        $etat = SubscriptionRules::evaluer($sub, $this->aujourdhui());

        $this->pdo->prepare('UPDATE etablissements SET plan_id = ?, plan_expires_at = ?, trial_ends_at = COALESCE(?, trial_ends_at) WHERE id = ?')
            ->execute([
                (int)$sub['plan_id'],
                $sub['cycle'] === 'trial' ? null : $etat['acces_jusqua'],
                $sub['cycle'] === 'trial' ? $sub['trial_ends_at'] : null,
                $etablissementId,
            ]);

        $suspension = 'inchangee';
        if ($etat['acces']) {
            $cible = $etat['etat'] === 'trial' ? 'trial' : 'active';
            if ($etab['statut'] === 'suspended' && $etab['suspension_origine'] === 'subscription') {
                $this->pdo->prepare("UPDATE etablissements SET statut = ?, suspension_origine = NULL, suspended_at = NULL, suspension_reason = NULL,
                        activated_at = COALESCE(activated_at, NOW()) WHERE id = ? AND statut = 'suspended' AND suspension_origine = 'subscription'")
                    ->execute([$cible, $etablissementId]);
                $suspension = 'levee_automatique';
            } else {
                $this->pdo->prepare("UPDATE etablissements SET statut = ?, activated_at = IF(? = 'active', COALESCE(activated_at, NOW()), activated_at)
                        WHERE id = ? AND statut IN ('trial','active') AND statut <> ?")
                    ->execute([$cible, $cible, $etablissementId, $cible]);
                if ($etab['statut'] === 'suspended') {
                    $suspension = 'suspension_manuelle_conservee';
                }
            }
        } elseif (in_array($etab['statut'], ['trial', 'active'], true)) {
            $this->pdo->prepare("UPDATE etablissements SET statut = 'suspended', suspended_at = NOW(), suspension_reason = ?, suspension_origine = 'subscription'
                    WHERE id = ? AND statut IN ('trial','active')")
                ->execute(['Abonnement expiré', $etablissementId]);
            $suspension = 'suspension_automatique';
        } elseif ($etab['statut'] === 'suspended') {
            $suspension = 'suspension_existante_conservee';
        }

        $apres = $this->instantane($subId);
        try {
            PlatformAuditService::make($this->pdo)->recordPar($operatorId, $action, [
                'etablissement_id' => $etablissementId, 'entity_type' => 'subscription', 'entity_id' => $subId,
                'avant' => $avant !== null ? $this->resume($avant) : null,
                'apres' => $apres + ['etat' => $etat['etat'], 'suspension' => $suspension, 'statut_etablissement_avant' => $etab['statut']] + $extra,
                'strict' => true,
            ]);
        } catch (\Throwable $e) {
            Logger::security('PLATFORM_SUBSCRIPTION_AUDIT_ABORTED', "{$action} etablissement_id={$etablissementId} par operator_id={$operatorId} : journal d'audit indisponible (" . get_class($e) . ')');
            throw SubscriptionException::audit('Opération annulée : la trace d\'audit n\'a pas pu être enregistrée. Aucune modification n\'a été appliquée.', $e);
        }
    }

    /** @return array<string,mixed> */
    private function instantane(int $id): array
    {
        $s = $this->pdo->prepare('SELECT * FROM platform_subscriptions WHERE id = ?');
        $s->execute([$id]);
        return $this->resume($s->fetch(PDO::FETCH_ASSOC) ?: []);
    }

    /** @param array<string,mixed> $sub @return array<string,mixed> */
    private function resume(array $sub): array
    {
        $garde = ['id', 'plan_id', 'statut', 'cycle', 'date_debut', 'date_fin', 'trial_ends_at', 'grace_ends_at', 'prix_applique', 'devise', 'jours_grace_appliques', 'precedent_id', 'cancel_reason'];
        return array_intersect_key($sub, array_flip($garde));
    }

    /**
     * Exécute $travail dans une transaction ; toute exception l'annule puis est relancée. Transaction englobante : point de sauvegarde.
     * Une erreur technique est journalisée avec une référence et remplacée par un message générique (jamais de message SQL à l'opérateur).
     *
     * @template T
     * @param callable():T $travail
     * @return T
     */
    private function transaction(callable $travail): mixed
    {
        $point = null;
        if ($this->pdo->inTransaction()) {
            $point = 'sub_' . bin2hex(random_bytes(4));
            $this->pdo->exec("SAVEPOINT $point");
        } else {
            $this->pdo->beginTransaction();
        }
        try {
            $r = $travail();
            $point !== null ? $this->pdo->exec("RELEASE SAVEPOINT $point") : $this->pdo->commit();
            return $r;
        } catch (\Throwable $e) {
            if ($point !== null) {
                $this->pdo->exec("ROLLBACK TO SAVEPOINT $point");
            } elseif ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof SubscriptionException) {
                throw $e;
            }
            $ref = strtoupper(bin2hex(random_bytes(3)));
            Logger::error("Abonnement [{$ref}] " . get_class($e) . ' : ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')');
            throw new SubscriptionException("Opération impossible pour une raison technique (référence {$ref}). Aucune modification n'a été appliquée.", 0, $e);
        }
    }
}
