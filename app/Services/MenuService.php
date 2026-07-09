<?php

namespace App\Services;

use Core\Session;

class MenuService
{
    /**
     * Charge la structure des menus pour un utilisateur selon son rôle et permissions
     * 
     * @param string $role
     * @param array $permissions
     * @return array Structure du menu
     */
    public static function getMenuStructure(string $role, array $permissions = []): array
    {
        $menus = self::getMenusDefinition();
        
        if (!isset($menus[$role])) {
            return [];
        }

        $roleMenus = $menus[$role];
        
        // Filtrer les menus selon les permissions
        return self::filterMenuByPermissions($roleMenus, $permissions, $role);
    }

    /**
     * Retourne les items de navigation principaux pour la bottom nav mobile.
     */
    public static function getBottomNavItems(string $role, array $permissions = []): array
    {
        $items = self::getMenuStructure($role, $permissions);
        $visible = [];

        foreach ($items as $item) {
            if (!empty($item['children']) || empty($item['url'])) {
                continue;
            }

            $id = $item['id'] ?? '';
            if ($id === 'notifications') {
                continue;
            }

            $visible[] = $item;
            if (count($visible) >= 4) {
                break;
            }
        }

        if (count($visible) < 4) {
            $visible[] = [
                'id' => 'profile',
                'label' => 'Profil',
                'icon' => 'user',
                'url' => '/profile',
                'permissions' => [],
            ];
        }

        return array_slice($visible, 0, 5);
    }

    /**
     * Mappe les icônes du menu vers Font Awesome 6.
     */
    public static function getMenuIconClass(string $icon): string
    {
        return match ($icon) {
            'layout-dashboard', 'dashboard' => 'fa-house',
            'users', 'students' => 'fa-users',
            'building-2', 'school' => 'fa-school',
            'book-open-check', 'book-open' => 'fa-book-open',
            'wallet', 'finance' => 'fa-money-bill-wave',
            'calendar-days', 'calendar' => 'fa-calendar-days',
            'bar-chart-2', 'chart' => 'fa-chart-line',
            'megaphone', 'announcement' => 'fa-bullhorn',
            'bell', 'notifications' => 'fa-bell',
            'user', 'profile' => 'fa-user',
            'settings' => 'fa-gear',
            'search' => 'fa-magnifying-glass',
            'menu' => 'fa-bars',
            'x' => 'fa-xmark',
            'chevron-right' => 'fa-chevron-right',
            'pencil-line', 'pencil' => 'fa-pen-to-square',
            'calendar-x', 'calendar-xmark' => 'fa-calendar-xmark',
            'file-text' => 'fa-file-lines',
            'banknote', 'credit-card' => 'fa-credit-card',
            'alert-circle', 'warning' => 'fa-circle-exclamation',
            'clock' => 'fa-clock',
            'door-open' => 'fa-door-open',
            'graduation-cap' => 'fa-graduation-cap',
            'user-check' => 'fa-user-check',
            'building' => 'fa-building-columns',
            'vault' => 'fa-vault',
            'book' => 'fa-book',
            'comments', 'messages' => 'fa-comments',
            'palette' => 'fa-palette',
            'home' => 'fa-house',
            default => 'fa-circle',
        };
    }

    /**
     * Récupère toutes les définitions de menus par rôle
     */
    private static function getMenusDefinition(): array
    {
        return [
            'admin' => self::getAdminMenus(),
            'directeur' => self::getDirectorMenus(),
            'enseignant' => self::getTeacherMenus(),
            'secretaire' => self::getSecretaryMenus(),
            'comptable' => self::getAccountantMenus(),
            'eleve' => self::getStudentMenus(),
            'parent' => self::getParentMenus(),
        ];
    }

