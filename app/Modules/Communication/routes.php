<?php

declare(strict_types=1);

use Core\Router;
use App\Modules\Communication\Controllers\NotificationController;
use App\Modules\Communication\Controllers\ThreadController;
use App\Modules\Communication\Controllers\TemplateController;
use App\Modules\Communication\Controllers\DiffusionController;
use App\Modules\Communication\Controllers\PreferenceController;
use App\Modules\Communication\Controllers\CampagneController;
use App\Modules\Communication\Controllers\AdminController;

// ─── Notifications ──────────────────────────────────────────────────────────
$router->get('/v2/notifications', NotificationController::class . '@index');
$router->get('/v2/notifications/panel', NotificationController::class . '@panel');
$router->post('/v2/notifications/{id}/read', NotificationController::class . '@markRead');
$router->post('/v2/notifications/read-all', NotificationController::class . '@markAllRead');
$router->delete('/v2/notifications/{id}', NotificationController::class . '@archive');
$router->get('/v2/notifications/preferences', PreferenceController::class . '@index');
$router->post('/v2/notifications/preferences', PreferenceController::class . '@update');

// ─── Push tokens ────────────────────────────────────────────────────────────
$router->post('/v2/notifications/push/subscribe', NotificationController::class . '@pushSubscribe');
$router->post('/v2/notifications/push/unsubscribe', NotificationController::class . '@pushUnsubscribe');

// ─── Messagerie interne ──────────────────────────────────────────────────────
$router->get('/v2/messages', ThreadController::class . '@index');
$router->get('/v2/messages/create', ThreadController::class . '@create');
$router->post('/v2/messages', ThreadController::class . '@store');
$router->get('/v2/messages/{id}', ThreadController::class . '@show');
$router->post('/v2/messages/{id}/reply', ThreadController::class . '@reply');
$router->post('/v2/messages/{id}/archive', ThreadController::class . '@archive');
$router->post('/v2/messages/{id}/read', ThreadController::class . '@markRead');

// ─── Diffusion ───────────────────────────────────────────────────────────────
$router->get('/v2/communication/groupes', DiffusionController::class . '@indexGroupes');
$router->post('/v2/communication/groupes', DiffusionController::class . '@storeGroupe');
$router->post('/v2/communication/groupes/{id}/membres', DiffusionController::class . '@ajouterMembre');
$router->delete('/v2/communication/groupes/{id}/membres/{userId}', DiffusionController::class . '@retirerMembre');
$router->delete('/v2/communication/groupes/{id}', DiffusionController::class . '@deleteGroupe');
$router->get('/v2/communication/diffuser', DiffusionController::class . '@composer');
$router->post('/v2/communication/diffuser', DiffusionController::class . '@diffuser');

// ─── Templates ───────────────────────────────────────────────────────────────
$router->get('/v2/communication/templates', TemplateController::class . '@index');
$router->get('/v2/communication/templates/create', TemplateController::class . '@create');
$router->post('/v2/communication/templates', TemplateController::class . '@store');
$router->get('/v2/communication/templates/{id}', TemplateController::class . '@edit');
$router->post('/v2/communication/templates/{id}', TemplateController::class . '@update');
$router->delete('/v2/communication/templates/{id}', TemplateController::class . '@delete');
$router->get('/v2/communication/templates/{id}/preview', TemplateController::class . '@preview');

// ─── Campagnes ───────────────────────────────────────────────────────────────
$router->get('/v2/communication/campagnes', CampagneController::class . '@index');
$router->post('/v2/communication/campagnes', CampagneController::class . '@store');
$router->get('/v2/communication/campagnes/{id}', CampagneController::class . '@show');
$router->post('/v2/communication/campagnes/{id}/launch', CampagneController::class . '@launch');
$router->post('/v2/communication/campagnes/{id}/cancel', CampagneController::class . '@cancel');

// ─── Administration ───────────────────────────────────────────────────────────
$router->get('/v2/communication/admin', AdminController::class . '@dashboard');
$router->post('/v2/communication/admin/queue/process', AdminController::class . '@processQueue');
$router->post('/v2/communication/admin/queue/{id}/retry', AdminController::class . '@retryJob');
$router->post('/v2/communication/admin/queue/retry-failed', AdminController::class . '@retryFailed');
$router->get('/v2/communication/admin/logs', AdminController::class . '@logs');
