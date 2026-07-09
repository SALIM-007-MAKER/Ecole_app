<?php
declare(strict_types=1);
return [
    ['id' => 'dashboard',   'label' => 'Tableau de bord',   'icon' => 'layout-dashboard', 'url' => '/v2/portals/rh'],
    ['id' => 'employes',    'label' => 'Employés',          'icon' => 'users',            'url' => '/v2/rh/employes',     'perm' => 'employee.view'],
    ['id' => 'contrats',    'label' => 'Contrats',          'icon' => 'file-text',        'url' => '/v2/rh/contrats',     'perm' => 'contract.view'],
    ['id' => 'presences_rh','label' => 'Présences',         'icon' => 'user-check',       'url' => '/v2/rh/presences',    'perm' => 'rh.presence.view', 'sep' => true],
    ['id' => 'conges',      'label' => 'Congés',            'icon' => 'calendar',         'url' => '/v2/rh/conges',       'perm' => 'leave.view'],
    ['id' => 'evaluations', 'label' => 'Évaluations RH',   'icon' => 'star',             'url' => '/v2/rh/evaluations',  'perm' => 'evaluation.view'],
    ['id' => 'formations',  'label' => 'Formations',        'icon' => 'book-open',        'url' => '/v2/rh/formations',   'perm' => 'training.view'],
    ['id' => 'documents_rh','label' => 'Documents RH',     'icon' => 'folder',           'url' => '/v2/rh/documents',    'perm' => 'hr_document.view'],
];
