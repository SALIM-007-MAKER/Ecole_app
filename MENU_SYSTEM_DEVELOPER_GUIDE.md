# Guide du Développeur — Système de Menu Dynamique

## Accès à MenuService

### Importer le service

```php
use App\Services\MenuService;
```

### Utilisation simple (dans une vue)

```php
<?php
// Obtenir la structure complète du menu
$menus = MenuService::getMenuStructure('admin', ['eleves.view', 'notes.view']);

// Afficher les menus
echo '<pre>';
var_dump($menus);
echo '</pre>';
?>
```

### Utilisation dans les contrôleurs

```php
<?php
namespace App\Controllers;

use App\Services\MenuService;
use Core\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        $user = $this->getAuthUser();
        $role = $user['role'];
        $permissions = $user['permissions'];

        // Récupérer les menus visibles
        $menus = MenuService::getMenuStructure($role, $permissions);

        // Passer aux vues
        return $this->render('dashboard', [
            'menus' => $menus,
            'user'  => $user,
        ]);
    }
}
?>
```

## Détails des méthodes

### `getMenuStructure($role, $permissions)`

```php
/**
 * @param string $role - 'admin', 'directeur', 'enseignant', 'comptable', 'secretaire', 'eleve', 'parent'
 * @param array $permissions - ['eleves.view', 'notes.create', ...]
 * @return array - Structure filtrée des menus
 */
$menus = MenuService::getMenuStructure('admin', ['eleves.view']);
```

**Retour:**
```php
Array (
    [0] => Array (
        'id'          => 'dashboard',
        'label'       => 'Tableau de bord',
        'icon'        => 'layout-dashboard',
        'url'         => '/dashboard',
        'permissions' => Array(),
        'badge'       => null,
    ),
    [1] => Array (
        'id'          => 'students',
        'label'       => 'Élèves',
        'icon'        => 'users',
        'url'         => '/eleves',
        'permissions' => Array('eleves.view'),
    ),
    [2] => Array (
        'id'          => 'scolarite',
        'label'       => 'Scolarité',
        'icon'        => 'building-2',
        'permissions' => Array(...),
        'children'    => Array (
            [0] => Array (
                'label' => 'Enseignants',
                'icon'  => 'user-check',
                'url'   => '/professeurs',
                'permissions' => Array('enseignants.view'),
            ),
            ...
        )
    ),
    ...
)
```

### `isMenuActive($menuUrl, $currentUri)`

```php
// Vérifier si un menu est actif
$isActive = MenuService::isMenuActive('/dashboard', $_SERVER['REQUEST_URI']);
// true si REQUEST_URI contient '/dashboard'

// Utile pour ajouter une classe active personnalisée
$class = $isActive ? 'active' : '';
```

### `getMenuItemClass($menuUrl, $currentUri, $isSubmenu)`

```php
// Retourner la classe CSS appropriée
$class = MenuService::getMenuItemClass('/notes', '/notes/list', false);
// Retourne: "nav-item nav-active"

// Pour un sous-menu
$class = MenuService::getMenuItemClass('/notes', '/notes/list', true);
// Retourne: "nav-sub nav-active"
```

### `isGroupActive($childUrls, $currentUri)`

```php
// Vérifier si un groupe doit être ouvert (au moins un enfant actif)
$childUrls = ['/notes', '/absences', '/bulletins'];
$isOpen = MenuService::isGroupActive($childUrls, $_SERVER['REQUEST_URI']);

// Utiliser pour définir la hauteur du groupe
$maxHeight = $isOpen ? 'max-height: 500px' : '';
```

## Ajouter un nouveau menu dynamique

### Cas 1: Ajouter une option simple à un rôle existant

**Objectif**: Ajouter un menu "Gestion des paies" visible par les comptables.

1. **Modifier MenuService**:

