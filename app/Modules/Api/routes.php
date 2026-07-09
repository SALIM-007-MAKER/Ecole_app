<?php
declare(strict_types=1);

/**
 * Routes API V1 — Platform API
 * Préfixe: /api
 */

use App\Modules\Api\Controllers\Auth\LoginController;
use App\Modules\Api\Controllers\Auth\ApiKeyController;
use App\Modules\Api\Controllers\V1\HealthController;
use App\Modules\Api\Controllers\V1\SearchController;
use App\Modules\Api\Controllers\V1\UploadController;
use App\Modules\Api\Controllers\V1\WebhookApiController;
use App\Modules\Api\Controllers\V1\Scolarite\EleveApiController;
use App\Modules\Api\Controllers\V1\Scolarite\ClasseApiController;
use App\Modules\Api\Controllers\V1\Scolarite\InscriptionApiController;
use App\Modules\Api\Controllers\V1\Scolarite\MatiereApiController;
use App\Modules\Api\Controllers\V1\Academique\PeriodeApiController;
use App\Modules\Api\Controllers\V1\Academique\NoteApiController;
use App\Modules\Api\Controllers\V1\Academique\BulletinApiController;
use App\Modules\Api\Controllers\V1\Academique\ClassementApiController;
use App\Modules\Api\Controllers\V1\Finance\FactureApiController;
use App\Modules\Api\Controllers\V1\Finance\PaiementApiController;
use App\Modules\Api\Controllers\V1\Finance\CaisseApiController;
use App\Modules\Api\Controllers\V1\VieScolaire\AbsenceApiController;
use App\Modules\Api\Controllers\V1\VieScolaire\EmploiDuTempsApiController;
use App\Modules\Api\Controllers\V1\VieScolaire\ActiviteApiController;
use App\Modules\Api\Controllers\V1\RH\EmployeApiController;
use App\Modules\Api\Controllers\V1\RH\CongeApiController;
use App\Modules\Api\Controllers\V1\RH\FormationApiController;
use App\Modules\Api\Controllers\V1\Documents\DocumentApiController;
use App\Modules\Api\Controllers\V1\Communication\NotificationApiController;
use App\Modules\Api\Controllers\V1\Bibliotheque\LivreApiController;
use App\Modules\Api\Controllers\V1\Bibliotheque\EmpruntApiController;
use App\Modules\Api\Controllers\V1\Inventaire\ArticleApiController;
use App\Modules\Api\Controllers\V1\Rapports\RapportApiController;
use App\Modules\Api\Controllers\Documentation\SwaggerController;

// ── Documentation ─────────────────────────────────────────────────────────────
$router->get('/api/docs',              SwaggerController::class . '@ui');
$router->get('/api/docs/openapi.json', SwaggerController::class . '@spec');

// ── Health ────────────────────────────────────────────────────────────────────
$router->get('/api/v1/health', HealthController::class . '@check');

// ── Auth ─────────────────────────────────────────────────────────────────────
$router->post('/api/v1/auth/login',   LoginController::class . '@login');
$router->post('/api/v1/auth/refresh', LoginController::class . '@refresh');
$router->post('/api/v1/auth/logout',  LoginController::class . '@logout');

// API Keys
$router->get('/api/v1/api-keys',        ApiKeyController::class . '@index');
$router->post('/api/v1/api-keys',       ApiKeyController::class . '@store');
$router->delete('/api/v1/api-keys/{id}', ApiKeyController::class . '@destroy');

// ── Scolarité ─────────────────────────────────────────────────────────────────
$router->get('/api/v1/eleves',         EleveApiController::class . '@index');
$router->post('/api/v1/eleves',        EleveApiController::class . '@store');
$router->get('/api/v1/eleves/{id}',    EleveApiController::class . '@show');
$router->put('/api/v1/eleves/{id}',    EleveApiController::class . '@update');
$router->delete('/api/v1/eleves/{id}', EleveApiController::class . '@destroy');

$router->get('/api/v1/classes',              ClasseApiController::class . '@index');
$router->get('/api/v1/classes/{id}',         ClasseApiController::class . '@show');
$router->get('/api/v1/classes/{id}/eleves',  ClasseApiController::class . '@eleves');

$router->get('/api/v1/inscriptions',      InscriptionApiController::class . '@index');
$router->get('/api/v1/inscriptions/{id}', InscriptionApiController::class . '@show');

$router->get('/api/v1/matieres',      MatiereApiController::class . '@index');
$router->get('/api/v1/matieres/{id}', MatiereApiController::class . '@show');

// ── Académique ────────────────────────────────────────────────────────────────
$router->get('/api/v1/periodes',      PeriodeApiController::class . '@index');
$router->get('/api/v1/periodes/{id}', PeriodeApiController::class . '@show');

$router->get('/api/v1/notes',         NoteApiController::class . '@index');
$router->get('/api/v1/notes/{id}',    NoteApiController::class . '@show');
$router->post('/api/v1/notes/batch',  NoteApiController::class . '@batch');

