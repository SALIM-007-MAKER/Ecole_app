<?php
declare(strict_types=1);

/**
 * Routes du module Portails V2
 * Utilise $router (instance Core\Router) — jamais Router::get() statique.
 * Handler format: 'Portals\Controllers\{Class}@{method}'
 * → résolu en App\Modules\Portals\Controllers\{Class}
 */

// ─────────────────────────────────────────────────────────────
// Préférences portail (toutes portails via {portal})
// ─────────────────────────────────────────────────────────────

$router->get('/v2/portals/{portal}/preferences',
    'Portals\Controllers\PreferencesController@show');

$router->post('/v2/portals/{portal}/preferences',
    'Portals\Controllers\PreferencesController@save');

$router->get('/v2/portals/{portal}/preferences/widget-layout',
    'Portals\Controllers\PreferencesController@getWidgetLayout');

$router->post('/v2/portals/{portal}/preferences/widget-layout',
    'Portals\Controllers\PreferencesController@saveWidgetLayout');

$router->post('/v2/portals/{portal}/preferences/widget/{id}/toggle',
    'Portals\Controllers\PreferencesController@toggleWidget');

$router->post('/v2/portals/{portal}/preferences/reset',
    'Portals\Controllers\PreferencesController@reset');

// Raccourcis
$router->get('/v2/portals/{portal}/raccourcis',
    'Portals\Controllers\PreferencesController@getShortcuts');

$router->post('/v2/portals/{portal}/raccourcis',
    'Portals\Controllers\PreferencesController@saveShortcuts');

// ─────────────────────────────────────────────────────────────
// API JSON (Bearer token + session fallback)
// ─────────────────────────────────────────────────────────────

// Auth / Token API
$router->post('/api/v2/portals/auth/token',
    'Portals\Controllers\Api\AuthTokenController@issue');

$router->post('/api/v2/portals/auth/revoke',
    'Portals\Controllers\Api\AuthTokenController@revoke');

$router->post('/api/v2/portals/auth/revoke-all',
    'Portals\Controllers\Api\AuthTokenController@revokeAll');

// Dashboard API
$router->get('/api/v2/portals/{portal}/dashboard',
    'Portals\Controllers\Api\PortalApiController@dashboard');

// Widget refresh API
$router->get('/api/v2/portals/{portal}/widgets/{id}',
    'Portals\Controllers\Api\PortalApiController@widget');

// Recherche API
$router->get('/api/v2/portals/{portal}/search',
    'Portals\Controllers\Api\PortalApiController@search');

// Notifications API
$router->get('/api/v2/portals/{portal}/notifications',
    'Portals\Controllers\Api\PortalApiController@notifications');

$router->post('/api/v2/portals/{portal}/notifications/{id}/read',
    'Portals\Controllers\Api\PortalApiController@markNotificationRead');

$router->post('/api/v2/portals/{portal}/notifications/read-all',
    'Portals\Controllers\Api\PortalApiController@markAllRead');

// ─────────────────────────────────────────────────────────────
// Portail ADMIN
// ─────────────────────────────────────────────────────────────

$router->get('/v2/portals/admin',
    'Portals\Admin\Controllers\AdminPortalController@dashboard');

$router->get('/v2/portals/admin/audit',
    'Portals\Admin\Controllers\AdminPortalController@audit');

$router->get('/v2/portals/admin/modules',
    'Portals\Admin\Controllers\AdminPortalController@modules');

$router->get('/v2/portals/admin/parametres',
    'Portals\Admin\Controllers\AdminPortalController@parametres');

// ─────────────────────────────────────────────────────────────
// Portail DIRECTION
// ─────────────────────────────────────────────────────────────

$router->get('/v2/portals/direction',
    'Portals\Direction\Controllers\DirectionPortalController@dashboard');

$router->get('/v2/portals/direction/scolarite',
    'Portals\Direction\Controllers\DirectionPortalController@scolarite');

$router->get('/v2/portals/direction/academique',
    'Portals\Direction\Controllers\DirectionPortalController@academique');

$router->get('/v2/portals/direction/finance',
    'Portals\Direction\Controllers\DirectionPortalController@finance');

$router->get('/v2/portals/direction/vie-scolaire',
    'Portals\Direction\Controllers\DirectionPortalController@vieScolaire');

$router->get('/v2/portals/direction/rh',
    'Portals\Direction\Controllers\DirectionPortalController@rh');

$router->get('/v2/portals/direction/rapports',
    'Portals\Direction\Controllers\DirectionPortalController@rapports');

// ─────────────────────────────────────────────────────────────
// Portail ENSEIGNANT
// ─────────────────────────────────────────────────────────────

$router->get('/v2/portals/enseignant',
    'Portals\Enseignant\Controllers\EnseignantPortalController@dashboard');

$router->get('/v2/portals/enseignant/mes-classes',
    'Portals\Enseignant\Controllers\EnseignantPortalController@mesClasses');

$router->get('/v2/portals/enseignant/notes',
    'Portals\Enseignant\Controllers\EnseignantPortalController@notes');

