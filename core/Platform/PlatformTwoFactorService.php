<?php

declare(strict_types=1);

namespace Core\Platform;

use Core\Database;
use Core\Logger;
use PDO;

/**
 * Double authentification (TOTP, RFC 6238) des opérateurs de la plateforme.
 *
 *  - Code à 6 chiffres, pas de 30 s, SHA-1 (compatible Google Authenticator, Microsoft Authenticator, Aegis, FreeOTP…), tolérance ±1 pas.
 *  - Anti-rejeu : un code déjà accepté (ou plus ancien) est refusé ; la mise à jour du dernier pas est atomique.
 *  - Secret chiffré au repos (sodium secretbox, clé dérivée de APP_KEY) ; jamais écrit dans un journal ni dans l'audit.
 *  - 10 codes de secours à usage unique, stockés hachés (HMAC-SHA256), affichés UNE seule fois.
 *  - Activation, régénération, désactivation et réinitialisation : transaction + audit global STRICT (audit impossible = rien de modifié).
 *
 * Le contrôle des tentatives (limitation) est fait par les contrôleurs ; ce service ne compte rien.
 */
final class PlatformTwoFactorService
{
    public const PERIODE = 30;
    public const CHIFFRES = 6;
    public const FENETRE = 1;
    public const NB_CODES_SECOURS = 10;
    public const EMETTEUR = 'EduNova';
    private const ALPHABET_B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    /** Alphabet des codes de secours : sans caractères ambigus (0/O, 1/I). */
    private const ALPHABET_SECOURS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** @var callable():int */
    private $horloge;

    public function __construct(private readonly PDO $pdo, ?callable $horloge = null)
    {
        $this->horloge = $horloge ?? static fn(): int => time();
    }

    public static function make(): self
    {
        return new self(Database::getInstance()->getConnection());
    }

