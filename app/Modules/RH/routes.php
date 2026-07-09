<?php

/**
 * Routes du module RH V2.
 *
 * Ce fichier n'est chargé QUE si le module est activé dans config/modules.php.
 * Préfixe  : /v2/rh
 * Phase 6.2 : Domaine Employés
 * Phase 6.3 : Domaine Enseignants
 *
 * Coexiste avec les routes V1 (/enseignants, /professeurs) — zéro collision.
 * RÈGLE : routes statiques AVANT wildcards {id}.
 */

use Core\Router;

/** @var Router $router */

// ── Employés ─────────────────────────────────────────────────────────────────
// Statiques en premier

$router->get('/v2/rh/employes',
    'RH\Employes\Controllers\EmployeeController@index');

$router->get('/v2/rh/employes/create',
    'RH\Employes\Controllers\EmployeeController@create');

$router->post('/v2/rh/employes',
    'RH\Employes\Controllers\EmployeeController@store');

$router->get('/v2/rh/employes/statistiques',
    'RH\Employes\Controllers\EmployeeController@statistiques');

$router->get('/v2/rh/employes/export',
    'RH\Employes\Controllers\EmployeeController@export');

// Wildcards {id} après les statiques

$router->get('/v2/rh/employes/{id}',
    'RH\Employes\Controllers\EmployeeController@show');

$router->get('/v2/rh/employes/{id}/edit',
    'RH\Employes\Controllers\EmployeeController@edit');

$router->post('/v2/rh/employes/{id}',
    'RH\Employes\Controllers\EmployeeController@update');

$router->post('/v2/rh/employes/{id}/archive',
    'RH\Employes\Controllers\EmployeeController@archive');

$router->post('/v2/rh/employes/{id}/restore',
    'RH\Employes\Controllers\EmployeeController@restore');

$router->post('/v2/rh/employes/{id}/statut',
    'RH\Employes\Controllers\EmployeeController@changerStatut');

// ── Enseignants ───────────────────────────────────────────────────────────────
// Statiques en premier

$router->get('/v2/rh/enseignants',
    'RH\Enseignants\Controllers\TeacherController@index');

$router->get('/v2/rh/enseignants/create',
    'RH\Enseignants\Controllers\TeacherController@create');

$router->post('/v2/rh/enseignants',
    'RH\Enseignants\Controllers\TeacherController@store');

$router->get('/v2/rh/enseignants/statistiques',
    'RH\Enseignants\Controllers\TeacherController@statistiques');

$router->get('/v2/rh/enseignants/export',
    'RH\Enseignants\Controllers\TeacherController@export');

// Wildcards {id} après les statiques

$router->get('/v2/rh/enseignants/{id}',
    'RH\Enseignants\Controllers\TeacherController@show');

$router->get('/v2/rh/enseignants/{id}/edit',
    'RH\Enseignants\Controllers\TeacherController@edit');

$router->post('/v2/rh/enseignants/{id}',
    'RH\Enseignants\Controllers\TeacherController@update');

$router->post('/v2/rh/enseignants/{id}/archive',
    'RH\Enseignants\Controllers\TeacherController@archive');

$router->post('/v2/rh/enseignants/{id}/restore',
    'RH\Enseignants\Controllers\TeacherController@restore');

$router->post('/v2/rh/enseignants/{id}/matieres',
    'RH\Enseignants\Controllers\TeacherController@assignerMatieres');

$router->post('/v2/rh/enseignants/{id}/qualifications',
    'RH\Enseignants\Controllers\TeacherController@ajouterQualification');

$router->post('/v2/rh/enseignants/{id}/qualifications/{qualId}/delete',
    'RH\Enseignants\Controllers\TeacherController@supprimerQualification');

// ── Organisation ──────────────────────────────────────────────────────────────
// Statiques en premier

$router->get('/v2/rh/organisation',
    'RH\Organisation\Controllers\OrganizationController@index');

$router->get('/v2/rh/organisation/statistiques',
    'RH\Organisation\Controllers\OrganizationController@statistiques');

$router->get('/v2/rh/organisation/export/departements',
    'RH\Organisation\Controllers\OrganizationController@exportDepartements');

$router->get('/v2/rh/organisation/export/postes',
    'RH\Organisation\Controllers\OrganizationController@exportPostes');

