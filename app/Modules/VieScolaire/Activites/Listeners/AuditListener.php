<?php

namespace App\Modules\VieScolaire\Activites\Listeners;

use App\Services\AuditService;
use Core\Event;
use Core\Listener;

class AuditListener implements Listener
{
    public function handle(Event $event): void
    {
        $class  = get_class($event);
        $name   = substr($class, strrpos($class, '\\') + 1);

        $entityId   = $event->activityId ?? $event->inscriptionId ?? 0;
        $entityType = str_ends_with($class, 'StudentRegisteredToActivity')
            ? 'activite_inscription'
            : 'activite';

        $details = [];
        if (isset($event->action))        $details['action']   = $event->action;
        if (isset($event->titre))         $details['titre']    = $event->titre;
        if (isset($event->statut))        $details['statut']   = $event->statut;
        if (isset($event->motif))         $details['motif']    = $event->motif;
        if (isset($event->categorieId))   $details['categorie']= $event->categorieId;

        $userId = $event->creeParId   ?? $event->modifieParId  ??
                  $event->publieParId ?? $event->annuleParId   ??
                  $event->inscritParId ?? 0;

        AuditService::log($userId, $name, $entityType, $entityId, $details);
    }
}
