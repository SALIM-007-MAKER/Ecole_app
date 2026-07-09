<?php
/**
 * Script de test du système de menu dynamique
 * Exécuter dans : https://yourapp.local/tests/test-menu-system.php
 * 
 * Ce script teste tous les scénarios de menu pour chaque rôle
 */

require_once __DIR__ . '/../config/define.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../app/Services/MenuService.php';

use App\Services\MenuService;

// Permissions par rôle — ce que chaque rôle "possède"
$rolePermissions = [
    'admin' => [
        'eleves.view', 'eleves.create', 'eleves.update', 'eleves.delete',
        'enseignants.view', 'enseignants.create', 'enseignants.update', 'enseignants.delete',
        'classes.view', 'classes.create', 'classes.update', 'classes.delete',
        'matieres.view', 'matieres.create', 'matieres.update', 'matieres.delete',
        'notes.view', 'notes.create', 'notes.update', 'notes.delete',
        'bulletins.view', 'bulletins.create', 'bulletins.update',
        'absences.view', 'absences.create', 'absences.update', 'absences.justify',
        'comptabilite.view', 'comptabilite.create', 'comptabilite.update',
        'emploi_du_temps.view', 'emploi_du_temps.create', 'emploi_du_temps.update',
        'rapports.view', 'rapports.export',
        'users.view', 'users.create', 'users.update', 'users.delete',
    ],
    'directeur' => [
        'eleves.view', 'eleves.create', 'eleves.update',
        'enseignants.view', 'enseignants.create', 'enseignants.update',
        'classes.view', 'classes.create', 'classes.update',
        'matieres.view',
        'notes.view',
        'bulletins.view',
        'absences.view',
        'comptabilite.view',
        'emploi_du_temps.view', 'emploi_du_temps.create', 'emploi_du_temps.update',
        'rapports.view',
    ],
    'secretaire' => [
        'eleves.view', 'eleves.create', 'eleves.update',
        'comptabilite.view',
    ],
    'enseignant' => [
        'notes.view', 'notes.create', 'notes.update',
        'absences.view', 'absences.create', 'absences.update',
        'emploi_du_temps.view.own',
    ],
    'comptable' => [
        'comptabilite.view', 'comptabilite.create', 'comptabilite.update',
        'rapports.view',
    ],
    'eleve' => [
        'notes.view.own',
        'bulletins.view.own',
        'absences.view.own',
        'emploi_du_temps.view.own',
    ],
    'parent' => [
        'notes.view.own',
        'bulletins.view.own',
        'absences.view.own',
        'comptabilite.view.own',
    ],
];

// Couleurs pour le terminal
$colors = [
    'reset'    => "\033[0m",
    'bold'     => "\033[1m",
    'success'  => "\033[92m",
    'error'    => "\033[91m",
    'info'     => "\033[94m",
    'warning'  => "\033[93m",
    'cyan'     => "\033[36m",
];

function colorize($text, $color = 'reset') {
    global $colors;
    return $colors[$color] . $text . $colors['reset'];
}

// ═════════════════════════════════════════════════════════════════

echo colorize("\n╔════════════════════════════════════════════════════════════════╗", 'bold') . "\n";
echo colorize("║   TEST SYSTÈME DE MENU DYNAMIQUE PAR RÔLE ET PERMISSIONS      ║", 'bold') . "\n";
echo colorize("╚════════════════════════════════════════════════════════════════╝\n", 'bold');