// Départements — statiques
$router->get('/v2/rh/organisation/departements',
    'RH\Organisation\Controllers\DepartmentController@index');

$router->get('/v2/rh/organisation/departements/create',
    'RH\Organisation\Controllers\DepartmentController@create');

$router->post('/v2/rh/organisation/departements',
    'RH\Organisation\Controllers\DepartmentController@store');

// Postes — statiques
$router->get('/v2/rh/organisation/postes',
    'RH\Organisation\Controllers\PositionController@index');

$router->get('/v2/rh/organisation/postes/create',
    'RH\Organisation\Controllers\PositionController@create');

$router->post('/v2/rh/organisation/postes',
    'RH\Organisation\Controllers\PositionController@store');

// Fonctions
$router->get('/v2/rh/organisation/fonctions',
    'RH\Organisation\Controllers\PositionController@fonctions');

$router->post('/v2/rh/organisation/fonctions',
    'RH\Organisation\Controllers\PositionController@storeFonction');

$router->post('/v2/rh/organisation/fonctions/{id}/archive',
    'RH\Organisation\Controllers\PositionController@archiveFonction');

// Départements — wildcards {id}
$router->get('/v2/rh/organisation/departements/{id}',
    'RH\Organisation\Controllers\DepartmentController@show');

$router->get('/v2/rh/organisation/departements/{id}/edit',
    'RH\Organisation\Controllers\DepartmentController@edit');

$router->post('/v2/rh/organisation/departements/{id}',
    'RH\Organisation\Controllers\DepartmentController@update');

$router->post('/v2/rh/organisation/departements/{id}/archive',
    'RH\Organisation\Controllers\DepartmentController@archive');

$router->post('/v2/rh/organisation/departements/{id}/restore',
    'RH\Organisation\Controllers\DepartmentController@restore');

$router->post('/v2/rh/organisation/departements/{deptId}/services',
    'RH\Organisation\Controllers\DepartmentController@storeService');

$router->post('/v2/rh/organisation/departements/{deptId}/services/{serviceId}/archive',
    'RH\Organisation\Controllers\DepartmentController@archiveService');

// Postes — wildcards {id}
$router->get('/v2/rh/organisation/postes/{id}/edit',
    'RH\Organisation\Controllers\PositionController@edit');

$router->post('/v2/rh/organisation/postes/{id}',
    'RH\Organisation\Controllers\PositionController@update');

$router->post('/v2/rh/organisation/postes/{id}/archive',
    'RH\Organisation\Controllers\PositionController@archive');

$router->post('/v2/rh/organisation/postes/{id}/restore',
    'RH\Organisation\Controllers\PositionController@restore');

$router->post('/v2/rh/organisation/fonctions/{id}',
    'RH\Organisation\Controllers\PositionController@updateFonction');

// ── Contrats ──────────────────────────────────────────────────────────────────
// Statiques AVANT wildcards {id}

$router->get('/v2/rh/contrats',
    'RH\Contrats\Controllers\ContractController@index');

$router->get('/v2/rh/contrats/create',
    'RH\Contrats\Controllers\ContractController@create');

$router->post('/v2/rh/contrats',
    'RH\Contrats\Controllers\ContractController@store');

$router->get('/v2/rh/contrats/echeances',
    'RH\Contrats\Controllers\ContractController@echeances');

$router->get('/v2/rh/contrats/statistiques',
    'RH\Contrats\Controllers\ContractController@statistiques');

$router->get('/v2/rh/contrats/export',
    'RH\Contrats\Controllers\ContractController@export');

// Wildcards {id}

$router->get('/v2/rh/contrats/{id}',
    'RH\Contrats\Controllers\ContractController@show');

$router->get('/v2/rh/contrats/{id}/edit',
    'RH\Contrats\Controllers\ContractController@edit');

$router->post('/v2/rh/contrats/{id}',
    'RH\Contrats\Controllers\ContractController@update');

$router->post('/v2/rh/contrats/{id}/avenants',
    'RH\Contrats\Controllers\ContractController@storeAvenant');

$router->post('/v2/rh/contrats/{id}/activer',
    'RH\Contrats\Controllers\ContractController@activer');

$router->post('/v2/rh/contrats/{id}/suspendre',
    'RH\Contrats\Controllers\ContractController@suspendre');

