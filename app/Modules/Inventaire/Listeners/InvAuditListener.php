<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Listeners;

use Core\Event;
use Core\Listener;
use App\Modules\Inventaire\Events\ArticleAjoute;
use App\Modules\Inventaire\Events\ArticleModifie;
use App\Modules\Inventaire\Events\ArticleArchive;
use App\Modules\Inventaire\Events\FournisseurAjoute;
use App\Modules\Inventaire\Events\FournisseurBloque;
use App\Modules\Inventaire\Events\CommandeCreee;
use App\Modules\Inventaire\Events\CommandeValidee;
use App\Modules\Inventaire\Events\CommandeRecue;
use App\Modules\Inventaire\Events\StockEntree;
use App\Modules\Inventaire\Events\StockSortie;
use App\Modules\Inventaire\Events\StockTransfert;
use App\Modules\Inventaire\Events\StockAjustement;
use App\Modules\Inventaire\Events\AffectationCreee;
use App\Modules\Inventaire\Events\AffectationRetournee;
use App\Modules\Inventaire\Events\AffectationPerdue;
use App\Modules\Inventaire\Events\MaintenanceCreee;
use App\Modules\Inventaire\Events\MaintenanceTerminee;
use App\Modules\Inventaire\Events\InventaireTermine;
use App\Modules\Inventaire\Events\AmortissementCalcule;
use App\Services\AuditService;

class InvAuditListener implements Listener
{
    public function handle(Event $event): void
    {
        $data = $event->toArray();

        match(true) {
            $event instanceof ArticleAjoute       => AuditService::logCreate('inv_articles', $data['article_id'], $data['user_id'], $data),
            $event instanceof ArticleModifie      => AuditService::log('update', 'inv_articles', $data['article_id'], $data['user_id'], $data),
            $event instanceof ArticleArchive      => AuditService::log('archive', 'inv_articles', $data['article_id'], $data['user_id'], $data),
            $event instanceof FournisseurAjoute   => AuditService::logCreate('inv_fournisseurs', $data['fournisseur_id'], $data['user_id'], $data),
            $event instanceof FournisseurBloque   => AuditService::log('block', 'inv_fournisseurs', $data['fournisseur_id'], $data['user_id'], $data),
            $event instanceof CommandeCreee       => AuditService::logCreate('inv_commandes', $data['commande_id'], $data['user_id'], $data),
            $event instanceof CommandeValidee     => AuditService::log('validate', 'inv_commandes', $data['commande_id'], $data['user_id'], $data),
            $event instanceof CommandeRecue       => AuditService::log('receive', 'inv_commandes', $data['commande_id'], $data['user_id'], $data),
            $event instanceof StockEntree         => AuditService::log('stock_in', 'inv_stocks', $data['article_id'], $data['user_id'], $data),
            $event instanceof StockSortie         => AuditService::log('stock_out', 'inv_stocks', $data['article_id'], $data['user_id'], $data),
            $event instanceof StockTransfert      => AuditService::log('transfer', 'inv_stocks', $data['article_id'], $data['user_id'], $data),
            $event instanceof StockAjustement     => AuditService::log('adjust', 'inv_stocks', $data['article_id'], $data['user_id'], $data),
            $event instanceof AffectationCreee    => AuditService::logCreate('inv_affectations', $data['affectation_id'], $data['user_id'], $data),
            $event instanceof AffectationRetournee=> AuditService::log('return', 'inv_affectations', $data['affectation_id'], $data['user_id'], $data),
            $event instanceof AffectationPerdue   => AuditService::log('lost', 'inv_affectations', $data['affectation_id'], $data['user_id'], $data),
            $event instanceof MaintenanceCreee    => AuditService::logCreate('inv_maintenances', $data['maintenance_id'], $data['user_id'], $data),
            $event instanceof MaintenanceTerminee => AuditService::log('finish', 'inv_maintenances', $data['maintenance_id'], $data['user_id'], $data),
            $event instanceof InventaireTermine   => AuditService::log('cloture', 'inv_inventaires', $data['inventaire_id'], $data['created_by'], $data),
            $event instanceof AmortissementCalcule=> AuditService::log('compute', 'inv_amortissements', $data['article_id'], 0, $data),
            default => null,
        };
    }
}
