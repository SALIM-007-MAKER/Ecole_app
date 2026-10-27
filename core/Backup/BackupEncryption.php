<?php

declare(strict_types=1);

namespace Core\Backup;

/**
 * Chiffrement des sauvegardes — AES-256-CBC, clé dérivée de APP_KEY (même
 * convention que Core\Storage\LocalStorageAdapter pour les URLs signées et
 * l'API Platform pour le secret JWT — voir config/api.php).
 * Phase 14.11, blueprint §19.1 ("Format : ZIP chiffré AES-256").
 */
final class BackupEncryption
{
    private const CIPHER = 'aes-256-cbc';

    public function __construct(private readonly string $appKey)
    {
    }

    public static function make(): self
    {
        $appKey = $_ENV['APP_KEY'] ?? (getenv('APP_KEY') ?: '');
        // Aucune clé par défaut : une sauvegarde « chiffrée » avec une clé publique ne protège rien.
        if (!is_string($appKey) || strlen($appKey) < 16 || str_contains($appKey, 'change-in-production')) {
            throw new \RuntimeException('APP_KEY absente ou trop courte (16 caractères minimum) : le chiffrement des sauvegardes est refusé.');
        }
        return new self($appKey);
    }

    private function derivedKey(): string
    {
        return hash('sha256', $this->appKey . ':backup-encryption', true); // 32 octets = AES-256
    }

    /** Retourne IV (16 octets) + texte chiffré concaténés. */
    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $this->derivedKey(), OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new \RuntimeException('Échec du chiffrement de la sauvegarde.');
        }
        return $iv . $ciphertext;
    }

    public function decrypt(string $payload): string
    {
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        if (strlen($payload) <= $ivLength) {
            throw new \RuntimeException('Charge utile chiffrée invalide (trop courte).');
        }
        $iv = substr($payload, 0, $ivLength);
        $ciphertext = substr($payload, $ivLength);
        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $this->derivedKey(), OPENSSL_RAW_DATA, $iv);
        if ($plaintext === false) {
            throw new \RuntimeException('Échec du déchiffrement — clé incorrecte ou sauvegarde corrompue.');
        }
        return $plaintext;
    }
}