$router->post('/v2/rh/contrats/{id}/reactiver',
    'RH\Contrats\Controllers\ContractController@reactiver');

$router->post('/v2/rh/contrats/{id}/renouveler',
    'RH\Contrats\Controllers\ContractController@renouveler');

$router->post('/v2/rh/contrats/{id}/resilier',
    'RH\Contrats\Controllers\ContractController@resilier');

$router->post('/v2/rh/contrats/{id}/archive',
    'RH\Contrats\Controllers\ContractController@archive');

// ── Affectations ──────────────────────────────────────────────────────────────
// Statiques AVANT wildcards {id}

$router->get('/v2/rh/affectations',
    'RH\Affectations\Controllers\AssignmentController@index');

$router->get('/v2/rh/affectations/create',
    'RH\Affectations\Controllers\AssignmentController@create');

$router->post('/v2/rh/affectations',
    'RH\Affectations\Controllers\AssignmentController@store');

$router->get('/v2/rh/affectations/statistiques',
    'RH\Affectations\Controllers\AssignmentController@statistiques');

$router->get('/v2/rh/affectations/export',
    'RH\Affectations\Controllers\AssignmentController@export');

// Wildcards {id}

$router->get('/v2/rh/affectations/{id}',
    'RH\Affectations\Controllers\AssignmentController@show');

$router->get('/v2/rh/affectations/{id}/edit',
    'RH\Affectations\Controllers\AssignmentController@edit');

$router->post('/v2/rh/affectations/{id}',
    'RH\Affectations\Controllers\AssignmentController@update');

$router->post('/v2/rh/affectations/{id}/transferer',
    'RH\Affectations\Controllers\AssignmentController@transferer');

$router->post('/v2/rh/affectations/{id}/suspendre',
    'RH\Affectations\Controllers\AssignmentController@suspendre');

$router->post('/v2/rh/affectations/{id}/reactiver',
    'RH\Affectations\Controllers\AssignmentController@reactiver');

$router->post('/v2/rh/affectations/{id}/clore',
    'RH\Affectations\Controllers\AssignmentController@clore');

$router->post('/v2/rh/affectations/{id}/archive',
    'RH\Affectations\Controllers\AssignmentController@archive');

$router->post('/v2/rh/affectations/{id}/matieres',
    'RH\Affectations\Controllers\AssignmentController@storeMatiereAssignment');

$router->post('/v2/rh/affectations/{id}/matieres/{matId}/remove',
    'RH\Affectations\Controllers\AssignmentController@removeMatiereAssignment');

// ── Présences ─────────────────────────────────────────────────────────────────
// Phase 6.7 — Statiques AVANT wildcards {id}

$router->get('/v2/rh/presences',
    'RH\Presences\Controllers\AttendanceController@index');

$router->get('/v2/rh/presences/create',
    'RH\Presences\Controllers\AttendanceController@create');

$router->post('/v2/rh/presences',
    'RH\Presences\Controllers\AttendanceController@store');

$router->get('/v2/rh/presences/statistiques',
    'RH\Presences\Controllers\AttendanceController@statistiques');

$router->get('/v2/rh/presences/export',
    'RH\Presences\Controllers\AttendanceController@export');

$router->get('/v2/rh/presences/validation',
    'RH\Presences\Controllers\AttendanceController@validation');

// Wildcards {id}

$router->get('/v2/rh/presences/{id}',
    'RH\Presences\Controllers\AttendanceController@show');

$router->get('/v2/rh/presences/{id}/edit',
    'RH\Presences\Controllers\AttendanceController@edit');

$router->post('/v2/rh/presences/{id}',
    'RH\Presences\Controllers\AttendanceController@update');

$router->post('/v2/rh/presences/{id}/valider',
    'RH\Presences\Controllers\AttendanceController@valider');

$router->post('/v2/rh/presences/{id}/justifier',
    'RH\Presences\Controllers\AttendanceController@justifier');

$router->post('/v2/rh/presences/{id}/regulariser',
    'RH\Presences\Controllers\AttendanceController@regulariser');

$router->post('/v2/rh/presences/{id}/archive',
    'RH\Presences\Controllers\AttendanceController@archive');

$router->post('/v2/rh/presences/{id}/restore',
    'RH\Presences\Controllers\AttendanceController@restore');

