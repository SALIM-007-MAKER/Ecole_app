<?php

declare(strict_types=1);

namespace Core\Platform;

use Core\Logger;
use Core\Platform\WebAuthn\WebAuthnException;
use Core\Platform\WebAuthn\WebAuthnVerifier as W;
use Core\Session;
use PDO;

/**
 * Passkeys (WebAuthn) des opérateurs : second facteur par clé publique, en complément du TOTP.
 *
 *  - Une passkey s'AJOUTE à la 2FA existante : elle ne peut être enregistrée que si la 2FA est active, et le TOTP comme les codes de secours
 *    restent disponibles. Désactiver ou réinitialiser la 2FA retire aussi les passkeys.
 *  - Seule la clé PUBLIQUE est conservée. Le défi est aléatoire (32 octets), lié à l'opérateur et à l'usage, valable 2 minutes, à usage unique.
 *  - Compteur de signatures : s'il ne progresse pas (clone possible), la connexion est refusée ; la mise à jour est atomique.
 *  - Ajout, renommage et suppression : transaction + audit global STRICT (audit impossible = rien de modifié), alerte e-mail après commit.
 *
 * L'origine et le RP ID viennent de la configuration (PLATFORM_WEBAUTHN_ORIGIN / PLATFORM_WEBAUTHN_RP_ID, sinon APP_URL), jamais de l'en-tête Host
 * de la requête. WebAuthn n'est utilisable qu'en HTTPS (ou sur localhost).
 *
 * Le contrôle des tentatives (limitation) est fait par les contrôleurs.
 */
final class PlatformPasskeyService
{
    public const MAX_PAR_OPERATEUR = 10;
    public const DUREE_DEFI = 120;
    private const CLE_DEFI = '_platform_passkey_challenge';

    private const ALERTES = [
        'operator.passkey_added'   => PlatformSecurityNotifier::PASSKEY_AJOUTEE,
        'operator.passkey_removed' => PlatformSecurityNotifier::PASSKEY_SUPPRIMEE,
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function make(): self
    {
        return new self(\Core\Database::getInstance()->getConnection());
    }

    /** Migration T073 appliquée ? N'avale pas les erreurs de base (voir PlatformTwoFactorService::disponible). */
    public static function disponible(PDO $pdo): bool
    {
        return (int)$pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_operator_passkeys'"
        )->fetchColumn() === 1;
    }

    /** @return array{rp_id:string, origine:string} */
    public static function contexte(): array
    {
        $env = static fn(string $k): string => (string)($_ENV[$k] ?? (getenv($k) ?: ''));
        $url = parse_url($env('APP_URL') !== '' ? $env('APP_URL') : 'http://localhost/ecole_app');
        $hote = strtolower((string)($url['host'] ?? 'localhost'));
        $origine = $env('PLATFORM_WEBAUTHN_ORIGIN');
        if ($origine === '') {
            $origine = ($url['scheme'] ?? 'http') . '://' . $hote . (isset($url['port']) ? ':' . $url['port'] : '');
        }
        $rpId = $env('PLATFORM_WEBAUTHN_RP_ID');
        return ['rp_id' => $rpId !== '' ? $rpId : $hote, 'origine' => rtrim($origine, '/')];
    }

    // ───────────────────────────────────────────── lecture ─────────────────────────────────────────────

    /** @return list<array{id:int, nom:string, created_at:string, last_used_at:?string}> */
    public function liste(int $operatorId): array
    {
        $s = $this->pdo->prepare('SELECT id, nom, created_at, last_used_at FROM platform_operator_passkeys WHERE operator_id = ? ORDER BY id');
        $s->execute([$operatorId]);
        return array_map(static fn(array $r): array => ['id' => (int)$r['id'], 'nom' => (string)$r['nom'], 'created_at' => (string)$r['created_at'], 'last_used_at' => $r['last_used_at']], $s->fetchAll(PDO::FETCH_ASSOC));
    }

    public function nombre(int $operatorId): int
    {
        $s = $this->pdo->prepare('SELECT COUNT(*) FROM platform_operator_passkeys WHERE operator_id = ?');
        $s->execute([$operatorId]);
        return (int)$s->fetchColumn();
    }

