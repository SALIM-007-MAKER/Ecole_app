<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Listeners;

use App\Services\AuditService;
use Core\Event;
use Core\Listener;
use App\Modules\Bibliotheque\Events\OuvrageAjoute;
use App\Modules\Bibliotheque\Events\OuvrageModifie;
use App\Modules\Bibliotheque\Events\OuvrageArchive;
use App\Modules\Bibliotheque\Events\ExemplaireAjoute;
use App\Modules\Bibliotheque\Events\ExemplaireStatutChange;
use App\Modules\Bibliotheque\Events\EmpruntCree;
use App\Modules\Bibliotheque\Events\EmpruntRetourne;
use App\Modules\Bibliotheque\Events\EmpruntEnRetard;
use App\Modules\Bibliotheque\Events\EmpruntProlonge;
use App\Modules\Bibliotheque\Events\EmpruntPerdu;
use App\Modules\Bibliotheque\Events\ReservationCree;
use App\Modules\Bibliotheque\Events\ReservationDisponible;
use App\Modules\Bibliotheque\Events\ReservationConfirmee;
use App\Modules\Bibliotheque\Events\ReservationAnnulee;
use App\Modules\Bibliotheque\Events\ReservationExpiree;
use App\Modules\Bibliotheque\Events\PenaliteCreee;
use App\Modules\Bibliotheque\Events\PenalitePayee;
use App\Modules\Bibliotheque\Events\InventaireTermine;

class AuditListener implements Listener
{
    public function handle(Event $event): void
    {
        $data = $event->toArray();

        match(true) {
            $event instanceof OuvrageAjoute          => AuditService::logCreate('biblio_ouvrage', $data['ouvrage_id'], $data['user_id'], $data),
            $event instanceof OuvrageModifie         => AuditService::log('update', 'biblio_ouvrage', $data['ouvrage_id'], $data['user_id'], $data),
            $event instanceof OuvrageArchive         => AuditService::log('archive', 'biblio_ouvrage', $data['ouvrage_id'], $data['user_id'], $data),
            $event instanceof ExemplaireAjoute       => AuditService::logCreate('biblio_exemplaire', $data['exemplaire_id'], $data['user_id'], $data),
            $event instanceof ExemplaireStatutChange => AuditService::log('status_change', 'biblio_exemplaire', $data['exemplaire_id'], $data['user_id'], $data),
            $event instanceof EmpruntCree            => AuditService::logCreate('biblio_emprunt', $data['emprunt_id'], $data['created_by'], $data),
            $event instanceof EmpruntRetourne        => AuditService::log('retour', 'biblio_emprunt', $data['emprunt_id'], $data['user_id'], $data),
            $event instanceof EmpruntEnRetard        => AuditService::log('retard', 'biblio_emprunt', $data['emprunt_id'], 0, $data),
            $event instanceof EmpruntProlonge        => AuditService::log('prolongation', 'biblio_emprunt', $data['emprunt_id'], $data['user_id'], $data),
            $event instanceof EmpruntPerdu           => AuditService::log('perdu', 'biblio_emprunt', $data['emprunt_id'], $data['created_by'], $data),
            $event instanceof ReservationCree        => AuditService::logCreate('biblio_reservation', $data['reservation_id'], $data['user_id'], $data),
            $event instanceof ReservationDisponible  => AuditService::log('disponible', 'biblio_reservation', $data['reservation_id'], 0, $data),
            $event instanceof ReservationConfirmee   => AuditService::log('confirmation', 'biblio_reservation', $data['reservation_id'], $data['user_id'], $data),
            $event instanceof ReservationAnnulee     => AuditService::log('annulation', 'biblio_reservation', $data['reservation_id'], $data['user_id'], $data),
            $event instanceof ReservationExpiree     => AuditService::log('expiration', 'biblio_reservation', $data['reservation_id'], 0, $data),
            $event instanceof PenaliteCreee          => AuditService::logCreate('biblio_penalite', $data['penalite_id'], $data['user_id'], $data),
            $event instanceof PenalitePayee          => AuditService::log('paiement', 'biblio_penalite', $data['penalite_id'], $data['user_id'], $data),
            $event instanceof InventaireTermine      => AuditService::log('cloture', 'biblio_inventaire', $data['inventaire_id'], $data['created_by'], $data),
            default => null,
        };
    }
}