// ── Congés ────────────────────────────────────────────────────────────────────
// Phase 6.8 — Statiques AVANT wildcards {id}

$router->get('/v2/rh/conges',
    'RH\Conges\Controllers\LeaveController@index');

$router->get('/v2/rh/conges/create',
    'RH\Conges\Controllers\LeaveController@create');

$router->post('/v2/rh/conges',
    'RH\Conges\Controllers\LeaveController@store');

$router->get('/v2/rh/conges/validation',
    'RH\Conges\Controllers\LeaveController@validation');

$router->get('/v2/rh/conges/soldes',
    'RH\Conges\Controllers\LeaveController@soldes');

$router->post('/v2/rh/conges/soldes',
    'RH\Conges\Controllers\LeaveController@storeSolde');

$router->get('/v2/rh/conges/export',
    'RH\Conges\Controllers\LeaveController@export');

// Wildcards {id}

$router->get('/v2/rh/conges/{id}',
    'RH\Conges\Controllers\LeaveController@show');

$router->get('/v2/rh/conges/{id}/edit',
    'RH\Conges\Controllers\LeaveController@edit');

$router->post('/v2/rh/conges/{id}',
    'RH\Conges\Controllers\LeaveController@update');

$router->post('/v2/rh/conges/{id}/soumettre',
    'RH\Conges\Controllers\LeaveController@soumettre');

$router->post('/v2/rh/conges/{id}/approuver',
    'RH\Conges\Controllers\LeaveController@approuver');

$router->post('/v2/rh/conges/{id}/rejeter',
    'RH\Conges\Controllers\LeaveController@rejeter');

$router->post('/v2/rh/conges/{id}/annuler',
    'RH\Conges\Controllers\LeaveController@annuler');

$router->post('/v2/rh/conges/{id}/demarrer',
    'RH\Conges\Controllers\LeaveController@demarrer');

$router->post('/v2/rh/conges/{id}/terminer',
    'RH\Conges\Controllers\LeaveController@terminer');

// ── Évaluations ───────────────────────────────────────────────────────────────
// Phase 6.9 — Statiques AVANT wildcards {id}

$router->get('/v2/rh/evaluations',
    'RH\Evaluations\Controllers\EvaluationController@index');

$router->get('/v2/rh/evaluations/campagnes',
    'RH\Evaluations\Controllers\EvaluationController@campagnes');

$router->get('/v2/rh/evaluations/campagnes/create',
    'RH\Evaluations\Controllers\EvaluationController@createCampagne');

$router->post('/v2/rh/evaluations/campagnes',
    'RH\Evaluations\Controllers\EvaluationController@storeCampagne');

$router->get('/v2/rh/evaluations/create',
    'RH\Evaluations\Controllers\EvaluationController@create');

$router->post('/v2/rh/evaluations',
    'RH\Evaluations\Controllers\EvaluationController@store');

$router->get('/v2/rh/evaluations/export',
    'RH\Evaluations\Controllers\EvaluationController@export');

// Wildcards campagnes avant wildcards évaluations

$router->post('/v2/rh/evaluations/campagnes/{id}/activer',
    'RH\Evaluations\Controllers\EvaluationController@activerCampagne');

$router->post('/v2/rh/evaluations/campagnes/{id}/cloturer',
    'RH\Evaluations\Controllers\EvaluationController@cloturerCampagne');

// Wildcards évaluations {id}

$router->get('/v2/rh/evaluations/{id}',
    'RH\Evaluations\Controllers\EvaluationController@show');

$router->post('/v2/rh/evaluations/{id}/demarrer-auto-eval',
    'RH\Evaluations\Controllers\EvaluationController@demarrerAutoEval');

$router->post('/v2/rh/evaluations/{id}/auto-eval',
    'RH\Evaluations\Controllers\EvaluationController@soumettreAutoEval');

$router->post('/v2/rh/evaluations/{id}/evaluer',
    'RH\Evaluations\Controllers\EvaluationController@evaluerResponsable');

$router->post('/v2/rh/evaluations/{id}/soumettre',
    'RH\Evaluations\Controllers\EvaluationController@soumettre');

$router->post('/v2/rh/evaluations/{id}/valider',
    'RH\Evaluations\Controllers\EvaluationController@valider');

