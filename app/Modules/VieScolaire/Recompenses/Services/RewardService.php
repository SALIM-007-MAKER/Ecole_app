<?php

namespace App\Modules\VieScolaire\Recompenses\Services;

use App\Modules\VieScolaire\Recompenses\DTO\RewardDTO;
use App\Modules\VieScolaire\Recompenses\DTO\RewardFiltersDTO;
use App\Modules\VieScolaire\Recompenses\Events\RewardGranted;
use App\Modules\VieScolaire\Recompenses\Events\RewardRevoked;
use App\Modules\VieScolaire\Recompenses\Events\RewardUpdated;
use App\Modules\VieScolaire\Recompenses\Repositories\RewardRepository;
use Core\EventDispatcher;

class RewardService
{
    private RewardRepository $repo;

    public function __construct()
    {
        $this->repo = new RewardRepository();
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    public function findById(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function paginate(RewardFiltersDTO $f): array
    {
        $total = $this->repo->countAll($f);
        return [
            'data'  => $this->repo->findAll($f),
            'total' => $total,
            'page'  => $f->page,
            'pages' => max(1, (int)ceil($total / $f->perPage)),
        ];
    }

    public function categories(): array
    {
        return $this->repo->findAllCategories();
    }

    public function historique(int $rewardId): array
    {
        return $this->repo->findHistorique($rewardId);
    }

    // ── Attribution ───────────────────────────────────────────────────────────

    public function attribuerRecompense(RewardDTO $dto, int $userId): array
    {
        $rewardId = $this->repo->insert(array_merge($dto->toArray(), [
            'attribue_par' => $userId,
        ]));

        $this->repo->insertHistorique([
            'recompense_id' => $rewardId,
            'ancien_statut' => null,
            'nouveau_statut'=> 'attribuee',
            'motif'         => 'Attribution initiale',
            'modifie_par'   => $userId,
        ]);

        $cat = $this->repo->findCategoryById($dto->categorieId);

        EventDispatcher::dispatch(new RewardGranted(
            rewardId:      $rewardId,
            eleveId:       $dto->eleveId,
            classeId:      $dto->classeId,
            anneeScolaire: $dto->anneeScolaire,
            categorieCode: $cat['code'] ?? 'autre',
            niveau:        $dto->niveau,
            motif:         $dto->motif,
            attribueParId: $userId,
        ));

        return $this->repo->findById($rewardId);
    }

    // ── Mise à jour ───────────────────────────────────────────────────────────

    public function mettreAJour(int $rewardId, RewardDTO $dto, int $userId): void
    {
        $reward = $this->repo->findById($rewardId);
        if ($reward === null) {
            throw new \RuntimeException('Récompense introuvable.');
        }
        if ($reward['statut'] !== 'attribuee') {
            throw new \RuntimeException('Seule une récompense "attribuée" peut être modifiée.');
        }

        $this->repo->update($rewardId, $dto->toArray());

        $this->repo->insertHistorique([
            'recompense_id' => $rewardId,
            'ancien_statut' => 'attribuee',
            'nouveau_statut'=> 'attribuee',
            'motif'         => 'Modification',
            'modifie_par'   => $userId,
        ]);

        EventDispatcher::dispatch(new RewardUpdated(
            rewardId:      $rewardId,
            eleveId:       $reward['eleve_id'],
            classeId:      $reward['classe_id'],
            anneeScolaire: $reward['annee_scolaire'],
            modifieParId:  $userId,
        ));
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function validerRecompense(int $rewardId, int $userId): void
    {
        $reward = $this->repo->findById($rewardId);
        if ($reward === null) {
            throw new \RuntimeException('Récompense introuvable.');
        }
        if ($reward['statut'] !== 'attribuee') {
            throw new \RuntimeException('Seule une récompense "attribuée" peut être validée.');
        }

        $this->repo->valider($rewardId, $userId);

        $this->repo->insertHistorique([
            'recompense_id' => $rewardId,
            'ancien_statut' => 'attribuee',
            'nouveau_statut'=> 'validee',
            'motif'         => 'Validation administrative',
            'modifie_par'   => $userId,
        ]);
    }

    // ── Révocation ────────────────────────────────────────────────────────────

    public function revoquerRecompense(int $rewardId, string $motif, int $userId): void
    {
        $reward = $this->repo->findById($rewardId);
        if ($reward === null) {
            throw new \RuntimeException('Récompense introuvable.');
        }
        if (!in_array($reward['statut'], ['attribuee', 'validee'], true)) {
            throw new \RuntimeException('Cette récompense ne peut plus être révoquée.');
        }
        if (mb_strlen(trim($motif)) < 10) {
            throw new \InvalidArgumentException('Le motif de révocation doit comporter au moins 10 caractères.');
        }

        $ancienStatut = $reward['statut'];
        $this->repo->revoquer($rewardId, $userId, $motif);

        $this->repo->insertHistorique([
            'recompense_id' => $rewardId,
            'ancien_statut' => $ancienStatut,
            'nouveau_statut'=> 'revoquee',
            'motif'         => $motif,
            'modifie_par'   => $userId,
        ]);

        EventDispatcher::dispatch(new RewardRevoked(
            rewardId:        $rewardId,
            eleveId:         $reward['eleve_id'],
            classeId:        $reward['classe_id'],
            anneeScolaire:   $reward['annee_scolaire'],
            motifRevocation: $motif,
            revoqueParId:    $userId,
        ));
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function statistiquesEleve(int $eleveId, string $annee): array
    {
        return $this->repo->statsByEleve($eleveId, $annee);
    }

    public function statistiquesClasse(int $classeId, string $annee): array
    {
        return $this->repo->statsByClasse($classeId, $annee);
    }

    public function classementComportemental(int $classeId, string $annee): array
    {
        return $this->repo->classementComportemental($classeId, $annee);
    }
}
