<?php

declare(strict_types=1);

namespace Core\Platform;

use Core\Database;
use Core\Logger;
use DateTimeImmutable;
use PDO;

/**
 * Paiements manuels des abonnements SaaS (étape 5) : saisie d'un encaissement, annulation, soldes par période.
 *
 * Principes :
 *  - un paiement est rattaché à UNE période d'abonnement de l'établissement ; montant et devise sont ceux de l'encaissement réel, la devise
 *    est celle de la période (aucune conversion) ;
 *  - jamais de suppression : une erreur de saisie s'annule (motif obligatoire), la ligne et son historique restent (déclencheurs en base) ;
 *  - un paiement NE modifie PAS l'abonnement : le renouvellement reste une décision explicite (étape 4). Les deux opérations sont distinctes ;
 *  - le total encaissé d'une période ne peut pas dépasser son prix figé (si connu) : le solde est vérifié sous verrou de la ligne d'abonnement,
 *    donc deux saisies simultanées ne peuvent pas le dépasser ;
 *  - chaque opération est une transaction (point de sauvegarde si l'appelant en a une) contenant la trace d'audit globale en mode strict :
 *    audit impossible = opération annulée.
 */
final class PlatformPaymentService
{
    /** Modes de paiement acceptés (clé stockée => libellé). Ajouter un fournisseur ne demande aucune migration. */
    public const MODES = [
        'especes'      => 'Espèces',
        'virement'     => 'Virement bancaire',
        'orange_money' => 'Orange Money',
        'mobile_money' => 'Autre mobile money',
        'cheque'       => 'Chèque',
        'autre'        => 'Autre',
    ];

    /** Modes pour lesquels la référence de l'opération est obligatoire (traçabilité). */
    private const MODES_AVEC_REFERENCE = ['virement', 'orange_money', 'mobile_money'];

    /** Devises sans sous-unité : le montant doit être entier. */
    private const DEVISES_ENTIERES = ['XOF', 'XAF'];

    /** @var array<int,bool> */
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

    /** Migration T070 appliquée (et abonnements disponibles) ? */
    public static function disponible(PDO $pdo): bool
    {
        $cle = spl_object_id($pdo);
        if (self::$disponible[$cle] ?? false) {
            return true;
        }
        try {
            $n = (int)$pdo->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('platform_payments','platform_subscriptions')"
            )->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
        return self::$disponible[$cle] = ($n === 2);
    }

