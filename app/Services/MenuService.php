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
            'globe' => 'fa-globe',
            'database' => 'fa-database',
            'activity' => 'fa-chart-line',
            'school' => 'fa-school',
            'layers' => 'fa-layer-group',
            'pencil-ruler' => 'fa-ruler-combined',
            'coins' => 'fa-coins',
            'database-backup' => 'fa-clock-rotate-left',
            'sliders-horizontal' => 'fa-sliders',
            'hash' => 'fa-hashtag',
            'shield' => 'fa-shield-halved',
            'shield-check' => 'fa-shield-halved',
            'gavel' => 'fa-gavel',
            'award' => 'fa-award',
            'key' => 'fa-key',
            'info' => 'fa-circle-info',
            'lock' => 'fa-lock',
            'users-round' => 'fa-user-group',
            'network' => 'fa-sitemap',
            'file-signature' => 'fa-file-signature',
            'shuffle' => 'fa-shuffle',
            'calendar-off' => 'fa-calendar-xmark',
            'star' => 'fa-star',
            'folder' => 'fa-folder',
            'calculator' => 'fa-calculator',
            'list-checks' => 'fa-list-check',
            'layout-grid' => 'fa-table-cells',
            'trending-up' => 'fa-arrow-trend-up',
            'calendar-check' => 'fa-calendar-check',
            'trophy' => 'fa-trophy',
            'users-2' => 'fa-users',
            'arrow-down-circle' => 'fa-circle-down',
            'calendar-range' => 'fa-calendar-week',
            'repeat' => 'fa-arrows-rotate',
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
                    ['label' => 'Réinscription', 'icon' => 'repeat', 'url' => '/reinscription', 'permissions' => ['classes.edit']],
                ],
            ],
            [
                'id' => 'academique',
                'label' => 'Académique',
                'icon' => 'book-open-check',
                'permissions' => ['notes.view', 'academique.evaluations.view', 'bulletins.view', 'attendance.view'],
                'children' => [
                    ['label' => 'Notes', 'icon' => 'pencil-line', 'url' => '/v2/academique/evaluations', 'permissions' => ['academique.evaluations.view']],
                    ['label' => 'Bulletins', 'icon' => 'file-text', 'url' => '/bulletins', 'permissions' => ['bulletins.view']],
                    ['label' => 'Absences', 'icon' => 'calendar-x', 'url' => '/v2/vie-scolaire/absences', 'permissions' => ['attendance.view']],
                ],
            ],
            [
                'id' => 'vie_scolaire',
                'label' => 'Vie scolaire',
                'icon' => 'shield-check',
                'permissions' => ['late.view', 'discipline.view', 'reward.view'],
                'children' => [
                    ['label' => 'Retards', 'icon' => 'clock', 'url' => '/v2/vie-scolaire/retards', 'permissions' => ['late.view']],
                    ['label' => 'Discipline', 'icon' => 'gavel', 'url' => '/v2/vie-scolaire/discipline', 'permissions' => ['discipline.view']],
                    ['label' => 'Récompenses', 'icon' => 'award', 'url' => '/v2/vie-scolaire/recompenses', 'permissions' => ['reward.view']],
                ],
            ],
            [
                'id' => 'rh',
                'label' => 'Ressources Humaines',
                'icon' => 'users-round',
                'permissions' => [
                    'employee.view', 'teacher.view', 'organization.view', 'contract.view',
                    'assignment.view', 'rh.presence.view', 'leave.view', 'evaluation.view',
                    'training.view', 'hr_document.view',
                ],
                'children' => [
                    ['label' => 'Employés', 'icon' => 'users-round', 'url' => '/v2/rh/employes', 'permissions' => ['employee.view']],
                    ['label' => 'Fiches enseignants (RH)', 'icon' => 'graduation-cap', 'url' => '/v2/rh/enseignants', 'permissions' => ['teacher.view']],
                    ['label' => 'Organisation', 'icon' => 'network', 'url' => '/v2/rh/organisation', 'permissions' => ['organization.view']],
                    ['label' => 'Contrats', 'icon' => 'file-signature', 'url' => '/v2/rh/contrats', 'permissions' => ['contract.view']],
                    ['label' => 'Affectations', 'icon' => 'shuffle', 'url' => '/v2/rh/affectations', 'permissions' => ['assignment.view']],
                    ['label' => 'Présences RH', 'icon' => 'clock', 'url' => '/v2/rh/presences', 'permissions' => ['rh.presence.view']],
                    ['label' => 'Congés', 'icon' => 'calendar-off', 'url' => '/v2/rh/conges', 'permissions' => ['leave.view']],
                    ['label' => 'Évaluations', 'icon' => 'star', 'url' => '/v2/rh/evaluations', 'permissions' => ['evaluation.view']],
                    ['label' => 'Formations', 'icon' => 'book-open', 'url' => '/v2/rh/formations', 'permissions' => ['training.view']],
                    ['label' => 'Documents RH', 'icon' => 'folder', 'url' => '/v2/rh/documents', 'permissions' => ['hr_document.view']],
                ],
            ],
            [
                'id' => 'finance',
                'label' => 'Finance',
                'icon' => 'wallet',
                'permissions' => [
                    'finance.dashboard.view', 'finance.frais.view', 'finance.factures.view',
                    'finance.paiements.view', 'finance.rapports.view', 'finance.caisse.view',
                    'finance.comptabilite.view', 'finance.decaissements.view',
                ],
                'children' => [
                    ['label' => 'Vue d\'ensemble', 'icon' => 'bar-chart-2', 'url' => '/v2/finance/rapports/dashboard', 'permissions' => ['finance.dashboard.view']],
                    ['label' => 'Frais scolaires', 'icon' => 'list-checks', 'url' => '/v2/finance/frais', 'permissions' => ['finance.frais.view']],
                    ['label' => 'Factures', 'icon' => 'file-text', 'url' => '/v2/finance/factures', 'permissions' => ['finance.factures.view']],
                    ['label' => 'Paiements', 'icon' => 'banknote', 'url' => '/v2/finance/paiements', 'permissions' => ['finance.paiements.view']],
                    ['label' => 'Impayés', 'icon' => 'alert-circle', 'url' => '/v2/finance/rapports/impayes', 'permissions' => ['finance.rapports.view']],
                    ['label' => 'Décaissements', 'icon' => 'arrow-down-circle', 'url' => '/v2/finance/decaissements', 'permissions' => ['finance.decaissements.view']],
                    ['label' => 'Caisse du jour', 'icon' => 'vault', 'url' => '/v2/finance/caisse', 'permissions' => ['finance.caisse.view']],
                    ['label' => 'Comptabilité', 'icon' => 'calculator', 'url' => '/v2/finance/comptabilite', 'permissions' => ['finance.comptabilite.view']],
                    ['label' => 'Rapports financiers', 'icon' => 'trending-up', 'url' => '/v2/finance/rapports', 'permissions' => ['finance.rapports.view']],
                ],
            ],
            [
                'id' => 'planning',
                'label' => 'Planning',
                'icon' => 'calendar-days',
                'permissions' => ['timetable.view'],
                'children' => [
                    ['label' => 'Emplois du temps', 'icon' => 'calendar', 'url' => '/v2/vie-scolaire/emplois-du-temps', 'permissions' => ['timetable.view']],
                    ['label' => 'Remplacements', 'icon' => 'repeat', 'url' => '/v2/vie-scolaire/emplois-du-temps/remplacements', 'permissions' => ['timetable.view']],
                    ['label' => 'Salles', 'icon' => 'door-open', 'url' => '/v2/vie-scolaire/emplois-du-temps/salles', 'permissions' => ['timetable.view']],
                    ['label' => 'Plages horaires', 'icon' => 'clock', 'url' => '/v2/vie-scolaire/emplois-du-temps/plages', 'permissions' => ['timetable.view']],
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
                'id' => 'parametres',
                'label' => 'Paramètres',
                'icon' => 'settings',
                'permissions' => [
                    'branding.view', 'settings.general.view', 'settings.academique.view',
                    'settings.notation.view', 'settings.finances.view', 'settings.documents.view',
                    'settings.notifications.view', 'settings.securite.view', 'settings.sauvegarde.view', 'settings.avance.view',
                ],
                'children' => [
                    ['label' => 'Établissement', 'icon' => 'school', 'url' => '/parametres/etablissement', 'permissions' => ['branding.view']],
                    ['label' => 'Année scolaire', 'icon' => 'calendar-range', 'url' => '/parametres/annee-scolaire', 'permissions' => ['settings.general.view']],
                    ['label' => 'Organisation académique', 'icon' => 'layers', 'url' => '/parametres/academique', 'permissions' => ['settings.academique.view']],
                    ['label' => 'Système de notation', 'icon' => 'pencil-ruler', 'url' => '/parametres/notation', 'permissions' => ['settings.notation.view']],
                    ['label' => 'Finances', 'icon' => 'coins', 'url' => '/parametres/finances', 'permissions' => ['settings.finances.view']],
                    ['label' => 'Documents', 'icon' => 'file-text', 'url' => '/parametres/documents', 'permissions' => ['settings.documents.view']],
                    ['label' => 'Notifications', 'icon' => 'bell', 'url' => '/parametres/notifications', 'permissions' => ['settings.notifications.view']],
                    ['label' => 'Apparence', 'icon' => 'palette', 'url' => '/parametres/apparence', 'permissions' => ['branding.view']],
                    ['label' => 'Sécurité', 'icon' => 'shield', 'url' => '/parametres/securite', 'permissions' => ['settings.securite.view']],
                    ['label' => 'Sauvegarde & restauration', 'icon' => 'database-backup', 'url' => '/parametres/sauvegarde', 'permissions' => ['settings.sauvegarde.view']],
                    ['label' => 'Paramètres avancés', 'icon' => 'sliders-horizontal', 'url' => '/parametres/avance', 'permissions' => ['settings.avance.view']],
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
                    ['label' => 'Réinscription', 'icon' => 'repeat', 'url' => '/reinscription', 'permissions' => ['classes.edit']],
                ],
            ],
            [
                'id' => 'academique',
                'label' => 'Académique',
                'icon' => 'book-open-check',
                'permissions' => ['notes.view', 'academique.evaluations.view', 'bulletins.view', 'attendance.view'],
                'children' => [
                    ['label' => 'Notes', 'icon' => 'pencil-line', 'url' => '/v2/academique/evaluations', 'permissions' => ['academique.evaluations.view']],
                    ['label' => 'Bulletins', 'icon' => 'file-text', 'url' => '/bulletins', 'permissions' => ['bulletins.view']],
                    ['label' => 'Absences', 'icon' => 'calendar-x', 'url' => '/v2/vie-scolaire/absences', 'permissions' => ['attendance.view']],
                ],
            ],
            [
                'id' => 'vie_scolaire',
                'label' => 'Vie scolaire',
                'icon' => 'shield-check',
                'permissions' => ['late.view', 'discipline.view', 'reward.view'],
                'children' => [
                    ['label' => 'Retards', 'icon' => 'clock', 'url' => '/v2/vie-scolaire/retards', 'permissions' => ['late.view']],
                    ['label' => 'Discipline', 'icon' => 'gavel', 'url' => '/v2/vie-scolaire/discipline', 'permissions' => ['discipline.view']],
                    ['label' => 'Récompenses', 'icon' => 'award', 'url' => '/v2/vie-scolaire/recompenses', 'permissions' => ['reward.view']],
                ],
            ],
            [
                'id' => 'rh',
                'label' => 'Ressources Humaines',
                'icon' => 'users-round',
                'permissions' => [
                    'employee.view', 'teacher.view', 'organization.view', 'contract.view',
                    'assignment.view', 'rh.presence.view', 'leave.view', 'evaluation.view',
                    'training.view', 'hr_document.view',
                ],
                'children' => [
                    ['label' => 'Employés', 'icon' => 'users-round', 'url' => '/v2/rh/employes', 'permissions' => ['employee.view']],
                    ['label' => 'Fiches enseignants (RH)', 'icon' => 'graduation-cap', 'url' => '/v2/rh/enseignants', 'permissions' => ['teacher.view']],
                    ['label' => 'Organisation', 'icon' => 'network', 'url' => '/v2/rh/organisation', 'permissions' => ['organization.view']],
                    ['label' => 'Contrats', 'icon' => 'file-signature', 'url' => '/v2/rh/contrats', 'permissions' => ['contract.view']],
                    ['label' => 'Affectations', 'icon' => 'shuffle', 'url' => '/v2/rh/affectations', 'permissions' => ['assignment.view']],
                    ['label' => 'Présences RH', 'icon' => 'clock', 'url' => '/v2/rh/presences', 'permissions' => ['rh.presence.view']],
                    ['label' => 'Congés', 'icon' => 'calendar-off', 'url' => '/v2/rh/conges', 'permissions' => ['leave.view']],
                    ['label' => 'Évaluations', 'icon' => 'star', 'url' => '/v2/rh/evaluations', 'permissions' => ['evaluation.view']],
                    ['label' => 'Formations', 'icon' => 'book-open', 'url' => '/v2/rh/formations', 'permissions' => ['training.view']],
                    ['label' => 'Documents RH', 'icon' => 'folder', 'url' => '/v2/rh/documents', 'permissions' => ['hr_document.view']],
                ],
            ],
            [
                'id' => 'finance',
                'label' => 'Finance',
                'icon' => 'wallet',
                'permissions' => [
                    'finance.dashboard.view', 'finance.frais.view', 'finance.factures.view',
                    'finance.paiements.view', 'finance.rapports.view', 'finance.caisse.view',
                    'finance.comptabilite.view', 'finance.decaissements.view',
                ],
                'children' => [
                    ['label' => 'Vue d\'ensemble', 'icon' => 'bar-chart-2', 'url' => '/v2/finance/rapports/dashboard', 'permissions' => ['finance.dashboard.view']],
                    ['label' => 'Frais scolaires', 'icon' => 'list-checks', 'url' => '/v2/finance/frais', 'permissions' => ['finance.frais.view']],
                    ['label' => 'Factures', 'icon' => 'file-text', 'url' => '/v2/finance/factures', 'permissions' => ['finance.factures.view']],
                    ['label' => 'Paiements', 'icon' => 'banknote', 'url' => '/v2/finance/paiements', 'permissions' => ['finance.paiements.view']],
                    ['label' => 'Impayés', 'icon' => 'alert-circle', 'url' => '/v2/finance/rapports/impayes', 'permissions' => ['finance.rapports.view']],
                    ['label' => 'Décaissements', 'icon' => 'arrow-down-circle', 'url' => '/v2/finance/decaissements', 'permissions' => ['finance.decaissements.view']],
                    ['label' => 'Caisse du jour', 'icon' => 'vault', 'url' => '/v2/finance/caisse', 'permissions' => ['finance.caisse.view']],
                    ['label' => 'Comptabilité', 'icon' => 'calculator', 'url' => '/v2/finance/comptabilite', 'permissions' => ['finance.comptabilite.view']],
                    ['label' => 'Rapports financiers', 'icon' => 'trending-up', 'url' => '/v2/finance/rapports', 'permissions' => ['finance.rapports.view']],
                ],
            ],
            [
                'id' => 'planning',
                'label' => 'Planning',
                'icon' => 'calendar-days',
                'permissions' => ['timetable.view'],
                'children' => [
                    ['label' => 'Emplois du temps', 'icon' => 'calendar', 'url' => '/v2/vie-scolaire/emplois-du-temps', 'permissions' => ['timetable.view']],
                    ['label' => 'Remplacements', 'icon' => 'repeat', 'url' => '/v2/vie-scolaire/emplois-du-temps/remplacements', 'permissions' => ['timetable.view']],
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
                'id' => 'parametres',
                'label' => 'Paramètres',
                'icon' => 'settings',
                'permissions' => [
                    'branding.view', 'settings.general.view', 'settings.academique.view',
                    'settings.notation.view', 'settings.finances.view', 'settings.documents.view',
                    'settings.notifications.view', 'settings.securite.view', 'settings.sauvegarde.view', 'settings.avance.view',
                ],
                'children' => [
                    ['label' => 'Établissement', 'icon' => 'school', 'url' => '/parametres/etablissement', 'permissions' => ['branding.view']],
                    ['label' => 'Année scolaire', 'icon' => 'calendar-range', 'url' => '/parametres/annee-scolaire', 'permissions' => ['settings.general.view']],
                    ['label' => 'Organisation académique', 'icon' => 'layers', 'url' => '/parametres/academique', 'permissions' => ['settings.academique.view']],
                    ['label' => 'Système de notation', 'icon' => 'pencil-ruler', 'url' => '/parametres/notation', 'permissions' => ['settings.notation.view']],
                    ['label' => 'Finances', 'icon' => 'coins', 'url' => '/parametres/finances', 'permissions' => ['settings.finances.view']],
                    ['label' => 'Documents', 'icon' => 'file-text', 'url' => '/parametres/documents', 'permissions' => ['settings.documents.view']],
                    ['label' => 'Notifications', 'icon' => 'bell', 'url' => '/parametres/notifications', 'permissions' => ['settings.notifications.view']],
                    ['label' => 'Apparence', 'icon' => 'palette', 'url' => '/parametres/apparence', 'permissions' => ['branding.view']],
                    ['label' => 'Sécurité', 'icon' => 'shield', 'url' => '/parametres/securite', 'permissions' => ['settings.securite.view']],
                    ['label' => 'Sauvegarde & restauration', 'icon' => 'database-backup', 'url' => '/parametres/sauvegarde', 'permissions' => ['settings.sauvegarde.view']],
                    ['label' => 'Paramètres avancés', 'icon' => 'sliders-horizontal', 'url' => '/parametres/avance', 'permissions' => ['settings.avance.view']],
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
                'id' => 'vie_scolaire',
                'label' => 'Vie scolaire',
                'icon' => 'shield-check',
                'permissions' => ['late.view', 'discipline.view', 'reward.view'],
                'children' => [
                    ['label' => 'Retards', 'icon' => 'clock', 'url' => '/v2/vie-scolaire/retards', 'permissions' => ['late.view']],
                    ['label' => 'Discipline', 'icon' => 'gavel', 'url' => '/v2/vie-scolaire/discipline', 'permissions' => ['discipline.view']],
                    ['label' => 'Récompenses', 'icon' => 'award', 'url' => '/v2/vie-scolaire/recompenses', 'permissions' => ['reward.view']],
                ],
            ],
            [
                'id' => 'rh',
                'label' => 'Ressources Humaines',
                'icon' => 'users-round',
                'permissions' => [
                    'employee.view', 'teacher.view', 'organization.view', 'contract.view',
                    'assignment.view', 'rh.presence.view', 'leave.view', 'evaluation.view',
                    'training.view', 'hr_document.view',
                ],
                'children' => [
                    ['label' => 'Employés', 'icon' => 'users-round', 'url' => '/v2/rh/employes', 'permissions' => ['employee.view']],
                    ['label' => 'Fiches enseignants (RH)', 'icon' => 'graduation-cap', 'url' => '/v2/rh/enseignants', 'permissions' => ['teacher.view']],
                    ['label' => 'Organisation', 'icon' => 'network', 'url' => '/v2/rh/organisation', 'permissions' => ['organization.view']],
                    ['label' => 'Contrats', 'icon' => 'file-signature', 'url' => '/v2/rh/contrats', 'permissions' => ['contract.view']],
                    ['label' => 'Affectations', 'icon' => 'shuffle', 'url' => '/v2/rh/affectations', 'permissions' => ['assignment.view']],
                    ['label' => 'Présences RH', 'icon' => 'clock', 'url' => '/v2/rh/presences', 'permissions' => ['rh.presence.view']],
                    ['label' => 'Congés', 'icon' => 'calendar-off', 'url' => '/v2/rh/conges', 'permissions' => ['leave.view']],
                    ['label' => 'Évaluations', 'icon' => 'star', 'url' => '/v2/rh/evaluations', 'permissions' => ['evaluation.view']],
                    ['label' => 'Formations', 'icon' => 'book-open', 'url' => '/v2/rh/formations', 'permissions' => ['training.view']],
                    ['label' => 'Documents RH', 'icon' => 'folder', 'url' => '/v2/rh/documents', 'permissions' => ['hr_document.view']],
                ],
            ],
            [
                'id' => 'finance',
                'label' => 'Gestion financière',
                'icon' => 'wallet',
                'permissions' => [
                    'finance.dashboard.view', 'finance.frais.view', 'finance.factures.view',
                    'finance.paiements.view', 'finance.rapports.view', 'finance.caisse.view',
                    'finance.decaissements.view',
                ],
                'children' => [
                    ['label' => 'Vue d\'ensemble', 'icon' => 'bar-chart-2', 'url' => '/v2/finance/rapports/dashboard', 'permissions' => ['finance.dashboard.view']],
                    ['label' => 'Frais scolaires', 'icon' => 'list-checks', 'url' => '/v2/finance/frais', 'permissions' => ['finance.frais.view']],
                    ['label' => 'Factures', 'icon' => 'file-text', 'url' => '/v2/finance/factures', 'permissions' => ['finance.factures.view']],
                    ['label' => 'Paiements', 'icon' => 'banknote', 'url' => '/v2/finance/paiements', 'permissions' => ['finance.paiements.view']],
                    ['label' => 'Impayés', 'icon' => 'alert-circle', 'url' => '/v2/finance/rapports/impayes', 'permissions' => ['finance.rapports.view']],
                    ['label' => 'Décaissements', 'icon' => 'arrow-down-circle', 'url' => '/v2/finance/decaissements', 'permissions' => ['finance.decaissements.view']],
                    ['label' => 'Caisse du jour', 'icon' => 'vault', 'url' => '/v2/finance/caisse', 'permissions' => ['finance.caisse.view']],
                    ['label' => 'Rapports financiers', 'icon' => 'trending-up', 'url' => '/v2/finance/rapports', 'permissions' => ['finance.rapports.view']],
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
                'permissions' => ['notes.view', 'academique.evaluations.view', 'attendance.view'],
                'children' => [
                    ['label' => 'Mes notes', 'icon' => 'pencil-line', 'url' => '/v2/academique/evaluations', 'permissions' => ['academique.evaluations.view']],
                    ['label' => 'Mes absences', 'icon' => 'calendar-x', 'url' => '/v2/vie-scolaire/absences', 'permissions' => ['attendance.view']],
                ],
            ],
            [
                'id' => 'vie_scolaire',
                'label' => 'Vie scolaire',
                'icon' => 'shield-check',
                'permissions' => ['late.view', 'discipline.view', 'reward.view'],
                'children' => [
                    ['label' => 'Retards', 'icon' => 'clock', 'url' => '/v2/vie-scolaire/retards', 'permissions' => ['late.view']],
                    ['label' => 'Discipline', 'icon' => 'gavel', 'url' => '/v2/vie-scolaire/discipline', 'permissions' => ['discipline.view']],
                    ['label' => 'Récompenses', 'icon' => 'award', 'url' => '/v2/vie-scolaire/recompenses', 'permissions' => ['reward.view']],
                ],
            ],
            [
                'id' => 'planning',
                'label' => 'Planning',
                'icon' => 'calendar-days',
                'permissions' => ['timetable.view'],
                'children' => [
                    ['label' => 'Mon emploi du temps', 'icon' => 'calendar', 'url' => '/v2/vie-scolaire/emplois-du-temps/enseignant', 'permissions' => ['timetable.view']],
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
                'permissions' => [
                    'finance.dashboard.view', 'finance.frais.view', 'finance.factures.view',
                    'finance.paiements.view', 'finance.rapports.view', 'finance.caisse.view',
                    'finance.comptabilite.view', 'finance.decaissements.view',
                ],
                'children' => [
                    ['label' => 'Vue d\'ensemble', 'icon' => 'bar-chart-2', 'url' => '/v2/finance/rapports/dashboard', 'permissions' => ['finance.dashboard.view']],
                    ['label' => 'Frais scolaires', 'icon' => 'list-checks', 'url' => '/v2/finance/frais', 'permissions' => ['finance.frais.view']],
                    ['label' => 'Factures', 'icon' => 'file-text', 'url' => '/v2/finance/factures', 'permissions' => ['finance.factures.view']],
                    ['label' => 'Paiements', 'icon' => 'banknote', 'url' => '/v2/finance/paiements', 'permissions' => ['finance.paiements.view']],
                    ['label' => 'Impayés', 'icon' => 'alert-circle', 'url' => '/v2/finance/rapports/impayes', 'permissions' => ['finance.rapports.view']],
                    ['label' => 'Décaissements', 'icon' => 'arrow-down-circle', 'url' => '/v2/finance/decaissements', 'permissions' => ['finance.decaissements.view']],
                    ['label' => 'Caisse du jour', 'icon' => 'vault', 'url' => '/v2/finance/caisse', 'permissions' => ['finance.caisse.view']],
                    ['label' => 'Comptabilité', 'icon' => 'calculator', 'url' => '/v2/finance/comptabilite', 'permissions' => ['finance.comptabilite.view']],
                ],
            ],
            [
                'id' => 'rh',
                'label' => 'Ressources Humaines',
                'icon' => 'users-round',
                'permissions' => ['rh.presence.view', 'leave.view', 'evaluation.view', 'training.view', 'hr_document.view'],
                'children' => [
                    ['label' => 'Présences RH', 'icon' => 'clock', 'url' => '/v2/rh/presences', 'permissions' => ['rh.presence.view']],
                    ['label' => 'Congés', 'icon' => 'calendar-off', 'url' => '/v2/rh/conges', 'permissions' => ['leave.view']],
                    ['label' => 'Évaluations', 'icon' => 'star', 'url' => '/v2/rh/evaluations', 'permissions' => ['evaluation.view']],
                    ['label' => 'Formations', 'icon' => 'book-open', 'url' => '/v2/rh/formations', 'permissions' => ['training.view']],
                    ['label' => 'Documents RH', 'icon' => 'folder', 'url' => '/v2/rh/documents', 'permissions' => ['hr_document.view']],
                ],
            ],
            [
                'id' => 'reporting',
                'label' => 'Rapports financiers',
                'icon' => 'trending-up',
                'permissions' => ['finance.rapports.view'],
                'url' => '/v2/finance/rapports',
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
                'url' => '/v2/vie-scolaire/absences',
                'permissions' => [],
            ],
            [
                'id' => 'scolarite',
                'label' => 'Scolarité et paiements',
                'icon' => 'credit-card',
                'url' => '/v2/finance/mes-paiements',
                'permissions' => ['finance.paiements.view.own'],
            ],
            [
                'id' => 'retards',
                'label' => 'Mes retards',
                'icon' => 'clock',
                'url' => '/v2/vie-scolaire/retards',
                'permissions' => ['late.view'],
            ],
            [
                'id' => 'discipline',
                'label' => 'Discipline',
                'icon' => 'gavel',
                'url' => '/v2/vie-scolaire/discipline',
                'permissions' => ['discipline.view'],
            ],
            [
                'id' => 'recompenses',
                'label' => 'Mes récompenses',
                'icon' => 'award',
                'url' => '/v2/vie-scolaire/recompenses',
                'permissions' => ['reward.view'],
            ],
            [
                'id' => 'activites',
                'label' => 'Activités scolaires',
                'icon' => 'star',
                'url' => '/v2/vie-scolaire/activites',
                'permissions' => ['activity.view'],
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
                'id' => 'retards',
                'label' => 'Retards',
                'icon' => 'clock',
                'url' => '/v2/vie-scolaire/retards',
                'permissions' => ['late.view'],
            ],
            [
                'id' => 'discipline',
                'label' => 'Discipline',
                'icon' => 'gavel',
                'url' => '/v2/vie-scolaire/discipline',
                'permissions' => ['discipline.view'],
            ],
            [
                'id' => 'recompenses',
                'label' => 'Récompenses',
                'icon' => 'award',
                'url' => '/v2/vie-scolaire/recompenses',
                'permissions' => ['reward.view'],
            ],
            [
                'id' => 'scolarite',
                'label' => 'Scolarité et paiements',
                'icon' => 'credit-card',
                'url' => '/v2/finance/mes-paiements',
                'permissions' => ['finance.paiements.view.own'],
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
     * Vérifie si un menu item est actif selon l'URI actuelle.
     *
     * Correction du 21/08/2026 : remplace un `str_contains($currentUri,
     * $menuUrl)` naïf, responsable d'un état actif instable dans la
     * sidebar. Un simple test de sous-chaîne matche n'importe où dans
     * l'URL, sans respecter les frontières de segments ni retirer le
     * préfixe BASE_URL — ex. le lien "Rapports financiers"
     * (`/v2/finance/rapports`) matchait aussi `/v2/finance/rapports/dashboard`
     * ("Vue d'ensemble"), les deux s'allumaient en même temps sur cette
     * page. Utilise désormais la même normalisation de chemin que
     * resolveActiveGroupId() (retrait de BASE_URL, de la query string, du
     * slash final) et n'accepte qu'une correspondance exacte ou un
     * sous-chemin réel (frontière `/`), jamais une sous-chaîne arbitraire.
     */
    public static function isMenuActive(string $menuUrl, string $currentUri): bool
    {
        if ($menuUrl === '' || $menuUrl === '/') {
            return false; // Un menu "racine" ne doit jamais matcher toutes les pages.
        }

        $current = self::normalizePath($currentUri);
        $target  = self::normalizePath($menuUrl);

        return $current === $target || str_starts_with($current, $target . '/');
    }

    /**
     * Récupère les classes CSS pour un menu item actif.
     *
     * Correction du 21/08/2026 : compare désormais $menuUrl à l'unique
     * gagnant résolu pour toute la sidebar par resolveActiveItemUrl()
     * (passé en $activeItemUrl, calculé une seule fois par la vue — voir
     * navigation.php), au lieu de faire matcher indépendamment chaque item
     * contre l'URL courante. Un matching indépendant par item pouvait
     * allumer deux liens à la fois quand l'un est un sous-chemin réel de
     * l'autre (ex. "Vue d'ensemble" `/v2/finance/rapports/dashboard` est
     * un sous-chemin de "Rapports financiers" `/v2/finance/rapports` — les
     * deux sont des entrées sœurs du même groupe, pas une hiérarchie
     * parent/enfant dans le menu) : la comparaison au gagnant unique
     * élimine ce double allumage, avec la même logique "correspondance
     * exacte prioritaire, sinon préfixe le plus long" déjà utilisée pour
     * ouvrir le bon groupe (resolveActiveGroupId) et le titre de page
     * (resolveActiveLabel).
     */
    public static function getMenuItemClass(string $menuUrl, ?string $activeItemUrl, bool $isSubmenu = false): string
    {
        $baseClass = $isSubmenu ? 'nav-sub' : 'nav-item';

        if ($menuUrl !== '' && $menuUrl === $activeItemUrl) {
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

    /**
     * Résout l'unique item de menu (top-level ou enfant de groupe) qui
     * correspond le mieux à l'URL courante — correspondance exacte
     * prioritaire, sinon préfixe de chemin le plus long (même règle que
     * resolveActiveGroupId, appliquée ici item par item plutôt que
     * groupe par groupe). Base commune de resolveActiveLabel() et
     * resolveActiveItemUrl() : un seul et même gagnant pour tout ce que
     * la sidebar doit synchroniser (libellé de page, lien surligné).
     */
    private static function resolveActiveItem(array $menus, string $currentUri): ?array
    {
        $current = self::normalizePath($currentUri);

        $best = null;
        $bestPrefixLength = -1;

        foreach ($menus as $item) {
            $candidates = (!empty($item['children']) && is_array($item['children']))
                ? $item['children']
                : [$item];

            foreach ($candidates as $candidate) {
                $candidatePath = self::normalizePath($candidate['url'] ?? '');
                if ($candidatePath === '' || $candidatePath === '/') {
                    continue;
                }

                if ($current === $candidatePath) {
                    // Correspondance exacte : priorité absolue.
                    return $candidate;
                }

                if (str_starts_with($current, $candidatePath . '/') && strlen($candidatePath) > $bestPrefixLength) {
                    $bestPrefixLength = strlen($candidatePath);
                    $best = $candidate;
                }
            }
        }

        return $best;
    }

    /**
     * Libellé du menu correspondant le mieux à l'URL courante — sert de
     * titre de page par défaut quand une vue ne définit pas explicitement
     * $title, pour que le header reste toujours synchronisé avec la
     * navigation.
     */
    public static function resolveActiveLabel(array $menus, string $currentUri): ?string
    {
        return self::resolveActiveItem($menus, $currentUri)['label'] ?? null;
    }

    /**
     * URL brute (telle que stockée dans la structure de menu) de l'item
     * actif — LE lien unique à surligner dans la sidebar pour l'URL
     * courante. À calculer une seule fois par rendu et comparer par
     * égalité stricte dans getMenuItemClass(), plutôt que de laisser
     * chaque item se juger « actif » indépendamment des autres (ce qui
     * pouvait en allumer plusieurs à la fois — voir le commentaire de
     * getMenuItemClass()).
     */
    public static function resolveActiveItemUrl(array $menus, string $currentUri): ?string
    {
        return self::resolveActiveItem($menus, $currentUri)['url'] ?? null;
    }
}
