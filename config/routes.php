<?php

use Core\Router;

/** @var Router $router */

// ─── Page d'accueil ──────────────────────────────────────────────────────────
$router->get('/',          'HomeController@index');
$router->get('/dashboard', 'HomeController@dashboard');

// ─── Fichiers uploadés (accès sécurisé — auth requise) ───────────────────────
$router->get('/uploads/serve/{type}/{filename}', 'UploadController@serve');

// ─── Authentification ─────────────────────────────────────────────────────────
$router->get('/login',     'AuthController@showLogin');
$router->post('/login',    'AuthController@login');
$router->post('/logout',   'AuthController@logout');
$router->get('/register',  'AuthController@showRegister');
$router->post('/register', 'AuthController@register');

// ─── Multi-établissements (Phase 14.6) ────────────────────────────────────────
$router->get('/choisir-etablissement',  'AuthController@showChooseEtablissement');
$router->post('/choisir-etablissement', 'AuthController@chooseEtablissement');
$router->post('/auth/switch-school',    'AuthController@switchSchool');

// ─── Mot de passe oublié ──────────────────────────────────────────────────────
$router->get('/forgot-password',        'AuthController@showForgotPassword');
$router->post('/forgot-password',       'AuthController@forgotPassword');
$router->get('/reset-password/{token}', 'AuthController@showResetPassword');
$router->post('/reset-password',        'AuthController@resetPassword');

// ─── Profil utilisateur ───────────────────────────────────────────────────────
$router->get('/profile',           'AuthController@showProfile');
$router->post('/profile',          'AuthController@updateProfile');
$router->post('/profile/password', 'AuthController@changePassword');

// ─── Branding établissement (Phase 14.5) ─────────────────────────────────────
$router->get('/parametres/branding',  'SettingsController@showBranding');
$router->post('/parametres/branding', 'SettingsController@updateBranding');

// ─── Domaines personnalisés (Phase 14.7) ──────────────────────────────────────
$router->get('/parametres/domaines',                  'DomainController@index');
$router->post('/parametres/domaines',                 'DomainController@store');
$router->post('/parametres/domaines/{id}/verifier',   'DomainController@verify');
$router->post('/parametres/domaines/{id}/toggle',     'DomainController@toggle');
$router->post('/parametres/domaines/{id}/supprimer',  'DomainController@destroy');

// ─── Stockage & Quotas (Phase 14.8) ────────────────────────────────────────────
$router->get('/parametres/quotas',             'QuotaController@index');
$router->get('/parametres/quotas/api',         'QuotaController@api');
$router->post('/parametres/quotas/recalculer', 'QuotaController@recalculate');

// ─── Cache & Files d'attente (Phase 14.9) ─────────────────────────────────────
$router->get('/parametres/monitoring',              'MonitoringController@index');
$router->get('/parametres/monitoring/api',          'MonitoringController@api');
$router->post('/parametres/monitoring/vider-cache', 'MonitoringController@flushCache');

// ─── Portail Super-Admin SaaS (Phase 14.10) — indépendant des établissements ──
$router->get('/platform/login',    'PlatformAuthController@showLogin');
$router->post('/platform/login',   'PlatformAuthController@login');
$router->post('/platform/logout',  'PlatformAuthController@logout');

$router->get('/platform/dashboard',          'PlatformDashboardController@index');
$router->get('/platform/dashboard/api',      'PlatformDashboardController@api');
$router->post('/platform/dashboard/snapshot','PlatformDashboardController@snapshot');

$router->get('/platform/etablissements',                    'PlatformEtablissementController@index');
$router->get('/platform/etablissements/create',              'PlatformEtablissementController@create');
$router->post('/platform/etablissements',                    'PlatformEtablissementController@store');
$router->get('/platform/etablissements/{id}',                'PlatformEtablissementController@show');
$router->post('/platform/etablissements/{id}/activer',       'PlatformEtablissementController@activate');
$router->post('/platform/etablissements/{id}/suspendre',     'PlatformEtablissementController@suspend');
$router->post('/platform/etablissements/{id}/archiver',      'PlatformEtablissementController@archive');
$router->post('/platform/etablissements/{id}/restaurer',     'PlatformEtablissementController@restore');
$router->post('/platform/etablissements/{id}/supprimer',     'PlatformEtablissementController@destroy');
$router->post('/platform/etablissements/{id}/plan',          'PlatformEtablissementController@assignPlan');

$router->get('/platform/plans',            'PlatformPlanController@index');
$router->post('/platform/plans',           'PlatformPlanController@store');
$router->post('/platform/plans/{id}',      'PlatformPlanController@update');
$router->post('/platform/plans/{id}/toggle', 'PlatformPlanController@toggle');

