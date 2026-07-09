<?php

declare(strict_types=1);

use Core\Router;
use App\Modules\Documents\Controllers\DocumentController;
use App\Modules\Documents\Controllers\FolderController;
use App\Modules\Documents\Controllers\TrashController;
use App\Modules\Documents\Controllers\ShareController;
use App\Modules\Documents\Controllers\SearchController;
use App\Modules\Documents\Controllers\TagController;
use App\Modules\Documents\Controllers\PreviewController;
use App\Modules\Documents\Controllers\SignatureController;
use App\Modules\Documents\Controllers\AdminController;

// ── Documents ─────────────────────────────────────────────────────────────────
$router->get('/v2/documents', DocumentController::class . '@index');
$router->get('/v2/documents/create', DocumentController::class . '@create');
$router->post('/v2/documents', DocumentController::class . '@store');
$router->get('/v2/documents/{id}', DocumentController::class . '@show');
$router->get('/v2/documents/{id}/edit', DocumentController::class . '@edit');
$router->post('/v2/documents/{id}', DocumentController::class . '@update');
$router->post('/v2/documents/{id}/archive', DocumentController::class . '@archive');
$router->post('/v2/documents/{id}/restore', DocumentController::class . '@restore');
$router->get('/v2/documents/{id}/download', DocumentController::class . '@download');
$router->post('/v2/documents/{id}/version', DocumentController::class . '@version');

// ── Fichiers (servi sécurisé) ─────────────────────────────────────────────────
$router->get('/v2/documents/file/{filename}', PreviewController::class . '@serveFile');

// ── Aperçu ────────────────────────────────────────────────────────────────────
$router->get('/v2/documents/{id}/preview', PreviewController::class . '@show');

// ── Partages ──────────────────────────────────────────────────────────────────
$router->get('/v2/documents/{id}/shares', ShareController::class . '@index');
$router->post('/v2/documents/{id}/shares', ShareController::class . '@store');
$router->post('/v2/shares/{id}/revoke', ShareController::class . '@revoke');
$router->get('/v2/share/{token}', ShareController::class . '@accessByToken');

// ── Signatures ────────────────────────────────────────────────────────────────
$router->get('/v2/documents/{id}/signatures', SignatureController::class . '@index');
$router->post('/v2/documents/{id}/signatures/request', SignatureController::class . '@request');
$router->post('/v2/signatures/{token}/sign', SignatureController::class . '@sign');
$router->post('/v2/signatures/{token}/reject', SignatureController::class . '@reject');

// ── Dossiers ──────────────────────────────────────────────────────────────────
$router->get('/v2/folders', FolderController::class . '@index');
$router->post('/v2/folders', FolderController::class . '@store');
$router->post('/v2/folders/{id}', FolderController::class . '@update');
$router->post('/v2/folders/{id}/delete', FolderController::class . '@destroy');
$router->get('/v2/folders/{id}/breadcrumb', FolderController::class . '@breadcrumb');

// ── Corbeille ─────────────────────────────────────────────────────────────────
$router->get('/v2/trash', TrashController::class . '@index');
$router->post('/v2/trash/{id}', TrashController::class . '@trash');
$router->post('/v2/trash/{id}/restore', TrashController::class . '@restore');
$router->post('/v2/trash/{id}/purge', TrashController::class . '@purge');
$router->post('/v2/trash/purge-all', TrashController::class . '@purgeAll');

// ── Recherche ─────────────────────────────────────────────────────────────────
$router->get('/v2/documents/search', SearchController::class . '@index');
$router->get('/v2/documents/search/suggestions', SearchController::class . '@suggestions');

// ── Tags ──────────────────────────────────────────────────────────────────────
$router->get('/v2/tags', TagController::class . '@index');
$router->post('/v2/tags', TagController::class . '@store');
$router->post('/v2/documents/{id}/tags', TagController::class . '@attach');
$router->post('/v2/documents/{id}/tags/sync', TagController::class . '@sync');

// ── Administration ────────────────────────────────────────────────────────────
$router->get('/v2/documents/admin/quotas', AdminController::class . '@quotas');
$router->post('/v2/documents/admin/quota/{module}', AdminController::class . '@updateQuota');
$router->get('/v2/documents/admin/expirations', AdminController::class . '@expirations');
$router->post('/v2/documents/admin/expire-check', AdminController::class . '@runExpireCheck');
$router->get('/v2/documents/admin/statistics', AdminController::class . '@statistics');