$router->post('/v2/rh/evaluations/{id}/publier',
    'RH\Evaluations\Controllers\EvaluationController@publier');

$router->post('/v2/rh/evaluations/{id}/plan',
    'RH\Evaluations\Controllers\EvaluationController@storePlan');

// ── Formations ────────────────────────────────────────────────────────────────
// Phase 6.10 — Statiques AVANT wildcards {id}

$router->get('/v2/rh/formations',
    'RH\Formations\Controllers\TrainingController@index');

$router->get('/v2/rh/formations/catalogue',
    'RH\Formations\Controllers\TrainingController@catalogue');

$router->get('/v2/rh/formations/catalogue/create',
    'RH\Formations\Controllers\TrainingController@createFormation');

$router->post('/v2/rh/formations/catalogue',
    'RH\Formations\Controllers\TrainingController@storeFormation');

$router->get('/v2/rh/formations/sessions/create',
    'RH\Formations\Controllers\TrainingController@createSession');

$router->post('/v2/rh/formations/sessions',
    'RH\Formations\Controllers\TrainingController@storeSession');

$router->get('/v2/rh/formations/certifications',
    'RH\Formations\Controllers\TrainingController@certifications');

$router->post('/v2/rh/formations/certifications',
    'RH\Formations\Controllers\TrainingController@storeCertification');

$router->get('/v2/rh/formations/competences',
    'RH\Formations\Controllers\TrainingController@competences');

$router->post('/v2/rh/formations/competences',
    'RH\Formations\Controllers\TrainingController@storeCompetence');

$router->get('/v2/rh/formations/export',
    'RH\Formations\Controllers\TrainingController@export');

// Wildcards inscriptions avant wildcards sessions

$router->post('/v2/rh/formations/inscriptions/{id}/presence',
    'RH\Formations\Controllers\TrainingController@marquerPresence');

$router->post('/v2/rh/formations/inscriptions/{id}/valider',
    'RH\Formations\Controllers\TrainingController@validerInscription');

$router->post('/v2/rh/formations/inscriptions/{id}/annuler',
    'RH\Formations\Controllers\TrainingController@annulerInscription');

// Wildcards sessions {id}

$router->get('/v2/rh/formations/sessions/{id}',
    'RH\Formations\Controllers\TrainingController@showSession');

$router->post('/v2/rh/formations/sessions/{id}/ouvrir',
    'RH\Formations\Controllers\TrainingController@ouvrirSession');

$router->post('/v2/rh/formations/sessions/{id}/demarrer',
    'RH\Formations\Controllers\TrainingController@demarrerSession');

$router->post('/v2/rh/formations/sessions/{id}/terminer',
    'RH\Formations\Controllers\TrainingController@terminerSession');

$router->post('/v2/rh/formations/sessions/{id}/annuler',
    'RH\Formations\Controllers\TrainingController@annulerSession');

$router->post('/v2/rh/formations/sessions/{id}/inscrire',
    'RH\Formations\Controllers\TrainingController@inscrire');

// ── Documents RH ──────────────────────────────────────────────────────────────
// Phase 6.11 — Statiques AVANT wildcards {id}

$router->get('/v2/rh/documents',
    'RH\Documents\Controllers\HRDocumentController@index');

$router->get('/v2/rh/documents/create',
    'RH\Documents\Controllers\HRDocumentController@create');

$router->post('/v2/rh/documents',
    'RH\Documents\Controllers\HRDocumentController@store');

$router->get('/v2/rh/documents/expirations',
    'RH\Documents\Controllers\HRDocumentController@expirations');

$router->get('/v2/rh/documents/export',
    'RH\Documents\Controllers\HRDocumentController@export');

// Wildcards {id}

$router->get('/v2/rh/documents/{id}',
    'RH\Documents\Controllers\HRDocumentController@show');

$router->get('/v2/rh/documents/{id}/edit',
    'RH\Documents\Controllers\HRDocumentController@edit');

$router->post('/v2/rh/documents/{id}',
    'RH\Documents\Controllers\HRDocumentController@update');

$router->post('/v2/rh/documents/{id}/archiver',
    'RH\Documents\Controllers\HRDocumentController@archiver');

$router->post('/v2/rh/documents/{id}/restaurer',
    'RH\Documents\Controllers\HRDocumentController@restaurer');
