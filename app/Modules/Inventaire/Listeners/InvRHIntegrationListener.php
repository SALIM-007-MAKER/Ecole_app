<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Listeners;

use Core\Event;
use Core\Listener;
use App\Modules\Inventaire\Events\AffectationCreee;
use App\Modules\Inventaire\Events\AffectationRetournee;
use App\Modules\Inventaire\Events\AffectationPerdue;

/**
 * Intégration Module RH V2.
 * AffectationCreee → profil équipement employé.
 * AffectationRetournee/Perdue → mise à jour profil.
 * Stub V2 : log uniquement.
 */
class InvRHIntegrationListener implements Listener
{
    public function handle(Event $event): void
    {
        $data = $event->toArray();

        if ($event instanceof AffectationCreee) {
            // TODO V3: EmployeService::ajouterEquipement($data['user_id'], $data['article_id'], $data['quantite'])
            error_log("[InvRH] AffectationCreee #{$data['affectation_id']} → user#{$data['user_id']} — intégration RH prévue V3");
            return;
        }

        if ($event instanceof AffectationRetournee) {
            error_log("[InvRH] AffectationRetournee #{$data['affectation_id']} → user#{$data['user_id']} retour {$data['etat']}");
            return;
        }

        if ($event instanceof AffectationPerdue) {
            error_log("[InvRH] AffectationPerdue #{$data['affectation_id']} → user#{$data['user_id']}");
        }
    }
}