    /**
     * Enregistre un encaissement.
     *
     * @param array<string,mixed> $data montant, mode, reference_externe, date_paiement (Y-m-d), note
     * @return int identifiant du paiement
     */
    public function enregistrer(int $etablissementId, int $subscriptionId, array $data, int $operatorId): int
    {
        return $this->transaction(function () use ($etablissementId, $subscriptionId, $data, $operatorId): int {
            $s = $this->pdo->prepare('SELECT * FROM platform_subscriptions WHERE id = ? AND etablissement_id = ? FOR UPDATE');
            $s->execute([$subscriptionId, $etablissementId]);
            $sub = $s->fetch(PDO::FETCH_ASSOC);
            if ($sub === false) {
                throw new PaymentException('Période d\'abonnement introuvable pour cet établissement.');
            }
            if ($sub['cycle'] === 'trial') {
                throw new PaymentException('Un essai ne se paie pas : souscrivez à un abonnement payant avant d\'enregistrer un paiement.');
            }
            $devise = (string)$sub['devise'];

            $cents = $this->parserMontant($data['montant'] ?? '', $devise);
            $mode = (string)($data['mode'] ?? '');
            if (!isset(self::MODES[$mode])) {
                throw new PaymentException('Mode de paiement invalide.');
            }
            $refExt = trim((string)($data['reference_externe'] ?? ''));
            if (mb_strlen($refExt) > 100) {
                throw new PaymentException('Référence externe trop longue (100 caractères maximum).');
            }
            if ($refExt === '' && in_array($mode, self::MODES_AVEC_REFERENCE, true)) {
                throw new PaymentException('La référence de l\'opération (n° de virement ou de transaction) est obligatoire pour ce mode de paiement.');
            }
            $note = trim((string)($data['note'] ?? ''));
            if (mb_strlen($note) > 255) {
                throw new PaymentException('Note trop longue (255 caractères maximum).');
            }
            $date = $this->parserDate((string)($data['date_paiement'] ?? ''));

            if ($sub['prix_applique'] !== null) {
                $restant = $this->centimes((string)$sub['prix_applique']) - $this->encaisseCentimes((int)$sub['id']);
                if ($cents > $restant) {
                    throw new PaymentException(sprintf(
                        'Ce montant dépasse le solde restant de la période (%s %s). Un paiement d\'avance se saisit sur la période suivante, une fois l\'abonnement renouvelé.',
                        $this->formater(max(0, $restant), $devise), $devise
                    ));
                }
            }

            if ($refExt !== '') {
                $d = $this->pdo->prepare("SELECT reference FROM platform_payments WHERE mode = ? AND reference_externe = ? AND statut = 'recorded' LIMIT 1");
                $d->execute([$mode, $refExt]);
                if (($deja = $d->fetchColumn()) !== false) {
                    throw new PaymentException("Cette référence d'opération est déjà enregistrée ({$deja}) : double saisie évitée.");
                }
            }

            $this->pdo->prepare(
                'INSERT INTO platform_payments (etablissement_id, subscription_id, montant, devise, mode, reference_externe, date_paiement, note, recorded_by)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([
                $etablissementId, $subscriptionId, $this->decimal($cents), $devise, $mode, $refExt !== '' ? $refExt : null,
                $date, $note !== '' ? $note : null, $operatorId ?: null,
            ]);
            $id = (int)$this->pdo->lastInsertId();
            $reference = sprintf('PAY-%s-%06d', substr($date, 0, 4), $id);
            $this->pdo->prepare('UPDATE platform_payments SET reference = ? WHERE id = ? AND reference IS NULL')->execute([$reference, $id]);

            $this->auditer('payment.recorded', $operatorId, $etablissementId, $id, null, [
                'reference' => $reference, 'subscription_id' => $subscriptionId, 'montant' => $this->decimal($cents), 'devise' => $devise,
                'mode' => $mode, 'reference_externe' => $refExt !== '' ? $refExt : null, 'date_paiement' => $date,
            ]);
            return $id;
        });
    }

    /** Annule un paiement (jamais supprimé). Motif obligatoire. */
    public function annuler(int $paymentId, string $motif, int $operatorId): void
    {
        $motif = trim($motif);
        if ($motif === '' || mb_strlen($motif) > 255) {
            throw new PaymentException('Le motif d\'annulation est obligatoire (255 caractères maximum).');
        }
        $this->transaction(function () use ($paymentId, $motif, $operatorId): void {
            $s = $this->pdo->prepare('SELECT * FROM platform_payments WHERE id = ? FOR UPDATE');
            $s->execute([$paymentId]);
            $p = $s->fetch(PDO::FETCH_ASSOC);
            if ($p === false) {
                throw new PaymentException('Paiement introuvable.');
            }
            if ($p['statut'] !== 'recorded') {
                throw new PaymentException('Ce paiement est déjà annulé.');
            }
            $this->pdo->prepare("UPDATE platform_payments SET statut = 'cancelled', cancelled_at = NOW(), cancelled_by = ?, cancel_reason = ? WHERE id = ? AND statut = 'recorded'")
                ->execute([$operatorId ?: null, $motif, $paymentId]);
            $this->auditer('payment.cancelled', $operatorId, (int)$p['etablissement_id'], $paymentId,
                ['reference' => $p['reference'], 'montant' => $p['montant'], 'devise' => $p['devise'], 'statut' => 'recorded'],
                ['statut' => 'cancelled', 'motif' => $motif]);
        });
    }

    /** @return ?array<string,mixed> */
    public function find(int $id): ?array
    {
        $s = $this->pdo->prepare(
            'SELECT p.*, e.nom AS etablissement_nom, sb.cycle, sb.date_debut, sb.date_fin, sb.prix_applique, pl.nom AS plan_nom,
                    o.email AS saisi_par_email, c.email AS annule_par_email
               FROM platform_payments p
               JOIN etablissements e ON e.id = p.etablissement_id
               JOIN platform_subscriptions sb ON sb.id = p.subscription_id
               JOIN platform_plans pl ON pl.id = sb.plan_id
          LEFT JOIN platform_operators o ON o.id = p.recorded_by
          LEFT JOIN platform_operators c ON c.id = p.cancelled_by
              WHERE p.id = ?'
        );
        $s->execute([$id]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return list<array<string,mixed>> paiements d'un établissement, du plus récent au plus ancien */
    public function liste(int $etablissementId): array
    {
        $s = $this->pdo->prepare(
            'SELECT p.*, sb.date_debut, sb.date_fin FROM platform_payments p JOIN platform_subscriptions sb ON sb.id = p.subscription_id
              WHERE p.etablissement_id = ? ORDER BY p.date_paiement DESC, p.id DESC'
        );
        $s->execute([$etablissementId]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Situation de règlement de chaque période d'un établissement.
     *
     * @return array<int,array{paye:string,reste:?string,etat:string}> indexé par id de période ; etat : essai | inconnu | impaye | partiel | solde
     */
    public function situationParPeriode(int $etablissementId): array
    {
        $s = $this->pdo->prepare(
            "SELECT sb.id, sb.cycle, sb.prix_applique, COALESCE(SUM(CASE WHEN p.statut = 'recorded' THEN p.montant END), 0) AS paye
               FROM platform_subscriptions sb LEFT JOIN platform_payments p ON p.subscription_id = sb.id
              WHERE sb.etablissement_id = ? GROUP BY sb.id, sb.cycle, sb.prix_applique"
        );
        $s->execute([$etablissementId]);
        $r = [];
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $paye = $this->centimes((string)$l['paye']);
            if ($l['cycle'] === 'trial') {
                $etat = 'essai';
                $reste = null;
            } elseif ($l['prix_applique'] === null) {
                $etat = 'inconnu';
                $reste = null;
            } else {
                $du = $this->centimes((string)$l['prix_applique']);
                $reste = $this->decimal(max(0, $du - $paye));
                $etat = $paye >= $du ? 'solde' : ($paye > 0 ? 'partiel' : 'impaye');
            }
            $r[(int)$l['id']] = ['paye' => $this->decimal($paye), 'reste' => $reste, 'etat' => $etat];
        }
        return $r;
    }

    // ───────────────────────────────────────────────── helpers ─────────────────────────────────────────────────

    private function aujourdhui(): DateTimeImmutable
    {
        return SubscriptionRules::aujourdhui(($this->horloge)());
    }

    private function encaisseCentimes(int $subscriptionId): int
    {
        $s = $this->pdo->prepare("SELECT COALESCE(SUM(montant), 0) FROM platform_payments WHERE subscription_id = ? AND statut = 'recorded'");
        $s->execute([$subscriptionId]);
        return $this->centimes((string)$s->fetchColumn());
    }

    /** "5 000", "5000,50", "5000.5" -> centimes ; refuse zéro, négatif, trop de décimales, décimales sur une devise sans sous-unité. */
    private function parserMontant(mixed $brut, string $devise): int
    {
        $t = str_replace(["\u{00A0}", "\u{202F}", ' '], '', trim((string)$brut));
        $t = str_replace(',', '.', $t);
        if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $t)) {
            throw new PaymentException('Montant invalide (nombre positif, deux décimales au plus).');
        }
        if (in_array($devise, self::DEVISES_ENTIERES, true) && str_contains($t, '.')) {
            throw new PaymentException("Le {$devise} n'a pas de centimes : saisissez un montant entier.");
        }
        $cents = $this->centimes($t);
        if ($cents <= 0) {
            throw new PaymentException('Le montant doit être strictement positif.');
        }
        return $cents;
    }

    private function parserDate(string $brut): string
    {
        $brut = trim($brut);
        // Lue dans le fuseau de la plateforme, comme « aujourd'hui » : sinon un paiement du jour serait « dans le futur » après minuit local.
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $brut, SubscriptionRules::fuseau());
        if ($d === false || $d->format('Y-m-d') !== $brut || (int)$d->format('Y') < 2000) {
            throw new PaymentException('Date de paiement invalide.');
        }
        if ($d > $this->aujourdhui()) {
            throw new PaymentException('La date de paiement ne peut pas être dans le futur.');
        }
        return $brut;
    }

    private function centimes(string $decimal): int
    {
        return (int)round(((float)$decimal) * 100);
    }

    private function decimal(int $centimes): string
    {
        return sprintf('%d.%02d', intdiv($centimes, 100), $centimes % 100);
    }

    private function formater(int $centimes, string $devise): string
    {
        return in_array($devise, self::DEVISES_ENTIERES, true)
            ? number_format($centimes / 100, 0, ',', ' ')
            : number_format($centimes / 100, 2, ',', ' ');
    }

    /** @param ?array<string,mixed> $avant @param array<string,mixed> $apres */
    private function auditer(string $action, int $operatorId, int $etablissementId, int $paymentId, ?array $avant, array $apres): void
    {
        try {
            PlatformAuditService::make($this->pdo)->recordPar($operatorId, $action, [
                'etablissement_id' => $etablissementId, 'entity_type' => 'payment', 'entity_id' => $paymentId,
                'avant' => $avant, 'apres' => $apres, 'strict' => true,
            ]);
        } catch (\Throwable $e) {
            Logger::security('PLATFORM_PAYMENT_AUDIT_ABORTED', "{$action} paiement_id={$paymentId} par operator_id={$operatorId} : journal d'audit indisponible (" . get_class($e) . ')');
            throw PaymentException::audit('Opération annulée : la trace d\'audit n\'a pas pu être enregistrée. Aucune modification n\'a été appliquée.', $e);
        }
    }

    /**
     * @template T
     * @param callable():T $travail
     * @return T
     */
    private function transaction(callable $travail): mixed
    {
        $point = null;
        if ($this->pdo->inTransaction()) {
            $point = 'pay_' . bin2hex(random_bytes(4));
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
            if ($e instanceof PaymentException) {
                throw $e;
            }
            $ref = strtoupper(bin2hex(random_bytes(3)));
            Logger::error("Paiement [{$ref}] " . get_class($e) . ' : ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')');
            throw new PaymentException("Opération impossible pour une raison technique (référence {$ref}). Aucune modification n'a été appliquée.", 0, $e);
        }
    }
}
