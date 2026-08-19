<?php

declare(strict_types=1);

namespace Core\Tenant;

use Core\Database;
use PDO;

/**
 * Paramètres génériques par établissement — couche générique au-dessus de
 * `etab_settings` (section/clé/valeur/type), déjà créée en Phase 14.2 mais
 * jamais exposée par un service générique (seule `BrandingService` l'utilisait,
 * limitée à la section 'branding'). Voir NIGER_APP_CONFIGURATION_AUDIT.md.
 *
 * Même discipline d'isolation que BrandingService/TenantAuthContext :
 * l'établissement est toujours un paramètre explicite, jamais un état
 * global implicite.
 *
 * Chaque section (ex: 'academique', 'finance', 'general') est un espace de
 * clés indépendant — ajouter un nouveau paramètre ne nécessite ni migration
 * ni modification de schéma, seulement un appel à set()/get() avec une
 * nouvelle clé. C'est la structure évolutive demandée : de nouveaux
 * paramètres peuvent être ajoutés plus tard sans changement de code ici.
 */
final class SettingsService
{
    private readonly TenantCache $cache;

    public function __construct(
        private readonly PDO $pdo,
        ?TenantCache $cache = null,
    ) {
        $this->cache = $cache ?? TenantCache::make();
    }

    public static function make(): self
    {
        return new self(Database::getInstance()->getConnection());
    }

    /** Valeur typée d'un paramètre, ou $default si absent/non défini. */
    public function get(int $etablissementId, string $section, string $cle, mixed $default = null): mixed
    {
        $section = $this->getSection($etablissementId, $section);
        return array_key_exists($cle, $section) ? $section[$cle] : $default;
    }

    /**
     * Toutes les clés d'une section, déjà castées selon leur `type` en base
     * (string/integer/boolean/json/datetime).
     *
     * @return array<string, mixed>
     */
    public function getSection(int $etablissementId, string $section): array
    {
        $cached = $this->cache->get($etablissementId, 'settings', $section);
        if (is_array($cached)) {
            return $cached;
        }

        $stmt = $this->pdo->prepare(
            "SELECT cle, valeur, type FROM etab_settings WHERE etablissement_id = ? AND section = ?"
        );
        $stmt->execute([$etablissementId, $section]);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[$row['cle']] = $this->cast($row['valeur'], $row['type']);
        }

        $this->cache->put($etablissementId, 'settings', $section, $out, 3600);
        return $out;
    }

    /**
     * Enregistre un paramètre (upsert). `$type` détermine le castage lors
     * de la relecture — 'json' sérialise automatiquement les tableaux.
     */
    public function set(
        int $etablissementId,
        string $section,
        string $cle,
        mixed $valeur,
        string $type = 'string',
        ?int $updatedBy = null,
    ): void {
        if (!in_array($type, ['string', 'integer', 'boolean', 'json', 'datetime'], true)) {
            throw new \InvalidArgumentException("Type de paramètre invalide : {$type}");
        }

        $stored = match ($type) {
            'json'    => json_encode($valeur, JSON_UNESCAPED_UNICODE),
            'boolean' => (string)(int)(bool)$valeur,
            default   => (string)$valeur,
        };

        $stmt = $this->pdo->prepare(
            "INSERT INTO etab_settings (etablissement_id, section, cle, valeur, type, updated_by)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), type = VALUES(type), updated_by = VALUES(updated_by)"
        );
        $stmt->execute([$etablissementId, $section, $cle, $stored, $type, $updatedBy]);

        $this->cache->forget($etablissementId, 'settings', $section);
    }

    /** Enregistre plusieurs clés d'une même section en une fois. */
    public function setMany(int $etablissementId, string $section, array $paires, ?int $updatedBy = null): void
    {
        foreach ($paires as $cle => $spec) {
            // $spec peut être soit une valeur brute (type 'string' implicite),
            // soit ['valeur' => ..., 'type' => ...] pour un type explicite.
            if (is_array($spec) && array_key_exists('valeur', $spec)) {
                $this->set($etablissementId, $section, $cle, $spec['valeur'], $spec['type'] ?? 'string', $updatedBy);
            } else {
                $this->set($etablissementId, $section, $cle, $spec, 'string', $updatedBy);
            }
        }
    }

    private function cast(?string $valeur, string $type): mixed
    {
        if ($valeur === null) {
            return null;
        }
        return match ($type) {
            'integer'  => (int)$valeur,
            'boolean'  => (bool)(int)$valeur,
            'json'     => json_decode($valeur, true),
            default    => $valeur,
        };
    }
}
