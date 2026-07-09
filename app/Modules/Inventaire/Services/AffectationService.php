<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Services;

use App\Modules\Inventaire\DTO\AffectationDTO;
use App\Modules\Inventaire\Repositories\AffectationRepository;
use App\Modules\Inventaire\Events\AffectationCreee;
use App\Modules\Inventaire\Events\AffectationRetournee;
use App\Modules\Inventaire\Events\AffectationPerdue;
use App\Services\AuditService;
use Core\EventDispatcher;

class AffectationService
{
    private AffectationRepository $affectations;
    private StockService          $stocks;

    public function __construct()
    {
        $this->affectations = new AffectationRepository();
        $this->stocks       = new StockService();
    }

    public function lister(int $etablissementId, ?string $statut = null): array
    {
        return $this->affectations->all($etablissementId, $statut);
    }

    public function trouver(int $id): ?array
    {
        return $this->affectations->findById($id);
    }

    public function affecter(AffectationDTO $dto, int $userId, int $etablissementId): int
    {
        if ($dto->emplacementId === null) {
            throw new \RuntimeException("L'emplacement source est obligatoire pour affecter un article.");
        }
        $this->stocks->sortie($dto->articleId, $dto->quantite, $dto->emplacementId, 'affectation', 0, $userId, $etablissementId);

        $id = $this->affectations->create([
            ':article_id'         => $dto->articleId,
            ':user_id'            => $dto->userId,
            ':quantite'           => $dto->quantite,
            ':date_affectation'   => $dto->dateAffectation,
            ':date_retour_prevue' => $dto->dateRetourPrevue,
            ':emplacement_id'     => $dto->emplacementId,
            ':notes'              => $dto->notes,
            ':created_by'         => $userId,
            ':etablissement_id'   => $etablissementId,
        ]);

        AuditService::logCreate('inv_affectations', $id, $userId, ['article_id' => $dto->articleId, 'user_id' => $dto->userId]);
        EventDispatcher::dispatch(new AffectationCreee($id, $dto->articleId, $dto->userId, $dto->quantite, $etablissementId));

        return $id;
    }

    public function retourner(int $id, string $etat, int $userId, int $etablissementId): void
    {
        $aff = $this->affectations->findById($id);
        if (!$aff || $aff['statut'] !== 'en_cours') {
            throw new \RuntimeException("Affectation non retournable.");
        }

        $emplacementId = (int)($aff['emplacement_id'] ?? 1);
        $this->stocks->entree((int)$aff['article_id'], (float)$aff['quantite'], $emplacementId, 'retour_affectation', $id, $userId, $etablissementId);

        $this->affectations->updateStatut($id, 'retournee', [
            'date_retour_effectif' => date('Y-m-d'),
        ]);

        AuditService::log('return', 'inv_affectations', $id, $userId, ['etat' => $etat]);
        EventDispatcher::dispatch(new AffectationRetournee($id, (int)$aff['article_id'], (int)$aff['user_id'], (float)$aff['quantite'], $etat, $etablissementId));
    }

    public function declararerPerdue(int $id, int $userId, int $etablissementId): void
    {
        $aff = $this->affectations->findById($id);
        if (!$aff || $aff['statut'] !== 'en_cours') {
            throw new \RuntimeException("Affectation introuvable ou déjà clôturée.");
        }

        $this->affectations->updateStatut($id, 'perdue');
        AuditService::log('lost', 'inv_affectations', $id, $userId, []);
        EventDispatcher::dispatch(new AffectationPerdue($id, (int)$aff['article_id'], (int)$aff['user_id'], (float)$aff['quantite'], $etablissementId));
    }

    public function parUser(int $userId): array
    {
        return $this->affectations->byUser($userId);
    }

    public function parArticle(int $articleId): array
    {
        return $this->affectations->byArticle($articleId);
    }
}