    /**
     * Menu Administrateur - Accès à TOUS les modules
     */
    private static function getAdminMenus(): array
    {
        return [
            [
                'id' => 'dashboard',
                'label' => 'Tableau de bord',
                'icon' => 'layout-dashboard',
                'url' => '/dashboard',
                'permissions' => [],
            ],
            [
                'id' => 'students',
                'label' => 'Élèves',
                'icon' => 'users',
                'url' => '/eleves',
                'permissions' => ['eleves.view'],
            ],
            [
                'id' => 'scolarite',
                'label' => 'Scolarité',
                'icon' => 'building-2',
                'permissions' => ['enseignants.view', 'classes.view', 'matieres.view'],
                'children' => [
                    ['label' => 'Enseignants', 'icon' => 'user-check', 'url' => '/professeurs', 'permissions' => ['enseignants.view']],
                    ['label' => 'Classes', 'icon' => 'building', 'url' => '/classes', 'permissions' => ['classes.view']],
                    ['label' => 'Matières', 'icon' => 'book', 'url' => '/matieres', 'permissions' => ['matieres.view']],
                ],
            ],
            [
                'id' => 'academique',
                'label' => 'Académique',
                'icon' => 'book-open-check',
                'permissions' => ['notes.view', 'bulletins.view', 'absences.view'],
                'children' => [
                    ['label' => 'Notes', 'icon' => 'pencil-line', 'url' => '/notes', 'permissions' => ['notes.view']],
                    ['label' => 'Bulletins', 'icon' => 'file-text', 'url' => '/bulletins', 'permissions' => ['bulletins.view']],
                    ['label' => 'Absences', 'icon' => 'calendar-x', 'url' => '/absences', 'permissions' => ['absences.view']],
                ],
            ],
            [
                'id' => 'finance',
                'label' => 'Finance',
                'icon' => 'wallet',
                'permissions' => ['comptabilite.view'],
                'children' => [
                    ['label' => 'Vue d\'ensemble', 'icon' => 'bar-chart-2', 'url' => '/comptabilite'],
                    ['label' => 'Paiements', 'icon' => 'banknote', 'url' => '/paiements', 'permissions' => ['comptabilite.view']],
                    ['label' => 'Dépenses', 'icon' => 'arrow-down-circle', 'url' => '/depenses', 'permissions' => ['comptabilite.view']],
                    ['label' => 'Impayés', 'icon' => 'alert-circle', 'url' => '/comptabilite/impayes', 'permissions' => ['comptabilite.view']],
                    ['label' => 'Caisse du jour', 'icon' => 'vault', 'url' => '/comptabilite/caisse', 'permissions' => ['comptabilite.view']],
                ],
            ],
            [
                'id' => 'planning',
                'label' => 'Planning',
                'icon' => 'calendar-days',
                'permissions' => ['emploi_du_temps.view'],
                'children' => [
                    ['label' => 'Hebdomadaire', 'icon' => 'calendar', 'url' => '/emplois-du-temps'],
                    ['label' => 'Mensuel', 'icon' => 'calendar-range', 'url' => '/emplois-du-temps/mensuel'],
                    ['label' => 'Salles', 'icon' => 'door-open', 'url' => '/salles', 'permissions' => ['emploi_du_temps.view']],
                    ['label' => 'Créneaux', 'icon' => 'clock', 'url' => '/creneaux', 'permissions' => ['emploi_du_temps.view']],
                ],
            ],
            [
                'id' => 'reporting',
                'label' => 'Rapports',
                'icon' => 'bar-chart-2',
                'permissions' => ['rapports.view', 'users.view'],
                'children' => [
                    ['label' => 'Vue d\'ensemble', 'icon' => 'layout-grid', 'url' => '/rapports', 'permissions' => ['rapports.view']],
                    ['label' => 'Stats scolaires', 'icon' => 'graduation-cap', 'url' => '/rapports/scolaire', 'permissions' => ['rapports.view']],
                    ['label' => 'Stats financières', 'icon' => 'trending-up', 'url' => '/rapports/financier', 'permissions' => ['rapports.view']],
                    ['label' => 'Présences', 'icon' => 'calendar-check', 'url' => '/rapports/presences', 'permissions' => ['rapports.view']],
                    ['label' => 'Réussite', 'icon' => 'trophy', 'url' => '/rapports/reussite', 'permissions' => ['rapports.view']],
                    ['label' => 'Utilisateurs', 'icon' => 'users-2', 'url' => '/utilisateurs', 'permissions' => ['users.view']],
                ],
            ],
            [
                'id' => 'branding',
                'label' => 'Branding',
                'icon' => 'palette',
                'url' => '/parametres/branding',
                'permissions' => ['branding.view'],
            ],
            [
                'id' => 'announcements',
                'label' => 'Annonces',
                'icon' => 'megaphone',
                'url' => '/annonces',
                'permissions' => [],
            ],
            [
                'id' => 'notifications',
                'label' => 'Notifications',
                'icon' => 'bell',
                'url' => '/notifications',
                'permissions' => [],
                'badge' => 'notifications',
            ],
        ];
    }

