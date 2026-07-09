<?php
declare(strict_types=1);
return [
    ['id' => 'dashboard',   'label' => 'Accueil',           'icon' => 'home',           'url' => '/v2/portals/parent'],
    ['id' => 'enfants',     'label' => 'Mes enfants',       'icon' => 'users',          'url' => '/v2/portals/parent/enfants'],
    ['id' => 'notes',       'label' => 'Notes & bulletins', 'icon' => 'file-text',      'url' => '/v2/portals/parent/notes',       'perm' => 'notes.view_own'],
    ['id' => 'absences',    'label' => 'Absences',          'icon' => 'user-x',         'url' => '/v2/portals/parent/absences',    'perm' => 'absences.view_own'],
    ['id' => 'paiements',   'label' => 'Paiements',         'icon' => 'credit-card',    'url' => '/v2/portals/parent/paiements',   'perm' => 'comptabilite.view_own', 'sep' => true],
    ['id' => 'messagerie',  'label' => 'Messagerie',        'icon' => 'mail',           'url' => '/v2/portals/parent/messagerie',  'perm' => 'communication.view'],
];
