# Diagrammes du Système de Menu Dynamique

## 1. Architecture générale

```
┌─────────────────────────────────────────────────────────────┐
│                    APPLICATION SCOLARIS                       │
└─────────────────────────────────────────────────────────────┘
                              ↓
                    ┌─────────────────┐
                    │   UTILISATEUR   │
                    │   SE CONNECTE   │
                    └─────────────────┘
                              ↓
                    ┌─────────────────────────────┐
                    │  AuthController::login()    │
                    │  Charge permissions en DB   │
                    └─────────────────────────────┘
                              ↓
        ┌─────────────────────────────────────────┐
        │  $_SESSION['_auth_user'] = [             │
        │    'id'          => 1,                   │
        │    'role'        => 'admin',             │
        │    'permissions' => ['eleves.view', ...] │
        │  ]                                       │
        └─────────────────────────────────────────┘
                              ↓
                    ┌─────────────────────┐
                    │  app/Views/layouts  │
                    │    /main.php        │
                    └─────────────────────┘
                              ↓
                ┌──────────────────────────────┐
                │ MenuService                  │
                │ ::getMenuStructure(          │
                │   $role,                     │
                │   $permissions               │
                │ )                            │
                └──────────────────────────────┘
                              ↓
        ┌─────────────────────────────────────────┐
        │   Array filtré des menus à afficher     │
        │   [                                     │
        │     ['id'=>'dashboard', ...],           │
        │     ['id'=>'scolarite', 'children'=>[]] │
        │   ]                                     │
        └─────────────────────────────────────────┘
                              ↓
        ┌─────────────────────────────────────────┐
        │ app/Views/layouts/partials/             │
        │ navigation.php                          │
        │ (Rend les menus en HTML)                │
        └─────────────────────────────────────────┘
                              ↓
        ┌─────────────────────────────────────────┐
        │           SIDEBAR DYNAMIQUE             │
        │    [Tableau de bord]                    │
        │    [Élèves] ←── Visible pour ce rôle    │
        │    [Scolarité ▼] (groupe collapsible)   │
        │      → Enseignants                      │
        │      → Classes                          │
        │    [Finance ▼]                          │
        │      → Paiements                        │
        │      → Impayés                          │
        │    [Annonces]                           │
        │    [Notifications]                      │
        └─────────────────────────────────────────┘
```

---

## 2. Flux de filtre des permissions

```
                       MENUS BRUTS
                    (définis par rôle)
                            ↓
        ┌───────────────────────────────────────┐
        │  filterMenuByPermissions()             │
        │  (pour chaque menu)                    │
        └───────────────────────────────────────┘
                            ↓
        ┌───────────────────────────────────────┐
        │ Permission requise?                   │
        │ - NON: Ajouter à la liste (visible)   │
        │ - OUI: Vérifier l'utilisateur         │
        └───────────────────────────────────────┘
                            ↓
        ┌───────────────────────────────────────┐
        │ Utilisateur a la permission?           │
        │ - OUI: Ajouter à la liste             │
        │ - NON: Ignorer (invisible)             │
        └───────────────────────────────────────┘
                            ↓
        ┌───────────────────────────────────────┐
        │ A des sous-menus (children)?           │
        │ - OUI: Les filtrer aussi               │
        │       Ne garder le groupe que s'il y   │
        │       a au moins un enfant visible     │
        │ - NON: Menu simple                     │
        └───────────────────────────────────────┘
                            ↓
                    MENUS FILTRÉS
                  (seulement ce que
                l'utilisateur peut voir)
```

---

## 3. Exemple: Admin vs Comptable

```
┌──────────────────────────────────────────────────────────┐
│                    MENUS BRUTS (définis)                │
│                                                           │
│  [Dashboard]                                             │
│  [Élèves]          ← permission: eleves.view            │
│  [Scolarité]       ← permissions: enseignants.view|...  │
│  [Académique]      ← permissions: notes.view|...        │
│  [Finance]         ← permissions: comptabilite.view     │
│  [Planning]        ← permissions: emploi_du_temps.view  │
│  [Rapports]        ← permissions: rapports.view|...     │
│  [Annonces]                                              │
│  [Notifications]                                         │
└──────────────────────────────────────────────────────────┘
                            ↓
        ┌──────────────────────────────────────┐
        │         FILTRE POUR ADMIN             │
        │  Permissions: (TOUTES)                │
        └──────────────────────────────────────┘
                            ↓
        ┌─────────────────────────────────────────────┐
        │  RÉSULTAT: ADMIN VOI TOUT                   │
        │  ✓ [Dashboard]                              │
        │  ✓ [Élèves]                                 │
        │  ✓ [Scolarité]                              │
        │  ✓ [Académique]                             │
        │  ✓ [Finance]                                │
        │  ✓ [Planning]                               │
        │  ✓ [Rapports]                               │
        │  ✓ [Annonces]                               │
        │  ✓ [Notifications]                          │
        └─────────────────────────────────────────────┘

        ┌──────────────────────────────────────┐
        │    FILTRE POUR COMPTABLE             │
        │  Permissions: comptabilite.view      │
        │               rapports.view          │
        └──────────────────────────────────────┘
                            ↓
        ┌─────────────────────────────────────────────┐
        │  RÉSULTAT: COMPTABLE VOI FINANCE SEULEMENT  │
        │  ✓ [Dashboard]    ← pas permission requise  │
        │  ✗ [Élèves]       ← faut eleves.view       │
        │  ✗ [Scolarité]    ← faut enseignants.view  │
        │  ✗ [Académique]   ← faut notes.view        │
        │  ✓ [Finance]      ← a comptabilite.view    │
        │  ✗ [Planning]     ← faut emploi_du_temps   │
        │  ✓ [Rapports]     ← a rapports.view        │
        │  ✓ [Annonces]     ← pas permission requise  │
        │  ✓ [Notifications]← pas permission requise  │
        └─────────────────────────────────────────────┘
```

