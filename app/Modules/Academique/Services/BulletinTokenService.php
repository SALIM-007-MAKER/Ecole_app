<?php

declare(strict_types=1);

namespace App\Modules\Academique\Services;

/**
 * Jetons de vérification des bulletins (QR codes officiels).
 *
 * ── Définition ───────────────────────────────────────────────────────────────────────────
 *   jeton = HMAC-SHA256( clé, message ), écrit en hexadécimal minuscule (64 caractères)
 *
 *   clé     = hex2bin(BULLETIN_TOKEN_SECRET)            (≥ 32 octets, jamais dérivée d'un autre secret)
 *   message = "bulletin|v2|{établissement}|{élève}|{période}"
 *
 * ── Représentation canonique du message (données signées) ───────────────────────────────
 *   - texte ASCII, encodage UTF-8, aucun espace, aucun retour à la ligne ;
 *   - 5 champs séparés par « | » dans cet ordre exact : étiquette « bulletin », version « v2 »,
 *     identifiant de l'établissement, identifiant de l'élève, identifiant de la période ;
 *   - chaque identifiant est un entier STRICTEMENT positif écrit en base 10, sans signe, sans zéro
 *     initial, sans séparateur de milliers (sprintf('%d')). Comme aucun champ ne peut contenir « | »,
 *     deux jeux d'identifiants différents ne produisent jamais le même message (pas d'ambiguïté) ;
 *   - l'étiquette et la version assurent la séparation de domaine : un HMAC calculé pour un autre
 *     usage, ou pour une autre version du format, ne peut pas être confondu avec un jeton de bulletin ;
 *   - l'établissement fait partie du message : le même couple (élève, période) donne des jetons
 *     différents d'un établissement à l'autre.
 *   Le statut, les notes et les noms ne sont PAS signés : le jeton identifie un bulletin, il ne
 *   certifie pas son contenu.
 *
 * ── Propriétés ───────────────────────────────────────────────────────────────────────────
 *   - déterministe : mêmes identifiants et même secret donnent toujours le même jeton, donc une
 *     régénération du bulletin ne change jamais silencieusement son jeton ;
 *   - non devinable sans le secret ; comparaison en temps constant (hash_equals) ;
 *   - AUCUN secret par défaut, AUCUN repli vers APP_KEY ni vers un autre secret : sans
 *     BULLETIN_TOKEN_SECRET valide, toute émission ou vérification échoue explicitement ;
 *   - le secret et les jetons complets ne sont jamais journalisés : utiliser fingerprint() pour les logs.
 */
final class BulletinTokenService
{
    public const VERSION = 2;
    public const ENV_VAR = 'BULLETIN_TOKEN_SECRET';

    /** Longueur minimale du secret hexadécimal : 64 caractères = 32 octets = 256 bits. */
    private const MIN_HEX_LENGTH = 64;

    /** Nombre minimal de caractères hexadécimaux distincts (refuse « 0000… », « abab… », etc.). */
    private const MIN_DISTINCT_CHARS = 12;

    /** Secrets de l'application avec lesquels BULLETIN_TOKEN_SECRET ne doit jamais être identique. */
    private const FORBIDDEN_REUSE = ['APP_KEY', 'JWT_SECRET', 'STORAGE_SIGNING_KEY', 'VAPID_PRIVATE_KEY'];

    private string $key;

    private function __construct(string $secretHex)
    {
        $this->key = (string)hex2bin($secretHex);
    }

    /**
     * Construit le service depuis l'environnement ($_ENV puis getenv()), ou depuis $env si fourni (tests).
     *
     * @param array<string,mixed>|null $env
     * @throws BulletinTokenException si le secret est absent ou invalide
     */
    public static function fromEnvironment(?array $env = null): self
    {
        $read = static function (string $name) use ($env): string {
            if ($env !== null) {
                $v = $env[$name] ?? '';
            } else {
                $v = $_ENV[$name] ?? getenv($name);
            }
            return is_string($v) ? $v : '';
        };

        $secret = $read(self::ENV_VAR);
        if ($secret === '') {
            throw BulletinTokenException::missing();
        }
        if (strlen($secret) < self::MIN_HEX_LENGTH || strlen($secret) % 2 !== 0 || preg_match('/^[0-9a-fA-F]+$/D', $secret) !== 1) {
            throw BulletinTokenException::invalid(
                BulletinTokenException::FORMAT,
                'au moins ' . self::MIN_HEX_LENGTH . ' caractères hexadécimaux (nombre pair) sont requis.'
            );
        }
        if (self::isPredictable(strtolower($secret))) {
            throw BulletinTokenException::invalid(BulletinTokenException::LOW_ENTROPY, 'valeur trop prévisible (peu de caractères distincts ou motif répété).');
        }
        foreach (self::FORBIDDEN_REUSE as $other) {
            $o = $read($other);
            if ($o !== '' && hash_equals(strtolower($o), strtolower($secret))) {
                throw BulletinTokenException::invalid(BulletinTokenException::REUSED, 'ne doit pas être identique à ' . $other . '.');
            }
        }

        return new self($secret);
    }