// ─── Sauvegardes & Reprise après incident (Phase 14.11) — super_admin uniquement ──
$router->get('/platform/backups',                     'PlatformBackupController@index');
$router->get('/platform/backups/api',                 'PlatformBackupController@api');
$router->post('/platform/backups/global',             'PlatformBackupController@createGlobal');
$router->post('/platform/backups/tenant',              'PlatformBackupController@createTenant');
$router->post('/platform/backups/differentiel',        'PlatformBackupController@createDifferential');
$router->get('/platform/backups/{id}/telecharger',     'PlatformBackupController@download');
$router->post('/platform/backups/{id}/verifier',       'PlatformBackupController@verifyIntegrity');
$router->post('/platform/backups/{id}/restaurer-verif','PlatformBackupController@restoreVerify');
$router->post('/platform/backups/{id}/restaurer-tenant','PlatformBackupController@restoreTenant');

// ─── Élèves (routes statiques AVANT les routes paramétrées) ──────────────────
$router->get('/eleves',              'EleveController@index');
$router->get('/eleves/create',       'EleveController@create');
$router->get('/eleves/export/pdf',   'EleveController@exportPdf');
$router->get('/eleves/export/excel', 'EleveController@exportExcel');
$router->get('/eleves/import',       'EleveController@showImport');
$router->post('/eleves/import',      'EleveController@import');
$router->post('/eleves/store',       'EleveController@store');
// Routes paramétrées après
$router->get('/eleves/{id}',             'EleveController@show');
$router->get('/eleves/{id}/edit',        'EleveController@edit');
$router->post('/eleves/{id}',            'EleveController@update');
$router->post('/eleves/{id}/delete',     'EleveController@delete');

// ─── Enseignants / Professeurs (statiques avant paramétrées) ─────────────────
$router->get('/professeurs',              'ProfesseurController@index');
$router->get('/professeurs/create',       'ProfesseurController@create');
$router->post('/professeurs/store',       'ProfesseurController@store');
// Routes paramétrées
$router->get('/professeurs/{id}',                       'ProfesseurController@show');
$router->get('/professeurs/{id}/edit',                  'ProfesseurController@edit');
$router->post('/professeurs/{id}',                      'ProfesseurController@update');
$router->post('/professeurs/{id}/delete',               'ProfesseurController@delete');
$router->post('/professeurs/{id}/affecter-enseignement','ProfesseurController@affecterEnseignement');
$router->post('/professeurs/{id}/retirer-enseignement', 'ProfesseurController@retirerEnseignement');

// ─── Classes (statiques avant paramétrées) ────────────────────────────────────
$router->get('/classes',              'ClasseController@index');
$router->get('/classes/create',       'ClasseController@create');
$router->post('/classes/store',       'ClasseController@store');
// Routes paramétrées
$router->get('/classes/{id}',                       'ClasseController@show');
$router->get('/classes/{id}/edit',                  'ClasseController@edit');
$router->post('/classes/{id}',                      'ClasseController@update');
$router->post('/classes/{id}/delete',               'ClasseController@delete');
$router->post('/classes/{id}/affecter-eleve',       'ClasseController@affecterEleve');
$router->post('/classes/{id}/retirer-eleve',        'ClasseController@retirerEleve');
$router->post('/classes/{id}/affecter-enseignant',  'ClasseController@affecterEnseignant');
$router->post('/classes/{id}/retirer-enseignant',   'ClasseController@retirerEnseignant');

// ─── Matières (statiques avant paramétrées) ───────────────────────────────────
$router->get('/matieres',              'MatiereController@index');
$router->get('/matieres/create',       'MatiereController@create');
$router->post('/matieres/store',       'MatiereController@store');
// Routes paramétrées
$router->get('/matieres/{id}',         'MatiereController@show');
$router->get('/matieres/{id}/edit',    'MatiereController@edit');
$router->post('/matieres/{id}',        'MatiereController@update');
$router->post('/matieres/{id}/delete', 'MatiereController@delete');

// ─── Notes & Évaluations (statiques AVANT paramétrées) ───────────────────────
$router->get('/notes',                          'NoteController@index');
$router->get('/notes/controles',                'NoteController@controles');
$router->get('/notes/controles/create',         'NoteController@createControle');
$router->post('/notes/controles/store',         'NoteController@storeControle');
$router->get('/notes/moyennes',                 'NoteController@moyennes');
// Routes paramétrées contrôles
$router->get('/notes/controles/{id}/edit',      'NoteController@editControle');
$router->post('/notes/controles/{id}',          'NoteController@updateControle');
$router->post('/notes/controles/{id}/delete',   'NoteController@deleteControle');
// Saisie
$router->get('/notes/saisie/{id}',              'NoteController@saisie');
$router->post('/notes/saisie/{id}',             'NoteController@storeSaisie');