$router->get('/v2/portals/enseignant/appel',
    'Portals\Enseignant\Controllers\EnseignantPortalController@appel');

$router->get('/v2/portals/enseignant/evaluations',
    'Portals\Enseignant\Controllers\EnseignantPortalController@evaluations');

$router->get('/v2/portals/enseignant/emploi-du-temps',
    'Portals\Enseignant\Controllers\EnseignantPortalController@emploiDuTemps');

$router->get('/v2/portals/enseignant/absences',
    'Portals\Enseignant\Controllers\EnseignantPortalController@absences');

$router->get('/v2/portals/enseignant/bulletins',
    'Portals\Enseignant\Controllers\EnseignantPortalController@bulletins');

$router->get('/v2/portals/enseignant/documents',
    'Portals\Enseignant\Controllers\EnseignantPortalController@documents');

$router->get('/v2/portals/enseignant/messagerie',
    'Portals\Enseignant\Controllers\EnseignantPortalController@messagerie');

// ─────────────────────────────────────────────────────────────
// Portail ÉLÈVE
// ─────────────────────────────────────────────────────────────

$router->get('/v2/portals/eleve',
    'Portals\Eleve\Controllers\ElevePortalController@dashboard');

$router->get('/v2/portals/eleve/notes',
    'Portals\Eleve\Controllers\ElevePortalController@notes');

$router->get('/v2/portals/eleve/bulletins',
    'Portals\Eleve\Controllers\ElevePortalController@bulletins');

$router->get('/v2/portals/eleve/absences',
    'Portals\Eleve\Controllers\ElevePortalController@absences');

$router->get('/v2/portals/eleve/emploi-du-temps',
    'Portals\Eleve\Controllers\ElevePortalController@emploiDuTemps');

$router->get('/v2/portals/eleve/bibliotheque',
    'Portals\Eleve\Controllers\ElevePortalController@bibliotheque');

$router->get('/v2/portals/eleve/messagerie',
    'Portals\Eleve\Controllers\ElevePortalController@messagerie');

$router->get('/v2/portals/eleve/profil',
    'Portals\Eleve\Controllers\ElevePortalController@profil');

// ─────────────────────────────────────────────────────────────
// Portail PARENT
// ─────────────────────────────────────────────────────────────

$router->get('/v2/portals/parent',
    'Portals\Parent\Controllers\ParentPortalController@dashboard');

$router->get('/v2/portals/parent/enfants',
    'Portals\Parent\Controllers\ParentPortalController@enfants');

$router->get('/v2/portals/parent/notes',
    'Portals\Parent\Controllers\ParentPortalController@notes');

$router->get('/v2/portals/parent/absences',
    'Portals\Parent\Controllers\ParentPortalController@absences');

$router->get('/v2/portals/parent/paiements',
    'Portals\Parent\Controllers\ParentPortalController@paiements');

$router->get('/v2/portals/parent/messagerie',
    'Portals\Parent\Controllers\ParentPortalController@messagerie');

$router->get('/v2/portals/parent/documents',
    'Portals\Parent\Controllers\ParentPortalController@documents');

// ─────────────────────────────────────────────────────────────
// Portail COMPTABILITÉ
// ─────────────────────────────────────────────────────────────

$router->get('/v2/portals/comptabilite',
    'Portals\Comptabilite\Controllers\ComptabilitePortalController@dashboard');

$router->get('/v2/portals/comptabilite/factures',
    'Portals\Comptabilite\Controllers\ComptabilitePortalController@factures');

$router->get('/v2/portals/comptabilite/paiements',
    'Portals\Comptabilite\Controllers\ComptabilitePortalController@paiements');

$router->get('/v2/portals/comptabilite/caisse',
    'Portals\Comptabilite\Controllers\ComptabilitePortalController@caisse');

$router->get('/v2/portals/comptabilite/impayes',
    'Portals\Comptabilite\Controllers\ComptabilitePortalController@impayes');

$router->get('/v2/portals/comptabilite/rapports',
    'Portals\Comptabilite\Controllers\ComptabilitePortalController@rapports');

// ─────────────────────────────────────────────────────────────
// Portail RH
// ─────────────────────────────────────────────────────────────

$router->get('/v2/portals/rh',
    'Portals\RH\Controllers\RHPortalController@dashboard');

$router->get('/v2/portals/rh/presences',
    'Portals\RH\Controllers\RHPortalController@presences');

$router->get('/v2/portals/rh/conges',
    'Portals\RH\Controllers\RHPortalController@conges');

$router->get('/v2/portals/rh/contrats',
    'Portals\RH\Controllers\RHPortalController@contrats');

$router->get('/v2/portals/rh/formations',
    'Portals\RH\Controllers\RHPortalController@formations');

$router->get('/v2/portals/rh/evaluations',
    'Portals\RH\Controllers\RHPortalController@evaluations');

$router->get('/v2/portals/rh/documents',
    'Portals\RH\Controllers\RHPortalController@documents');