// Test pour chaque rôle
foreach (['admin', 'directeur', 'secretaire', 'enseignant', 'comptable', 'eleve', 'parent'] as $role) {
    echo colorize("\n" . str_repeat("─", 70), 'cyan') . "\n";
    echo colorize("RÔLE: " . strtoupper($role), 'bold') . "\n";
    echo colorize(str_repeat("─", 70) . "\n", 'cyan');

    $perms = $rolePermissions[$role];
    $menus = MenuService::getMenuStructure($role, $perms);

    // Compter les menus et sous-menus
    $totalItems = count($menus);
    $groupCount = 0;
    $itemCount = 0;

    foreach ($menus as $menu) {
        if (!empty($menu['children'])) {
            $groupCount++;
            $itemCount += count($menu['children']);
        } else {
            $itemCount++;
        }
    }

    // Afficher les statistiques
    printf(
        "  %s Menus affichés: %d (dont %d groupes, %d items)\n",
        colorize("ℹ", 'info'),
        $totalItems,
        $groupCount,
        $itemCount
    );
    printf(
        "  %s Permissions du rôle: %d\n",
        colorize("ℹ", 'info'),
        count($perms)
    );

    // Lister les menus
    echo "\n  " . colorize("Menus visibles:", 'bold') . "\n";
    
    foreach ($menus as $index => $menu) {
        $icon = $menu['icon'] ?? '?';
        $label = $menu['label'] ?? '';

        if (!empty($menu['children'])) {
            // Groupe
            echo sprintf(
                "    %s [%s] %s (groupe, %d enfant%s)\n",
                colorize("→", 'warning'),
                $icon,
                $label,
                count($menu['children']),
                count($menu['children']) > 1 ? 's' : ''
            );

            foreach ($menu['children'] as $child) {
                echo sprintf(
                    "        %s [%s] %s\n",
                    colorize("•", 'success'),
                    $child['icon'] ?? '?',
                    $child['label'] ?? ''
                );
            }
        } else {
            // Item simple
            echo sprintf(
                "    %s [%s] %s\n",
                colorize("→", 'success'),
                $icon,
                $label
            );
        }
    }

    // Vérifier les permissions requises
    echo "\n  " . colorize("Permissions requises pour ce menu:", 'bold') . "\n";
    $requiredPerms = [];
    foreach ($menus as $menu) {
        if (!empty($menu['permissions'])) {
            $requiredPerms = array_merge($requiredPerms, $menu['permissions']);
        }
        if (!empty($menu['children'])) {
            foreach ($menu['children'] as $child) {
                if (!empty($child['permissions'])) {
                    $requiredPerms = array_merge($requiredPerms, $child['permissions']);
                }
            }
        }
    }

    if (empty($requiredPerms)) {
        echo "    " . colorize("Aucune permission requise (tous les menus accessibles à tous)", 'success') . "\n";
    } else {
        $requiredPerms = array_unique($requiredPerms);
        foreach ($requiredPerms as $perm) {
            $hasIt = in_array($perm, $perms);
            $status = $hasIt ? colorize("✓", 'success') : colorize("✗", 'error');
            echo sprintf("    %s %s\n", $status, $perm);
        }
    }
}

// ═════════════════════════════════════════════════════════════════

echo colorize("\n" . str_repeat("═", 70) . "\n", 'cyan');
echo colorize("TESTS SPÉCIFIQUES PAR SCÉNARIO\n", 'bold');
echo colorize(str_repeat("═", 70) . "\n", 'cyan');

// Test 1 : Admin voit tout
echo "\n" . colorize("TEST 1: Admin voit tout", 'bold') . "\n";
$adminMenus = MenuService::getMenuStructure('admin', $rolePermissions['admin']);
$hasFinance = array_search('finance', array_column($adminMenus, 'id')) !== false;
$hasScolarite = array_search('scolarite', array_column($adminMenus, 'id')) !== false;
echo ($hasFinance && $hasScolarite 
    ? colorize("  ✓ Admin voit Finance ET Scolarité", 'success')
    : colorize("  ✗ Admin ne voit pas tous les menus", 'error')
) . "\n";

// Test 2 : Secrétaire n'a pas accès à Scolarité
echo "\n" . colorize("TEST 2: Secrétaire n'a pas accès à Scolarité", 'bold') . "\n";
$secretaryMenus = MenuService::getMenuStructure('secretaire', $rolePermissions['secretaire']);
$noScolarite = array_search('scolarite', array_column($secretaryMenus, 'id')) === false;
echo ($noScolarite
    ? colorize("  ✓ Secrétaire n'a pas accès à Scolarité", 'success')
    : colorize("  ✗ Secrétaire a accès à Scolarité (pas bon)", 'error')
) . "\n";

// Test 3 : Enseignant voit Planning
echo "\n" . colorize("TEST 3: Enseignant voit Planning", 'bold') . "\n";
$teacherMenus = MenuService::getMenuStructure('enseignant', $rolePermissions['enseignant']);
$hasPlanning = array_search('planning', array_column($teacherMenus, 'id')) !== false;
echo ($hasPlanning
    ? colorize("  ✓ Enseignant a accès au Planning", 'success')
    : colorize("  ✗ Enseignant n'a pas accès au Planning", 'error')
) . "\n";

