<?php

/**
 * Routes du module Vie Scolaire V2.
 *
 * Ce fichier n'est chargé QUE si le module est activé dans config/modules.php.
 * Préfixe  : /v2/vie-scolaire
 * Domaines : Absences, Présences, Retards
 *
 * /absences V1 (App\Controllers\AbsenceController) décommissionnée le
 * 20/08/2026 — ce module est désormais l'unique système d'absences (voir
 * CHANGELOG.md). /presences n'a jamais eu d'équivalent V1 : la V1 ne
 * suivait que les absences, pas les sessions de présence.
 */

use Core\Router;

/** @var Router $router */

// ── Absences ─────────────────────────────────────────────────────────────────

$router->get('/v2/vie-scolaire/absences',
    'VieScolaire\Absences\Controllers\AbsenceController@index');

$router->get('/v2/vie-scolaire/absences/statistiques',
    'VieScolaire\Absences\Controllers\AbsenceController@statistiques');

$router->get('/v2/vie-scolaire/absences/pointage',
    'VieScolaire\Absences\Controllers\AbsenceController@pointage');

$router->post('/v2/vie-scolaire/absences/pointage',
    'VieScolaire\Absences\Controllers\AbsenceController@storePointage');

$router->get('/v2/vie-scolaire/absences/create',
    'VieScolaire\Absences\Controllers\AbsenceController@create');

$router->post('/v2/vie-scolaire/absences',
    'VieScolaire\Absences\Controllers\AbsenceController@store');

$router->get('/v2/vie-scolaire/absences/{id}',
    'VieScolaire\Absences\Controllers\AbsenceController@show');

$router->get('/v2/vie-scolaire/absences/{id}/edit',
    'VieScolaire\Absences\Controllers\AbsenceController@edit');

$router->post('/v2/vie-scolaire/absences/{id}/justifier',
    'VieScolaire\Absences\Controllers\AbsenceController@storeJustification');

$router->get('/v2/vie-scolaire/absences/{id}/justifier',
    'VieScolaire\Absences\Controllers\AbsenceController@justifier');

$router->post('/v2/vie-scolaire/absences/{id}/valider',
    'VieScolaire\Absences\Controllers\AbsenceController@valider');

$router->post('/v2/vie-scolaire/absences/{id}/refuser',
    'VieScolaire\Absences\Controllers\AbsenceController@refuser');

$router->post('/v2/vie-scolaire/absences/{id}/delete',
    'VieScolaire\Absences\Controllers\AbsenceController@destroy');

// ── Présences / Appel ────────────────────────────────────────────────────────

$router->get('/v2/vie-scolaire/presences',
    'VieScolaire\Presences\Controllers\PresenceController@index');

$router->get('/v2/vie-scolaire/presences/statistiques',
    'VieScolaire\Presences\Controllers\PresenceController@statistiques');

$router->get('/v2/vie-scolaire/presences/create',
    'VieScolaire\Presences\Controllers\PresenceController@create');

$router->post('/v2/vie-scolaire/presences',
    'VieScolaire\Presences\Controllers\PresenceController@store');

$router->get('/v2/vie-scolaire/presences/{id}',
    'VieScolaire\Presences\Controllers\PresenceController@show');

$router->post('/v2/vie-scolaire/presences/{id}/pointer',
    'VieScolaire\Presences\Controllers\PresenceController@pointer');

$router->get('/v2/vie-scolaire/presences/{id}/valider',
    'VieScolaire\Presences\Controllers\PresenceController@valider');

$router->post('/v2/vie-scolaire/presences/{id}/valider',
    'VieScolaire\Presences\Controllers\PresenceController@confirmerValidation');

$router->get('/v2/vie-scolaire/presences/{id}/historique',
    'VieScolaire\Presences\Controllers\PresenceController@historique');

$router->post('/v2/vie-scolaire/presences/{id}/delete',
    'VieScolaire\Presences\Controllers\PresenceController@destroy');

// ── Retards ──────────────────────────────────────────────────────────────────

