<?php

/**
 * Routes du module Académique V2.
 *
 * ATTENTION — Ce fichier n'est chargé QUE si le module est activé
 * dans config/modules.php ('enabled' => true).
 *
 * Préfixe : /v2/academique
 * Namespace contrôleurs : App\Modules\Academique\Controllers\
 *
 * Ces routes coexistent avec les routes V1 (/notes, /bulletins).
 * La V1 reste active jusqu'à la fin de la migration.
 */

use Core\Router;

/** @var Router $router */

// ─── Périodes Scolaires V2 ───────────────────────────────────────────────────
$router->get('/v2/academique/periodes',                         'Academique\Controllers\PeriodeController@index');
$router->get('/v2/academique/periodes/create',                  'Academique\Controllers\PeriodeController@create');
$router->post('/v2/academique/periodes',                        'Academique\Controllers\PeriodeController@store');
$router->get('/v2/academique/periodes/{id}',                    'Academique\Controllers\PeriodeController@show');
$router->get('/v2/academique/periodes/{id}/edit',               'Academique\Controllers\PeriodeController@edit');
$router->post('/v2/academique/periodes/{id}',                   'Academique\Controllers\PeriodeController@update');
$router->post('/v2/academique/periodes/{id}/activer',           'Academique\Controllers\PeriodeController@activer');
$router->post('/v2/academique/periodes/{id}/fermer',            'Academique\Controllers\PeriodeController@fermer');
$router->post('/v2/academique/periodes/{id}/verrouiller',       'Academique\Controllers\PeriodeController@verrouiller');
$router->post('/v2/academique/periodes/{id}/deverrouiller',     'Academique\Controllers\PeriodeController@deverrouiller');
$router->post('/v2/academique/periodes/{id}/archiver',          'Academique\Controllers\PeriodeController@archiver');

// ─── Types d'évaluations V2 ──────────────────────────────────────────────────
$router->get('/v2/academique/types-evaluations',                         'Academique\Controllers\TypeEvaluationController@index');
$router->get('/v2/academique/types-evaluations/create',                  'Academique\Controllers\TypeEvaluationController@create');
$router->post('/v2/academique/types-evaluations',                        'Academique\Controllers\TypeEvaluationController@store');
$router->get('/v2/academique/types-evaluations/{id}',                    'Academique\Controllers\TypeEvaluationController@show');
$router->get('/v2/academique/types-evaluations/{id}/edit',               'Academique\Controllers\TypeEvaluationController@edit');
$router->post('/v2/academique/types-evaluations/{id}',                   'Academique\Controllers\TypeEvaluationController@update');
$router->post('/v2/academique/types-evaluations/{id}/activer',           'Academique\Controllers\TypeEvaluationController@activer');
$router->post('/v2/academique/types-evaluations/{id}/desactiver',        'Academique\Controllers\TypeEvaluationController@desactiver');
$router->post('/v2/academique/types-evaluations/{id}/archiver',          'Academique\Controllers\TypeEvaluationController@archiver');

// ─── Évaluations V2 ──────────────────────────────────────────────────────────
$router->get('/v2/academique/evaluations',                          'Academique\Controllers\EvaluationController@index');
$router->get('/v2/academique/evaluations/create',                   'Academique\Controllers\EvaluationController@create');
$router->post('/v2/academique/evaluations',                         'Academique\Controllers\EvaluationController@store');
$router->get('/v2/academique/evaluations/{id}',                     'Academique\Controllers\EvaluationController@show');
$router->get('/v2/academique/evaluations/{id}/edit',                'Academique\Controllers\EvaluationController@edit');
$router->post('/v2/academique/evaluations/{id}',                    'Academique\Controllers\EvaluationController@update');
$router->post('/v2/academique/evaluations/{id}/publier',            'Academique\Controllers\EvaluationController@publier');
$router->post('/v2/academique/evaluations/{id}/verrouiller',        'Academique\Controllers\EvaluationController@verrouiller');
$router->post('/v2/academique/evaluations/{id}/deverrouiller',      'Academique\Controllers\EvaluationController@deverrouiller');
$router->post('/v2/academique/evaluations/{id}/archiver',           'Academique\Controllers\EvaluationController@archiver');

// ─── Notes V2 ────────────────────────────────────────────────────────────────
$router->get('/v2/academique/evaluations/{id}/notes',               'Academique\Controllers\NoteController@index');
$router->get('/v2/academique/evaluations/{id}/notes/saisie',        'Academique\Controllers\NoteController@saisie');
$router->post('/v2/academique/evaluations/{id}/notes',              'Academique\Controllers\NoteController@store');
$router->post('/v2/academique/evaluations/{id}/notes/publier',      'Academique\Controllers\NoteController@publier');
$router->post('/v2/academique/evaluations/{id}/notes/verrouiller',  'Academique\Controllers\NoteController@verrouiller');
$router->get('/v2/academique/evaluations/{id}/notes/importer',      'Academique\Controllers\NoteController@importerForm');
$router->post('/v2/academique/evaluations/{id}/notes/importer',     'Academique\Controllers\NoteController@importerCsv');
$router->get('/v2/academique/notes/{id}',                           'Academique\Controllers\NoteController@show');
$router->post('/v2/academique/notes/{id}',                          'Academique\Controllers\NoteController@update');
