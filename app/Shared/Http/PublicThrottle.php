<?php

declare(strict_types=1);

namespace App\Shared\Http;

/**
 * Limitation de débit des pages publiques, par seau nommé et par adresse IP (fenêtre glissante).
 *
 * Stockage : un petit fichier JSON par couple (seau, IP) dans un dossier dédié ; le nom du fichier est un
 * hachage SHA-256 (aucune adresse IP en clair dans les noms de fichiers). Verrou exclusif pour éviter les
 * conditions de concurrence entre requêtes simultanées.
 *
 * Disponibilité : si le stockage est inutilisable (dossier non inscriptible…), la requête est AUTORISÉE et
 * l'incident est journalisé — une panne de la limitation ne doit pas rendre la page publique indisponible.
 * (Les jetons font 256 bits : la limitation protège contre l'abus, pas contre le devinement.)
 */
final class PublicThrottle
{
    /** @var callable():int */
    private $clock;

    public function __construct(
        private readonly string $directory,
        private readonly int $limit = 30,
        private readonly int $windowSeconds = 60,
        ?callable $clock = null,
    ) {
        $this->clock = $clock ?? static fn(): int => time();
    }

    /**
     * Enregistre une requête et indique si elle est autorisée.
     *
     * @return array{allowed:bool, remaining:int, retryAfter:int}
     */
    public function hit(string $bucket, string $ip): array
    {
        $now = ($this->clock)();
        try {
            if (!is_dir($this->directory) && !@mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
                throw new \RuntimeException('dossier de limitation indisponible');
            }
            $file = $this->directory . DIRECTORY_SEPARATOR . hash('sha256', $bucket . '|' . $ip) . '.json';
            $fh = @fopen($file, 'c+');
            if ($fh === false || !flock($fh, LOCK_EX)) {
                throw new \RuntimeException('fichier de limitation inutilisable');
            }
            try {
                $raw = stream_get_contents($fh);
                $stamps = is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
                $stamps = array_values(array_filter($stamps, fn($t) => is_int($t) && $t > $now - $this->windowSeconds));

                if (count($stamps) >= $this->limit) {
                    $retry = max(1, ($stamps[0] + $this->windowSeconds) - $now);
                    return ['allowed' => false, 'remaining' => 0, 'retryAfter' => $retry];
                }
                $stamps[] = $now;
                ftruncate($fh, 0);
                rewind($fh);
                fwrite($fh, json_encode($stamps));
                fflush($fh);
                return ['allowed' => true, 'remaining' => $this->limit - count($stamps), 'retryAfter' => 0];
            } finally {
                flock($fh, LOCK_UN);
                fclose($fh);
                $this->purgeOldFiles();
            }
        } catch (\Throwable $e) {
            if (class_exists(\Core\Logger::class)) {
                \Core\Logger::error('PublicThrottle indisponible (requête autorisée) : ' . $e->getMessage());
            }
            return ['allowed' => true, 'remaining' => $this->limit, 'retryAfter' => 0];
        }
    }

    /**
     * Nettoyage occasionnel (1 requête sur 50) des fichiers dont la fenêtre est entièrement écoulée. Il se fonde sur
     * la date RÉELLE de modification des fichiers (time()), jamais sur l'horloge injectée, qui n'existe que pour les tests.
     */
    private function purgeOldFiles(): void
    {
        if (random_int(1, 50) !== 1) {
            return;
        }
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*.json') ?: [] as $f) {
            if (@filemtime($f) !== false && filemtime($f) < time() - 2 * $this->windowSeconds) {
                @unlink($f);
            }
        }
    }
}
