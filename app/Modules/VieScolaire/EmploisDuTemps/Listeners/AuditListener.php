<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\Listeners;

use App\Services\AuditService;
use Core\Event;
use Core\Listener;

class AuditListener implements Listener
{
    public function handle(Event $event): void
    {
        $class = get_class($event);
        $name  = class_basename($class);

        $entityId   = $event->edtId    ?? $event->remplacementId ?? $event->creneauId ?? 0;
        $entityType = match (true) {
            str_ends_with($class, 'TeacherReplacementAssigned') => 'remplacement',
            default                                              => 'emploi_du_temps',
        };

        $details = [];
        if (isset($event->action))        $details['action']  = $event->action;
        if (isset($event->typeConflit))   $details['conflit'] = $event->typeConflit;
        if (isset($event->version))       $details['version'] = $event->version;
        if (isset($event->details))       $details['details'] = $event->details;

        $userId = $event->creeParId    ?? $event->modifieParId  ??
                  $event->publieParId  ?? $event->detecteParId  ?? 0;

        AuditService::log($userId, $name, $entityType, $entityId, $details);
    }
}
