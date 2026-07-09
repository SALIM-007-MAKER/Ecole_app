# Guide d'Intégration du Système de Menu Dynamique

## Checklist d'Intégration

### Phase 1: Vérification de base

- [ ] **MenuService.php créé** → `app/Services/MenuService.php`
- [ ] **Partial navigation.php créé** → `app/Views/layouts/partials/navigation.php`
- [ ] **Layout main.php modifié** → inclut le MenuService et le partial
- [ ] **Namespace correct** → `use App\Services\MenuService;` fonctionne

### Phase 2: Vérification de la session

Avant le lancement, s'assurer que l'authentification charge bien les permissions:

```php
// Dans AuthController::login() (ou équivalent)
$user = User::authenticate($email, $password);
$permissions = RbacService::loadUserPermissions($user['id']);

$_SESSION['_auth_user'] = [
    'id'          => $user['id'],
    'nom'         => $user['nom'],
    'prenom'      => $user['prenom'],
    'email'       => $user['email'],
    'role'        => $user['role'],
    'photo'       => $user['photo'] ?? null,
    'permissions' => $permissions,  // ← CRUCIAL
];
```

### Phase 3: Test dans l'application

#### Test 1: Vérifier les permissions en session

```php
// Dans n'importe quelle page après login
<?php
echo '<pre>';
var_dump($_SESSION['_auth_user']['permissions']);
echo '</pre>';
?>
```

**Attendu**: Array avec les codes de permissions comme `['eleves.view', 'notes.view', ...]`

#### Test 2: Vérifier les menus générés

Dans le layout ou une vue:

```php
<?php
use App\Services\MenuService;

$menus = MenuService::getMenuStructure(
    $_SESSION['_auth_user']['role'],
    $_SESSION['_auth_user']['permissions']
);

echo '<h2>Menus générés:</h2>';
echo '<pre>';
var_dump($menus);
echo '</pre>';
?>
```

**Attendu**: Array avec les menus visibles pour ce rôle (environ 5-15 éléments selon le rôle)

#### Test 3: Vérifier le rendu HTML

1. Ouvrir l'application dans le navigateur
2. Vérifier que le sidebar affiche les menus
3. Cliquer sur les groupes (Scolarité, Finance, etc.)
4. Vérifier que l'expand/collapse fonctionne

### Phase 4: Tests par rôle

Créer des comptes de test pour chaque rôle:

```sql
-- Admin
INSERT INTO users (nom, prenom, email, password, role)
VALUES ('Test', 'Admin', 'admin@test.local', PASSWORD('password123'), 'admin');

-- Directeur
INSERT INTO users (nom, prenom, email, password, role)
VALUES ('Test', 'Directeur', 'directeur@test.local', PASSWORD('password123'), 'directeur');

-- Secrétaire
INSERT INTO users (nom, prenom, email, password, role)
VALUES ('Test', 'Secrétaire', 'secretaire@test.local', PASSWORD('password123'), 'secretaire');

-- Enseignant
INSERT INTO users (nom, prenom, email, password, role)
VALUES ('Test', 'Enseignant', 'enseignant@test.local', PASSWORD('password123'), 'enseignant');

-- Comptable
INSERT INTO users (nom, prenom, email, password, role)
VALUES ('Test', 'Comptable', 'comptable@test.local', PASSWORD('password123'), 'comptable');

-- Élève
INSERT INTO users (nom, prenom, email, password, role)
VALUES ('Test', 'Élève', 'eleve@test.local', PASSWORD('password123'), 'eleve');

-- Parent
INSERT INTO users (nom, prenom, email, password, role)
VALUES ('Test', 'Parent', 'parent@test.local', PASSWORD('password123'), 'parent');
```

Assigner les permissions:

```sql
-- Admin a toutes les permissions
INSERT INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM users u
JOIN roles r ON r.slug = 'admin'
WHERE u.role = 'admin' LIMIT 1;

-- Pour les autres, assigner via les rôles
-- (Les permissions viennent des role_permissions)
```

#### Test 5: Vérifier chaque rôle

Pour chaque compte de test:

1. **Se connecter** avec le compte
2. **Vérifier le sidebar** — doit afficher les menus appropriés
3. **Cliquer sur Annonces** — doit être visible pour TOUS
4. **Cliquer sur Notifications** — doit être visible pour TOUS
5. **Vérifier les accès**:
   - Admin: voit TOUT
   - Directeur: Élèves, Scolarité, Rapports (limité)
   - Secrétaire: Élèves, Finance (paiements)
   - Enseignant: Notes, Absences, Planning
   - Comptable: Finance uniquement
   - Élève: Ses notes, bulletin, planning
   - Parent: Notes de ses enfants, paiements

### Phase 5: Tests de robustesse

#### Test: Utilisateur sans permissions

```php
// Permissions vide
$menus = MenuService::getMenuStructure('admin', []);

// Doit quand même afficher les menus sans permission requise
// (Dashboard, Annonces, Notifications)
```

**Attendu**: Au minimum 3 menus (Dashboard, Annonces, Notifications)

#### Test: Permissions mal formatées

```php
// Permissions non existantes
$menus = MenuService::getMenuStructure('admin', ['fake.permission', 'another.fake']);

// Ne doit pas générer d'erreur
// Doit afficher les menus sans permission requise
```

**Attendu**: Pas d'erreur, au moins les menus génériques

#### Test: Rôle inexistant