    /**
     * Valeur évidente : trop peu de caractères distincts (un secret aléatoire de 64 hexadécimaux en a
     * au moins 12 avec une probabilité de 1 - 2·10⁻⁷) ou motif court répété sur toute la longueur.
     */
    private static function isPredictable(string $hex): bool
    {
        if (count(array_unique(str_split($hex))) < self::MIN_DISTINCT_CHARS) {
            return true;
        }
        $len = strlen($hex);
        for ($p = 1; $p <= intdiv($len, 2); $p++) {
            if ($len % $p === 0 && str_repeat(substr($hex, 0, $p), intdiv($len, $p)) === $hex) {
                return true;
            }
        }
        return false;
    }

    /** Vrai si l'environnement contient un secret valide (sans lever d'exception). */
    public static function isConfigured(?array $env = null): bool
    {
        try {
            self::fromEnvironment($env);
            return true;
        } catch (BulletinTokenException) {
            return false;
        }
    }

    /** Message signé, sous sa représentation canonique (voir l'en-tête de la classe). */
    public static function canonicalMessage(int $etablissementId, int $eleveId, int $periodeId): string
    {
        if ($etablissementId < 1 || $eleveId < 1 || $periodeId < 1) {
            throw new BulletinTokenException('Identifiants de bulletin invalides.', BulletinTokenException::INPUT);
        }
        return sprintf('bulletin|v%d|%d|%d|%d', self::VERSION, $etablissementId, $eleveId, $periodeId);
    }

    /** Jeton officiel (64 hexadécimaux minuscules) pour ce bulletin. */
    public function issue(int $etablissementId, int $eleveId, int $periodeId): string
    {
        return hash_hmac('sha256', self::canonicalMessage($etablissementId, $eleveId, $periodeId), $this->key);
    }

    /** Le jeton présenté est-il exactement celui de ce bulletin ? (format strict + temps constant) */
    public function matches(string $token, int $etablissementId, int $eleveId, int $periodeId): bool
    {
        if (!self::isWellFormed($token)) {
            return false;
        }
        return hash_equals($this->issue($etablissementId, $eleveId, $periodeId), $token);
    }

    /** Format attendu d'un jeton : exactement 64 hexadécimaux minuscules (aucune tolérance de casse). */
    public static function isWellFormed(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/D', $token) === 1;
    }

    /**
     * Empreinte courte d'un jeton, destinée AUX JOURNAUX (12 caractères, non réversible, avec séparation
     * de domaine). Permet de recouper des événements sans jamais écrire le jeton complet.
     */
    public static function fingerprint(string $token): string
    {
        return substr(hash('sha256', 'bulletin-token-fingerprint|' . $token), 0, 12);
    }

    /**
     * Empreinte complète servant d'identifiant d'un jeton RETIRÉ (table bulletin_tokens_retired) :
     * on ne conserve jamais l'ancien jeton lui-même.
     */
    public static function retiredDigest(string $token): string
    {
        return hash('sha256', $token);
    }

    // ── Garde-fous contre les fuites accidentelles de la clé ─────────────────────────────

    /** var_dump / print_r / débogueurs : la clé n'apparaît jamais. */
    public function __debugInfo(): array
    {
        return ['version' => self::VERSION, 'key' => '[masquée]'];
    }

    /** Interdit la sérialisation (cache, session, journaux) qui écrirait la clé. */
    public function __serialize(): array
    {
        throw new \LogicException('BulletinTokenService ne doit pas être sérialisé.');
    }

    public function __unserialize(array $data): void
    {
        throw new \LogicException('BulletinTokenService ne doit pas être désérialisé.');
    }
}
