<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Services;

use App\Modules\Inventaire\DTO\ArticleDTO;
use App\Modules\Inventaire\DTO\ArticleFiltersDTO;
use App\Modules\Inventaire\Repositories\ArticleRepository;
use App\Modules\Inventaire\Repositories\AlerteRepository;
use App\Modules\Inventaire\Events\ArticleAjoute;
use App\Modules\Inventaire\Events\ArticleModifie;
use App\Modules\Inventaire\Events\ArticleArchive;
use App\Modules\Inventaire\Events\StockAlerte;
use App\Services\AuditService;
use Core\EventDispatcher;

class ArticleService
{
    private ArticleRepository $articles;
    private AlerteRepository  $alertes;

    public function __construct()
    {
        $this->articles = new ArticleRepository();
        $this->alertes  = new AlerteRepository();
    }

    public function lister(ArticleFiltersDTO $filters, int $etablissementId): array
    {
        return $this->articles->paginate(
            [
                'search'         => $filters->search,
                'type'           => $filters->type,
                'categorie_id'   => $filters->categorieId,
                'fournisseur_id' => $filters->fournisseurId,
                'actif'          => $filters->actif,
                'alerte'         => $filters->alerte,
            ],
            $etablissementId,
            $filters->page,
            $filters->perPage,
        );
    }

    public function trouver(int $id): ?array
    {
        return $this->articles->findById($id);
    }

    public function trouverParBarcode(string $barcode, int $etablissementId): ?array
    {
        return $this->articles->findByBarcode($barcode, $etablissementId);
    }

    public function creer(ArticleDTO $dto, int $userId, int $etablissementId): int
    {
        if ($this->articles->findByReference($dto->reference, $etablissementId)) {
            throw new \RuntimeException("Référence '{$dto->reference}' déjà utilisée.");
        }

        $id = $this->articles->create([
            ':reference'          => $dto->reference,
            ':designation'        => $dto->designation,
            ':description'        => $dto->description,
            ':categorie_id'       => $dto->categorieId,
            ':type'               => $dto->type,
            ':unite_mesure'       => $dto->uniteMesure,
            ':seuil_alerte'       => $dto->seuilAlerte,
            ':seuil_critique'     => $dto->seuilCritique,
            ':valeur_unitaire'    => $dto->valeurUnitaire,
            ':fournisseur_id'     => $dto->fournisseurId,
            ':barcode'            => $dto->barcode,
            ':numero_serie'       => $dto->numeroSerie,
            ':localisation_defaut'=> $dto->localisationDefaut,
            ':garantie_mois'      => $dto->garantieMois,
            ':actif'              => (int)$dto->actif,
            ':created_by'         => $userId,
            ':etablissement_id'   => $etablissementId,
        ]);

        AuditService::logCreate('inv_articles', $id, $userId, ['designation' => $dto->designation]);
        EventDispatcher::dispatch(new ArticleAjoute($id, $dto->reference, $dto->designation, $dto->type, $userId, $etablissementId));

        return $id;
    }

    public function modifier(int $id, ArticleDTO $dto, int $userId): void
    {
        $this->articles->update($id, [
            ':designation'       => $dto->designation,
            ':description'       => $dto->description,
            ':categorie_id'      => $dto->categorieId,
            ':type'              => $dto->type,
            ':unite_mesure'      => $dto->uniteMesure,
            ':seuil_alerte'      => $dto->seuilAlerte,
            ':seuil_critique'    => $dto->seuilCritique,
            ':valeur_unitaire'   => $dto->valeurUnitaire,
            ':fournisseur_id'    => $dto->fournisseurId,
            ':barcode'           => $dto->barcode,
            ':garantie_mois'     => $dto->garantieMois,
            ':actif'             => (int)$dto->actif,
        ]);

        AuditService::log('update', 'inv_articles', $id, $userId, ['designation' => $dto->designation]);
        EventDispatcher::dispatch(new ArticleModifie($id, $dto->designation, $userId));
    }

    public function archiver(int $id, int $userId): void
    {
        $article = $this->articles->findById($id);
        if (!$article) throw new \RuntimeException("Article introuvable.");
        $this->articles->softDelete($id);

        AuditService::log('archive', 'inv_articles', $id, $userId, []);
        EventDispatcher::dispatch(new ArticleArchive($id, $article['designation'], $userId));
    }

    public function verifierAlertes(int $articleId, float $stockActuel, int $etablissementId): void
    {
        $article = $this->articles->findById($articleId);
        if (!$article) return;

        $seuil = (float)$article['seuil_alerte'];
        if ($stockActuel <= $seuil && !$this->alertes->alreadyActive($articleId, 'stock_min')) {
            $this->alertes->create([
                ':article_id'       => $articleId,
                ':type'             => 'stock_min',
                ':valeur_seuil'     => $seuil,
                ':valeur_actuelle'  => $stockActuel,
                ':statut'           => 'active',
                ':etablissement_id' => $etablissementId,
            ]);
            EventDispatcher::dispatch(new StockAlerte($articleId, $article['designation'], 'stock_min', $stockActuel, $seuil, $etablissementId));
        }

        $seuilCrit = (float)$article['seuil_critique'];
        if ($seuilCrit > 0 && $stockActuel <= $seuilCrit && !$this->alertes->alreadyActive($articleId, 'stock_critique')) {
            $this->alertes->create([
                ':article_id'       => $articleId,
                ':type'             => 'stock_critique',
                ':valeur_seuil'     => $seuilCrit,
                ':valeur_actuelle'  => $stockActuel,
                ':statut'           => 'active',
                ':etablissement_id' => $etablissementId,
            ]);
            EventDispatcher::dispatch(new StockAlerte($articleId, $article['designation'], 'stock_critique', $stockActuel, $seuilCrit, $etablissementId));
        }
    }

    public function articlesEnAlerte(int $etablissementId): array
    {
        return $this->articles->articlesEnAlerte($etablissementId);
    }
}
