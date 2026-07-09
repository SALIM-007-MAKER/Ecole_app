<?php
declare(strict_types=1);
/**
 * Menu portail Enseignant
 */
return [
    [
        'id'    => 'dashboard',
        'label' => 'Tableau de bord',
        'icon'  => 'layout-dashboard',
        'url'   => '/v2/portals/enseignant',
    ],
    [
        'id'    => 'mes_classes',
        'label' => 'Mes classes',
        'icon'  => 'book-open',
        'url'   => '/v2/portals/enseignant/mes-classes',
        'perm'  => 'academique.notes.manage',
    ],
    [
        'id'    => 'evaluations',
        'label' => 'Évaluations',
        'icon'  => 'edit',
        'url'   => '#',
        'perm'  => 'academique.notes.manage',
        'children' => [
            ['id' => 'ev_notes',    'label' => 'Saisie des notes',    'icon' => 'edit',         'url' => '/v2/portals/enseignant/notes',       'perm' => 'academique.notes.manage'],
            ['id' => 'ev_controls', 'label' => 'Contrôles',           'icon' => 'clipboard',    'url' => '/v2/portals/enseignant/evaluations', 'perm' => 'academique.evaluations.manage'],
            ['id' => 'ev_bulletins','label' => 'Bulletins',           'icon' => 'file-text',    'url' => '/v2/portals/enseignant/bulletins',   'perm' => 'academique.bulletin.view'],
        ],
    ],
    [
        'id'    => 'appel',
        'label' => 'Appel / Présences',
        'icon'  => 'user-check',
        'url'   => '#',
        'perm'  => 'attendance.session.create',
        'children' => [
            ['id' => 'appel_faire',    'label' => 'Faire l\'appel',    'icon' => 'check-square', 'url' => '/v2/portals/enseignant/appel',     'perm' => 'attendance.session.create'],
            ['id' => 'appel_absences', 'label' => 'Absences classe',   'icon' => 'user-x',       'url' => '/v2/portals/enseignant/absences',  'perm' => 'attendance.session.validate'],
        ],
    ],
    [
        'id'    => 'emploi_du_temps',
        'label' => 'Emploi du temps',
        'icon'  => 'calendar',
        'url'   => '/v2/portals/enseignant/emploi-du-temps',
        'perm'  => 'timetable.view',
    ],
    [
        'id'    => 'documents',
        'label' => 'Documents',
        'icon'  => 'folder',
        'url'   => '/v2/portals/enseignant/documents',
        'perm'  => 'document.view',
        'sep'   => true,
    ],
    [
        'id'    => 'messagerie',
        'label' => 'Messagerie',
        'icon'  => 'mail',
        'url'   => '/v2/portals/enseignant/messagerie',
        'perm'  => 'communication.view',
    ],
];
