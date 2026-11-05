<?php

declare(strict_types=1);

namespace Core\Platform\WebAuthn;

/**
 * Vérification WebAuthn niveau 2 (passkeys, clés de sécurité), sans dépendance externe.
 *
 * Périmètre volontairement étroit : algorithmes ES256 (ECDSA P-256) et RS256 (RSASSA-PKCS1-v1_5), attestation « none » (l'attestation n'est pas
 * vérifiée : elle ne sert qu'à prouver le modèle de l'appareil, pas à authentifier l'utilisateur). Les contrôles faits :
 *   - type de l'opération, défi (à usage unique, comparé à temps constant) et origine exacte ;
 *   - empreinte du RP ID, bit « présence de l'utilisateur » ;
 *   - signature de l'assertion sur authData || SHA-256(clientDataJSON), avec la clé publique enregistrée.
 * Le compteur de signatures est contrôlé par PlatformPasskeyService (il demande l'état en base).
 */
final class WebAuthnVerifier
{
    public const ALG_ES256 = -7;
    public const ALG_RS256 = -257;

    private const FLAG_UP = 0x01;
    private const FLAG_AT = 0x40;

    public static function b64uEncode(string $binaire): string
    {
        return rtrim(strtr(base64_encode($binaire), '+/', '-_'), '=');
    }

