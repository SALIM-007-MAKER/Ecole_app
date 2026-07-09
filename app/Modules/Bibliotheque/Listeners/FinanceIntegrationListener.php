<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Listeners;

use App\Modules\Bibliotheque\Services\PenaliteService;
use Core\Event;
use Core\Listener;

class FinanceIntegrationListener implements Listener
{
    public function handle(Event $event): void
    {
        $data = $event->toArray();

        if (!isset($data['facture_id'])) return;

        $penaliteService = new PenaliteService();
        $penalite = $penaliteService->findByFactureId((int)$data['facture_id']);

        if ($penalite !== null && (string)($penalite['statut'] ?? '') === 'impayee') {
            $penaliteService->marquerPayeeViaFinance((int)$penalite['id']);
        }
    }
}