---

## 4. Hiérarchie des rôles et menus

```
                     ┌─────────────┐
                     │   ADMIN     │
                     │ Tous les    │
                     │  menus      │
                     └─────────────┘
                           │
              ┌────────────┼────────────┐
              │            │            │
         ┌────────┐   ┌─────────┐  ┌──────────┐
         │Directeur   │Comptable │  │Secrétaire│
         │Élèves,     │Finance   │  │Élèves,   │
         │Scolaire,   │Rapports  │  │Paiements │
         │Rapports    │          │  │          │
         └────────┘   └─────────┘  └──────────┘
              │            │            │
              │       ┌──────────┐      │
              │       │Enseignant│      │
              │       │Ses notes │      │
              │       │Ses EDT   │      │
              │       └──────────┘      │
              │            │            │
              └────────────┼────────────┘
                           │
              ┌────────────┼────────────┐
              │            │            │
          ┌────────┐  ┌──────────┐  ┌────────┐
          │Élève   │  │Parent    │  │... ?   │
          │Ses     │  │Ses       │  │Rôles   │
          │données │  │enfants   │  │custom  │
          └────────┘  └──────────┘  └────────┘
```

---

## 5. Cycle de vie d'une requête

```
1. UTILISATEUR ACCÈDE À L'APP
   ↓
2. AUTHENTIFICATION (login)
   ↓
3. PERMISSIONS CHARGÉES EN SESSION
   ├─ RbacService::loadUserPermissions($userId)
   ├─ Query: user_roles → roles → permissions
   └─ Session['_auth_user']['permissions'] = [...]
   ↓
4. PAGE CHARGÉE (ex: /dashboard)
   ↓
5. LAYOUT MAIN.PHP INCLUS
   ├─ use App\Services\MenuService;
   ├─ $menus = MenuService::getMenuStructure($role, $perms);
   └─ include 'layouts/partials/navigation.php';
   ↓
6. PARTIAL NAVIGATION.PHP RENDU
   ├─ Boucle sur $menus (déjà filtrés)
   ├─ Affiche menus simples
   ├─ Affiche groupes collapsibles
   └─ Généré du HTML
   ↓
7. HTML SIDEBAR AFFICHÉ
   ├─ Utilisateur voit les menus appropriés
   └─ Peut cliquer/naviguer
   ↓
8. NOUVELLE PAGE
   ├─ Contrôleur vérifie permission
   ├─ requirePermission('xxx.view')
   └─ Retour 403 si pas permission
   ↓
9. CYCLE RÉPÉTÉ
```

---

## 6. Structure de MenuService

```
MenuService
├── Public
│   ├── getMenuStructure($role, $perms)
│   │   └── Retourne array filtré des menus
│   ├── isMenuActive($url, $uri)
│   │   └── Vérifie si menu actif
│   ├── getMenuItemClass($url, $uri, $isSubmenu)
│   │   └── Retourne classe CSS
│   └── isGroupActive($urls, $uri)
│       └── Vérifie si groupe actif
│
└── Private
    ├── getMenusDefinition()
    │   └── Map rôle → méthode
    │
    ├── get{Role}Menus() (7 méthodes)
    │   ├── getAdminMenus()
    │   ├── getDirectorMenus()
    │   ├── getSecretaryMenus()
    │   ├── getTeacherMenus()
    │   ├── getAccountantMenus()
    │   ├── getStudentMenus()
    │   └── getParentMenus()
    │
    └── Helpers
        ├── filterMenuByPermissions()
        │   └── Récursif pour groupes
        ├── hasAnyPermission()
        │   └── Vérification permission
        └── isMenuActive()
            └── Détection URI active
```

---

## 7. Exemple de structures de données

### Menu simple
```
┌─────────────────────────────────────┐
│ Array                               │
├─────────────────────────────────────┤
│ [id]          => "dashboard"        │
│ [label]       => "Tableau de bord"  │
│ [icon]        => "layout-dashboard" │
│ [url]         => "/dashboard"       │
│ [permissions] => []                 │
│ [badge]       => null               │
└─────────────────────────────────────┘
```