$router->get('/v2/vie-scolaire/retards',
    'VieScolaire\Retards\Controllers\LateController@index');

$router->get('/v2/vie-scolaire/retards/statistiques',
    'VieScolaire\Retards\Controllers\LateController@statistiques');

$router->get('/v2/vie-scolaire/retards/export',
    'VieScolaire\Retards\Controllers\LateController@export');

$router->get('/v2/vie-scolaire/retards/create',
    'VieScolaire\Retards\Controllers\LateController@create');

$router->post('/v2/vie-scolaire/retards',
    'VieScolaire\Retards\Controllers\LateController@store');

$router->get('/v2/vie-scolaire/retards/{id}',
    'VieScolaire\Retards\Controllers\LateController@show');

$router->get('/v2/vie-scolaire/retards/{id}/edit',
    'VieScolaire\Retards\Controllers\LateController@edit');

$router->post('/v2/vie-scolaire/retards/{id}/update',
    'VieScolaire\Retards\Controllers\LateController@update');

$router->get('/v2/vie-scolaire/retards/{id}/justifier',
    'VieScolaire\Retards\Controllers\LateController@justifier');

$router->post('/v2/vie-scolaire/retards/{id}/justifier',
    'VieScolaire\Retards\Controllers\LateController@storeJustification');

$router->post('/v2/vie-scolaire/retards/{id}/valider',
    'VieScolaire\Retards\Controllers\LateController@valider');

$router->post('/v2/vie-scolaire/retards/{id}/refuser',
    'VieScolaire\Retards\Controllers\LateController@refuser');

$router->post('/v2/vie-scolaire/retards/{id}/delete',
    'VieScolaire\Retards\Controllers\LateController@destroy');

// ── Discipline ────────────────────────────────────────────────────────────────

$router->get('/v2/vie-scolaire/discipline',
    'VieScolaire\Discipline\Controllers\DisciplineController@index');

$router->get('/v2/vie-scolaire/discipline/statistiques',
    'VieScolaire\Discipline\Controllers\DisciplineController@statistiques');

$router->get('/v2/vie-scolaire/discipline/export',
    'VieScolaire\Discipline\Controllers\DisciplineController@export');

$router->get('/v2/vie-scolaire/discipline/create',
    'VieScolaire\Discipline\Controllers\DisciplineController@create');

$router->post('/v2/vie-scolaire/discipline',
    'VieScolaire\Discipline\Controllers\DisciplineController@store');

$router->post('/v2/vie-scolaire/discipline/incidents/{id}/traiter',
    'VieScolaire\Discipline\Controllers\DisciplineController@traiterIncident');

$router->post('/v2/vie-scolaire/discipline/sanctions/{id}/valider',
    'VieScolaire\Discipline\Controllers\DisciplineController@validerSanction');

$router->post('/v2/vie-scolaire/discipline/sanctions/{id}/lever',
    'VieScolaire\Discipline\Controllers\DisciplineController@leverSanction');

$router->get('/v2/vie-scolaire/discipline/sanctions/{id}/appel',
    'VieScolaire\Discipline\Controllers\DisciplineController@appelView');

$router->post('/v2/vie-scolaire/discipline/sanctions/{id}/appel',
    'VieScolaire\Discipline\Controllers\DisciplineController@soumettreAppel');

$router->post('/v2/vie-scolaire/discipline/appels/{id}/traiter',
    'VieScolaire\Discipline\Controllers\DisciplineController@traiterAppel');

$router->get('/v2/vie-scolaire/discipline/{id}',
    'VieScolaire\Discipline\Controllers\DisciplineController@show');

$router->get('/v2/vie-scolaire/discipline/{id}/sanctionner',
    'VieScolaire\Discipline\Controllers\DisciplineController@sanctionner');

$router->post('/v2/vie-scolaire/discipline/{id}/sanctionner',
    'VieScolaire\Discipline\Controllers\DisciplineController@storeSanction');

