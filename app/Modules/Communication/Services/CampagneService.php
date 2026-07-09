<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\DTO\CampagneDTO;
use App\Modules\Communication\DTO\DiffusionDTO;
use App\Modules\Communication\Events\CampagneLancee;
use App\Modules\Communication\Events\CampagneTerminee;
use App\Modules\Communication\Repositories\CampagneRepository;
use Core\EventDispatcher;

class CampagneService
{
    private CampagneRepository $repo;
    private DiffusionService   $diffusionService;
    private QueueService       $queueService;
    private TemplateService    $templateService;

    public function __construct()
    {
        $this->repo             = new CampagneRepository();
        $this->diffusionService = new DiffusionService();
        $this->queueService     = new QueueService();
        $this->templateService  = new TemplateService();
    }

    public function creer(CampagneDTO $dto, int $userId, int $etablissementId = 1): int
    {
        return $this->repo->insert([
            'nom'             => $dto->nom,
            'description'     => $dto->description,
            'canaux'          => $dto->canaux,
            'cible_type'      => $dto->cibleType,
            'cible_id'        => $dto->cibleId,
            'template_id'     => $dto->templateId,
            'planifie_at'     => $dto->planifieAt,
            'variables_json'  => $dto->variables,
            'etablissement_id'=> $etablissementId,
            'created_by'      => $userId,
        ]);
    }

    public function modifier(int $campagneId, CampagneDTO $dto, int $userId): void
    {
        $campagne = $this->repo->findById($campagneId);
        if ($campagne === null) {
            throw new \RuntimeException("Campagne #{$campagneId} introuvable.");
        }
        if (!in_array($campagne['statut'], ['brouillon', 'planifiee'], true)) {
            throw new \RuntimeException("Cette campagne ne peut plus être modifiée.");
        }
        $this->repo->update($campagneId, [
            'nom'         => $dto->nom,
            'description' => $dto->description,
            'statut'      => $campagne['statut'],
        ]);
    }

    public function lancer(int $campagneId, int $userId, int $etablissementId = 1): array
    {
        $campagne = $this->repo->findById($campagneId);
        if ($campagne === null) {
            throw new \RuntimeException("Campagne #{$campagneId} introuvable.");
        }
        if (!in_array($campagne['statut'], ['brouillon', 'planifiee'], true)) {
            throw new \RuntimeException("Cette campagne est déjà lancée ou terminée.");
        }

        $canaux = is_string($campagne['canaux']) ? json_decode($campagne['canaux'], true) : $campagne['canaux'];
        $dto    = new DiffusionDTO(
            sujet:      $campagne['nom'],
            corps:      $campagne['description'] ?? $campagne['nom'],
            canaux:     $canaux ?? ['internal'],
            cibleType:  $campagne['cible_type'],
            cibleId:    $campagne['cible_id'] ? (int) $campagne['cible_id'] : null,
            templateId: $campagne['template_id'] ? (int) $campagne['template_id'] : null,
            variables:  $campagne['variables_json'] ? (is_string($campagne['variables_json']) ? json_decode($campagne['variables_json'], true) : $campagne['variables_json']) : [],
        );

        $userIds = $this->diffusionService->resoudreDestinataires($dto, $etablissementId);
        $total   = count($userIds);

        $this->repo->setTotalDestinataires($campagneId, $total);
        $this->repo->updateStatut($campagneId, 'en_cours');

        foreach ($userIds as $uid) {
            foreach (($canaux ?? ['internal']) as $canal) {
                $this->repo->insertDestinataire($campagneId, (int) $uid, $canal);
            }
        }

        EventDispatcher::dispatch(new CampagneLancee($campagneId, $total, $userId));

        // Si campagne vide, terminer immédiatement
        if ($total === 0) {
            $this->repo->updateStatut($campagneId, 'terminee');
            EventDispatcher::dispatch(new CampagneTerminee($campagneId, 0, 0));
        }

        return ['total_destinataires' => $total, 'statut' => 'en_cours'];
    }

    public function annuler(int $campagneId, int $userId): void
    {
        $campagne = $this->repo->findById($campagneId);
        if ($campagne === null) {
            throw new \RuntimeException("Campagne #{$campagneId} introuvable.");
        }
        $this->repo->updateStatut($campagneId, 'annulee');
    }

    public function statistiques(int $campagneId): array
    {
        $campagne = $this->repo->findById($campagneId);
        if ($campagne === null) {
            return [];
        }
        $stats = $this->repo->statsDestinataires($campagneId);
        return array_merge($campagne, ['stats_destinataires' => $stats]);
    }

    public function trouver(int $campagneId): ?array
    {
        return $this->repo->findById($campagneId);
    }

    public function dernierMessages(int $campagneId, int $limit = 20): array
    {
        return $this->repo->findMessages($campagneId, $limit);
    }

    public function lister(int $etablissementId = 1, int $page = 1): array
    {
        return $this->repo->findAll($etablissementId, $page);
    }
}
