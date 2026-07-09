<?php

declare(strict_types=1);

use Core\Router;
use App\Modules\Bibliotheque\Controllers\AnalyticsController;
use App\Modules\Bibliotheque\Controllers\CatalogueController;
use App\Modules\Bibliotheque\Controllers\EmpruntController;
use App\Modules\Bibliotheque\Controllers\ExemplaireController;
use App\Modules\Bibliotheque\Controllers\InventaireController;
use App\Modules\Bibliotheque\Controllers\MonCompteController;
use App\Modules\Bibliotheque\Controllers\PenaliteController;
use App\Modules\Bibliotheque\Controllers\ReferentielController;
use App\Modules\Bibliotheque\Controllers\ReservationController;

/* ───── Catalogue ───── */
$router->get('/v2/bibliotheque/catalogue', CatalogueController::class . '@index');
$router->get('/v2/bibliotheque/catalogue/search', CatalogueController::class . '@search');
$router->get('/v2/bibliotheque/catalogue/create', CatalogueController::class . '@create');
$router->post('/v2/bibliotheque/catalogue', CatalogueController::class . '@store');
$router->get('/v2/bibliotheque/catalogue/{id}', CatalogueController::class . '@show');
$router->get('/v2/bibliotheque/catalogue/{id}/edit', CatalogueController::class . '@edit');
$router->post('/v2/bibliotheque/catalogue/{id}/update', CatalogueController::class . '@update');
$router->post('/v2/bibliotheque/catalogue/{id}/archive', CatalogueController::class . '@archive');

/* ───── Exemplaires ───── */
$router->get('/v2/bibliotheque/exemplaires/{ouvrageId}', ExemplaireController::class . '@index');
$router->post('/v2/bibliotheque/exemplaires/{ouvrageId}', ExemplaireController::class . '@store');
$router->post('/v2/bibliotheque/exemplaires/{id}/update', ExemplaireController::class . '@update');
$router->post('/v2/bibliotheque/exemplaires/{id}/archive', ExemplaireController::class . '@archive');
$router->post('/v2/bibliotheque/exemplaires/{id}/statut', ExemplaireController::class . '@changerStatut');
$router->get('/v2/bibliotheque/exemplaires/{id}/qrcode', ExemplaireController::class . '@qrCode');
$router->get('/v2/bibliotheque/exemplaires/{id}/barcode', ExemplaireController::class . '@barcode');

/* ───── Emprunts ───── */
$router->get('/v2/bibliotheque/emprunts', EmpruntController::class . '@index');
$router->get('/v2/bibliotheque/emprunts/en-retard', EmpruntController::class . '@enRetard');
$router->get('/v2/bibliotheque/emprunts/create', EmpruntController::class . '@create');
$router->post('/v2/bibliotheque/emprunts', EmpruntController::class . '@store');
$router->get('/v2/bibliotheque/emprunts/{id}', EmpruntController::class . '@show');
$router->post('/v2/bibliotheque/emprunts/{id}/retour', EmpruntController::class . '@retour');
$router->post('/v2/bibliotheque/emprunts/{id}/prolonger', EmpruntController::class . '@prolonger');
$router->post('/v2/bibliotheque/emprunts/{id}/perdu', EmpruntController::class . '@declarerPerdu');
$router->get('/v2/bibliotheque/emprunts/cron/retards', EmpruntController::class . '@cronRetards');
$router->get('/v2/bibliotheque/emprunts/cron/rappels', EmpruntController::class . '@cronRappels');

/* ───── Réservations ───── */
$router->get('/v2/bibliotheque/reservations', ReservationController::class . '@index');
$router->get('/v2/bibliotheque/reservations/mes', ReservationController::class . '@mes');
$router->post('/v2/bibliotheque/reservations', ReservationController::class . '@store');
$router->get('/v2/bibliotheque/reservations/{id}', ReservationController::class . '@show');
$router->post('/v2/bibliotheque/reservations/{id}/confirmer', ReservationController::class . '@confirmer');
$router->post('/v2/bibliotheque/reservations/{id}/annuler', ReservationController::class . '@annuler');
$router->get('/v2/bibliotheque/reservations/cron/expirer', ReservationController::class . '@cronExpirer');

/* ───── Pénalités ───── */
$router->get('/v2/bibliotheque/penalites', PenaliteController::class . '@index');
$router->get('/v2/bibliotheque/penalites/mes', PenaliteController::class . '@mes');
$router->get('/v2/bibliotheque/penalites/{id}', PenaliteController::class . '@show');
$router->post('/v2/bibliotheque/penalites/{id}/payer', PenaliteController::class . '@payer');
$router->post('/v2/bibliotheque/penalites/{id}/annuler', PenaliteController::class . '@annuler');

/* ───── Inventaire ───── */
$router->get('/v2/bibliotheque/inventaire', InventaireController::class . '@index');
$router->post('/v2/bibliotheque/inventaire', InventaireController::class . '@store');
$router->get('/v2/bibliotheque/inventaire/{id}', InventaireController::class . '@show');
$router->post('/v2/bibliotheque/inventaire/{id}/scan', InventaireController::class . '@scan');
$router->post('/v2/bibliotheque/inventaire/{id}/terminer', InventaireController::class . '@terminer');

/* ───── Analytics ───── */
$router->get('/v2/bibliotheque/analytics', AnalyticsController::class . '@dashboard');
$router->get('/v2/bibliotheque/analytics/export', AnalyticsController::class . '@export');

/* ───── Référentiels ───── */
$router->get('/v2/bibliotheque/referentiels', ReferentielController::class . '@index');
$router->post('/v2/bibliotheque/referentiels/auteurs', ReferentielController::class . '@storeAuteur');
$router->post('/v2/bibliotheque/referentiels/auteurs/{id}/update', ReferentielController::class . '@updateAuteur');
$router->post('/v2/bibliotheque/referentiels/auteurs/{id}/archive', ReferentielController::class . '@archiveAuteur');
$router->post('/v2/bibliotheque/referentiels/editeurs', ReferentielController::class . '@storeEditeur');
$router->post('/v2/bibliotheque/referentiels/editeurs/{id}/update', ReferentielController::class . '@updateEditeur');
$router->post('/v2/bibliotheque/referentiels/editeurs/{id}/archive', ReferentielController::class . '@archiveEditeur');
$router->post('/v2/bibliotheque/referentiels/categories', ReferentielController::class . '@storeCategorie');
$router->post('/v2/bibliotheque/referentiels/categories/{id}/update', ReferentielController::class . '@updateCategorie');
$router->post('/v2/bibliotheque/referentiels/categories/{id}/archive', ReferentielController::class . '@archiveCategorie');
$router->post('/v2/bibliotheque/referentiels/tags', ReferentielController::class . '@storeTag');

/* ───── Mon compte ───── */
$router->get('/v2/bibliotheque/mon-compte', MonCompteController::class . '@index');
$router->get('/v2/bibliotheque/mon-compte/historique', MonCompteController::class . '@historique');
$router->get('/v2/bibliotheque/mon-compte/penalites', MonCompteController::class . '@mesPenalites');
