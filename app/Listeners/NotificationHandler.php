<?php

namespace App\Listeners;

use Core\Event;
use Core\Listener;
use App\Services\NotificationService;
use App\Events\PaiementValide;
use App\Events\NoteAjoutee;
use App\Events\AbsenceCreee;
use App\Models\PeriodeModel;

class NotificationHandler implements Listener
{
    private NotificationService $notif;

    public function __construct()
    {
        $this->notif = new NotificationService();
    }

    public function handle(Event $event): void
    {
        match (true) {

            $event instanceof AbsenceCreee => $event->isAbsenceOuRetard()
                ? $this->notif->onAbsence($event->eleveId, $event->date, $event->statut)
                : null,

            $event instanceof PaiementValide =>
                $this->notif->onPaiement($event->eleveId, $event->montant, $event->fraisNom),

            // Notification envoyée uniquement quand la période est publiée
            $event instanceof NoteAjoutee => ($event->periodePubliee && !$event->isBatch())
                ? $this->notif->onNote(
                    $event->eleveId,
                    $this->getPeriodeNom($event->periodeId),
                    $event->moyenneGenerale
                )
                : null,

            default => null,
        };
    }

    private function getPeriodeNom(int $periodeId): string
    {
        $periode = (new PeriodeModel())->findById($periodeId);
        return $periode?->nom ?? "Période #{$periodeId}";
    }
}
