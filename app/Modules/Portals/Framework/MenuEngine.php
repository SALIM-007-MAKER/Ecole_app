<?php
declare(strict_types=1);

namespace App\Modules\Portals\Framework;

use App\Modules\Portals\DTO\MenuDTO;
use App\Modules\Portals\DTO\MenuItemDTO;

class MenuEngine
{
    /** Badges dynamiques injectés avant le rendu (ex: notifications non lues) */
    private array $badges = [];

    public function build(string $portal, string $uri, array $perms): MenuDTO
    {
        $config = $this->loadConfig($portal);
        $items  = $this->buildItems($config, $uri, $perms);

        return new MenuDTO(
            portal:      $portal,
            items:       $items,
            breadcrumbs: $this->buildBreadcrumbs($uri, $portal),
        );
    }

    public function addBadge(string $itemId, int $count): void
    {
        if ($count > 0) {
            $this->badges[$itemId] = $count;
        }
    }

    private function buildItems(array $config, string $uri, array $perms): array
    {
        $items = [];
        foreach ($config as $itemCfg) {
            if (!$this->hasPermission($itemCfg, $perms)) {
                continue;
            }
            $children = [];
            foreach ($itemCfg['children'] ?? [] as $childCfg) {
                if (!$this->hasPermission($childCfg, $perms)) {
                    continue;
                }
                $children[] = new MenuItemDTO(
                    id:         $childCfg['id'],
                    label:      $childCfg['label'],
                    icon:       $childCfg['icon']   ?? '',
                    url:        $childCfg['url']    ?? '#',
                    permission: $childCfg['perm']   ?? null,
                    badge:      $this->badges[$childCfg['id']] ?? null,
                    active:     $this->isActive($childCfg['url'] ?? '', $uri),
                    children:   [],
                    separator:  $childCfg['sep']    ?? false,
                );
            }
            $items[] = new MenuItemDTO(
                id:         $itemCfg['id'],
                label:      $itemCfg['label'],
                icon:       $itemCfg['icon']   ?? '',
                url:        $itemCfg['url']    ?? '#',
                permission: $itemCfg['perm']   ?? null,
                badge:      $this->badges[$itemCfg['id']] ?? null,
                active:     $this->isActive($itemCfg['url'] ?? '', $uri) || $this->hasActiveChild($children),
                children:   $children,
                separator:  $itemCfg['sep'] ?? false,
            );
        }
        return $items;
    }

    private function hasPermission(array $cfg, array $perms): bool
    {
        return !isset($cfg['perm']) || in_array($cfg['perm'], $perms, true);
    }

    private function isActive(string $url, string $uri): bool
    {
        if ($url === '' || $url === '#') {
            return false;
        }
        $cleanUrl = strtok($url, '?') ?: $url;
        $cleanUri = strtok($uri, '?') ?: $uri;
        return str_starts_with($cleanUri, $cleanUrl);
    }

    private function hasActiveChild(array $children): bool
    {
        foreach ($children as $child) {
            if ($child->active) {
                return true;
            }
        }
        return false;
    }

    private function buildBreadcrumbs(string $uri, string $portal): array
    {
        $uri   = strtok($uri, '?') ?: $uri;
        $parts = array_values(array_filter(explode('/', trim($uri, '/'))));
        $crumbs = [];
        $path   = '';
        foreach ($parts as $part) {
            $path .= '/' . $part;
            $crumbs[] = [
                'label' => $this->labelFor($part, $portal),
                'url'   => $path,
            ];
        }
        return $crumbs;
    }

    private function labelFor(string $slug, string $portal): string
    {
        $labels = [
            'v2'          => 'V2',
            'portals'     => 'Portails',
            $portal       => ucfirst($portal),
            'dashboard'   => 'Tableau de bord',
            'preferences' => 'Préférences',
            'notifications' => 'Notifications',
            'recherche'   => 'Recherche',
            'raccourcis'  => 'Raccourcis',
        ];
        return $labels[$slug] ?? ucfirst(str_replace(['-', '_'], ' ', $slug));
    }

    private function loadConfig(string $portal): array
    {
        $file = ROOT_PATH . '/app/Modules/Portals/Config/menu.' . $portal . '.php';
        if (file_exists($file)) {
            return require $file;
        }
        return [];
    }
}