    private function deuxFacteursActive(int $operatorId): bool
    {
        $s = $this->pdo->prepare('SELECT COUNT(*) FROM platform_operators WHERE id = ? AND actif = 1 AND totp_enabled_at IS NOT NULL');
        $s->execute([$operatorId]);
        return (int)$s->fetchColumn() === 1;
    }

    // ───────────────────────────────────────────── défis ─────────────────────────────────────────────

    private function nouveauDefi(string $usage, int $operatorId): string
    {
        $defi = random_bytes(32);
        Session::set(self::CLE_DEFI, ['c' => W::b64uEncode($defi), 'at' => time(), 'usage' => $usage, 'op' => $operatorId]);
        return $defi;
    }

    /** Défi attendu pour cet usage et cet opérateur, ou null. Toujours détruit : un défi ne sert qu'une fois. */
    private function consommerDefi(string $usage, int $operatorId): ?string
    {
        $d = Session::get(self::CLE_DEFI);
        Session::delete(self::CLE_DEFI);
        if (!is_array($d) || ($d['usage'] ?? null) !== $usage || (int)($d['op'] ?? 0) !== $operatorId || time() - (int)($d['at'] ?? 0) > self::DUREE_DEFI) {
            return null;
        }
        try {
            return W::b64uDecode((string)$d['c']);
        } catch (WebAuthnException) {
            return null;
        }
    }

    // ───────────────────────────────────────────── enregistrement ─────────────────────────────────────────────

    /**
     * Options à passer à navigator.credentials.create().
     *
     * @return array<string,mixed>
     * @throws TwoFactorException
     */
    public function optionsEnregistrement(int $operatorId, string $email): array
    {
        if (!$this->deuxFacteursActive($operatorId)) {
            throw new TwoFactorException('Activez d\'abord la double authentification par application : une passkey s\'y ajoute.');
        }
        if ($this->nombre($operatorId) >= self::MAX_PAR_OPERATEUR) {
            throw new TwoFactorException('Nombre maximal de passkeys atteint (' . self::MAX_PAR_OPERATEUR . ').');
        }
        $ctx = self::contexte();
        return [
            'challenge'              => W::b64uEncode($this->nouveauDefi('create', $operatorId)),
            'rp'                     => ['id' => $ctx['rp_id'], 'name' => PlatformTwoFactorService::EMETTEUR],
            'user'                   => ['id' => W::b64uEncode(hash('sha256', 'platform-operator:' . $operatorId, true)), 'name' => $email, 'displayName' => $email],
            'pubKeyCredParams'       => [['type' => 'public-key', 'alg' => W::ALG_ES256], ['type' => 'public-key', 'alg' => W::ALG_RS256]],
            'timeout'                => 60000,
            'attestation'            => 'none',
            'authenticatorSelection' => ['residentKey' => 'discouraged', 'userVerification' => 'preferred'],
            'excludeCredentials'     => $this->identifiants($operatorId),
        ];
    }

