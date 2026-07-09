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
        $appKey = $_ENV['APP_KEY'] ?? 'edunova-default-backup-key-change-in-production';
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