$router->post('/v2/vie-scolaire/discipline/{id}/cloturer',
    'VieScolaire\Discipline\Controllers\DisciplineController@cloturer');

// ── Récompenses ───────────────────────────────────────────────────────────────

$router->get('/v2/vie-scolaire/recompenses',
    'VieScolaire\Recompenses\Controllers\RewardController@index');

$router->get('/v2/vie-scolaire/recompenses/statistiques',
    'VieScolaire\Recompenses\Controllers\RewardController@statistiques');

$router->get('/v2/vie-scolaire/recompenses/export',
    'VieScolaire\Recompenses\Controllers\RewardController@export');

$router->get('/v2/vie-scolaire/recompenses/classement',
    'VieScolaire\Recompenses\Controllers\RewardController@classement');

$router->get('/v2/vie-scolaire/recompenses/create',
    'VieScolaire\Recompenses\Controllers\RewardController@create');

$router->post('/v2/vie-scolaire/recompenses',
    'VieScolaire\Recompenses\Controllers\RewardController@store');

$router->get('/v2/vie-scolaire/recompenses/{id}',
    'VieScolaire\Recompenses\Controllers\RewardController@show');

$router->get('/v2/vie-scolaire/recompenses/{id}/edit',
    'VieScolaire\Recompenses\Controllers\RewardController@edit');

$router->post('/v2/vie-scolaire/recompenses/{id}/update',
    'VieScolaire\Recompenses\Controllers\RewardController@update');

$router->post('/v2/vie-scolaire/recompenses/{id}/valider',
    'VieScolaire\Recompenses\Controllers\RewardController@valider');

$router->post('/v2/vie-scolaire/recompenses/{id}/revoquer',
    'VieScolaire\Recompenses\Controllers\RewardController@revoquer');

// ── Emplois du temps ──────────────────────────────────────────────────────────
// Règle routeur : statiques AVANT les wildcards {id}

$router->get('/v2/vie-scolaire/emplois-du-temps',
    'VieScolaire\EmploisDuTemps\Controllers\TimetableController@index');

$router->get('/v2/vie-scolaire/emplois-du-temps/statistiques',
    'VieScolaire\EmploisDuTemps\Controllers\TimetableController@statistiques');

$router->get('/v2/vie-scolaire/emplois-du-temps/export',
    'VieScolaire\EmploisDuTemps\Controllers\TimetableController@export');

$router->get('/v2/vie-scolaire/emplois-du-temps/enseignant',
    'VieScolaire\EmploisDuTemps\Controllers\TimetableController@enseignant');

$router->get('/v2/vie-scolaire/emplois-du-temps/remplacements',
    'VieScolaire\EmploisDuTemps\Controllers\TimetableController@remplacements');

$router->post('/v2/vie-scolaire/emplois-du-temps/remplacements',
    'VieScolaire\EmploisDuTemps\Controllers\TimetableController@storeRemplacement');

// Référentiel — salles et plages horaires (statiques, avant /create et {id})
$router->get('/v2/vie-scolaire/emplois-du-temps/salles',
    'VieScolaire\EmploisDuTemps\Controllers\SalleController@index');
$router->post('/v2/vie-scolaire/emplois-du-temps/salles',
    'VieScolaire\EmploisDuTemps\Controllers\SalleController@store');
$router->post('/v2/vie-scolaire/emplois-du-temps/salles/{id}',
    'VieScolaire\EmploisDuTemps\Controllers\SalleController@update');
$router->post('/v2/vie-scolaire/emplois-du-temps/salles/{id}/toggle',
    'VieScolaire\EmploisDuTemps\Controllers\SalleController@toggle');

$router->get('/v2/vie-scolaire/emplois-du-temps/plages',
    'VieScolaire\EmploisDuTemps\Controllers\PlageHoraireController@index');
$router->post('/v2/vie-scolaire/emplois-du-temps/plages',
    'VieScolaire\EmploisDuTemps\Controllers\PlageHoraireController@store');
