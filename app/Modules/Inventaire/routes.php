<?php

declare(strict_types=1);

use Core\Router;
use App\Modules\Inventaire\Controllers\ArticleController;
use App\Modules\Inventaire\Controllers\CategorieController;
use App\Modules\Inventaire\Controllers\FournisseurController;
use App\Modules\Inventaire\Controllers\CommandeController;
use App\Modules\Inventaire\Controllers\ReceptionController;
use App\Modules\Inventaire\Controllers\StockController;
use App\Modules\Inventaire\Controllers\AffectationController;
use App\Modules\Inventaire\Controllers\MaintenanceController;
use App\Modules\Inventaire\Controllers\InvPhysiqueController;
use App\Modules\Inventaire\Controllers\AlerteController;
use App\Modules\Inventaire\Controllers\InventaireAnalyticsController;

// ─────────────────────────────────────────────────────────────
// Dashboard analytique
// ─────────────────────────────────────────────────────────────
$router->get('/v2/inventaire',                            [InventaireAnalyticsController::class, 'dashboard']);
$router->get('/v2/inventaire/dashboard',                  [InventaireAnalyticsController::class, 'dashboard']);

// ─────────────────────────────────────────────────────────────
// Articles
// ─────────────────────────────────────────────────────────────
$router->get('/v2/inventaire/articles',                   [ArticleController::class, 'index']);
$router->get('/v2/inventaire/articles/creer',             [ArticleController::class, 'create']);
$router->post('/v2/inventaire/articles',                  [ArticleController::class, 'store']);
$router->get('/v2/inventaire/articles/{id}',              [ArticleController::class, 'show']);
$router->get('/v2/inventaire/articles/{id}/modifier',     [ArticleController::class, 'edit']);
$router->post('/v2/inventaire/articles/{id}/modifier',    [ArticleController::class, 'update']);
$router->post('/v2/inventaire/articles/{id}/supprimer',   [ArticleController::class, 'destroy']);
$router->get('/v2/inventaire/articles/scan',              [ArticleController::class, 'scanBarcode']);

// ─────────────────────────────────────────────────────────────
// Catégories
// ─────────────────────────────────────────────────────────────
$router->get('/v2/inventaire/categories',                 [CategorieController::class, 'index']);
$router->post('/v2/inventaire/categories',                [CategorieController::class, 'store']);
$router->post('/v2/inventaire/categories/{id}/modifier',  [CategorieController::class, 'update']);
$router->post('/v2/inventaire/categories/{id}/supprimer', [CategorieController::class, 'destroy']);

// ─────────────────────────────────────────────────────────────
// Fournisseurs
// ─────────────────────────────────────────────────────────────
$router->get('/v2/inventaire/fournisseurs',               [FournisseurController::class, 'index']);
$router->get('/v2/inventaire/fournisseurs/creer',         [FournisseurController::class, 'create']);
$router->post('/v2/inventaire/fournisseurs',              [FournisseurController::class, 'store']);
$router->get('/v2/inventaire/fournisseurs/{id}',          [FournisseurController::class, 'show']);
$router->get('/v2/inventaire/fournisseurs/{id}/modifier', [FournisseurController::class, 'edit']);
$router->post('/v2/inventaire/fournisseurs/{id}/modifier',[FournisseurController::class, 'update']);
$router->post('/v2/inventaire/fournisseurs/{id}/bloquer', [FournisseurController::class, 'bloquer']);
$router->post('/v2/inventaire/fournisseurs/{id}/supprimer',[FournisseurController::class, 'destroy']);

