<?php

/**
 * Routes du module Finance V2.
 *
 * ATTENTION — Ce fichier n'est chargé QUE si le module est activé
 * dans config/modules.php ('enabled' => true).
 *
 * Préfixe : /v2/finance
 * Namespace contrôleurs : App\Modules\Finance\Controllers\
 *
 * Étape 3 (unification V1/V2) : le menu de navigation pointe désormais
 * exclusivement vers /v2/finance/* pour tous les rôles. Les routes V1
 * (/comptabilite, /paiements, /depenses) restent techniquement actives
 * (accès direct par URL) le temps de l'observation post-bascule, mais ne
 * sont plus atteignables depuis aucune navigation de l'application.
 * Phase 3.x — Décaissements : domaine V2 natif construit (finance_decaissements,
 * finance_fournisseurs, finance_categories_depenses, finance_justificatifs),
 * intégré à la comptabilité (ExpenseValidated → écriture 6010/4010) et à la
 * caisse (imputation automatique si paiement en espèces).
 */

use Core\Router;

/** @var Router $router */

// ─── Espace parent / élève — scolarité et paiements de l'enfant ─────────────
$router->get('/v2/finance/mes-paiements', 'Finance\Controllers\FactureController@mesPaiements');

// ─── Phase 3.2 — Référentiel des frais ───────────────────────────────────────

// Catégories (avant /frais/{id} pour éviter le conflit de routing)
$router->get('/v2/finance/frais/categories',           'Finance\Controllers\FraisController@categories');
$router->post('/v2/finance/frais/categories',          'Finance\Controllers\FraisController@storeCategorie');
$router->post('/v2/finance/frais/categories/{id}',     'Finance\Controllers\FraisController@updateCategorie');
$router->post('/v2/finance/frais/categories/{id}/toggle','Finance\Controllers\FraisController@toggleCategorie');

// Types de frais
$router->get('/v2/finance/frais',                      'Finance\Controllers\FraisController@index');
$router->get('/v2/finance/frais/create',               'Finance\Controllers\FraisController@create');
$router->post('/v2/finance/frais',                     'Finance\Controllers\FraisController@store');
$router->get('/v2/finance/frais/{id}',                 'Finance\Controllers\FraisController@show');
$router->get('/v2/finance/frais/{id}/edit',            'Finance\Controllers\FraisController@edit');
$router->post('/v2/finance/frais/{id}',                'Finance\Controllers\FraisController@update');
$router->post('/v2/finance/frais/{id}/activer',        'Finance\Controllers\FraisController@activer');
$router->post('/v2/finance/frais/{id}/desactiver',     'Finance\Controllers\FraisController@desactiver');
$router->post('/v2/finance/frais/{id}/archiver',       'Finance\Controllers\FraisController@archiver');
$router->post('/v2/finance/frais/{id}/delete',         'Finance\Controllers\FraisController@destroy');
$router->post('/v2/finance/frais/{id}/tarif',          'Finance\Controllers\FraisController@storeTarif');

// ─── Phase 3.3 — Facturation ─────────────────────────────────────────────────

// Génération masse (avant /{id} pour éviter conflit de routing)
$router->get('/v2/finance/factures/generer',                           'Finance\Controllers\FactureController@genererForm');
$router->post('/v2/finance/factures/generer',                          'Finance\Controllers\FactureController@generer');

// CRUD factures
$router->get('/v2/finance/factures',                                   'Finance\Controllers\FactureController@index');
$router->get('/v2/finance/factures/create',                            'Finance\Controllers\FactureController@create');
$router->post('/v2/finance/factures',                                  'Finance\Controllers\FactureController@store');
$router->get('/v2/finance/factures/{id}',                              'Finance\Controllers\FactureController@show');
$router->get('/v2/finance/factures/{id}/edit',                         'Finance\Controllers\FactureController@edit');
$router->post('/v2/finance/factures/{id}',                             'Finance\Controllers\FactureController@update');
$router->get('/v2/finance/factures/{id}/print',                        'Finance\Controllers\FactureController@print');

// Actions de statut
$router->post('/v2/finance/factures/{id}/emettre',                     'Finance\Controllers\FactureController@emettre');
$router->post('/v2/finance/factures/{id}/annuler',                     'Finance\Controllers\FactureController@annuler');
$router->post('/v2/finance/factures/{id}/archiver',                    'Finance\Controllers\FactureController@archiver');
$router->post('/v2/finance/factures/{id}/delete',                      'Finance\Controllers\FactureController@destroy');

// Lignes
$router->post('/v2/finance/factures/{id}/ligne',                       'Finance\Controllers\FactureController@storeLigne');
$router->post('/v2/finance/factures/{id}/ligne/{ligneId}/delete',      'Finance\Controllers\FactureController@destroyLigne');