$router->get('/api/v1/bulletins',          BulletinApiController::class . '@index');
$router->get('/api/v1/bulletins/{id}',     BulletinApiController::class . '@show');
$router->get('/api/v1/bulletins/{id}/export', BulletinApiController::class . '@export');

$router->get('/api/v1/classements/classe/{classeId}', ClassementApiController::class . '@parClasse');
$router->get('/api/v1/classements/global',            ClassementApiController::class . '@global');

// ── Finance ───────────────────────────────────────────────────────────────────
$router->get('/api/v1/factures',          FactureApiController::class . '@index');
$router->get('/api/v1/factures/impayes',  FactureApiController::class . '@impayes');
$router->get('/api/v1/factures/{id}',     FactureApiController::class . '@show');

$router->get('/api/v1/paiements',       PaiementApiController::class . '@index');
$router->get('/api/v1/paiements/stats', PaiementApiController::class . '@stats');
$router->get('/api/v1/paiements/{id}',  PaiementApiController::class . '@show');

$router->get('/api/v1/caisse/solde',      CaisseApiController::class . '@solde');
$router->get('/api/v1/caisse/mouvements', CaisseApiController::class . '@mouvements');

// ── Vie Scolaire ──────────────────────────────────────────────────────────────
$router->get('/api/v1/absences',        AbsenceApiController::class . '@index');
$router->get('/api/v1/absences/stats',  AbsenceApiController::class . '@stats');
$router->get('/api/v1/absences/{id}',   AbsenceApiController::class . '@show');

$router->get('/api/v1/emplois-du-temps', EmploiDuTempsApiController::class . '@index');

$router->get('/api/v1/activites',      ActiviteApiController::class . '@index');
$router->get('/api/v1/activites/{id}', ActiviteApiController::class . '@show');

// ── RH ───────────────────────────────────────────────────────────────────────
$router->get('/api/v1/employes',      EmployeApiController::class . '@index');
$router->get('/api/v1/employes/{id}', EmployeApiController::class . '@show');

$router->get('/api/v1/conges',      CongeApiController::class . '@index');
$router->get('/api/v1/conges/{id}', CongeApiController::class . '@show');

$router->get('/api/v1/formations',      FormationApiController::class . '@index');
$router->get('/api/v1/formations/{id}', FormationApiController::class . '@show');

// ── Documents ─────────────────────────────────────────────────────────────────
$router->get('/api/v1/documents',                  DocumentApiController::class . '@index');
$router->get('/api/v1/documents/{id}',             DocumentApiController::class . '@show');
$router->get('/api/v1/documents/{id}/download',    DocumentApiController::class . '@download');

$router->post('/api/v1/upload',          UploadController::class . '@store');
$router->get('/api/v1/files/{slug}',     UploadController::class . '@download');

// ── Communication ─────────────────────────────────────────────────────────────
$router->get('/api/v1/notifications',            NotificationApiController::class . '@index');
$router->get('/api/v1/notifications/non-lues',   NotificationApiController::class . '@nonLues');
$router->patch('/api/v1/notifications/{id}/lire', NotificationApiController::class . '@marquerLue');
$router->patch('/api/v1/notifications/lire-tout', NotificationApiController::class . '@marquerToutesLues');

// ── Bibliothèque ──────────────────────────────────────────────────────────────
$router->get('/api/v1/livres',      LivreApiController::class . '@index');
$router->get('/api/v1/livres/{id}', LivreApiController::class . '@show');

$router->get('/api/v1/emprunts',           EmpruntApiController::class . '@index');
$router->get('/api/v1/emprunts/en-retard', EmpruntApiController::class . '@enRetard');

// ── Inventaire ────────────────────────────────────────────────────────────────
$router->get('/api/v1/articles',               ArticleApiController::class . '@index');
$router->get('/api/v1/articles/alertes-stock', ArticleApiController::class . '@alertesStock');
$router->get('/api/v1/articles/{id}',          ArticleApiController::class . '@show');

// ── Rapports & BI ─────────────────────────────────────────────────────────────
$router->get('/api/v1/rapports',           RapportApiController::class . '@index');
$router->get('/api/v1/rapports/dashboard', RapportApiController::class . '@dashboard');
$router->get('/api/v1/rapports/{id}',      RapportApiController::class . '@show');

// ── Webhooks ──────────────────────────────────────────────────────────────────
$router->get('/api/v1/webhooks',                         WebhookApiController::class . '@index');
$router->post('/api/v1/webhooks',                        WebhookApiController::class . '@store');
$router->delete('/api/v1/webhooks/{id}',                 WebhookApiController::class . '@destroy');
$router->get('/api/v1/webhooks/{id}/deliveries',         WebhookApiController::class . '@deliveries');

// ── Recherche ─────────────────────────────────────────────────────────────────
$router->get('/api/v1/search', SearchController::class . '@search');