### Menu groupe
```
┌─────────────────────────────────────────┐
│ Array                                   │
├─────────────────────────────────────────┤
│ [id]          => "scolarite"            │
│ [label]       => "Scolarité"            │
│ [icon]        => "building-2"           │
│ [permissions] => [                      │
│                   "enseignants.view",   │
│                   "classes.view"        │
│                 ]                       │
│ [children]    => [                      │
│                   [                     │
│                     "label"=>"Ens.",    │
│                     "url"=>"/prof",     │
│                     "permissions"=>...  │
│                   ],                    │
│                   ...                   │
│                 ]                       │
└─────────────────────────────────────────┘
```

---

## 8. Table de vérité: Permission + Utilisateur = Visible?

```
┌────────────────────┬──────────────────┬──────────┐
│ Permission requise │ Utilisateur a-t-il? │ Visible? │
├────────────────────┼──────────────────┼──────────┤
│ (none)            │ -                 │ OUI ✓    │
│ eleves.view       │ OUI               │ OUI ✓    │
│ eleves.view       │ NON               │ NON ✗    │
│ [a.view OR b.view]│ a.view OUI        │ OUI ✓    │
│ [a.view OR b.view]│ b.view OUI        │ OUI ✓    │
│ [a.view OR b.view]│ Aucun             │ NON ✗    │
└────────────────────┴──────────────────┴──────────┘
```

---

## 9. Interaction avec collapsibles (JavaScript)

```
HTML sidebar
├── [Dashboard] ← lien simple
│
├── [Scolarité ▼] ← groupe
│   └── div.nav-group-content (max-height: 500px | 0)
│       ├── [Enseignants] ← lien enfant
│       ├── [Classes]     ← lien enfant
│       └── [Matières]    ← lien enfant
│
├── [Finance ▼] ← groupe
│   └── div.nav-group-content
│       ├── [Vue d'ensemble]
│       ├── [Paiements]
│       └── [Dépenses]
│
└── [Annonces] ← lien simple

User Click
├── Si lien simple: naviguer
├── Si bouton groupe:
│   ├── Basculer max-height (0 ↔ 500px)
│   ├── Animer la transition
│   └── Afficher/Masquer enfants
└── Icône chevron tourne
```

---

## 10. Comparaison: Avant vs Après

### AVANT (hardcodé)

```php
<!-- main.php -->
<?php if ($role === 'parent'): ?>
    <a href="/parent/notes">Notes</a>
    <a href="/parent/bulletin">Bulletins</a>
    ...
<?php elseif ($role === 'eleve'): ?>
    <a href="/eleve/notes">Mes notes</a>
    <a href="/eleve/bulletin">Bulletin</a>
    ...
<?php else: ?>
    <?php if (hasPerm($perms, 'eleves.view')): ?>
        <a href="/eleves">Élèves</a>
    <?php endif; ?>
    ...
<?php endif; ?>

/* 
   PROBLÈMES:
   ✗ Hardcodé dans le layout
   ✗ Difficile à modifier
   ✗ Dupplication de code
   ✗ Logique complexe
   ✗ Pas testable
*/
```

### APRÈS (dynamique)

```php
<!-- main.php -->
<?php
use App\Services\MenuService;
$menus = MenuService::getMenuStructure($role, $perms);
include 'layouts/partials/navigation.php';
?>

/* 
   AVANTAGES:
   ✓ Source unique (MenuService)
   ✓ Facile à modifier
   ✓ Pas de duplication
   ✓ Logique claire
   ✓ Testable
   ✓ Extensible
*/
```

---

## 11. Performance

```
Appel à MenuService::getMenuStructure()

┌──────────────────────────┐
│ Rôle: admin              │
│ Permissions: 50          │
│ Menus bruts: 100         │
└──────────────────────────┘
        ↓ (0.1ms)
   Charger menus de admin
        ↓ (0.3ms)
   Filtrer par permissions
        ↓ (0.2ms)
   Retourner résultat
        ↓
   Total: ~0.6ms
   
Parallèle avec requête BD:
   Requête BD simple: 5-50ms
   MenuService: 0.5-2ms
   
   MenuService est 10x+ plus rapide!
```

---

## 12. Cycle de développement: Ajouter un menu

```
1. ANALYSER LE BESOIN
   "Ajouter Gestion des salles pour admin+directeur"
   ↓
2. VÉRIFIER LA PERMISSION EN DB
   SELECT * FROM permissions WHERE code = 'salles.view';
   └─ Si n'existe pas: la créer
   ↓
3. ASSIGNER AU RÔLE
   INSERT INTO role_permissions...
   ↓
4. AJOUTER DANS MenuService
   getAdminMenus() + getDirectorMenus()
   ↓
5. CRÉER LA ROUTE
   $router->get('/admin/salles', 'SallesController@index');
   ↓
6. CRÉER LE CONTRÔLEUR ET VUE
   Logique métier...
   ↓
7. TESTER
   Menu apparaît? ✓
   Peut naviguer? ✓
   Sécurité OK? ✓
   ↓
8. DÉPLOYER
```

---

**Dernière mise à jour**: 2026-01-06
