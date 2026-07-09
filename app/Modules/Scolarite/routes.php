<?php

/**
 * Routes du module Scolarité V2.
 *
 * ATTENTION — Ce fichier n'est chargé QUE si le module est activé
 * dans config/modules.php ('enabled' => true).
 *
 * Préfixe : /v2/scolarite
 * Namespace contrôleurs : App\Modules\Scolarite\Controllers\
 *
 * Ces routes coexistent avec les routes V1 (/eleves, /classes, /matieres).
 * La V1 reste active jusqu'à la fin de la migration.
 */

use Core\Router;

/** @var Router $router */

// ─── Élèves V2 ───────────────────────────────────────────────────────────────
$router->get('/v2/scolarite/eleves',                    'Scolarite\Controllers\EleveController@index');
$router->get('/v2/scolarite/eleves/create',             'Scolarite\Controllers\EleveController@create');
$router->post('/v2/scolarite/eleves',                   'Scolarite\Controllers\EleveController@store');
$router->get('/v2/scolarite/eleves/import',             'Scolarite\Controllers\EleveController@showImport');
$router->post('/v2/scolarite/eleves/import',            'Scolarite\Controllers\EleveController@import');
$router->get('/v2/scolarite/eleves/export/pdf',         'Scolarite\Controllers\EleveController@exportPdf');
$router->get('/v2/scolarite/eleves/export/excel',       'Scolarite\Controllers\EleveController@exportExcel');
$router->get('/v2/scolarite/eleves/{id}',               'Scolarite\Controllers\EleveController@show');
$router->get('/v2/scolarite/eleves/{id}/edit',          'Scolarite\Controllers\EleveController@edit');
$router->post('/v2/scolarite/eleves/{id}',              'Scolarite\Controllers\EleveController@update');
$router->post('/v2/scolarite/eleves/{id}/delete',       'Scolarite\Controllers\EleveController@destroy');

// ─── Classes V2 ──────────────────────────────────────────────────────────────
$router->get('/v2/scolarite/classes',                         'Scolarite\Controllers\ClasseController@index');
$router->get('/v2/scolarite/classes/create',                  'Scolarite\Controllers\ClasseController@create');
$router->post('/v2/scolarite/classes',                        'Scolarite\Controllers\ClasseController@store');
$router->get('/v2/scolarite/classes/{id}',                    'Scolarite\Controllers\ClasseController@show');
$router->get('/v2/scolarite/classes/{id}/edit',               'Scolarite\Controllers\ClasseController@edit');
$router->post('/v2/scolarite/classes/{id}',                   'Scolarite\Controllers\ClasseController@update');
$router->post('/v2/scolarite/classes/{id}/affecter-eleve',     'Scolarite\Controllers\ClasseController@affecterEleve');
$router->post('/v2/scolarite/classes/{id}/retirer-eleve',      'Scolarite\Controllers\ClasseController@retirerEleve');
$router->post('/v2/scolarite/classes/{id}/affecter-enseignant','Scolarite\Controllers\ClasseController@affecterEnseignant');
$router->post('/v2/scolarite/classes/{id}/retirer-enseignant', 'Scolarite\Controllers\ClasseController@retirerEnseignant');
$router->post('/v2/scolarite/classes/{id}/delete',             'Scolarite\Controllers\ClasseController@delete');

// ─── Inscriptions V2 ─────────────────────────────────────────────────────────
$router->get('/v2/scolarite/inscriptions',               'Scolarite\Controllers\InscriptionController@index');
$router->get('/v2/scolarite/inscriptions/create',        'Scolarite\Controllers\InscriptionController@create');
$router->post('/v2/scolarite/inscriptions',              'Scolarite\Controllers\InscriptionController@store');
$router->get('/v2/scolarite/inscriptions/{id}',                'Scolarite\Controllers\InscriptionController@show');
$router->get('/v2/scolarite/inscriptions/{id}/edit',           'Scolarite\Controllers\InscriptionController@edit');
$router->post('/v2/scolarite/inscriptions/{id}',               'Scolarite\Controllers\InscriptionController@update');
$router->post('/v2/scolarite/inscriptions/{id}/valider',       'Scolarite\Controllers\InscriptionController@valider');
$router->post('/v2/scolarite/inscriptions/{id}/rejeter',       'Scolarite\Controllers\InscriptionController@rejeter');
$router->post('/v2/scolarite/inscriptions/{id}/annuler',       'Scolarite\Controllers\InscriptionController@annuler');
$router->post('/v2/scolarite/inscriptions/{id}/reinscrire',    'Scolarite\Controllers\InscriptionController@reinscrire');
$router->post('/v2/scolarite/inscriptions/{id}/changer-classe','Scolarite\Controllers\InscriptionController@changerClasse');

// ─── Familles V2 ─────────────────────────────────────────────────────────────
$router->get('/v2/scolarite/familles',                       'Scolarite\Controllers\FamilleController@index');
$router->get('/v2/scolarite/familles/create',                'Scolarite\Controllers\FamilleController@create');
$router->post('/v2/scolarite/familles',                      'Scolarite\Controllers\FamilleController@store');
$router->get('/v2/scolarite/familles/{id}',                  'Scolarite\Controllers\FamilleController@show');
$router->get('/v2/scolarite/familles/{id}/edit',             'Scolarite\Controllers\FamilleController@edit');
$router->post('/v2/scolarite/familles/{id}',                 'Scolarite\Controllers\FamilleController@update');
$router->post('/v2/scolarite/familles/{id}/rattacher-eleve', 'Scolarite\Controllers\FamilleController@rattacherEleve');
$router->post('/v2/scolarite/familles/{id}/detacher-eleve',  'Scolarite\Controllers\FamilleController@detacherEleve');
$router->post('/v2/scolarite/familles/{id}/archiver',        'Scolarite\Controllers\FamilleController@archiver');

// ─── Matières V2 ─────────────────────────────────────────────────────────────
$router->get('/v2/scolarite/matieres',                'Scolarite\Controllers\MatiereController@index');
$router->get('/v2/scolarite/matieres/create',         'Scolarite\Controllers\MatiereController@create');
$router->post('/v2/scolarite/matieres',               'Scolarite\Controllers\MatiereController@store');
$router->get('/v2/scolarite/matieres/{id}',           'Scolarite\Controllers\MatiereController@show');
$router->get('/v2/scolarite/matieres/{id}/edit',      'Scolarite\Controllers\MatiereController@edit');
$router->post('/v2/scolarite/matieres/{id}',          'Scolarite\Controllers\MatiereController@update');
$router->post('/v2/scolarite/matieres/{id}/archiver', 'Scolarite\Controllers\MatiereController@archiver');
$router->post('/v2/scolarite/matieres/{id}/delete',   'Scolarite\Controllers\MatiereController@destroy');