// ─── Bulletins (statiques AVANT paramétrées) ──────────────────────────────────
$router->get('/bulletins',                      'BulletinController@index');
$router->get('/bulletins/classe',               'BulletinController@classe');
$router->get('/bulletins/classement',           'BulletinController@classement');
// Routes paramétrées bulletins
$router->get('/bulletins/print/{id}',           'BulletinController@printBulletin');
$router->get('/bulletins/{id}',                 'BulletinController@eleve');

// ─── Absences (statiques AVANT paramétrées) ───────────────────────────────────
$router->get('/absences',                 'AbsenceController@index');
$router->get('/absences/pointage',        'AbsenceController@pointage');
$router->post('/absences/pointage',       'AbsenceController@storePointage');
$router->get('/absences/liste',           'AbsenceController@liste');
$router->get('/absences/stats',           'AbsenceController@stats');
$router->get('/absences/alertes',         'AbsenceController@alertes');
$router->get('/absences/create',          'AbsenceController@create');
$router->post('/absences/store',          'AbsenceController@store');
// Routes paramétrées absences
$router->get('/absences/{id}',            'AbsenceController@show');
$router->post('/absences/{id}/delete',    'AbsenceController@delete');
$router->post('/absences/{id}/justifier', 'AbsenceController@storeJustification');
$router->post('/absences/{id}/valider',   'AbsenceController@validerJustification');

// ─── Comptabilité (statiques AVANT paramétrées) ───────────────────────────────
$router->get('/comptabilite',                        'ComptabiliteController@index');
$router->get('/comptabilite/frais',                  'ComptabiliteController@frais');
$router->get('/comptabilite/frais/affecter',         'ComptabiliteController@affecter');
$router->post('/comptabilite/frais/affecter',        'ComptabiliteController@storeAffectation');
$router->post('/comptabilite/frais/store',           'ComptabiliteController@storeFrais');
$router->get('/comptabilite/impayes',                'ComptabiliteController@impayes');
$router->get('/comptabilite/caisse',                 'ComptabiliteController@caisse');
$router->get('/comptabilite/rapport',                'ComptabiliteController@rapport');
$router->get('/comptabilite/rapport/print',          'ComptabiliteController@rapportPrint');
$router->get('/comptabilite/rapport/excel',          'ComptabiliteController@exportExcel');
// Routes paramétrées comptabilité
$router->get('/comptabilite/frais/{id}/edit',        'ComptabiliteController@editFrais');
$router->post('/comptabilite/frais/{id}',            'ComptabiliteController@updateFrais');
$router->post('/comptabilite/frais/{id}/delete',     'ComptabiliteController@deleteFrais');

// ─── Paiements (statiques AVANT paramétrées) ──────────────────────────────────
$router->get('/paiements',              'PaiementController@index');
$router->get('/paiements/create',       'PaiementController@create');
$router->post('/paiements/store',       'PaiementController@store');
// Routes paramétrées paiements
$router->get('/paiements/{id}',         'PaiementController@show');
$router->get('/paiements/{id}/recu',    'PaiementController@recu');
$router->post('/paiements/{id}/delete', 'PaiementController@delete');

// ─── Dépenses (statiques AVANT paramétrées) ───────────────────────────────────
$router->get('/depenses',              'DepenseController@index');
$router->get('/depenses/create',       'DepenseController@create');
$router->post('/depenses/store',       'DepenseController@store');
// Routes paramétrées dépenses
$router->get('/depenses/{id}/edit',    'DepenseController@edit');
$router->post('/depenses/{id}',        'DepenseController@update');
$router->post('/depenses/{id}/delete', 'DepenseController@delete');

// ─── Espace Parent ────────────────────────────────────────────────────────────
$router->get('/parent/dashboard', 'ParentController@dashboard');
$router->get('/parent/notes',     'ParentController@notes');
$router->get('/parent/bulletin',  'ParentController@bulletin');
$router->get('/parent/absences',  'ParentController@absences');
$router->get('/parent/paiements', 'ParentController@paiements');
// Routes paramétrées parent
$router->post('/parent/absences/{id}/justifier', 'ParentController@justifier');

// ─── Espace Élève ──────────────────────────────────────────────────────────────
$router->get('/eleve/dashboard',     'EspaceEleveController@dashboard');
$router->get('/eleve/notes',         'EspaceEleveController@notes');
$router->get('/eleve/bulletin',      'EspaceEleveController@bulletin');
$router->get('/eleve/emploi-du-temps', 'EspaceEleveController@emploiDuTemps');
$router->get('/eleve/profil',        'EspaceEleveController@profil');

// ─── Notifications (statiques AVANT paramétrées) ───────────────────────────────
$router->get('/notifications',                  'NotificationController@index');
$router->get('/notifications/preferences',      'NotificationController@preferences');
$router->post('/notifications/preferences',     'NotificationController@savePreferences');
$router->post('/notifications/mark-all-read',   'NotificationController@markAllRead');
// Routes paramétrées notifications
$router->post('/notifications/{id}/read',       'NotificationController@markRead');

