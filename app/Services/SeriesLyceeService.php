<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use Core\Tenant\SettingsService;
use Core\Tenant\TenantContext;

/**
 * Séries du lycée (A, C, D… ou personnalisées), configurées INDÉPENDAMMENT pour chaque niveau (Seconde, Première, Terminale).
 *
 * - Réglage `academique.series_par_niveau` (etab_settings, json) : {"Seconde": ["A","C"], "Première": ["A","C","D"], "Terminale": ["C","D"]}.
 *   Un niveau absent = aucune série (ex. Seconde en tronc commun) ; rien n'est présumé : un établissement doit activer ses séries.
 * - A, C et D sont PROPOSÉES par défaut dans l'écran de configuration, mais une série personnalisée (1 à 10 caractères) est possible
 *   sans migration : la colonne classes.serie est libre.
 * - La série est optionnelle sur une classe, réservée aux niveaux du lycée, et doit être activée pour le niveau de la classe ;
 *   une classe dont la série a été désactivée depuis reste modifiable (série inchangée acceptée).
 * - Avant la migration T074 (colonne absente), toute la fonctionnalité est inerte : {@see disponible()}.
 */
final class SeriesLyceeService
{
    public const NIVEAUX_LYCEE = ['Seconde', 'Première', 'Terminale'];
    public const SERIES_PAR_DEFAUT = ['A', 'C', 'D'];

    private static ?bool $disponible = null;

    public function __construct(private readonly SettingsService $settings, private readonly int $etablissementId) {}

    public static function pour(?int $etablissementId = null): self
    {
        $id = $etablissementId
            ?? TenantContext::current()
            ?? (int)((require ROOT_PATH . '/config/tenant.php')['default_id'] ?? 1);
        return new self(SettingsService::make(), $id);
    }

    /** La colonne classes.serie existe-t-elle (migration T074 appliquée) ? Mémorisé pour la requête. */
    public static function disponible(): bool
    {
        if (self::$disponible === null) {
            $pdo = Database::getInstance()->getConnection();
            $s = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classes' AND COLUMN_NAME = 'serie'");
            self::$disponible = (int)$s->fetchColumn() > 0;
        }
        return self::$disponible;
    }

    /** Pour les tests : oublie la détection mémorisée (changement de base). */
    public static function oublierDetection(): void
    {
        self::$disponible = null;
    }

    public static function estNiveauLycee(?string $niveau): bool
    {
        return in_array((string)$niveau, self::NIVEAUX_LYCEE, true);
    }

    /**
     * Code de série normalisé : espaces retirés, majuscules ; '' → null. Retourne false si le code est invalide
     * (1 à 10 caractères : lettres ou chiffres, tiret admis à l'intérieur).
     */
    public static function normaliser(?string $serie): string|false|null
    {
        $serie = mb_strtoupper(trim((string)$serie), 'UTF-8');
        if ($serie === '') {
            return null;
        }
        return preg_match('/^[\p{L}\p{N}](?:[\p{L}\p{N}\-]{0,8}[\p{L}\p{N}])?$/u', $serie) === 1 && mb_strlen($serie, 'UTF-8') <= 10 ? $serie : false;
    }

    /**
     * Séries activées, par niveau de lycée. Contenu illisible ou invalide : ignoré, jamais d'erreur.
     *
     * @return array<string, list<string>>
     */
    public function seriesParNiveau(): array
    {
        $brut = $this->settings->get($this->etablissementId, 'academique', 'series_par_niveau', null);
        $out = [];
        if (!is_array($brut)) {
            return $out;
        }
        foreach (self::NIVEAUX_LYCEE as $niveau) {
            $codes = [];
            foreach ((array)($brut[$niveau] ?? []) as $code) {
                $n = is_string($code) ? self::normaliser($code) : false;
                if (is_string($n) && !in_array($n, $codes, true)) {
                    $codes[] = $n;
                }
            }
            if ($codes !== []) {
                $out[$niveau] = $codes;
            }
        }
        return $out;
    }

    /** @return list<string> séries activées pour ce niveau (vide hors lycée ou si rien n'est activé) */
    public function seriesPour(string $niveau): array
    {
        return $this->seriesParNiveau()[$niveau] ?? [];
    }

    /**
     * Valide la série d'une classe. Retourne un message d'erreur, ou null si valide.
     *
     * @param string|null $serie          série normalisée (null = aucune, toujours acceptée)
     * @param string|null $niveauActuel   niveau enregistré de la classe (modification), null à la création
     * @param string|null $serieActuelle  série enregistrée de la classe (modification), null à la création
     */
    public function erreurSerie(string $niveau, ?string $serie, ?string $niveauActuel = null, ?string $serieActuelle = null): ?string
    {
        if ($serie === null) {
            return null;
        }
        if (!self::estNiveauLycee($niveau)) {
            return 'Une série n\'existe qu\'au lycée (Seconde, Première, Terminale).';
        }
        // Classe déjà dans cette série et ce niveau : inchangée, reste valide même si la série a été désactivée depuis.
        if ($serieActuelle !== null && $serie === $serieActuelle && $niveau === $niveauActuel) {
            return null;
        }
        if (!in_array($serie, $this->seriesPour($niveau), true)) {
            return 'La série « ' . $serie . ' » n\'est pas activée pour ' . $niveau . ' (Paramètres → Organisation académique).';
        }
        return null;
    }

    /**
     * Valide et normalise la configuration postée : séries cochées + codes personnalisés, par niveau.
     *
     * @param  array<string, mixed> $postees  ['Seconde' => ['A','C'], …]
     * @param  array<string, mixed> $perso    ['Seconde' => 'B, E', …] (codes séparés par virgule)
     * @return array{series: array<string, list<string>>, erreurs: list<string>}
     */
    public static function depuisSaisie(array $postees, array $perso): array
    {
        $series = [];
        $erreurs = [];
        foreach (self::NIVEAUX_LYCEE as $niveau) {
            $codes = [];
            $candidats = is_array($postees[$niveau] ?? null) ? $postees[$niveau] : [];
            foreach (preg_split('/[,;\s]+/', (string)($perso[$niveau] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $c) {
                $candidats[] = $c;
            }
            foreach ($candidats as $c) {
                $n = is_string($c) ? self::normaliser($c) : false;
                if ($n === false) {
                    $erreurs[] = $niveau . ' : série invalide « ' . (is_string($c) ? $c : '?') . ' » (1 à 10 caractères : lettres, chiffres, tiret).';
                } elseif ($n !== null && !in_array($n, $codes, true)) {
                    $codes[] = $n;
                }
            }
            if ($codes !== []) {
                $series[$niveau] = $codes;
            }
        }
        return ['series' => $series, 'erreurs' => $erreurs];
    }
}
