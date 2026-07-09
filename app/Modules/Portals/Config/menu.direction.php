<?php
declare(strict_types=1);
return [
    ['id' => 'dashboard',    'label' => 'Tableau de bord',   'icon' => 'layout-dashboard', 'url' => '/v2/portals/direction'],
    ['id' => 'scolarite',    'label' => 'Scolarité',         'icon' => 'book-open',        'url' => '/v2/portals/direction/scolarite',  'perm' => 'eleves.view'],
    ['id' => 'academique',   'label' => 'Résultats',         'icon' => 'bar-chart-2',      'url' => '/v2/portals/direction/academique', 'perm' => 'academique.bulletin.view'],
    ['id' => 'finance',      'label' => 'Finance',           'icon' => 'dollar-sign',      'url' => '/v2/portals/direction/finance',    'perm' => 'finance.dashboard'],
    ['id' => 'vie_scolaire', 'label' => 'Vie scolaire',      'icon' => 'activity',         'url' => '/v2/portals/direction/vie-scolaire', 'perm' => 'attendance.view'],
    ['id' => 'rh',           'label' => 'RH',                'icon' => 'briefcase',        'url' => '/v2/portals/direction/rh',         'perm' => 'employee.view', 'sep' => true],
    ['id' => 'rapports',     'label' => 'Rapports & BI',     'icon' => 'pie-chart',        'url' => '/v2/portals/direction/rapports',   'perm' => 'rapports.dashboard.direction'],
];
