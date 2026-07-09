<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Services;

use App\Modules\Inventaire\DTO\AmortissementDTO;
use App\Modules\Inventaire\Contracts\AmortissementInterface;
use App\Modules\Inventaire\Contracts\AmortissementStub;
use App\Modules\Inventaire\Repositories\AmortissementRepository;
use App\Modules\Inventaire\Models\InvAmortissementModel;
use App\Modules\Inventaire\Events\AmortissementCalcule;
use App\Services\AuditService;
use Core\EventDispatcher;

/** Service stub V2 — moteur fiscal complet prévu V3 */
class AmortissementService
{
    private AmortissementRepository  $repo;
    private InvAmortissementModel    $model;
    private AmortissementInterface   $calcul;

    public function __construct()
    {
        $this->repo   = new AmortissementRepository();
        $this->model  = new InvAmortissementModel();
        $this->calcul = new AmortissementStub();
    }

    public function lister(int $etablissementId): array
    {
        return $this->repo->all($etablissementId);
    }

    public function initialiser(AmortissementDTO $dto, int $userId, int $etablissementId): int
    {
        if ($this->repo->findByArticle($dto->articleId)) {
            throw new \RuntimeException("Plan d'amortissement déjà existant pour cet article.");
        }

        $id = $this->repo->create([
            ':article_id'                => $dto->articleId,
            ':valeur_achat'              => $dto->valeurAchat,
            ':date_achat'                => $dto->dateAchat,
            ':duree_amortissement_mois'  => $dto->dureeAmortissementMois,
            ':methode'                   => $dto->methode,
            ':valeur_residuelle'         => $dto->valeurResiduelle,
            ':etablissement_id'          => $etablissementId,
        ]);

        AuditService::logCreate('inv_amortissements', $id, $userId, ['article_id' => $dto->articleId]);
        return $id;
    }

    public function calculerVnc(int $articleId, int $etablissementId): float
    {
        $moisEcoules = $this->model->moisEcoules($articleId);
        $row         = $this->repo->findByArticle($articleId);
        if (!$row) return 0.0;

        $vnc = $row['methode'] === 'lineaire'
            ? $this->calcul->calculerLineaire(
                (float)$row['valeur_achat'],
                (int)$row['duree_amortissement_mois'],
                (float)$row['valeur_residuelle'],
                $moisEcoules
            )
            : $this->calcul->calculerDegressif(
                (float)$row['valeur_achat'],
                (int)$row['duree_amortissement_mois'],
                (float)$row['valeur_residuelle'],
                $moisEcoules
            );

        $dotation = round(((float)$row['valeur_achat'] - (float)$row['valeur_residuelle']) / max(1, (int)$row['duree_amortissement_mois']), 2);
        $this->repo->updateVnc($articleId, $vnc);

        EventDispatcher::dispatch(new AmortissementCalcule($articleId, $vnc, $dotation, $etablissementId));
        return $vnc;
    }

    public function tableau(int $articleId): array
    {
        $row = $this->repo->findByArticle($articleId);
        if (!$row) return [];
        return $this->calcul->tableauAmortissement([
            'valeur_achat'      => (float)$row['valeur_achat'],
            'duree_mois'        => (int)$row['duree_amortissement_mois'],
            'valeur_residuelle' => (float)$row['valeur_residuelle'],
            'methode'           => $row['methode'],
        ]);
    }

    public function totalValeurNette(int $etablissementId): float
    {
        return $this->repo->totalValeurNette($etablissementId);
    }
}