    /** @throws WebAuthnException */
    public static function b64uDecode(string $texte): string
    {
        if ($texte === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $texte)) {
            throw new WebAuthnException('Valeur base64url invalide.');
        }
        $brut = base64_decode(strtr($texte, '-_', '+/') . str_repeat('=', (4 - strlen($texte) % 4) % 4), true);
        if ($brut === false) {
            throw new WebAuthnException('Valeur base64url invalide.');
        }
        return $brut;
    }

    /**
     * Vérifie une création de passkey.
     *
     * @return array{credential_id:string, public_key_pem:string, alg:int, sign_count:int} identifiant en octets bruts
     * @throws WebAuthnException
     */
    public static function verifierEnregistrement(string $clientDataB64, string $attestationB64, string $defi, string $rpId, string $origine): array
    {
        self::verifierClientData(self::b64uDecode($clientDataB64), 'webauthn.create', $defi, $origine);

        $attestation = Cbor::decodeComplet(self::b64uDecode($attestationB64));
        if (!is_array($attestation) || !isset($attestation['authData']) || !is_string($attestation['authData'])) {
            throw new WebAuthnException('Objet d\'attestation invalide.');
        }
        $auth = self::lireAuthData($attestation['authData'], $rpId, true);
        if (!$auth['donnees_credential']) {
            throw new WebAuthnException('Aucune donnée de credential.');
        }
        ['id' => $id, 'cose' => $cose] = $auth['donnees_credential'];
        if ($id === '' || strlen($id) > 1023) {
            throw new WebAuthnException('Identifiant de credential invalide.');
        }
        [$pem, $alg] = self::coseVersPem($cose);
        return ['credential_id' => $id, 'public_key_pem' => $pem, 'alg' => $alg, 'sign_count' => $auth['sign_count']];
    }

    /**
     * Vérifie une assertion (connexion). Renvoie le compteur de signatures annoncé par l'appareil.
     *
     * @throws WebAuthnException
     */
    public static function verifierAssertion(string $clientDataB64, string $authDataB64, string $signatureB64, string $defi, string $rpId, string $origine, string $pem): int
    {
        $clientData = self::b64uDecode($clientDataB64);
        self::verifierClientData($clientData, 'webauthn.get', $defi, $origine);

        $authData = self::b64uDecode($authDataB64);
        $auth = self::lireAuthData($authData, $rpId, false);
        $signature = self::b64uDecode($signatureB64);

        $ok = openssl_verify($authData . hash('sha256', $clientData, true), $signature, $pem, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            throw new WebAuthnException('Signature invalide.');
        }
        return $auth['sign_count'];
    }

    // ───────────────────────────────────────────── éléments communs ─────────────────────────────────────────────

    /** @throws WebAuthnException */
    private static function verifierClientData(string $json, string $typeAttendu, string $defi, string $origine): void
    {
        $c = json_decode($json, true);
        if (!is_array($c) || ($c['type'] ?? null) !== $typeAttendu) {
            throw new WebAuthnException('Type d\'opération inattendu.');
        }
        if (!is_string($c['challenge'] ?? null) || !hash_equals(self::b64uEncode($defi), $c['challenge'])) {
            throw new WebAuthnException('Défi incorrect.');
        }
        if (!is_string($c['origin'] ?? null) || !hash_equals($origine, $c['origin'])) {
            throw new WebAuthnException('Origine incorrecte.');
        }
        if (($c['crossOrigin'] ?? false) === true) {
            throw new WebAuthnException('Requête inter-origine refusée.');
        }
    }

    /**
     * @return array{sign_count:int, donnees_credential:?array{id:string, cose:array}}
     * @throws WebAuthnException
     */
    private static function lireAuthData(string $a, string $rpId, bool $avecCredential): array
    {
        if (strlen($a) < 37) {
            throw new WebAuthnException('Données d\'authentificateur trop courtes.');
        }
        if (!hash_equals(hash('sha256', $rpId, true), substr($a, 0, 32))) {
            throw new WebAuthnException('RP ID incorrect.');
        }
        $flags = ord($a[32]);
        if (($flags & self::FLAG_UP) === 0) {
            throw new WebAuthnException('Présence de l\'utilisateur non confirmée.');
        }
        $signCount = (int)unpack('N', substr($a, 33, 4))[1];
        $credential = null;
        if ($avecCredential) {
            if (($flags & self::FLAG_AT) === 0 || strlen($a) < 55) {
                throw new WebAuthnException('Données de credential absentes.');
            }
            $longueur = (int)unpack('n', substr($a, 53, 2))[1];
            if (strlen($a) < 55 + $longueur) {
                throw new WebAuthnException('Données de credential tronquées.');
            }
            $id = substr($a, 55, $longueur);
            $offset = 55 + $longueur;
            $cose = Cbor::decode($a, $offset);
            if (!is_array($cose)) {
                throw new WebAuthnException('Clé publique invalide.');
            }
            $credential = ['id' => $id, 'cose' => $cose];
        }
        return ['sign_count' => $signCount, 'donnees_credential' => $credential];
    }

    // ───────────────────────────────────────────── clé publique COSE → PEM ─────────────────────────────────────────────

    /**
     * @param array<int|string,mixed> $c clé COSE décodée
     * @return array{0:string, 1:int} [PEM SubjectPublicKeyInfo, algorithme]
     * @throws WebAuthnException
     */
    private static function coseVersPem(array $c): array
    {
        $kty = $c[1] ?? null;
        $alg = $c[3] ?? null;
        if ($kty === 2 && $alg === self::ALG_ES256 && ($c[-1] ?? null) === 1 && is_string($c[-2] ?? null) && is_string($c[-3] ?? null)
            && strlen($c[-2]) === 32 && strlen($c[-3]) === 32) {
            $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . "\x04" . $c[-2] . $c[-3];
            return [self::pem($der), self::ALG_ES256];
        }
        if ($kty === 3 && $alg === self::ALG_RS256 && is_string($c[-1] ?? null) && is_string($c[-2] ?? null) && strlen($c[-1]) >= 256 && strlen($c[-1]) <= 1024) {
            $rsa = self::der(0x30, self::derEntier($c[-1]) . self::derEntier($c[-2]));
            $spki = self::der(0x30, hex2bin('300d06092a864886f70d0101010500') . self::der(0x03, "\x00" . $rsa));
            return [self::pem($spki), self::ALG_RS256];
        }
        throw new WebAuthnException('Algorithme de clé non pris en charge (ES256 et RS256 uniquement).');
    }

    private static function pem(string $der): string
    {
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        if (openssl_pkey_get_public($pem) === false) {
            throw new WebAuthnException('Clé publique illisible.');
        }
        return $pem;
    }

    private static function der(int $tag, string $contenu): string
    {
        $n = strlen($contenu);
        if ($n < 128) {
            $l = chr($n);
        } else {
            $octets = ltrim(pack('N', $n), "\x00");
            $l = chr(0x80 | strlen($octets)) . $octets;
        }
        return chr($tag) . $l . $contenu;
    }

    private static function derEntier(string $binaire): string
    {
        $binaire = ltrim($binaire, "\x00");
        if ($binaire === '' || (ord($binaire[0]) & 0x80)) {
            $binaire = "\x00" . $binaire;
        }
        return self::der(0x02, $binaire);
    }
}