    /**
     * Menu Directeur - Gestion de l'école, enseignants, élèves, classes, rapports
     */
    private static function getDirectorMenus(): array
    {
        return [
            [
                'id' => 'dashboard',
                'label' => 'Tableau de bord',
                'icon' => 'layout-dashboard',
                'url' => '/dashboard',
                'permissions' => [],
            ],
            [
                'id' => 'students',
                'label' => 'Élèves',
                'icon' => 'users',
                'url' => '/eleves',
                'permissions' => ['eleves.view'],
            ],
            [
                'id' => 'scolarite',
                'label' => 'Scolarité',
                'icon' => 'building-2',
                'permissions' => ['enseignants.view', 'classes.view', 'matieres.view'],
                'children' => [
                    ['label' => 'Enseignants', 'icon' => 'user-check', 'url' => '/professeurs', 'permissions' => ['enseignants.view']],
                    ['label' => 'Classes', 'icon' => 'building', 'url' => '/classes', 'permissions' => ['classes.view']],
                    ['label' => 'Matières', 'icon' => 'book', 'url' => '/matieres', 'permissions' => ['matieres.view']],
                ],
            ],
            [
                'id' => 'academique',
                'label' => 'Académique',
                'icon' => 'book-open-check',
                'permissions' => ['notes.view', 'bulletins.view', 'absences.view'],
                'children' => [
                    ['label' => 'Notes', 'icon' => 'pencil-line', 'url' => '/notes', 'permissions' => ['notes.view']],
                    ['label' => 'Bulletins', 'icon' => 'file-text', 'url' => '/bulletins', 'permissions' => ['bulletins.view']],
                    ['label' => 'Absences', 'icon' => 'calendar-x', 'url' => '/absences', 'permissions' => ['absences.view']],
                ],
            ],
            [
                'id' => 'finance',
                'label' => 'Finance',
                'icon' => 'wallet',
                'permissions' => ['comptabilite.view'],
                'children' => [
                    ['label' => 'Vue d\'ensemble', 'icon' => 'bar-chart-2', 'url' => '/comptabilite'],
                    ['label' => 'Paiements', 'icon' => 'banknote', 'url' => '/paiements', 'permissions' => ['comptabilite.view']],
                    ['label' => 'Rapports financiers', 'icon' => 'trending-up', 'url' => '/rapports/financier', 'permissions' => ['rapports.view']],
                ],
            ],
            [
                'id' => 'planning',
                'label' => 'Planning',
                'icon' => 'calendar-days',
                'permissions' => ['emploi_du_temps.view'],
                'children' => [
                    ['label' => 'Hebdomadaire', 'icon' => 'calendar', 'url' => '/emplois-du-temps'],
                    ['label' => 'Mensuel', 'icon' => 'calendar-range', 'url' => '/emplois-du-temps/mensuel'],
                ],
            ],
            [
                'id' => 'reporting',
                'label' => 'Rapports',
                'icon' => 'bar-chart-2',
                'permissions' => ['rapports.view'],
                'children' => [
                    ['label' => 'Vue d\'ensemble', 'icon' => 'layout-grid', 'url' => '/rapports'],
                    ['label' => 'Stats scolaires', 'icon' => 'graduation-cap', 'url' => '/rapports/scolaire'],
                    ['label' => 'Présences', 'icon' => 'calendar-check', 'url' => '/rapports/presences'],
                    ['label' => 'Réussite', 'icon' => 'trophy', 'url' => '/rapports/reussite'],
                ],
            ],
            [
                'id' => 'branding',
                'label' => 'Branding',
                'icon' => 'palette',
                'url' => '/parametres/branding',
                'permissions' => ['branding.view'],
            ],
            [
                'id' => 'announcements',
                'label' => 'Annonces',
                'icon' => 'megaphone',
                'url' => '/annonces',
                'permissions' => [],
            ],
            [
                'id' => 'notifications',
                'label' => 'Notifications',
                'icon' => 'bell',
                'url' => '/notifications',
                'permissions' => [],
                'badge' => 'notifications',
            ],
        ];
    }