    /**
     * Migration T072 appliquée ? Sinon le portail se comporte exactement comme avant.
     *
     * N'AVALE PAS les erreurs : « migration absente » (réponse false) et « base momentanément illisible » (exception) sont deux situations
     * différentes. Si l'on ne peut pas savoir si la 2FA est en place, la connexion doit ÉCHOUER (jamais s'ouvrir avec le seul mot de passe).
     */
    public static function disponible(PDO $pdo): bool
    {
        return (int)$pdo->query(
            "SELECT (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_operators' AND COLUMN_NAME = 'totp_enabled_at')
                  + (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_operator_recovery_codes')"
        )->fetchColumn() === 2;
    }

    /** La 2FA est-elle exigée de tous les opérateurs (paramètre « deux_facteurs_obligatoire ») ? */
    public static function obligatoire(): bool
    {
        return PlatformSettings::valeurSiConnecte(PlatformSettings::DEUX_FACTEURS) === '1';
    }

    // ───────────────────────────────────────────── primitives TOTP ─────────────────────────────────────────────

    public static function base32Encode(string $binaire): string
    {
        $bits = '';
        foreach (str_split($binaire) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $r = '';
        foreach (str_split($bits, 5) as $morceau) {
            $r .= self::ALPHABET_B32[bindec(str_pad($morceau, 5, '0', STR_PAD_RIGHT))];
        }
        return $r;
    }

    public static function base32Decode(string $texte): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim(str_replace(' ', '', $texte), '='))) as $c) {
            $p = strpos(self::ALPHABET_B32, $c);
            if ($p === false) {
                throw new \InvalidArgumentException('Secret base32 invalide.');
            }
            $bits .= str_pad(decbin($p), 5, '0', STR_PAD_LEFT);
        }
        $r = '';
        foreach (str_split($bits, 8) as $octet) {
            if (strlen($octet) === 8) {
                $r .= chr(bindec($octet));
            }
        }
        return $r;
    }

    /** Code TOTP du pas $pas (nombre de périodes de 30 s depuis l'époque Unix). */
    public static function totp(string $secretBase32, int $pas): string
    {
        $cle = self::base32Decode($secretBase32);
        $h = hash_hmac('sha1', pack('N2', ($pas >> 32) & 0xFFFFFFFF, $pas & 0xFFFFFFFF), $cle, true);
        $o = ord($h[19]) & 0x0F;
        $v = ((ord($h[$o]) & 0x7F) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
        return str_pad((string)($v % (10 ** self::CHIFFRES)), self::CHIFFRES, '0', STR_PAD_LEFT);
    }

    public static function nouveauSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /** URI à encoder en QR code (format otpauth de Google Authenticator). */
    public static function uri(string $email, string $secret): string
    {
        return 'otpauth://totp/' . rawurlencode(self::EMETTEUR . ':' . $email) . '?secret=' . $secret
            . '&issuer=' . rawurlencode(self::EMETTEUR) . '&algorithm=SHA1&digits=' . self::CHIFFRES . '&period=' . self::PERIODE;
    }

    private function pasCourant(): int
    {
        return intdiv(($this->horloge)(), self::PERIODE);
    }

    /** Pas dont le code correspond (dans la fenêtre de tolérance), ou null. Comparaisons à temps constant, tous les pas testés. */
    private function pasCorrespondant(string $secret, string $code): ?int
    {
        $trouve = null;
        $courant = $this->pasCourant();
        for ($d = -self::FENETRE; $d <= self::FENETRE; $d++) {
            if (hash_equals(self::totp($secret, $courant + $d), $code) && ($trouve === null || $courant + $d > $trouve)) {
                $trouve = $courant + $d;
            }
        }
        return $trouve;
    }

    private static function codeSaisi(string $saisie): ?string
    {
        $c = preg_replace('/\s+/', '', $saisie) ?? '';
        return preg_match('/^\d{' . self::CHIFFRES . '}$/', $c) ? $c : null;
    }

    // ───────────────────────────────────────────── chiffrement / hachage ─────────────────────────────────────────────

    private static function cleDerivee(string $contexte): string
    {
        $appKey = (string)($_ENV['APP_KEY'] ?? (getenv('APP_KEY') ?: ''));
        if (strlen($appKey) < 16) {
            throw new TwoFactorException('APP_KEY absente ou trop courte : la double authentification ne peut pas être utilisée tant qu\'elle n\'est pas définie dans le .env.');
        }
        return hash_hkdf('sha256', $appKey, 32, 'platform-2fa:' . $contexte);
    }

    private static function chiffrer(string $clair): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($clair, $nonce, self::cleDerivee('secret')));
    }

    private static function dechiffrer(string $charge): ?string
    {
        $brut = base64_decode($charge, true);
        $n = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if ($brut === false || strlen($brut) <= $n) {
            return null;
        }
        $clair = sodium_crypto_secretbox_open(substr($brut, $n), substr($brut, 0, $n), self::cleDerivee('secret'));
        return $clair === false ? null : $clair;
    }

    private static function hacherCodeSecours(string $code): string
    {
        return hash_hmac('sha256', $code, self::cleDerivee('recovery'));
    }

    /** Forme canonique d'un code de secours saisi (majuscules, sans espace ni tiret), ou null s'il n'a pas le bon format. */
    private static function codeSecoursSaisi(string $saisie): ?string
    {
        $c = strtoupper(str_replace(['-', ' '], '', trim($saisie)));
        return preg_match('/^[' . self::ALPHABET_SECOURS . ']{10}$/', $c) ? $c : null;
    }

    /** @return list<string> codes au format XXXXX-XXXXX */
    private function genererCodesSecours(): array
    {
        $codes = [];
        while (count($codes) < self::NB_CODES_SECOURS) {
            $c = '';
            for ($i = 0; $i < 10; $i++) {
                $c .= self::ALPHABET_SECOURS[random_int(0, strlen(self::ALPHABET_SECOURS) - 1)];
            }
            $codes[$c] = substr($c, 0, 5) . '-' . substr($c, 5);
        }
        return array_values($codes);
    }

    // ───────────────────────────────────────────── état ─────────────────────────────────────────────

    /** @return array{active:bool,depuis:?string,codes_restants:int} */
    public function statut(int $operatorId): array
    {
        $s = $this->pdo->prepare('SELECT totp_enabled_at FROM platform_operators WHERE id = ?');
        $s->execute([$operatorId]);
        $depuis = $s->fetchColumn();
        $n = $this->pdo->prepare('SELECT COUNT(*) FROM platform_operator_recovery_codes WHERE operator_id = ? AND used_at IS NULL');
        $n->execute([$operatorId]);
        return ['active' => $depuis !== false && $depuis !== null, 'depuis' => $depuis ?: null, 'codes_restants' => (int)$n->fetchColumn()];
    }

    public function estActive(int $operatorId): bool
    {
        return $this->statut($operatorId)['active'];
    }

    // ───────────────────────────────────────────── vérification ─────────────────────────────────────────────

    /**
     * Vérifie le second facteur saisi à la connexion : code TOTP (6 chiffres) ou code de secours (usage unique).
     *
     * @return ?string 'totp' | 'recovery' si accepté, null sinon (aucune indication sur la cause)
     */
    public function verifier(int $operatorId, string $saisie): ?string
    {
        $s = $this->pdo->prepare('SELECT totp_secret FROM platform_operators WHERE id = ? AND actif = 1 AND totp_enabled_at IS NOT NULL');
        $s->execute([$operatorId]);
        $chiffre = $s->fetchColumn();
        if (!is_string($chiffre) || $chiffre === '') {
            return null;
        }
        try {
            if (($code = self::codeSaisi($saisie)) !== null) {
                return $this->accepterTotp($operatorId, $chiffre, $code) ? 'totp' : null;
            }
            if (($secours = self::codeSecoursSaisi($saisie)) !== null) {
                $u = $this->pdo->prepare('UPDATE platform_operator_recovery_codes SET used_at = NOW() WHERE operator_id = ? AND code_hash = ? AND used_at IS NULL LIMIT 1');
                $u->execute([$operatorId, self::hacherCodeSecours($secours)]);
                return $u->rowCount() === 1 ? 'recovery' : null;
            }
        } catch (\Throwable $e) {
            Logger::error('2FA opérateur #' . $operatorId . ' : vérification impossible (' . get_class($e) . ' : ' . $e->getMessage() . ')');
        }
        return null;
    }

    /** Accepte un code TOTP s'il correspond ET n'a pas déjà servi (mise à jour atomique du dernier pas). */
    private function accepterTotp(int $operatorId, string $chiffre, string $code): bool
    {
        $secret = self::dechiffrer($chiffre);
        if ($secret === null) {
            Logger::error('2FA opérateur #' . $operatorId . ' : secret illisible (APP_KEY modifiée ?)');
            return false;
        }
        $pas = $this->pasCorrespondant($secret, $code);
        if ($pas === null) {
            return false;
        }
        $u = $this->pdo->prepare('UPDATE platform_operators SET totp_last_step = ? WHERE id = ? AND totp_enabled_at IS NOT NULL AND (totp_last_step IS NULL OR totp_last_step < ?)');
        $u->execute([$pas, $operatorId, $pas]);
        return $u->rowCount() === 1;
    }

    // ───────────────────────────────────────────── activation et gestion ─────────────────────────────────────────────

    /**
     * Active la 2FA : le code prouve que l'application d'authentification a bien enregistré le secret. Renvoie les codes de secours (clairs,
     * à montrer UNE fois).
     *
     * @return list<string>
     * @throws TwoFactorException
     */
    public function activer(int $operatorId, string $secret, string $saisie): array
    {
        $code = self::codeSaisi($saisie);
        try {
            $pas = $code !== null ? $this->pasCorrespondant($secret, $code) : null;
        } catch (\InvalidArgumentException) {
            throw new TwoFactorException('Le secret d\'enregistrement n\'est plus valide : rechargez la page.');
        }
        if ($pas === null) {
            throw TwoFactorException::identifiantsIncorrects('Code incorrect. Vérifiez l\'heure de votre téléphone et réessayez.');
        }
        return $this->transaction($operatorId, 'operator.2fa_enabled', function () use ($operatorId, $secret, $pas): array {
            $u = $this->pdo->prepare('UPDATE platform_operators SET totp_secret = ?, totp_enabled_at = NOW(), totp_last_step = ? WHERE id = ? AND actif = 1 AND totp_enabled_at IS NULL');
            $u->execute([self::chiffrer($secret), $pas, $operatorId]);
            if ($u->rowCount() !== 1) {
                throw new TwoFactorException('La double authentification est déjà activée pour ce compte.');
            }
            return ['codes' => $this->remplacerCodesSecours($operatorId), 'extra' => []];
        });
    }

    /**
     * Nouveaux codes de secours (les anciens cessent de fonctionner). Exige un code TOTP valide (pas un code de secours).
     *
     * @return list<string>
     * @throws TwoFactorException
     */
    public function regenererCodes(int $operatorId, string $saisie): array
    {
        $this->exigerCodeTotp($operatorId, $saisie);
        return $this->transaction($operatorId, 'operator.2fa_recovery_regenerated', fn(): array => ['codes' => $this->remplacerCodesSecours($operatorId), 'extra' => []]);
    }

    /**
     * Désactivation par l'opérateur lui-même : mot de passe ET code TOTP exigés. Refusée si la 2FA est obligatoire pour la plateforme.
     *
     * @throws TwoFactorException
     */
    public function desactiver(int $operatorId, string $motDePasse, string $saisie): void
    {
        if (self::obligatoire()) {
            throw new TwoFactorException('La double authentification est obligatoire pour tous les opérateurs : elle ne peut pas être désactivée.');
        }
        $this->verifierMotDePasse($operatorId, $motDePasse);
        $this->exigerCodeTotp($operatorId, $saisie);
        $this->transaction($operatorId, 'operator.2fa_disabled', function () use ($operatorId): array {
            $this->effacer($operatorId);
            return ['codes' => [], 'extra' => []];
        });
    }

    /**
     * Réinitialisation (téléphone perdu) par un tiers : l'opérateur pourra se reconnecter avec son seul mot de passe et devra se réinscrire si
     * la 2FA est obligatoire. Acteur d'audit : l'opérateur qui agit, ou « cli ».
     *
     * @param ?array $acteur acteur d'audit (PlatformOperatorService::ACTEUR_CLI en ligne de commande) ; null = opérateur connecté
     * @throws TwoFactorException
     */
    public function reinitialiser(int $operatorId, ?array $acteur = null): void
    {
        if (!$this->estActive($operatorId)) {
            throw new TwoFactorException('La double authentification n\'est pas activée pour ce compte.');
        }
        $this->transaction($operatorId, 'operator.2fa_reset', function () use ($operatorId): array {
            $this->effacer($operatorId);
            return ['codes' => [], 'extra' => ['par' => 'administrateur']];
        }, $acteur);
    }

    /**
     * Exige le mot de passe actuel de l'opérateur (ré-authentification avant une opération sensible : activation, désactivation).
     *
     * @throws TwoFactorException
     */
    public function verifierMotDePasse(int $operatorId, string $motDePasse): void
    {
        $s = $this->pdo->prepare('SELECT password_hash FROM platform_operators WHERE id = ? AND actif = 1');
        $s->execute([$operatorId]);
        $hash = $s->fetchColumn();
        if (!is_string($hash) || $hash === '' || !password_verify($motDePasse, $hash)) {
            throw TwoFactorException::identifiantsIncorrects('Mot de passe incorrect.');
        }
    }

    private function exigerCodeTotp(int $operatorId, string $saisie): void
    {
        $code = self::codeSaisi($saisie);
        $s = $this->pdo->prepare('SELECT totp_secret FROM platform_operators WHERE id = ? AND actif = 1 AND totp_enabled_at IS NOT NULL');
        $s->execute([$operatorId]);
        $chiffre = $s->fetchColumn();
        if ($code === null || !is_string($chiffre) || !$this->accepterTotp($operatorId, $chiffre, $code)) {
            throw TwoFactorException::identifiantsIncorrects('Code incorrect.');
        }
    }

    private function effacer(int $operatorId): void
    {
        $this->pdo->prepare('UPDATE platform_operators SET totp_secret = NULL, totp_enabled_at = NULL, totp_last_step = NULL WHERE id = ?')->execute([$operatorId]);
        $this->pdo->prepare('DELETE FROM platform_operator_recovery_codes WHERE operator_id = ?')->execute([$operatorId]);
        // Les passkeys s'ajoutent à la 2FA : sans 2FA, elles n'ont plus lieu d'être (et ne doivent pas rester comme accès oublié).
        PlatformPasskeyService::toutesRetirer($this->pdo, $operatorId);
    }

    /** @return list<string> */
    private function remplacerCodesSecours(int $operatorId): array
    {
        $this->pdo->prepare('DELETE FROM platform_operator_recovery_codes WHERE operator_id = ?')->execute([$operatorId]);
        $codes = $this->genererCodesSecours();
        $ins = $this->pdo->prepare('INSERT INTO platform_operator_recovery_codes (operator_id, code_hash) VALUES (?, ?)');
        foreach ($codes as $c) {
            $ins->execute([$operatorId, self::hacherCodeSecours(str_replace('-', '', $c))]);
        }
        return $codes;
    }

    /**
     * Transaction (point de sauvegarde si l'appelant en a une) : écritures + audit strict. Le travail renvoie ['codes' => [...], 'extra' => [...]].
     *
     * @param callable():array{codes:list<string>,extra:array<string,mixed>} $travail
     * @param ?array $acteur
     * @return list<string> les codes de secours éventuels
     */
    /** Action d'audit => alerte e-mail envoyée à l'opérateur concerné. */
    private const ALERTES = [
        'operator.2fa_enabled'              => PlatformSecurityNotifier::DEUX_FACTEURS_ACTIVEE,
        'operator.2fa_recovery_regenerated' => PlatformSecurityNotifier::CODES_REGENERES,
        'operator.2fa_disabled'             => PlatformSecurityNotifier::DEUX_FACTEURS_DESACTIVEE,
        'operator.2fa_reset'                => PlatformSecurityNotifier::DEUX_FACTEURS_REINITIALISEE,
    ];

    private function transaction(int $operatorId, string $action, callable $travail, ?array $acteur = null): array
    {
        $point = null;
        if ($this->pdo->inTransaction()) {
            $point = 'tf_' . bin2hex(random_bytes(4));
            $this->pdo->exec("SAVEPOINT $point");
        } else {
            $this->pdo->beginTransaction();
        }
        try {
            $r = $travail();
            try {
                $options = ['entity_type' => 'platform_operator', 'entity_id' => $operatorId, 'apres' => $r['extra'], 'strict' => true];
                $audit = PlatformAuditService::make($this->pdo);
                $acteur !== null ? $audit->record($action, ['actor' => $acteur] + $options) : $audit->recordPar($operatorId, $action, $options);
            } catch (\Throwable $e) {
                Logger::security('PLATFORM_2FA_AUDIT_ABORTED', "{$action} operator_id={$operatorId} : journal d'audit indisponible (" . get_class($e) . ')');
                throw new TwoFactorException('Opération annulée : la trace d\'audit n\'a pas pu être enregistrée. Aucune modification n\'a été appliquée.', 0, $e);
            }
            $point !== null ? $this->pdo->exec("RELEASE SAVEPOINT $point") : $this->pdo->commit();
            // Alerte par e-mail UNIQUEMENT après un vrai commit (jamais dans une transaction englobante qui pourrait encore être annulée).
            if ($point === null && isset(self::ALERTES[$action])) {
                PlatformSecurityNotifier::notifier($operatorId, self::ALERTES[$action], [], $this->pdo);
            }
            return $r['codes'];
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
            Logger::error("2FA [{$ref}] " . get_class($e) . ' : ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')');
            throw new TwoFactorException("Opération impossible pour une raison technique (référence {$ref}). Aucune modification n'a été appliquée.", 0, $e);
        }
    }
}
