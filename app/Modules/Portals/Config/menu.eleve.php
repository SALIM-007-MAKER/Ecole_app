<?php
declare(strict_types=1);
return [
    ['id' => 'dashboard',   'label' => 'Accueil',           'icon' => 'home',           'url' => '/v2/portals/eleve'],
    ['id' => 'notes',       'label' => 'Mes notes',         'icon' => 'bar-chart',      'url' => '/v2/portals/eleve/notes',           'perm' => 'notes.view_own'],
    ['id' => 'bulletins',   'label' => 'Mes bulletins',     'icon' => 'file-text',      'url' => '/v2/portals/eleve/bulletins',       'perm' => 'bulletins.view'],
    ['id' => 'absences',    'label' => 'Mes absences',      'icon' => 'user-x',         'url' => '/v2/portals/eleve/absences',        'perm' => 'absences.view_own'],
    ['id' => 'edt',         'label' => 'Emploi du temps',   'icon' => 'calendar',       'url' => '/v2/portals/eleve/emploi-du-temps', 'perm' => 'emploi_du_temps.view_own'],
    ['id' => 'biblio',      'label' => 'Bibliothèque',      'icon' => 'book',           'url' => '/v2/portals/eleve/bibliotheque',    'perm' => 'biblio.view', 'sep' => true],
    ['id' => 'messagerie',  'label' => 'Messagerie',        'icon' => 'mail',           'url' => '/v2/portals/eleve/messagerie',      'perm' => 'communication.view'],
    ['id' => 'profil',      'label' => 'Mon profil',        'icon' => 'user',           'url' => '/v2/portals/eleve/profil'],
];