    /**
     * Menu Secrétaire - Élèves, inscriptions, documents, paiements
     */
    private static function getSecretaryMenus(): array
    {
        return [
            [
                'id' => 'dashboard',
                'label' => 'Tableau de bord',
                'icon' => 'layout-dashboard',
                'url' => '/dashboard',
                'permissions' => [],
            ],
            [
                'id' => 'students',
                'label' => 'Élèves',
                'icon' => 'users',
                'url' => '/eleves',
                'permissions' => ['eleves.view'],
            ],
            [
                'id' => 'finance',
                'label' => 'Gestion financière',
                'icon' => 'wallet',
                'permissions' => ['comptabilite.view'],
                'children' => [
                    ['label' => 'Paiements', 'icon' => 'banknote', 'url' => '/paiements'],
                    ['label' => 'Impayés', 'icon' => 'alert-circle', 'url' => '/comptabilite/impayes'],
                ],
            ],
            [
                'id' => 'announcements',
                'label' => 'Annonces',
                'icon' => 'megaphone',
                'url' => '/annonces',
                'permissions' => [],
            ],
            [
                'id' => 'notifications',
                'label' => 'Notifications',
                'icon' => 'bell',
                'url' => '/notifications',
                'permissions' => [],
                'badge' => 'notifications',
            ],
        ];
    }

    /**
     * Menu Enseignant - Ses classes, matières, notes, absences, emploi du temps
     */
    private static function getTeacherMenus(): array
    {
        return [
            [
                'id' => 'dashboard',
                'label' => 'Tableau de bord',
                'icon' => 'layout-dashboard',
                'url' => '/dashboard',
                'permissions' => [],
            ],
            [
                'id' => 'academique',
                'label' => 'Académique',
                'icon' => 'book-open-check',
                'permissions' => ['notes.view', 'absences.view'],
                'children' => [
                    ['label' => 'Mes notes', 'icon' => 'pencil-line', 'url' => '/notes', 'permissions' => ['notes.view']],
                    ['label' => 'Mes absences', 'icon' => 'calendar-x', 'url' => '/absences', 'permissions' => ['absences.view']],
                ],
            ],
            [
                'id' => 'planning',
                'label' => 'Planning',
                'icon' => 'calendar-days',
                'permissions' => ['emploi_du_temps.view'],
                'children' => [
                    ['label' => 'Mon emploi du temps', 'icon' => 'calendar', 'url' => '/emplois-du-temps'],
                    ['label' => 'Vue mensuelle', 'icon' => 'calendar-range', 'url' => '/emplois-du-temps/mensuel'],
                ],
            ],
            [
                'id' => 'announcements',
                'label' => 'Annonces',
                'icon' => 'megaphone',
                'url' => '/annonces',
                'permissions' => [],
            ],
            [
                'id' => 'notifications',
                'label' => 'Notifications',
                'icon' => 'bell',
                'url' => '/notifications',
                'permissions' => [],
                'badge' => 'notifications',
            ],
        ];
    }

    /**
     * Menu Comptable - Paiements, facturation, dépenses, rapports financiers
     */
    private static function getAccountantMenus(): array
    {
        return [
            [
                'id' => 'dashboard',
                'label' => 'Tableau de bord',
                'icon' => 'layout-dashboard',
                'url' => '/dashboard',
                'permissions' => [],
            ],
            [
                'id' => 'finance',
                'label' => 'Finance',
                'icon' => 'wallet',
                'permissions' => ['comptabilite.view'],
                'children' => [
                    ['label' => 'Vue d\'ensemble', 'icon' => 'bar-chart-2', 'url' => '/comptabilite'],
                    ['label' => 'Paiements', 'icon' => 'banknote', 'url' => '/paiements'],
                    ['label' => 'Dépenses', 'icon' => 'arrow-down-circle', 'url' => '/depenses'],
                    ['label' => 'Impayés', 'icon' => 'alert-circle', 'url' => '/comptabilite/impayes'],
                    ['label' => 'Caisse du jour', 'icon' => 'vault', 'url' => '/comptabilite/caisse'],
                ],
            ],
            [
                'id' => 'reporting',
                'label' => 'Rapports financiers',
                'icon' => 'trending-up',
                'permissions' => ['rapports.view'],
                'url' => '/rapports/financier',
            ],
            [
                'id' => 'notifications',
                'label' => 'Notifications',
                'icon' => 'bell',
                'url' => '/notifications',
                'permissions' => [],
                'badge' => 'notifications',
            ],
        ];
    }