    /**
     * Enregistre la passkey créée par le navigateur.
     *
     * @throws TwoFactorException
     */
    public function enregistrer(int $operatorId, string $nom, string $clientDataB64, string $attestationB64): void
    {
        $defi = $this->consommerDefi('create', $operatorId);
        if ($defi === null) {
            throw new TwoFactorException('La demande d\'enregistrement a expiré : recommencez.');
        }
        if (!$this->deuxFacteursActive($operatorId) || $this->nombre($operatorId) >= self::MAX_PAR_OPERATEUR) {
            throw new TwoFactorException('Enregistrement impossible.');
        }
        $ctx = self::contexte();
        try {
            $cle = W::verifierEnregistrement($clientDataB64, $attestationB64, $defi, $ctx['rp_id'], $ctx['origine']);
        } catch (WebAuthnException $e) {
            Logger::security('PLATFORM_PASSKEY_REGISTER_REJECTED', "operator_id={$operatorId} : " . $e->getMessage());
            throw new TwoFactorException('Passkey refusée : elle n\'a pas pu être vérifiée.');
        }
        $nom = self::nomPropre($nom);
        $identifiant = W::b64uEncode($cle['credential_id']);
        $this->transaction($operatorId, 'operator.passkey_added', function () use ($operatorId, $nom, $identifiant, $cle): array {
            try {
                $this->pdo->prepare('INSERT INTO platform_operator_passkeys (operator_id, credential_id, public_key, alg, sign_count, nom) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$operatorId, $identifiant, $cle['public_key_pem'], $cle['alg'], $cle['sign_count'], $nom]);
            } catch (\PDOException $e) {
                if ($e->getCode() === '23000') {
                    throw new TwoFactorException('Cette passkey est déjà enregistrée.');
                }
                throw $e;
            }
            return ['passkey_id' => (int)$this->pdo->lastInsertId(), 'nom' => $nom, 'alg' => $cle['alg']];
        });
    }

    // ───────────────────────────────────────────── connexion ─────────────────────────────────────────────

    /**
     * Options à passer à navigator.credentials.get() pour l'opérateur dont le mot de passe vient d'être validé.
     *
     * @return ?array<string,mixed> null si l'opérateur n'a aucune passkey
     */
    public function optionsConnexion(int $operatorId): ?array
    {
        $ids = $this->identifiants($operatorId);
        if ($ids === []) {
            return null;
        }
        return [
            'challenge'        => W::b64uEncode($this->nouveauDefi('get', $operatorId)),
            'rpId'             => self::contexte()['rp_id'],
            'timeout'          => 60000,
            'userVerification' => 'preferred',
            'allowCredentials' => $ids,
        ];
    }

    /** Vérifie l'assertion d'une passkey de cet opérateur. Aucune indication sur la cause d'un refus. */
    public function verifierConnexion(int $operatorId, string $identifiantB64, string $clientDataB64, string $authDataB64, string $signatureB64): bool
    {
        $defi = $this->consommerDefi('get', $operatorId);
        if ($defi === null) {
            return false;
        }
        try {
            $s = $this->pdo->prepare('SELECT id, public_key, sign_count FROM platform_operator_passkeys WHERE operator_id = ? AND credential_id = ?');
            $s->execute([$operatorId, $identifiantB64]);
            $ligne = $s->fetch(PDO::FETCH_ASSOC);
            if (!$ligne) {
                return false;
            }
            $ctx = self::contexte();
            $compteur = W::verifierAssertion($clientDataB64, $authDataB64, $signatureB64, $defi, $ctx['rp_id'], $ctx['origine'], (string)$ligne['public_key']);
            $ancien = (int)$ligne['sign_count'];
            // Compteur : doit progresser. Les appareils qui renvoient toujours 0 sont acceptés tant que le compteur enregistré est 0.
            if (!($compteur > $ancien || ($compteur === 0 && $ancien === 0))) {
                Logger::security('PLATFORM_PASSKEY_COUNTER_REGRESSION', "operator_id={$operatorId} passkey_id={$ligne['id']} : compteur {$compteur} <= {$ancien} (clone possible)");
                return false;
            }
            $u = $this->pdo->prepare('UPDATE platform_operator_passkeys SET sign_count = ?, last_used_at = NOW() WHERE id = ? AND sign_count = ?');
            $u->execute([$compteur, (int)$ligne['id'], $ancien]);
            return $u->rowCount() === 1 || ($compteur === 0 && $ancien === 0);
        } catch (WebAuthnException $e) {
            Logger::security('PLATFORM_PASSKEY_ASSERTION_REJECTED', "operator_id={$operatorId} : " . $e->getMessage());
        } catch (\Throwable $e) {
            Logger::error('Passkey opérateur #' . $operatorId . ' : vérification impossible (' . get_class($e) . ' : ' . $e->getMessage() . ')');
        }
        return false;
    }

    // ───────────────────────────────────────────── gestion ─────────────────────────────────────────────

    /** @throws TwoFactorException */
    public function renommer(int $operatorId, int $passkeyId, string $nom): void
    {
        $nom = self::nomPropre($nom);
        $this->transaction($operatorId, 'operator.passkey_renamed', function () use ($operatorId, $passkeyId, $nom): array {
            $u = $this->pdo->prepare('UPDATE platform_operator_passkeys SET nom = ? WHERE id = ? AND operator_id = ?');
            $u->execute([$nom, $passkeyId, $operatorId]);
            $existe = $this->pdo->prepare('SELECT COUNT(*) FROM platform_operator_passkeys WHERE id = ? AND operator_id = ?');
            $existe->execute([$passkeyId, $operatorId]);
            if ((int)$existe->fetchColumn() !== 1) {
                throw new TwoFactorException('Passkey introuvable.');
            }
            return ['passkey_id' => $passkeyId, 'nom' => $nom];
        });
    }

    /** @throws TwoFactorException */
    public function supprimer(int $operatorId, int $passkeyId): void
    {
        $this->transaction($operatorId, 'operator.passkey_removed', function () use ($operatorId, $passkeyId): array {
            $s = $this->pdo->prepare('SELECT nom FROM platform_operator_passkeys WHERE id = ? AND operator_id = ? FOR UPDATE');
            $s->execute([$passkeyId, $operatorId]);
            $nom = $s->fetchColumn();
            if ($nom === false) {
                throw new TwoFactorException('Passkey introuvable.');
            }
            $this->pdo->prepare('DELETE FROM platform_operator_passkeys WHERE id = ? AND operator_id = ?')->execute([$passkeyId, $operatorId]);
            return ['passkey_id' => $passkeyId, 'nom' => (string)$nom];
        });
    }

    /** Retire toutes les passkeys (appelé par la désactivation ou la réinitialisation de la 2FA, dans leur transaction). */
    public static function toutesRetirer(PDO $pdo, int $operatorId): void
    {
        if (self::disponible($pdo)) {
            $pdo->prepare('DELETE FROM platform_operator_passkeys WHERE operator_id = ?')->execute([$operatorId]);
        }
    }

    // ───────────────────────────────────────────── interne ─────────────────────────────────────────────

    /** @return list<array{type:string, id:string}> */
    private function identifiants(int $operatorId): array
    {
        $s = $this->pdo->prepare('SELECT credential_id FROM platform_operator_passkeys WHERE operator_id = ? ORDER BY id');
        $s->execute([$operatorId]);
        return array_map(static fn($id): array => ['type' => 'public-key', 'id' => (string)$id], $s->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function nomPropre(string $nom): string
    {
        $nom = trim((string)preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $nom));
        $nom = mb_substr($nom, 0, 80);
        return $nom !== '' ? $nom : 'Passkey';
    }

    /**
     * Transaction (point de sauvegarde si l'appelant en a une) : écritures + audit strict, alerte e-mail après un vrai commit.
     *
     * @param callable():array<string,mixed> $travail renvoie les valeurs à inscrire dans l'audit
     */
    private function transaction(int $operatorId, string $action, callable $travail): void
    {
        $point = null;
        if ($this->pdo->inTransaction()) {
            $point = 'pk_' . bin2hex(random_bytes(4));
            $this->pdo->exec("SAVEPOINT $point");
        } else {
            $this->pdo->beginTransaction();
        }
        try {
            $apres = $travail();
            try {
                PlatformAuditService::make($this->pdo)->recordPar($operatorId, $action, ['entity_type' => 'platform_operator', 'entity_id' => $operatorId, 'apres' => $apres, 'strict' => true]);
            } catch (\Throwable $e) {
                Logger::security('PLATFORM_PASSKEY_AUDIT_ABORTED', "{$action} operator_id={$operatorId} : journal d'audit indisponible (" . get_class($e) . ')');
                throw new TwoFactorException('Opération annulée : la trace d\'audit n\'a pas pu être enregistrée. Aucune modification n\'a été appliquée.', 0, $e);
            }
            $point !== null ? $this->pdo->exec("RELEASE SAVEPOINT $point") : $this->pdo->commit();
            if ($point === null && isset(self::ALERTES[$action])) {
                PlatformSecurityNotifier::notifier($operatorId, self::ALERTES[$action], ['nom' => (string)($apres['nom'] ?? '')], $this->pdo);
            }
        } catch (\Throwable $e) {
            if ($point !== null) {
                $this->pdo->exec("ROLLBACK TO SAVEPOINT $point");
            } elseif ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof TwoFactorException) {
                throw $e;
            }
            $ref = strtoupper(bin2hex(random_bytes(3)));
            Logger::error("Passkey [{$ref}] " . get_class($e) . ' : ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')');
            throw new TwoFactorException("Opération impossible pour une raison technique (référence {$ref}). Aucune modification n'a été appliquée.", 0, $e);
        }
    }
}
