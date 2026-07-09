<?php
declare(strict_types=1);
/**
 * Menu portail Administration
 * Chaque item : id, label, icon, url, perm (optionnel), children (optionnel), sep (bool)
 */
return [
    [
        'id'    => 'dashboard',
        'label' => 'Tableau de bord',
        'icon'  => 'layout-dashboard',
        'url'   => '/v2/portals/admin',
    ],
    [
        'id'    => 'utilisateurs',
        'label' => 'Utilisateurs',
        'icon'  => 'users',
        'url'   => '/utilisateurs',
        'perm'  => 'users.view',
    ],
    [
        'id'    => 'scolarite',
        'label' => 'Scolarité',
        'icon'  => 'book-open',
        'url'   => '#',
        'perm'  => 'eleves.view',
        'children' => [
            ['id' => 'sc_eleves',   'label' => 'Élèves',    'icon' => 'user',       'url' => '/eleves',   'perm' => 'eleves.view'],
            ['id' => 'sc_classes',  'label' => 'Classes',   'icon' => 'layers',     'url' => '/classes',  'perm' => 'classes.view'],
            ['id' => 'sc_matieres', 'label' => 'Matières',  'icon' => 'book',       'url' => '/matieres', 'perm' => 'matieres.view'],
        ],
    ],
    [
        'id'    => 'finance',
        'label' => 'Finance',
        'icon'  => 'dollar-sign',
        'url'   => '#',
        'perm'  => 'finance.dashboard',
        'children' => [
            ['id' => 'fin_factures',    'label' => 'Factures',    'icon' => 'file-text',   'url' => '/v2/finance/factures',    'perm' => 'finance.factures.view'],
            ['id' => 'fin_paiements',   'label' => 'Paiements',   'icon' => 'credit-card', 'url' => '/v2/finance/paiements',   'perm' => 'finance.paiements.view'],
            ['id' => 'fin_caisse',      'label' => 'Caisse',      'icon' => 'database',    'url' => '/v2/finance/caisse',      'perm' => 'finance.caisse.view'],
        ],
    ],
    [
        'id'    => 'rh',
        'label' => 'Ressources humaines',
        'icon'  => 'briefcase',
        'url'   => '#',
        'perm'  => 'employee.view',
        'children' => [
            ['id' => 'rh_employes',  'label' => 'Employés',   'icon' => 'user',       'url' => '/v2/rh/employes',  'perm' => 'employee.view'],
            ['id' => 'rh_contrats',  'label' => 'Contrats',   'icon' => 'file-text',  'url' => '/v2/rh/contrats',  'perm' => 'contract.view'],
            ['id' => 'rh_conges',    'label' => 'Congés',     'icon' => 'calendar',   'url' => '/v2/rh/conges',    'perm' => 'leave.view'],
        ],
    ],
    [
        'id'    => 'audit',
        'label' => 'Journal d\'audit',
        'icon'  => 'list',
        'url'   => '/v2/portals/admin/audit',
        'perm'  => 'audit.view',
        'sep'   => true,
    ],
    [
        'id'    => 'parametres',
        'label' => 'Paramètres',
        'icon'  => 'settings',
        'url'   => '/v2/portals/admin/parametres',
        'perm'  => 'parametres.manage',
    ],
];