    /**
     * Menu Élève - Son emploi du temps, notes, devoirs, absences, paiements
     */
    private static function getStudentMenus(): array
    {
        return [
            [
                'id' => 'dashboard',
                'label' => 'Tableau de bord',
                'icon' => 'layout-dashboard',
                'url' => '/eleve/dashboard',
                'permissions' => [],
            ],
            [
                'id' => 'notes',
                'label' => 'Mes notes',
                'icon' => 'pencil',
                'url' => '/eleve/notes',
                'permissions' => [],
            ],
            [
                'id' => 'bulletin',
                'label' => 'Bulletin',
                'icon' => 'file-text',
                'url' => '/eleve/bulletin',
                'permissions' => [],
            ],
            [
                'id' => 'planning',
                'label' => 'Mon planning',
                'icon' => 'calendar',
                'url' => '/eleve/emploi-du-temps',
                'permissions' => [],
            ],
            [
                'id' => 'absences',
                'label' => 'Mes absences',
                'icon' => 'calendar-x',
                'url' => '/eleve/absences',
                'permissions' => [],
            ],
            [
                'id' => 'announcements',
                'label' => 'Annonces',
                'icon' => 'megaphone',
                'url' => '/annonces',
                'permissions' => [],
            ],
            [
                'id' => 'profile',
                'label' => 'Mon profil',
                'icon' => 'user',
                'url' => '/eleve/profil',
                'permissions' => [],
            ],
            [
                'id' => 'notifications',
                'label' => 'Notifications',
                'icon' => 'bell',
                'url' => '/notifications',
                'permissions' => [],
                'badge' => 'notifications',
            ],
        ];
    }

    /**
     * Menu Parent - Informations de ses enfants uniquement
     */
    private static function getParentMenus(): array
    {
        return [
            [
                'id' => 'dashboard',
                'label' => 'Tableau de bord',
                'icon' => 'layout-dashboard',
                'url' => '/parent/dashboard',
                'permissions' => [],
            ],
            [
                'id' => 'notes',
                'label' => 'Notes',
                'icon' => 'book-open',
                'url' => '/parent/notes',
                'permissions' => [],
            ],
            [
                'id' => 'bulletins',
                'label' => 'Bulletins',
                'icon' => 'file-text',
                'url' => '/parent/bulletin',
                'permissions' => [],
            ],
            [
                'id' => 'absences',
                'label' => 'Absences',
                'icon' => 'calendar-x',
                'url' => '/parent/absences',
                'permissions' => [],
            ],
            [
                'id' => 'scolarite',
                'label' => 'Scolarité',
                'icon' => 'credit-card',
                'url' => '/parent/paiements',
                'permissions' => [],
            ],
            [
                'id' => 'announcements',
                'label' => 'Annonces',
                'icon' => 'megaphone',
                'url' => '/annonces',
                'permissions' => [],
            ],
            [
                'id' => 'notifications',
                'label' => 'Notifications',
                'icon' => 'bell',
                'url' => '/notifications',
                'permissions' => [],
                'badge' => 'notifications',
            ],
        ];
    }

    /**
     * Filtre un menu en fonction des permissions
     * Si aucune permission requise, le menu est affiché
     * Si des permissions requises : le menu n'est affiché QUE si au moins une permission est possédée
     */
    private static function filterMenuByPermissions(array $menus, array $permissions, string $role): array
    {
        $filtered = [];

        foreach ($menus as $menu) {
            // Tous les utilisateurs voient un élément sans permission requise, sauf pour les rôles spécialisés
            if (empty($menu['permissions'])) {
                // Pour les rôles parent et élève, pas de filtre
                if (in_array($role, ['parent', 'eleve'], true)) {
                    $filtered[] = $menu;
                    continue;
                }

                // Pour les autres rôles, on peut afficher
                $filtered[] = $menu;
                continue;
            }

            // Vérifier si l'utilisateur a au moins une des permissions requises
            if (self::hasAnyPermission($permissions, $menu['permissions'])) {
                $filtered[] = $menu;

                // Filtrer également les sous-menus s'il y en a
                if (!empty($menu['children'])) {
                    $menu['children'] = self::filterMenuByPermissions($menu['children'], $permissions, $role);
                    // Ne garder le menu parent que s'il a des enfants
                    if (empty($menu['children'])) {
                        array_pop($filtered);
                        continue;
                    }
                    $filtered[count($filtered) - 1] = $menu;
                }
            }
        }

        return $filtered;
    }

