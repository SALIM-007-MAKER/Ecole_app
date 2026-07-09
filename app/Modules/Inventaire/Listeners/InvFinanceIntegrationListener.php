<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Listeners;

use Core\Event;
use Core\Listener;
use App\Modules\Inventaire\Events\CommandeValidee;
use App\Modules\Inventaire\Events\AmortissementCalcule;

/**
 * Intégration Module Finance V2.
 * CommandeValidee → prépare une facture fournisseur (Finance module à activer).
 * AmortissementCalcule → prépare une écriture comptable dotation.
 * Stub V2 : log uniquement, service Finance appelé V3 quand les 2 modules sont actifs.
 */
class InvFinanceIntegrationListener implements Listener
{
    public function handle(Event $event): void
    {
        $data = $event->toArray();

        if ($event instanceof CommandeValidee) {
            // TODO V3: FactureService::creerDepuisCommande($data['commande_id'], $data['total_ttc'], $data['fournisseur_id'])
            error_log("[InvFinance] CommandeValidee #{$data['commande_id']} — TTC={$data['total_ttc']} — intégration Finance prévue V3");
            return;
        }

        if ($event instanceof AmortissementCalcule) {
            // TODO V3: ComptabiliteService::dotationAmortissement($data['article_id'], $data['montant_dotation'])
            error_log("[InvFinance] AmortissementCalcule article#{$data['article_id']} — dotation={$data['montant_dotation']}");
        }
    }
}
