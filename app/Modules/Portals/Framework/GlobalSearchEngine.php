<?php
declare(strict_types=1);

namespace App\Modules\Portals\Framework;

use App\Modules\Portals\Contracts\SearchHandlerInterface;
use App\Modules\Portals\DTO\SearchResultDTO;
use App\Modules\Portals\DTO\SearchResultsDTO;

/**
 * Moteur de recherche fédérée — même pattern statique que EventDispatcher/WidgetRegistry.
 * Les handlers sont enregistrés via PortalBootstrap::boot().
 */
class GlobalSearchEngine
{
    /** @var SearchHandlerInterface[] */
    private static array $handlers = [];

    public static function registerHandler(SearchHandlerInterface $handler): void
    {
        self::$handlers[] = $handler;
    }

    public static function reset(): void
    {
        self::$handlers = [];
    }

    public function search(
        string $query,
        string $portal,
        int    $etablissementId,
        int    $userId,
        array  $perms,
        int    $limit = 10
    ): SearchResultsDTO {
        if (mb_strlen(trim($query)) < 2) {
            return new SearchResultsDTO($query, [], 0, 0);
        }

        $start    = hrtime(true);
        $results  = [];
        $handlers = $this->getHandlersForPortal($portal);

        // Trier par priorité ascendante
        usort($handlers, fn($a, $b) => $a->getPriority() <=> $b->getPriority());

        $perHandler = max(1, (int)ceil($limit / max(1, count($handlers))));

        foreach ($handlers as $handler) {
            if (!$this->handlerAllowed($handler, $perms)) {
                continue;
            }
            try {
                $found = $handler->search($query, $etablissementId, $userId, $perHandler);
                foreach ($found as $item) {
                    $results[] = SearchResultDTO::fromArray($item);
                }
            } catch (\Throwable $e) {
                error_log('[GlobalSearchEngine] Handler ' . $handler->getModule() . ': ' . $e->getMessage());
            }
        }

        // Dédoublonnage par module:id
        $seen  = [];
        $dedup = [];
        foreach ($results as $r) {
            $key = $r->module . ':' . $r->id;
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $dedup[]    = $r;
            }
        }

        $elapsed = (int)((hrtime(true) - $start) / 1e6);

        return new SearchResultsDTO(
            query:   $query,
            results: array_slice($dedup, 0, $limit),
            total:   count($dedup),
            timeMs:  $elapsed,
        );
    }

    private function getHandlersForPortal(string $portal): array
    {
        return array_values(array_filter(
            self::$handlers,
            fn(SearchHandlerInterface $h) => in_array($portal, $h->getPortals(), true)
        ));
    }

    private function handlerAllowed(SearchHandlerInterface $handler, array $perms): bool
    {
        foreach ($handler->getPermissions() as $perm) {
            if (!in_array($perm, $perms, true)) {
                return false;
            }
        }
        return true;
    }
}