    /**
     * Vérifie si l'utilisateur possède au moins une des permissions requises
     */
    private static function hasAnyPermission(array $userPermissions, array $requiredPermissions): bool
    {
        foreach ($requiredPermissions as $perm) {
            if (in_array($perm, $userPermissions, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Vérifie si un menu item est actif selon l'URI actuelle
     */
    public static function isMenuActive(string $menuUrl, string $currentUri): bool
    {
        return str_contains($currentUri, $menuUrl);
    }

    /**
     * Récupère les classes CSS pour un menu item actif
     */
    public static function getMenuItemClass(string $menuUrl, string $currentUri, bool $isSubmenu = false): string
    {
        $baseClass = $isSubmenu ? 'nav-sub' : 'nav-item';

        if (self::isMenuActive($menuUrl, $currentUri)) {
            return $baseClass . ' nav-active';
        }

        return $baseClass;
    }

    /**
     * Détermine si un groupe de menu doit être actif (au moins un enfant actif)
     *
     * @deprecated Ne plus utiliser pour ouvrir un accordéon dans la sidebar :
     * un enfant partagé par deux groupes (ex. /rapports/financier dans
     * Finance, vs /rapports dans Rapports) fait matcher les DEUX groupes
     * par simple préfixe. Utiliser resolveActiveGroupId() qui résout un
     * groupe unique pour toute la sidebar. Conservée pour compatibilité API.
     */
    public static function isGroupActive(array $childUrls, string $currentUri): bool
    {
        foreach ($childUrls as $url) {
            if (str_contains($currentUri, $url)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Normalise une URI/URL en chemin absolu comparable (sans BASE_URL,
     * sans query string, sans slash final).
     */
    private static function normalizePath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? $uri;

        $basePath = defined('BASE_URL') ? (parse_url(BASE_URL, PHP_URL_PATH) ?? '') : '';
        if ($basePath !== '' && $basePath !== '/' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        $path = '/' . ltrim($path, '/');
        $path = rtrim($path, '/');

        return $path === '' ? '/' : $path;
    }

    /**
     * Résout l'unique groupe (accordéon) de la sidebar qui doit être ouvert
     * pour l'URI courante.
     *
     * Chaque groupe est évalué indépendamment, mais quand une route est
     * enregistrée EXACTEMENT comme enfant d'un groupe (ex. /rapports/financier
     * dans Finance) tout en étant aussi un sous-chemin d'un enfant d'un AUTRE
     * groupe (ex. /rapports dans Rapports), la correspondance exacte gagne
     * toujours sur la correspondance par préfixe : un seul groupe est retenu.
     * Si aucune correspondance exacte n'existe nulle part, on retombe sur la
     * correspondance hiérarchique la plus spécifique (préfixe le plus long),
     * ce qui garde le comportement existant pour les sous-pages non listées
     * explicitement (ex. /eleves/5/edit sous /eleves).
     */
    public static function resolveActiveGroupId(array $menus, string $currentUri): ?string
    {
        $current = self::normalizePath($currentUri);

        $bestGroupId = null;
        $bestPrefixLength = -1;

        foreach ($menus as $item) {
            if (empty($item['children']) || !is_array($item['children'])) {
                continue;
            }

            $groupId = $item['id'] ?? null;
            if ($groupId === null) {
                continue;
            }

            foreach ($item['children'] as $child) {
                $childPath = self::normalizePath($child['url'] ?? '');
                if ($childPath === '' || $childPath === '/') {
                    continue;
                }

                if ($current === $childPath) {
                    // Correspondance exacte : priorité absolue, on s'arrête ici.
                    return $groupId;
                }

                if (str_starts_with($current, $childPath . '/') && strlen($childPath) > $bestPrefixLength) {
                    $bestPrefixLength = strlen($childPath);
                    $bestGroupId = $groupId;
                }
            }
        }

        return $bestGroupId;
    }
}
