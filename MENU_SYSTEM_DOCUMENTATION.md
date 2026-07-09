# Système de Menu Dynamique par Rôles et Permissions

## Vue d'ensemble

Le système de menu dynamique génère automatiquement la navigation (sidebar et hamburger menu) en fonction du rôle et des permissions de l'utilisateur. **Chaque utilisateur ne voit que les fonctionnalités qui le concernent**.

## Architecture

```
MenuService (App\Services\MenuService)
    ├── getMenuStructure($role, $permissions)
    │   └── Retourne array de menus filtrés
    │
    ├── getAdminMenus()              → Accès COMPLET
    ├── getDirectorMenus()           → Gestion école
    ├── getSecretaryMenus()          → Élèves, docs, paiements
    ├── getTeacherMenus()            → Ses classes et notes
    ├── getAccountantMenus()         → Finance uniquement
    ├── getStudentMenus()            → Ses données perso
    └── getParentMenus()             → Ses enfants uniquement

    Partial (app/Views/layouts/partials/navigation.php)
    └── Rend le HTML du menu dynamique
```

## Utilisation dans les layout

Dans [main.php](main.php#L50-L60):

```php
<?php
use App\Services\MenuService;

// Récupérer le menu structuré pour ce rôle
$menus = MenuService::getMenuStructure($role, $perms);
$currentUri = $uri;
?>

<!-- Navigation -->
<?php include __DIR__ . '/partials/navigation.php'; ?>
```

## Structure d'un élément de menu

### Menu simple (lien)

```php
[
    'id'          => 'dashboard',      // Identifiant unique
    'label'       => 'Tableau de bord', // Texte affiché
    'icon'        => 'layout-dashboard', // Icône Lucide
    'url'         => '/dashboard',     // Route relative
    'permissions' => [],               // Permissions requises (vide = accessible à tous)
    'badge'       => 'notifications',  // (optionnel) 'notifications' pour afficher badge
]
```

### Menu groupe (avec sous-menus)

```php
[
    'id'          => 'scolarite',
    'label'       => 'Scolarité',
    'icon'        => 'building-2',
    'permissions' => ['enseignants.view', 'classes.view'],
    'children'    => [
        [
            'label' => 'Enseignants',
            'icon'  => 'user-check',
            'url'   => '/professeurs',
            'permissions' => ['enseignants.view'],
        ],
        [
            'label' => 'Classes',
            'icon'  => 'building',
            'url'   => '/classes',
            'permissions' => ['classes.view'],
        ],
    ],
]
```

## Règles de visibilité

### 1. Menus sans permissions requises

- Affichés à TOUS les utilisateurs
- Exemple: "Tableau de bord", "Annonces", "Notifications"

```php
'permissions' => []  // Accessible à tous
```

### 2. Menus avec permissions requises

- L'utilisateur doit avoir **au moins UNE** des permissions listées
- Exemple: L'admin a `enseignants.view` donc voit "Enseignants"
- Un comptable n'a pas `enseignants.view` donc ne le voit pas

```php
'permissions' => ['enseignants.view', 'classes.view']
// Affiché si l'utilisateur a enseignants.view OU classes.view
```

### 3. Menus groupes (avec children)

- Le groupe est affiché si l'utilisateur a au moins une permission du groupe
- Les sous-menus sont filtrés individuellement
- Le groupe disparaît s'il n'a aucun sous-menu valide

### 4. Logique de filtre

```php
// Dans MenuService::filterMenuByPermissions()

foreach ($menus as $menu) {
    // Menu sans permission → toujours affiché
    if (empty($menu['permissions'])) {
        $filtered[] = $menu;
        continue;
    }

    // Menu avec permissions → vérifier l'utilisateur
    if (self::hasAnyPermission($userPermissions, $menu['permissions'])) {
        $filtered[] = $menu;
        
        // Filtrer aussi les enfants s'il y en a
        if (!empty($menu['children'])) {
            $menu['children'] = self::filterMenuByPermissions(
                $menu['children'], 
                $permissions, 
                $role
            );
            // Ne garder le groupe que s'il a des enfants restants
        }
    }
}
```

## Par rôle : Quoi voit qui ?

| Rôle | Tableau de bord | Élèves | Scolarité | Académique | Finance | Planning | Rapports | Notes |
|------|---|---|---|---|---|---|---|---|
| **Admin** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Directeur** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ (limité) | ❌ |
| **Secrétaire** | ✅ | ✅ | ❌ | ❌ | ✅ (paiements) | ❌ | ❌ | ❌ |
| **Enseignant** | ✅ | ❌ | ❌ | ✅ (propres notes) | ❌ | ✅ (son EDT) | ❌ | ✅ |
| **Comptable** | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ (finance) | ✅ |
| **Élève** | ✅ | ❌ | ❌ | ✅ (ses notes) | ❌ | ✅ (son EDT) | ❌ | ✅ |
| **Parent** | ✅ | ❌ | ❌ | ✅ (ses enfants) | ✅ (scolarité) | ❌ | ❌ | ✅ |

## Exemple : Ajout d'une nouvelle option de menu

### 1. Ajouter dans MenuService

Admettons qu'on veut ajouter un menu "Gestion des salles" visible par les administrateurs et directeurs.

```php
// Dans MenuService::getAdminMenus()
[
    'id'          => 'room-management',
    'label'       => 'Gestion des salles',
    'icon'        => 'door-open',
    'url'         => '/admin/salles',
    'permissions' => ['salles.view'],  // Cette permission doit exister dans la DB
]

// Dans MenuService::getDirectorMenus()
[
    'id'          => 'room-management',
    'label'       => 'Gestion des salles',
    'icon'        => 'door-open',
    'url'         => '/admin/salles',
    'permissions' => ['salles.view'],
]
```

### 2. S'assurer que la permission existe en DB

```sql
-- Vérifier que la permission est bien dans la table permissions
SELECT * FROM permissions WHERE code = 'salles.view';
```

### 3. Assigner le rôle aux utilisateurs

```sql
-- Assigner la permission au rôle admin
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code = 'salles.view'
WHERE r.slug = 'admin';
```

### 4. Modifier le contrôleur si besoin

Pas besoin de modifier le contrôleur — MenuService affiche/cache uniquement selon les permissions.

## Classe MenuService — Méthodes publiques

### `getMenuStructure(string $role, array $permissions): array`

Récupère le menu complet pour un rôle donné.

```php
$menus = MenuService::getMenuStructure('admin', ['eleves.view', 'notes.view']);
// Retourne les éléments visibles pour cet admin
```

### `isMenuActive(string $menuUrl, string $currentUri): bool`

Vérifie si un menu est actuellement actif (URL courante).

```php
$active = MenuService::isMenuActive('/dashboard', $_SERVER['REQUEST_URI']);
```

### `getMenuItemClass(string $menuUrl, string $currentUri, bool $isSubmenu): string`

Retourne les classes CSS appropriées (avec `.nav-active` si actif).

```php
$class = MenuService::getMenuItemClass('/notes', $uri, false);
// Retourne: "nav-item" ou "nav-item nav-active"
```

### `isGroupActive(array $childUrls, string $currentUri): bool`

Vérifie si un groupe de menu est actif (au moins un enfant actif).

```php
$isOpen = MenuService::isGroupActive(['/notes', '/absences'], $uri);
```

## Partial navigation.php

Le partial `app/Views/layouts/partials/navigation.php` reçoit:

- `$menus` → array filtré des éléments à afficher
- `$currentUri` → URI actuelle pour highlight
- `$currentUser` → données de l'utilisateur (optionnel)

**Les éléments sont déjà filtrés** — le partial les rend simplement en HTML.

```php
<!-- Rendre un lien simple -->
<a href="<?= $itemUrl ?>" class="<?= $itemClass ?>">
    <i data-lucide="<?= $item['icon'] ?>"></i>
    <span><?= $item['label'] ?></span>
</a>

<!-- Rendre un groupe avec enfants -->
<button class="nav-item<?= $groupActive ? ' nav-active' : '' ?>" data-group="<?= $item['id'] ?>">
    <i data-lucide="<?= $item['icon'] ?>"></i>
    <span><?= $item['label'] ?></span>
    <i data-lucide="chevron-right" class="nav-group-arrow"></i>
</button>
<div class="nav-group-content" id="sg-<?= $item['id'] ?>">
    <!-- Enfants ici -->
</div>
```

## Gestion des permissions

Les permissions d'un utilisateur sont chargées à la connexion dans la session:

```php
// Dans AuthController::login()
$permissions = RbacService::loadUserPermissions($userId);
// Retourne: ['eleves.view', 'notes.view_own', 'bulletins.view', ...]

// Stocké en session
$_SESSION['_auth_user']['permissions'] = $permissions;
```

Pour modifier les permissions en temps réel (sans déconnexion), il faut recharger manuellement:

```php
// Dans un contrôleur admin
$permissions = RbacService::loadUserPermissions($userId);
Session::updatePermissions($permissions);
```

## Détection responsive du menu

Le CSS détecte automatiquement les actions sur les groupes de menu:

```javascript
// Gestion toggle du menu (script app.js)
document.querySelectorAll('[data-group]').forEach(btn => {
    btn.addEventListener('click', () => {
        const group = btn.getAttribute('data-group');
        const content = document.getElementById(`sg-${group}`);
        content.style.maxHeight = content.style.maxHeight ? '' : '500px';
    });
});
```

## Bonnes pratiques

1. **Permissions cohérentes** : Vérifier qu'une permission est assignée avant de l'utiliser dans MenuService

2. **URLs correctes** : Les URLs du menu doivent correspondre aux routes existantes

3. **Icônes Lucide** : Utiliser uniquement des icônes de [lucide.dev](https://lucide.dev)

4. **Hiérarchie logique** : Grouper les menus par domaine (Scolarité, Finance, etc.)

5. **Performance** : Le filtrage se fait en PHP, pas en BD, donc pas de requête supplémentaire

## Troubleshooting

### Menu n'apparaît pas ?

1. Vérifier que l'utilisateur a la permission requise

```php
// Vérifier en session:
var_dump($_SESSION['_auth_user']['permissions']);
```

2. Vérifier que l'utilisateur a le rôle correct

```php
// Vérifier le rôle:
var_dump($_SESSION['_auth_user']['role']);
```

3. Ajouter un `var_dump($menus)` dans le partial pour déboguer

### Menu disparaît après modification de permissions ?

L'utilisateur doit se **déconnecter et reconnecter** pour que les permissions se rechargent en session.

### Icône ne s'affiche pas ?

Vérifier que l'icône existe sur [lucide.dev](https://lucide.dev) et que le nom est correct (tirets, pas underscores).

---

**Mis à jour le**: 2026-01-06  
**Version**: 1.0  
**Auteur**: Système de Menu Dynamique