// ─── Administration notifications ─────────────────────────────────────────────
$router->get('/admin/notifications',            'NotificationController@history');
$router->post('/admin/notifications/test',      'NotificationController@adminTest');
$router->post('/admin/notifications/purge',     'NotificationController@adminPurge');

// ─── Annonces (statiques AVANT paramétrées) ───────────────────────────────────
$router->get('/annonces',          'AnnonceController@index');
$router->get('/annonces/create',   'AnnonceController@create');
$router->post('/annonces/store',   'AnnonceController@store');
// Routes paramétrées annonces
$router->get('/annonces/{id}/edit',    'AnnonceController@edit');
$router->post('/annonces/{id}',        'AnnonceController@update');
$router->post('/annonces/{id}/delete', 'AnnonceController@delete');

// ─── Emploi du temps (statiques AVANT paramétrées) ───────────────────────────
$router->get('/emplois-du-temps',                 'EmploiDuTempsController@index');
$router->get('/emplois-du-temps/mensuel',         'EmploiDuTempsController@mensuel');
$router->get('/emplois-du-temps/create',          'EmploiDuTempsController@create');
$router->post('/emplois-du-temps/store',          'EmploiDuTempsController@store');
$router->get('/emplois-du-temps/print',           'EmploiDuTempsController@print');
// Routes paramétrées emploi du temps
$router->get('/emplois-du-temps/{id}/edit',       'EmploiDuTempsController@edit');
$router->post('/emplois-du-temps/{id}',           'EmploiDuTempsController@update');
$router->post('/emplois-du-temps/{id}/delete',    'EmploiDuTempsController@delete');

// ─── Salles (statiques AVANT paramétrées) ─────────────────────────────────────
$router->get('/salles',              'SalleController@index');
$router->post('/salles/store',       'SalleController@store');
// Routes paramétrées salles
$router->get('/salles/{id}/edit',    'SalleController@edit');
$router->post('/salles/{id}',        'SalleController@update');
$router->post('/salles/{id}/delete', 'SalleController@delete');

// ─── Créneaux (statiques AVANT paramétrées) ───────────────────────────────────
$router->get('/creneaux',              'CreneauController@index');
$router->post('/creneaux/store',       'CreneauController@store');
// Routes paramétrées créneaux
$router->post('/creneaux/{id}',        'CreneauController@update');
$router->post('/creneaux/{id}/delete', 'CreneauController@delete');

// ─── Rapports (statiques AVANT paramétrées) ──────────────────────────────────
$router->get('/rapports',                          'RapportController@index');
$router->get('/rapports/scolaire',                 'RapportController@scolaire');
$router->get('/rapports/financier',                'RapportController@financier');
$router->get('/rapports/presences',                'RapportController@presences');
$router->get('/rapports/reussite',                 'RapportController@reussite');
$router->get('/rapports/export/pdf',               'RapportController@exportPdf');
// Routes paramétrées rapports
$router->get('/rapports/export/excel/{type}',      'RapportController@exportExcel');

// ─── Utilisateurs (statiques AVANT paramétrées) ──────────────────────────────
$router->get('/utilisateurs',              'UtilisateurController@index');
$router->get('/utilisateurs/create',       'UtilisateurController@create');
$router->post('/utilisateurs/store',       'UtilisateurController@store');
// Routes paramétrées utilisateurs
$router->get('/utilisateurs/{id}/edit',          'UtilisateurController@edit');
$router->post('/utilisateurs/{id}',              'UtilisateurController@update');
$router->post('/utilisateurs/{id}/delete',       'UtilisateurController@delete');
$router->post('/utilisateurs/{id}/toggle-actif', 'UtilisateurController@toggleActif');

// ─── API JSON (pour PWA) ──────────────────────────────────────────────────────
$router->get('/api/eleves',                      'Api\EleveApiController@index');
$router->get('/api/eleves/{id}',                 'Api\EleveApiController@show');
$router->get('/api/frais-eleve',                 'Api\FraisEleveApiController@index');
$router->get('/api/emploi-du-temps/conflits',    'EmploiDuTempsController@checkConflicts');
$router->get('/api/notifications/unread-count', 'Api\NotificationApiController@unreadCount');
$router->get('/api/notifications/recent',       'Api\NotificationApiController@recent');
$router->get('/api/notifications/latest',       'Api\NotificationApiController@latest');

// ─── Push Notifications (PWA) ────────────────────────────────────────────────
$router->get('/api/push/vapid-key',   'Api\PushController@vapidKey');
$router->post('/api/push/subscribe',  'Api\PushController@subscribe');
$router->post('/api/push/unsubscribe','Api\PushController@unsubscribe');
$router->post('/api/push/send',       'Api\PushController@send');