// ─────────────────────────────────────────────────────────────
// Commandes
// ─────────────────────────────────────────────────────────────
$router->get('/v2/inventaire/commandes',                  [CommandeController::class, 'index']);
$router->get('/v2/inventaire/commandes/creer',            [CommandeController::class, 'create']);
$router->post('/v2/inventaire/commandes',                 [CommandeController::class, 'store']);
$router->get('/v2/inventaire/commandes/{id}',             [CommandeController::class, 'show']);
$router->post('/v2/inventaire/commandes/{id}/valider',    [CommandeController::class, 'valider']);
$router->post('/v2/inventaire/commandes/{id}/annuler',    [CommandeController::class, 'annuler']);
$router->post('/v2/inventaire/commandes/{id}/supprimer',  [CommandeController::class, 'destroy']);

// ─────────────────────────────────────────────────────────────
// Réceptions
// ─────────────────────────────────────────────────────────────
$router->get('/v2/inventaire/commandes/{commandeId}/reception/creer',  [ReceptionController::class, 'create']);
$router->post('/v2/inventaire/commandes/{commandeId}/reception',       [ReceptionController::class, 'store']);

// ─────────────────────────────────────────────────────────────
// Stocks
// ─────────────────────────────────────────────────────────────
$router->get('/v2/inventaire/stocks',                     [StockController::class, 'index']);
$router->get('/v2/inventaire/stocks/mouvements',          [StockController::class, 'mouvements']);
$router->post('/v2/inventaire/stocks/ajuster',            [StockController::class, 'ajuster']);
$router->post('/v2/inventaire/stocks/transferer',         [StockController::class, 'transferer']);

// ─────────────────────────────────────────────────────────────
// Affectations
// ─────────────────────────────────────────────────────────────
$router->get('/v2/inventaire/affectations',                             [AffectationController::class, 'index']);
$router->get('/v2/inventaire/affectations/creer',                       [AffectationController::class, 'create']);
$router->post('/v2/inventaire/affectations',                            [AffectationController::class, 'store']);
$router->post('/v2/inventaire/affectations/{id}/retourner',             [AffectationController::class, 'retourner']);
$router->post('/v2/inventaire/affectations/{id}/perdu',                 [AffectationController::class, 'declararerPerdue']);

// ─────────────────────────────────────────────────────────────
// Maintenances
// ─────────────────────────────────────────────────────────────
$router->get('/v2/inventaire/maintenances',               [MaintenanceController::class, 'index']);
$router->get('/v2/inventaire/maintenances/creer',         [MaintenanceController::class, 'create']);
$router->post('/v2/inventaire/maintenances',              [MaintenanceController::class, 'store']);
$router->post('/v2/inventaire/maintenances/{id}/demarrer',[MaintenanceController::class, 'demarrer']);
$router->post('/v2/inventaire/maintenances/{id}/terminer',[MaintenanceController::class, 'terminer']);
$router->post('/v2/inventaire/maintenances/{id}/annuler', [MaintenanceController::class, 'annuler']);

// ─────────────────────────────────────────────────────────────
// Inventaires physiques
// ─────────────────────────────────────────────────────────────
$router->get('/v2/inventaire/inventaires-physiques',                        [InvPhysiqueController::class, 'index']);
$router->get('/v2/inventaire/inventaires-physiques/creer',                  [InvPhysiqueController::class, 'create']);
$router->post('/v2/inventaire/inventaires-physiques',                       [InvPhysiqueController::class, 'store']);
$router->get('/v2/inventaire/inventaires-physiques/{id}/session',           [InvPhysiqueController::class, 'session']);
$router->post('/v2/inventaire/inventaires-physiques/{id}/saisir',           [InvPhysiqueController::class, 'saisir']);
$router->post('/v2/inventaire/inventaires-physiques/{id}/cloture',          [InvPhysiqueController::class, 'cloture']);

// ─────────────────────────────────────────────────────────────
// Alertes
// ─────────────────────────────────────────────────────────────
$router->get('/v2/inventaire/alertes',                    [AlerteController::class, 'index']);
$router->post('/v2/inventaire/alertes/{id}/acquitter',    [AlerteController::class, 'acquitter']);
$router->post('/v2/inventaire/alertes/scanner',           [AlerteController::class, 'scanner']);