// Remises, pénalités, échéancier
$router->post('/v2/finance/factures/{id}/remise',                      'Finance\Controllers\FactureController@storeRemise');
$router->post('/v2/finance/factures/{id}/echeancier',                  'Finance\Controllers\FactureController@storeEcheancier');

// Paiements d'une facture (vue inline)
$router->get('/v2/finance/factures/{factureId}/paiements',             'Finance\Controllers\PaiementController@parFacture');

// ─── Phase 3.4 — Encaissements ───────────────────────────────────────────────

// Actions sur paiements (avant /{id} pour éviter conflits de routing)
$router->get('/v2/finance/paiements/create',                           'Finance\Controllers\PaiementController@create');

// CRUD
$router->get('/v2/finance/paiements',                                  'Finance\Controllers\PaiementController@index');
$router->post('/v2/finance/paiements',                                 'Finance\Controllers\PaiementController@store');
$router->get('/v2/finance/paiements/{id}',                             'Finance\Controllers\PaiementController@show');

// Reçus
$router->get('/v2/finance/paiements/{id}/recu/print',                  'Finance\Controllers\PaiementController@recuPrint');
$router->get('/v2/finance/paiements/{id}/recu',                        'Finance\Controllers\PaiementController@recu');

// Transitions de statut
$router->post('/v2/finance/paiements/{id}/valider',                    'Finance\Controllers\PaiementController@valider');
$router->post('/v2/finance/paiements/{id}/completer',                  'Finance\Controllers\PaiementController@completer');
$router->post('/v2/finance/paiements/{id}/annuler',                    'Finance\Controllers\PaiementController@annuler');
$router->post('/v2/finance/paiements/{id}/rembourser',                 'Finance\Controllers\PaiementController@rembourser');

// Trop-perçu
$router->post('/v2/finance/paiements/{id}/trop-percu/{tpId}/{action}', 'Finance\Controllers\PaiementController@traiterTropPercu');

// ─── Phase 3.5 — Caisse ──────────────────────────────────────────────────────

// Redirection vers session active (avant /{id} pour éviter conflit)
$router->get('/v2/finance/caisse/active',                              'Finance\Controllers\CaisseController@active');
$router->get('/v2/finance/caisse/create',                              'Finance\Controllers\CaisseController@create');

// CRUD sessions
$router->get('/v2/finance/caisse',                                     'Finance\Controllers\CaisseController@index');
$router->post('/v2/finance/caisse',                                    'Finance\Controllers\CaisseController@store');
$router->get('/v2/finance/caisse/{id}',                                'Finance\Controllers\CaisseController@show');

// Fermeture
$router->get('/v2/finance/caisse/{id}/fermer',                         'Finance\Controllers\CaisseController@fermerForm');
$router->post('/v2/finance/caisse/{id}/fermer',                        'Finance\Controllers\CaisseController@fermer');

// Mouvements
$router->post('/v2/finance/caisse/{id}/mouvement',                     'Finance\Controllers\CaisseController@storeMouvement');
$router->post('/v2/finance/caisse/{id}/mouvement/{mouvId}/annuler',    'Finance\Controllers\CaisseController@annulerMouvement');

// Rapprochement
$router->post('/v2/finance/caisse/{id}/rapprocher',                    'Finance\Controllers\CaisseController@rapprocher');

// Journal imprimable
$router->get('/v2/finance/caisse/{id}/journal/print',                  'Finance\Controllers\CaisseController@journalPrint');

// ─── Phase 3.6 — Comptabilité ────────────────────────────────────────────────

// Sous-sections statiques (avant les routes avec {id} pour éviter conflits)
$router->get('/v2/finance/comptabilite/plan-comptable',                'Finance\Controllers\ComptabiliteController@planComptable');
$router->get('/v2/finance/comptabilite/journal',                       'Finance\Controllers\ComptabiliteController@journal');
$router->get('/v2/finance/comptabilite/grand-livre',                   'Finance\Controllers\ComptabiliteController@grandLivre');
$router->get('/v2/finance/comptabilite/balance',                       'Finance\Controllers\ComptabiliteController@balance');

// Exercices (create avant /{id})
$router->get('/v2/finance/comptabilite/exercices/create',              'Finance\Controllers\ComptabiliteController@createExercice');
$router->get('/v2/finance/comptabilite/exercices',                     'Finance\Controllers\ComptabiliteController@exercices');
$router->post('/v2/finance/comptabilite/exercices',                    'Finance\Controllers\ComptabiliteController@storeExercice');
$router->get('/v2/finance/comptabilite/exercices/{id}',                'Finance\Controllers\ComptabiliteController@showExercice');
$router->post('/v2/finance/comptabilite/exercices/{id}/cloturer',      'Finance\Controllers\ComptabiliteController@cloturerExercice');

