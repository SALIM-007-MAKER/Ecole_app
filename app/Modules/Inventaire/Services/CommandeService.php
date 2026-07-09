<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Services;

use App\Modules\Inventaire\DTO\CommandeDTO;
use App\Modules\Inventaire\DTO\CommandeLigneDTO;
use App\Modules\Inventaire\Repositories\CommandeRepository;
use App\Modules\Inventaire\Repositories\CommandeLigneRepository;
use App\Modules\Inventaire\Events\CommandeCreee;
use App\Modules\Inventaire\Events\CommandeValidee;
use App\Services\AuditService;
use Core\EventDispatcher;

class CommandeService
{
    private CommandeRepository      $commandes;
    private CommandeLigneRepository $lignes;

    public function __construct()
    {
        $this->commandes = new CommandeRepository();
        $this->lignes    = new CommandeLigneRepository();
    }

    public function lister(int $etablissementId, ?string $statut = null): array
    {
        return $this->commandes->all($etablissementId, $statut);
    }

    public function trouver(int $id): ?array
    {
        return $this->commandes->findById($id);
    }

    public function lignes(int $commandeId): array
    {
        return $this->lignes->byCommande($commandeId);
    }

    public function creer(CommandeDTO $dto, array $lignesData, int $userId, int $etablissementId): int
    {
        $numero = $this->commandes->generateNumero($etablissementId);

        $id = $this->commandes->create([
            ':numero'                => $numero,
            ':fournisseur_id'        => $dto->fournisseurId,
            ':date_commande'         => $dto->dateCommande,
            ':date_livraison_prevue' => $dto->dateLivraisonPrevue,
            ':total_ht'              => 0,
            ':total_ttc'             => 0,
            ':tva_taux'              => $dto->tvaTaux,
            ':notes'                 => $dto->notes,
            ':created_by'            => $userId,
            ':etablissement_id'      => $etablissementId,
        ]);

        foreach ($lignesData as $ligneData) {
            $ligne = CommandeLigneDTO::fromRequest($ligneData);
            $this->lignes->create([
                ':commande_id'       => $id,
                ':article_id'        => $ligne->articleId,
                ':quantite_commandee'=> $ligne->quantiteCommandee,
                ':prix_unitaire_ht'  => $ligne->prixUnitaireHt,
                ':tva_taux'          => $ligne->tvaTaux,
                ':total_ht'          => round($ligne->quantiteCommandee * $ligne->prixUnitaireHt, 2),
                ':notes'             => $ligne->notes,
            ]);
        }

        $this->commandes->updateTotaux($id);
        AuditService::logCreate('inv_commandes', $id, $userId, ['numero' => $numero]);
        $commande = $this->commandes->findById($id);
        EventDispatcher::dispatch(new CommandeCreee($id, $numero, $dto->fournisseurId, (float)($commande['total_ht'] ?? 0), $userId, $etablissementId));

        return $id;
    }

    public function valider(int $id, int $userId, int $etablissementId): void
    {
        $commande = $this->commandes->findById($id);
        if (!$commande) throw new \RuntimeException("Commande introuvable.");
        if ($commande['statut'] !== 'brouillon') {
            throw new \RuntimeException("Seule une commande brouillon peut être validée.");
        }

        $this->commandes->updateStatut($id, 'validee', [
            'validated_by' => $userId,
            'validated_at' => date('Y-m-d H:i:s'),
        ]);

        AuditService::log('validate', 'inv_commandes', $id, $userId, []);
        EventDispatcher::dispatch(new CommandeValidee($id, $commande['numero'], (int)$commande['fournisseur_id'], (float)$commande['total_ttc'], $userId, $etablissementId));
    }

    public function annuler(int $id, int $userId): void
    {
        $commande = $this->commandes->findById($id);
        if (!$commande) throw new \RuntimeException("Commande introuvable.");
        if (!in_array($commande['statut'], ['brouillon', 'validee', 'envoyee'], true)) {
            throw new \RuntimeException("Cette commande ne peut pas être annulée.");
        }
        $this->commandes->updateStatut($id, 'annulee');
        AuditService::log('cancel', 'inv_commandes', $id, $userId, []);
    }

    public function supprimer(int $id, int $userId): void
    {
        $commande = $this->commandes->findById($id);
        if (!$commande || $commande['statut'] !== 'brouillon') {
            throw new \RuntimeException("Seule une commande brouillon peut être supprimée.");
        }
        $this->lignes->deleteByCommande($id);
        $this->commandes->softDelete($id);
        AuditService::log('delete', 'inv_commandes', $id, $userId, []);
    }
}