```php
$menus = MenuService::getMenuStructure('role_inexistant', []);

// Doit retourner un array vide
```

**Attendu**: `[]` (array vide)

#### Test: Performance

```php
$start = microtime(true);

for ($i = 0; $i < 1000; $i++) {
    MenuService::getMenuStructure('admin', ['eleves.view']);
}

$duration = microtime(true) - $start;
echo "1000 appels en " . round($duration * 1000) . "ms";
```

**Attendu**: < 2 secondes pour 1000 appels

### Phase 6: Tests de sécurité

#### Test: Utilisateur ne peut pas voir de menus interdits

```php
// Un comptable n'a pas 'eleves.view'
$menus = MenuService::getMenuStructure('comptable', ['comptabilite.view']);

// 'eleves' (students) ne doit pas apparaître
$studentMenuId = array_search('students', array_column($menus, 'id'));
assert($studentMenuId === false, "Comptable ne doit pas voir les élèves");
```

#### Test: Pas d'accès direct à une route cachée

Même si un utilisateur connaît l'URL `/eleves`, il ne doit pas y accéder:

```php
// Dans ElévesController::list()
public function list() {
    // Vérifier la permission
    $this->requirePermission('eleves.view');
    
    // Si pas de permission → HTTP 403
    // Si permission OK → afficher la page
}
```

## Dépannage

### Problème: Menu n'apparaît pas

**Solution 1**: Vérifier que les permissions sont en session

```php
if (empty($_SESSION['_auth_user']['permissions'])) {
    echo "ERREUR: Permissions vides en session";
}
```

**Solution 2**: Vérifier le rôle

```php
if (empty($_SESSION['_auth_user']['role'])) {
    echo "ERREUR: Rôle vide en session";
}
```

**Solution 3**: Vérifier que MenuService fonctionne

```php
use App\Services\MenuService;
$menus = MenuService::getMenuStructure('admin', ['eleves.view']);
var_dump($menus); // Doit retourner un array
```

### Problème: Menu affiche alors qu'il ne devrait pas

**Solution**: Vérifier la permission

```php
// Le comptable a 'comptabilite.view'?
$hasCompOccPermission = in_array('comptabilite.view', $userPermissions);

// Si oui, finance doit apparaître
```

### Problème: Erreur PHP

**Solution**: Vérifier le namespace

```php
// Vérifier que use App\Services\MenuService; est présent
// Vérifier que le namespace dans MenuService.php est correct
```

### Problème: Icônes manquantes

**Solution**: Vérifier que Lucide est chargé

```html
<!-- Dans main.php -->
<script src="https://unpkg.com/lucide@latest"></script>
<script>
  lucide.createIcons();
</script>
```

## Migration depuis l'ancien système

### Avant (ancien layout)

```php
<?php if (hasPerm($perms, 'eleves.view')): ?>
    <a href="/eleves">Élèves</a>
<?php endif; ?>
```

### Après (nouveau system)

```php
<?php
use App\Services\MenuService;
$menus = MenuService::getMenuStructure($role, $perms);
include 'layouts/partials/navigation.php';
?>
```

**Avantage**: 
- ✅ Une source de vérité (MenuService)
- ✅ Facile à modifier (un endroit)
- ✅ Testable
- ✅ Pas de répétition de code

## Documentation additionnelle

- 📖 [MENU_SYSTEM_DOCUMENTATION.md](./MENU_SYSTEM_DOCUMENTATION.md) — Guide complet
- 👨‍💻 [MENU_SYSTEM_DEVELOPER_GUIDE.md](./MENU_SYSTEM_DEVELOPER_GUIDE.md) — Guide dev
- 🧪 [/public/tests/test-menu-system.php](/public/tests/test-menu-system.php) — Tests

## Support et Questions

Si vous avez des questions:

1. Vérifier la documentation
2. Examiner les tests
3. Ajouter `var_dump()` pour déboguer
4. Vérifier les permissions en session

## Chronologie d'intégration recommandée

| Étape | Action | Durée |
|-------|--------|-------|
| 1 | Créer les fichiers (MenuService.php, navigation.php) | 5 min |
| 2 | Modifier main.php | 10 min |
| 3 | Tester en développement | 20 min |
| 4 | Créer les comptes de test | 10 min |
| 5 | Tester chaque rôle | 30 min |
| 6 | Valider la sécurité | 20 min |
| 7 | Documentation et rollout | 15 min |

**Total**: ~2 heures

## Rollout en production

### Avant le déploiement

- [ ] Tous les tests passent
- [ ] Performances OK (< 2ms par rôle)
- [ ] Sécurité validée
- [ ] Permissions en DB synchronisées
- [ ] Backup de la DB fait

### Déploiement

```bash
# 1. Déployer les fichiers
git push
# ou
cp app/Services/MenuService.php /production/
cp app/Views/layouts/partials/navigation.php /production/

# 2. Vérifier que main.php a été modifié
# 3. Tester dans production
```

### Après le déploiement

- [ ] Vérifier les logs
- [ ] Tester avec des vrais utilisateurs
- [ ] Monitorer les performances
- [ ] Recueillir du feedback

## Rollback en cas de problème

```bash
# Revenir au layout original
git revert <commit_hash>
# ou
cp backups/main.php.backup app/Views/layouts/main.php
```

---

**Dernière mise à jour**: 2026-01-06
**Statut**: Production Ready