// Périodes
$router->post('/v2/finance/comptabilite/periodes/{id}/cloturer',       'Finance\Controllers\ComptabiliteController@cloturerPeriode');

// Écritures
$router->get('/v2/finance/comptabilite/ecritures/{id}',                'Finance\Controllers\ComptabiliteController@showEcriture');
$router->post('/v2/finance/comptabilite/ecritures/{id}/extourner',     'Finance\Controllers\ComptabiliteController@extourner');

// Dashboard (en dernier)
$router->get('/v2/finance/comptabilite',                               'Finance\Controllers\ComptabiliteController@index');

// ─── Phase 3.7 — Rapports financiers ─────────────────────────────────────────

// Export + Print (avant les rapports spécifiques pour éviter conflit)
$router->get('/v2/finance/rapports/export',                            'Finance\Controllers\RapportController@export');
$router->get('/v2/finance/rapports/print',                             'Finance\Controllers\RapportController@print');

// Rapports individuels (chemins statiques avant index)
$router->get('/v2/finance/rapports/dashboard',                         'Finance\Controllers\RapportController@dashboard');
$router->get('/v2/finance/rapports/paiements',                         'Finance\Controllers\RapportController@paiements');
$router->get('/v2/finance/rapports/factures',                          'Finance\Controllers\RapportController@factures');
$router->get('/v2/finance/rapports/impayes',                           'Finance\Controllers\RapportController@impayes');
$router->get('/v2/finance/rapports/caisse',                            'Finance\Controllers\RapportController@caisse');
$router->get('/v2/finance/rapports/analytique',                        'Finance\Controllers\RapportController@analytique');

// Index rapports (en dernier)
$router->get('/v2/finance/rapports',                                   'Finance\Controllers\RapportController@index');

// ─── Phase 3.8 — Dépenses / Décaissements ────────────────────────────────────

// Fournisseurs (chemin dédié, avant les routes décaissements avec {id})
$router->get('/v2/finance/fournisseurs',                               'Finance\Controllers\DecaissementController@fournisseurs');
$router->post('/v2/finance/fournisseurs',                              'Finance\Controllers\DecaissementController@storeFournisseur');
$router->post('/v2/finance/fournisseurs/{id}',                         'Finance\Controllers\DecaissementController@updateFournisseur');
$router->post('/v2/finance/fournisseurs/{id}/toggle',                  'Finance\Controllers\DecaissementController@toggleFournisseur');

// Catégories de dépenses (avant /decaissements/{id} pour éviter le conflit)
$router->get('/v2/finance/decaissements/categories',                   'Finance\Controllers\DecaissementController@categories');
$router->post('/v2/finance/decaissements/categories',                  'Finance\Controllers\DecaissementController@storeCategorie');
$router->post('/v2/finance/decaissements/categories/{id}',             'Finance\Controllers\DecaissementController@updateCategorie');
$router->post('/v2/finance/decaissements/categories/{id}/toggle',      'Finance\Controllers\DecaissementController@toggleCategorie');

// CRUD décaissements
$router->get('/v2/finance/decaissements',                              'Finance\Controllers\DecaissementController@index');
$router->get('/v2/finance/decaissements/create',                       'Finance\Controllers\DecaissementController@create');
$router->post('/v2/finance/decaissements',                             'Finance\Controllers\DecaissementController@store');
$router->get('/v2/finance/decaissements/{id}',                         'Finance\Controllers\DecaissementController@show');

// Workflow
$router->post('/v2/finance/decaissements/{id}/valider',                'Finance\Controllers\DecaissementController@valider');
$router->post('/v2/finance/decaissements/{id}/approuver',              'Finance\Controllers\DecaissementController@approuver');
$router->post('/v2/finance/decaissements/{id}/rejeter',                'Finance\Controllers\DecaissementController@rejeter');
$router->post('/v2/finance/decaissements/{id}/payer',                  'Finance\Controllers\DecaissementController@payer');
$router->post('/v2/finance/decaissements/{id}/annuler',                'Finance\Controllers\DecaissementController@annuler');

// Justificatifs
$router->post('/v2/finance/decaissements/{id}/justificatifs',          'Finance\Controllers\DecaissementController@uploadJustificatif');
$router->post('/v2/finance/decaissements/{id}/justificatifs/{jusId}/delete', 'Finance\Controllers\DecaissementController@destroyJustificatif');