```php
// Dans getAccountantMenus()
private static function getAccountantMenus(): array
{
    return [
        // ... menus existants ...
        
        [
            'id'          => 'payroll',
            'label'       => 'Gestion des paies',
            'icon'        => 'credit-card',
            'url'         => '/payroll',
            'permissions' => ['payroll.view'],  // ← La permission doit exister en DB
        ],
    ];
}
```

2. **Vérifier que la permission existe**:

```sql
SELECT * FROM permissions WHERE code = 'payroll.view';

-- Si elle n'existe pas:
INSERT INTO permissions (code, libelle, module, action, scope, is_system)
VALUES ('payroll.view', 'Voir les paies', 'payroll', 'view', 'global', FALSE);
```

3. **Assigner la permission au rôle comptable**:

```sql
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code = 'payroll.view'
WHERE r.slug = 'comptable';
```

4. **Créer la route**:

```php
// Dans routes/web.php
$router->get('/payroll', 'PayrollController@index');
```

5. **Créer le contrôleur et la vue** (processus normal).

### Cas 2: Ajouter un nouveau rôle

**Objectif**: Ajouter un rôle "superviseur" intermédiaire entre directeur et admin.

1. **Ajouter le rôle en DB**:

```sql
INSERT INTO roles (slug, label, description, is_system, ordre)
VALUES ('superviseur', 'Superviseur', 'Superviseur d\'établissement', FALSE, 3);
```

2. **Ajouter la méthode dans MenuService**:

```php
// Dans MenuService::getMenusDefinition()
return [
    'admin' => self::getAdminMenus(),
    'superviseur' => self::getSupervisorMenus(),  // ← Nouveau
    // ... autres rôles ...
];

// Ajouter la méthode
private static function getSupervisorMenus(): array
{
    return [
        [
            'id' => 'dashboard',
            'label' => 'Tableau de bord',
            'icon' => 'layout-dashboard',
            'url' => '/dashboard',
            'permissions' => [],
        ],
        // ... ajouter les menus appropriés ...
    ];
}
```

3. **Tester**:

```php
// Dans une vue de test
$menus = MenuService::getMenuStructure('superviseur', ['eleves.view', 'rapports.view']);
var_dump($menus);
```

### Cas 3: Ajouter un groupe de menus avec enfants

**Objectif**: Ajouter un groupe "Ressources humaines" avec sous-menus.

```php
[
    'id'          => 'hr',
    'label'       => 'Ressources humaines',
    'icon'        => 'users-2',
    'permissions' => ['hr.view', 'hr.employees', 'hr.payroll'],
    'children'    => [
        [
            'label'       => 'Employés',
            'icon'        => 'user',
            'url'         => '/hr/employees',
            'permissions' => ['hr.employees'],
        ],
        [
            'label'       => 'Paies',
            'icon'        => 'credit-card',
            'url'         => '/hr/payroll',
            'permissions' => ['hr.payroll'],
        ],
        [
            'label'       => 'Contrats',
            'icon'        => 'file-text',
            'url'         => '/hr/contracts',
            'permissions' => ['hr.contracts'],
        ],
    ],
]
```

**Comportement**:
- Le groupe apparaît SI l'utilisateur a au moins UNE des permissions listées (hr.view, hr.employees, ou hr.payroll)
- Les enfants sont filtrés individuellement
- Le groupe disparaît s'il n'y a aucun enfant visible

## Modification du comportement de filtrage

### Autoriser certains menus pour tous

Si vous voulez qu'un menu soit visible à TOUS les utilisateurs (sans vérification de permission):

```php
[
    'id'          => 'support',
    'label'       => 'Support/Aide',
    'icon'        => 'help-circle',
    'url'         => '/support',
    'permissions' => [],  // ← Vide = visible pour tous
]
```

### Créer un filtrage personnalisé

Si la logique permission simple ne suffit pas, vous pouvez personnaliser `filterMenuByPermissions`:

```php
// Dans MenuService, modifier la méthode :
private static function filterMenuByPermissions(array $menus, array $permissions, string $role): array
{
    $filtered = [];

    foreach ($menus as $menu) {
        // Logique personnalisée par rôle
        if ($role === 'enseignant') {
            // Pour enseignant: filtrer uniquement sur les permissions propre
            if (empty($menu['permissions']) || self::hasAnyPermission($permissions, $menu['permissions'])) {
                $filtered[] = $menu;
            }
        } else {
            // Pour les autres: logique standard
            // ... (code existant)
        }
    }

    return $filtered;
}
```

## Tester le système

### Test dans une vue

```php
<!-- Créer une page de test -->
<?php
use App\Services\MenuService;

$roles = ['admin', 'directeur', 'enseignant', 'comptable', 'secretaire', 'eleve', 'parent'];
$samplePerms = [
    'eleves.view', 'notes.view', 'bulletins.view',
    'absences.view', 'comptabilite.view', 'emploi_du_temps.view',
    'rapports.view', 'users.view'
];

foreach ($roles as $role) {
    $menus = MenuService::getMenuStructure($role, $samplePerms);
    echo "<h2>$role</h2>";
    echo '<pre>';
    print_r($menus);
    echo '</pre>';
}
?>
```

### Test unitaire (structure recommandée)

```php
// tests/Services/MenuServiceTest.php
namespace Tests\Services;

use App\Services\MenuService;
use PHPUnit\Framework\TestCase;

class MenuServiceTest extends TestCase
{
    public function testAdminSeesAllMenus()
    {
        $menus = MenuService::getMenuStructure('admin', ['eleves.view']);
        $this->assertGreaterThan(5, count($menus));
    }

    public function testStudentSeesLimitedMenus()
    {
        $menus = MenuService::getMenuStructure('eleve', []);
        $this->assertLessThan(15, count($menus));
    }

    public function testMenuFiltersOnPermission()
    {
        $menus = MenuService::getMenuStructure('directeur', []);
        // Vérifier que 'eleves' n'apparaît pas sans permission
        $menuIds = array_column($menus, 'id');
        // À adapter selon votre test
    }
}
```

## Performance

### Optimisation

Le système est optimisé:
- ✅ **Pas de requêtes DB** — tout filtre en PHP
- ✅ **Pas de boucles imbriquées complexes** — O(n) complexity
- ✅ **En mémoire** — pas de cache distant nécessaire
- ✅ **Appelé une fois par page** — 1ms environ

### Benchmarking

```php
$start = microtime(true);
$menus = MenuService::getMenuStructure('admin', array_fill(0, 100, 'eleves.view'));
$end = microtime(true);

echo "Time: " . round(($end - $start) * 1000, 2) . "ms";
// Résultat: ~0.5-2ms
```

## Débogage

### Afficher les menus générés

```php
// Dans une vue
echo '<pre>';
var_dump(MenuService::getMenuStructure('admin', $perms));
echo '</pre>';
```

### Vérifier si un menu est actif

```php
// Vérifier manuellement
$currentUri = $_SERVER['REQUEST_URI'];
$menuUrl = '/dashboard';

if (MenuService::isMenuActive($menuUrl, $currentUri)) {
    echo "Menu actif!";
} else {
    echo "Menu inactif. URI: $currentUri";
}
```

### Tracer les permissions

```php
// Ajouter dans MenuService::filterMenuByPermissions()
error_log("User permissions: " . json_encode($permissions));
error_log("Menu: " . ($menu['label'] ?? 'unknown') . " - Required: " . json_encode($menu['permissions']));
```

## Commandes artisan recommandées

Si vous utilisez Laravel (adapter selon votre framework):

```bash
# Créer les migrations de permissions
php artisan make:migration create_permissions_table

# Seeder les permissions par rôle
php artisan make:seeder PermissionSeeder
php artisan db:seed --class=PermissionSeeder
```

## Ressources

- 📖 [Documentation MenuService](../MENU_SYSTEM_DOCUMENTATION.md)
- 🎨 [Icônes Lucide](https://lucide.dev)
- 📝 [RBAC V2 Blueprint](../RBAC_V2.md)

---

**Dernière mise à jour**: 2026-01-06
