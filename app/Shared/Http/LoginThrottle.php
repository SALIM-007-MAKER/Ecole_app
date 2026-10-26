<?php

declare(strict_types=1);

namespace App\Shared\Http;

/**
 * Limitation des ÉCHECS de connexion (anti-force-brute), par clé nommée sur une fenêtre glissante.
 *
 * Contrairement à PublicThrottle (qui compte chaque requête), seuls les échecs sont comptés : une connexion réussie
 * ne consomme rien et efface le compteur de sa clé. Le contrôle (isLocked) se fait AVANT la vérification du mot de
 * passe, pour qu'un bon mot de passe ne passe pas pendant un blocage.
 *
 * Stockage : un fichier JSON par clé, nom = SHA-256 (ni e-mail ni IP en clair), verrou exclusif.
 * Disponibilité : si le stockage est inutilisable, la connexion reste possible et l'incident est journalisé.
 */
final class LoginThrottle
{
    /** @var callable():int */
    private $clock;

    public function __construct(
        private readonly string $directory,
        private readonly int $windowSeconds = 900,
        ?callable $clock = null,
    ) {
        $this->clock = $clock ?? static fn(): int => time();
    }

    /** Secondes restantes avant la fin du blocage de cette clé (0 = non bloquée) pour la limite donnée. */
    public function retryAfter(string $key, int $limit): int
    {
        $now = ($this->clock)();
        return $this->withFile($key, function (array $stamps) use ($limit, $now) {
            $stamps = $this->recent($stamps, $now);
            if (count($stamps) < $limit) {
                return [0, null];
            }
            // Le blocage cesse quand le plus ancien échec du lot décisif sort de la fenêtre.
            $decisif = $stamps[count($stamps) - $limit];
            return [max(1, $decisif + $this->windowSeconds - $now), null];
        }, 0);
    }

    /** Enregistre un échec pour cette clé. */
    public function recordFailure(string $key): void
    {
        $now = ($this->clock)();
        $this->withFile($key, function (array $stamps) use ($now) {
            $stamps = $this->recent($stamps, $now);
            $stamps[] = $now;
            return [null, $stamps];
        }, null);
    }

    /** Efface le compteur de cette clé (connexion réussie). */
    public function clear(string $key): void
    {
        $this->withFile($key, fn(array $s) => [null, []], null);
    }

    /** @return list<int> */
    private function recent(array $stamps, int $now): array
    {
        return array_values(array_filter($stamps, fn($t) => is_int($t) && $t > $now - $this->windowSeconds));
    }

    /**
     * Lit le fichier sous verrou, applique $fn(stamps) => [valeur, nouveauxStamps|null] et réécrit si besoin.
     *
     * @return mixed la valeur de $fn, ou $fallback si le stockage est indisponible
     */
    private function withFile(string $key, callable $fn, mixed $fallback): mixed
    {
        try {
            if (!is_dir($this->directory) && !@mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
                throw new \RuntimeException('dossier de limitation indisponible');
            }
            $file = $this->directory . DIRECTORY_SEPARATOR . 'login_' . hash('sha256', $key) . '.json';
            $fh = @fopen($file, 'c+');
            if ($fh === false || !flock($fh, LOCK_EX)) {
                throw new \RuntimeException('fichier de limitation inutilisable');
            }
            try {
                $raw = stream_get_contents($fh);
                $stamps = is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?: []) : [];
                [$value, $new] = $fn($stamps);
                if ($new !== null) {
                    ftruncate($fh, 0);
                    rewind($fh);
                    fwrite($fh, json_encode($new));
                    fflush($fh);
                }
                return $value;
            } finally {
                flock($fh, LOCK_UN);
                fclose($fh);
                $this->purgeOldFiles();
            }
        } catch (\Throwable $e) {
            if (class_exists(\Core\Logger::class)) {
                \Core\Logger::error('LoginThrottle indisponible (connexion non limitée) : ' . $e->getMessage());
            }
            return $fallback;
        }
    }

    /** Nettoyage occasionnel (1 appel sur 50) des fichiers dont la fenêtre est écoulée (date réelle des fichiers). */
    private function purgeOldFiles(): void
    {
        if (random_int(1, 50) !== 1) {
            return;
        }
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . 'login_*.json') ?: [] as $f) {
            if (@filemtime($f) !== false && filemtime($f) < time() - 2 * $this->windowSeconds) {
                @unlink($f);
            }
        }
    }
}