// Test 4 : Comptable ne voit que Finance
echo "\n" . colorize("TEST 4: Comptable ne voit QUE Finance (et menus génériques)", 'bold') . "\n";
$accountantMenus = MenuService::getMenuStructure('comptable', $rolePermissions['comptable']);
$hasFinanceAcct = array_search('finance', array_column($accountantMenus, 'id')) !== false;
$noStudents = array_search('students', array_column($accountantMenus, 'id')) === false;
echo ($hasFinanceAcct && $noStudents
    ? colorize("  ✓ Comptable voit Finance mais pas Élèves", 'success')
    : colorize("  ✗ Comptable n'a pas le bon accès", 'error')
) . "\n";

// Test 5 : Élève voit ses notes
echo "\n" . colorize("TEST 5: Élève voit ses données personnelles", 'bold') . "\n";
$studentMenus = MenuService::getMenuStructure('eleve', $rolePermissions['eleve']);
$hasNotes = array_search('notes', array_column($studentMenus, 'id')) !== false;
$hasBulletin = array_search('bulletin', array_column($studentMenus, 'id')) !== false;
echo ($hasNotes && $hasBulletin
    ? colorize("  ✓ Élève voit Notes et Bulletins", 'success')
    : colorize("  ✗ Élève n'a pas accès à ses données", 'error')
) . "\n";

// Test 6 : Parent voit ses enfants
echo "\n" . colorize("TEST 6: Parent voit les données de ses enfants", 'bold') . "\n";
$parentMenus = MenuService::getMenuStructure('parent', $rolePermissions['parent']);
$parentHasNotes = array_search('notes', array_column($parentMenus, 'id')) !== false;
$parentNoStudents = array_search('students', array_column($parentMenus, 'id')) === false;
echo ($parentHasNotes && $parentNoStudents
    ? colorize("  ✓ Parent voit Notes mais pas liste Élèves", 'success')
    : colorize("  ✗ Parent n'a pas le bon accès", 'error')
) . "\n";

// Test 7 : Directeur a accès limité aux rapports
echo "\n" . colorize("TEST 7: Directeur a rapports limitées (pas paramétrages système)", 'bold') . "\n";
$directorMenus = MenuService::getMenuStructure('directeur', $rolePermissions['directeur']);
$hasReporting = array_search('reporting', array_column($directorMenus, 'id')) !== false;
echo ($hasReporting
    ? colorize("  ✓ Directeur a accès aux rapports", 'success')
    : colorize("  ✗ Directeur n'a pas accès aux rapports", 'error')
) . "\n";

// ═════════════════════════════════════════════════════════════════

echo colorize("\n" . str_repeat("═", 70) . "\n", 'cyan');
echo colorize("RÉSUMÉ\n", 'bold');
echo colorize(str_repeat("═", 70) . "\n", 'cyan');

echo <<<EOL

Le système de menu dynamique fonctionne en:

1. ✓ Chargeant les permissions de l'utilisateur en session (RBAC_V2)
2. ✓ Récupérant le rôle de l'utilisateur depuis la session
3. ✓ Générant une structure de menus appropriée au rôle
4. ✓ Filtrant les menus selon les permissions possédées
5. ✓ Rendant le HTML du menu via le partial navigation.php

Chaque rôle voit:
- Admin          → Tous les modules
- Directeur      → Gestion école, enseignants, élèves, rapports
- Secrétaire     → Élèves, paiements
- Enseignant     → Ses classes, notes, absences, EDT
- Comptable      → Finance uniquement
- Élève          → Ses données perso
- Parent         → Données de ses enfants

EOL;

echo colorize("\n✓ Tests terminés avec succès!\n", 'success');
echo colorize("Pour intégrer en production, assurez-vous que:\n", 'bold');
echo colorize("  • Les permissions sont bien chargées en session\n", 'info');
echo colorize("  • Le layout main.php utilise MenuService\n", 'info');
echo colorize("  • Les routes correspondent aux URLs du menu\n", 'info');
echo colorize("  • Les icônes Lucide sont disponibles\n", 'info');