$router->post('/v2/vie-scolaire/emplois-du-temps/plages/{id}',
    'VieScolaire\EmploisDuTemps\Controllers\PlageHoraireController@update');
$router->post('/v2/vie-scolaire/emplois-du-temps/plages/{id}/toggle',
    'VieScolaire\EmploisDuTemps\Controllers\PlageHoraireController@toggle');

$router->get('/v2/vie-scolaire/emplois-du-temps/create',
    'VieScolaire\EmploisDuTemps\Controllers\TimetableController@create');

$router->post('/v2/vie-scolaire/emplois-du-temps',
    'VieScolaire\EmploisDuTemps\Controllers\TimetableController@store');

// creneaux/{id} avant {id}/creneaux pour éviter ambiguïté
$router->post('/v2/vie-scolaire/emplois-du-temps/creneaux/{id}/modifier',
    'VieScolaire\EmploisDuTemps\Controllers\TimetableController@modifierCreneau');

$router->post('/v2/vie-scolaire/emplois-du-temps/creneaux/{id}/supprimer',
    'VieScolaire\EmploisDuTemps\Controllers\TimetableController@supprimerCreneau');

// Wildcards {id}
$router->get('/v2/vie-scolaire/emplois-du-temps/{id}',
    'VieScolaire\EmploisDuTemps\Controllers\TimetableController@show');

$router->get('/v2/vie-scolaire/emplois-du-temps/{id}/creneaux/ajouter',
    'VieScolaire\EmploisDuTemps\Controllers\TimetableController@ajouterCreneauForm');

$router->post('/v2/vie-scolaire/emplois-du-temps/{id}/creneaux',
    'VieScolaire\EmploisDuTemps\Controllers\TimetableController@storeCreneau');

$router->post('/v2/vie-scolaire/emplois-du-temps/{id}/publier',
    'VieScolaire\EmploisDuTemps\Controllers\TimetableController@publier');

// ── Activités scolaires ───────────────────────────────────────────────────────
// Statiques AVANT wildcards {id}

$router->get('/v2/vie-scolaire/activites',
    'VieScolaire\Activites\Controllers\ActivityController@index');

$router->get('/v2/vie-scolaire/activites/statistiques',
    'VieScolaire\Activites\Controllers\ActivityController@statistiques');

$router->get('/v2/vie-scolaire/activites/export',
    'VieScolaire\Activites\Controllers\ActivityController@export');

$router->get('/v2/vie-scolaire/activites/create',
    'VieScolaire\Activites\Controllers\ActivityController@create');

$router->post('/v2/vie-scolaire/activites',
    'VieScolaire\Activites\Controllers\ActivityController@store');

// Inscriptions (route fixe avant {id})
$router->post('/v2/vie-scolaire/activites/inscriptions/{id}/annuler',
    'VieScolaire\Activites\Controllers\ActivityController@annulerInscription');

// Wildcards {id}
$router->get('/v2/vie-scolaire/activites/{id}',
    'VieScolaire\Activites\Controllers\ActivityController@show');

$router->get('/v2/vie-scolaire/activites/{id}/edit',
    'VieScolaire\Activites\Controllers\ActivityController@edit');

$router->post('/v2/vie-scolaire/activites/{id}/update',
    'VieScolaire\Activites\Controllers\ActivityController@update');

$router->post('/v2/vie-scolaire/activites/{id}/publier',
    'VieScolaire\Activites\Controllers\ActivityController@publier');

$router->post('/v2/vie-scolaire/activites/{id}/annuler',
    'VieScolaire\Activites\Controllers\ActivityController@annuler');

$router->get('/v2/vie-scolaire/activites/{id}/inscrire',
    'VieScolaire\Activites\Controllers\ActivityController@inscrireForm');

$router->post('/v2/vie-scolaire/activites/{id}/inscrire',
    'VieScolaire\Activites\Controllers\ActivityController@inscrireEleve');

$router->post('/v2/vie-scolaire/activites/{id}/presences',
    'VieScolaire\Activites\Controllers\ActivityController@marquerPresences');
