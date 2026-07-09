<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Services;

use App\Modules\Inventaire\DTO\ReceptionDTO;
use App\Modules\Inventaire\Repositories\ReceptionRepository;
use App\Modules\Inventaire\Repositories\CommandeRepository;
use App\Modules\Inventaire\Repositories\CommandeLigneRepository;
use App\Modules\Inventaire\Events\CommandeRecue;
use App\Services\AuditService;
use Core\EventDispatcher;

class ReceptionService
{
    private ReceptionRepository      $receptions;
    private CommandeRepository       $commandes;
    private CommandeLigneRepository  $lignes;
    private StockService             $stocks;

    public function __construct()
    {
        $this->receptions = new ReceptionRepository();
        $this->commandes  = new CommandeRepository();
        $this->lignes     = new CommandeLigneRepository();
        $this->stocks     = new StockService();
    }

    public function traiterReception(ReceptionDTO $dto, int $userId, int $etablissementId): int
    {
        $commande = $this->commandes->findById($dto->commandeId);
        if (!$commande) throw new \RuntimeException("Commande introuvable.");
        if (!in_array($commande['statut'], ['validee', 'envoyee', 'partiellement_recue'], true)) {
            throw new \RuntimeException("Commande non réceptionnable dans son état actuel.");
        }

        $receptionId = $this->receptions->create([
            ':commande_id'     => $dto->commandeId,
            ':date_reception'  => $dto->dateReception,
            ':bon_livraison'   => $dto->bonLivraison,
            ':notes'           => $dto->notes,
            ':created_by'      => $userId,
            ':etablissement_id'=> $etablissementId,
        ]);

        foreach ($dto->lignes as $ligne) {
            $ligneId      = (int)$ligne['commande_ligne_id'];
            $articleId    = (int)$ligne['article_id'];
            $qte          = (float)$ligne['quantite_recue'];
            $emplacementId= (int)$ligne['emplacement_id'];

            $this->receptions->addLigne([
                ':reception_id'       => $receptionId,
                ':commande_ligne_id'  => $ligneId,
                ':article_id'         => $articleId,
                ':quantite_recue'     => $qte,
                ':emplacement_id'     => $emplacementId,
                ':notes'              => $ligne['notes'] ?? null,
            ]);

            $this->lignes->updateQuantiteRecue($ligneId, $qte);
            $this->stocks->entree($articleId, $qte, $emplacementId, 'reception', $receptionId, $userId, $etablissementId);
        }

        // Vérifie la complétude sur les quantités réelles en base
        $lignesActuelles = $this->lignes->byCommande($dto->commandeId);
        $complete        = true;
        foreach ($lignesActuelles as $la) {
            if ((float)$la['quantite_recue'] < (float)$la['quantite_commandee']) {
                $complete = false;
                break;
            }
        }

        $newStatut = $complete ? 'recue' : 'partiellement_recue';
        $this->commandes->updateStatut($dto->commandeId, $newStatut);

        AuditService::logCreate('inv_receptions', $receptionId, $userId, ['commande_id' => $dto->commandeId]);
        EventDispatcher::dispatch(new CommandeRecue($dto->commandeId, $receptionId, $complete, $userId, $etablissementId));

        return $receptionId;
    }

    public function byCommande(int $commandeId): array
    {
        return $this->receptions->byCommande($commandeId);
    }

    public function trouver(int $id): ?array
    {
        return $this->receptions->findById($id);
    }
}
