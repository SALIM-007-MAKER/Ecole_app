<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Services;

use App\Modules\Inventaire\Repositories\StockRepository;
use App\Modules\Inventaire\Repositories\MouvementRepository;
use App\Modules\Inventaire\Events\StockEntree;
use App\Modules\Inventaire\Events\StockSortie;
use App\Modules\Inventaire\Events\StockTransfert;
use App\Modules\Inventaire\Events\StockAjustement;
use App\Services\AuditService;
use Core\EventDispatcher;

class StockService
{
    private StockRepository     $stocks;
    private MouvementRepository $mouvements;
    private ArticleService      $articleService;

    public function __construct()
    {
        $this->stocks         = new StockRepository();
        $this->mouvements     = new MouvementRepository();
        $this->articleService = new ArticleService();
    }

    public function entree(int $articleId, float $qte, int $emplacementId, string $refType, int $refId, int $userId, int $etablissementId): void
    {
        $avant = $this->stocks->totalDisponible($articleId);
        $apres = $this->stocks->upsert($articleId, $emplacementId, $qte, $etablissementId);

        $this->mouvements->create([
            ':article_id'            => $articleId,
            ':type'                  => 'entree',
            ':quantite'              => $qte,
            ':quantite_avant'        => $avant,
            ':quantite_apres'        => $apres,
            ':emplacement_source_id' => null,
            ':emplacement_dest_id'   => $emplacementId,
            ':reference_type'        => $refType,
            ':reference_id'          => $refId,
            ':notes'                 => null,
            ':created_by'            => $userId,
            ':etablissement_id'      => $etablissementId,
        ]);

        EventDispatcher::dispatch(new StockEntree($articleId, $qte, $emplacementId, $refType, $refId, $avant, $apres, $userId, $etablissementId));
    }

    public function sortie(int $articleId, float $qte, int $emplacementId, string $refType, int $refId, int $userId, int $etablissementId): void
    {
        $line = $this->stocks->getLine($articleId, $emplacementId);
        $disponibleEmplacement = $line ? (float)$line['quantite_disponible'] : 0.0;
        if ($disponibleEmplacement < $qte) {
            throw new \RuntimeException("Stock insuffisant à l'emplacement #{$emplacementId}: {$disponibleEmplacement} disponible(s), {$qte} demandé(s).");
        }

        $avant = $this->stocks->totalDisponible($articleId);
        $this->stocks->upsert($articleId, $emplacementId, -$qte, $etablissementId);
        $apres = $this->stocks->totalDisponible($articleId);

        $this->mouvements->create([
            ':article_id'            => $articleId,
            ':type'                  => 'sortie',
            ':quantite'              => $qte,
            ':quantite_avant'        => $avant,
            ':quantite_apres'        => $apres,
            ':emplacement_source_id' => $emplacementId,
            ':emplacement_dest_id'   => null,
            ':reference_type'        => $refType,
            ':reference_id'          => $refId,
            ':notes'                 => null,
            ':created_by'            => $userId,
            ':etablissement_id'      => $etablissementId,
        ]);

        EventDispatcher::dispatch(new StockSortie($articleId, $qte, $emplacementId, $refType, $refId, $avant, $apres, $userId, $etablissementId));
        $this->articleService->verifierAlertes($articleId, $apres, $etablissementId);
    }

    public function transferer(int $articleId, float $qte, int $sourceId, int $destId, int $userId, int $etablissementId): void
    {
        $lineSource = $this->stocks->getLine($articleId, $sourceId);
        $disponibleSource = $lineSource ? (float)$lineSource['quantite_disponible'] : 0.0;
        if ($disponibleSource < $qte) {
            throw new \RuntimeException("Stock insuffisant à l'emplacement source #{$sourceId}: {$disponibleSource} disponible(s), {$qte} demandé(s).");
        }

        $avant = $this->stocks->totalDisponible($articleId);
        $this->stocks->upsert($articleId, $sourceId, -$qte, $etablissementId);
        $this->stocks->upsert($articleId, $destId, $qte, $etablissementId);

        $this->mouvements->create([
            ':article_id'            => $articleId,
            ':type'                  => 'transfert',
            ':quantite'              => $qte,
            ':quantite_avant'        => $avant,
            ':quantite_apres'        => $avant,
            ':emplacement_source_id' => $sourceId,
            ':emplacement_dest_id'   => $destId,
            ':reference_type'        => 'transfert',
            ':reference_id'          => 0,
            ':notes'                 => null,
            ':created_by'            => $userId,
            ':etablissement_id'      => $etablissementId,
        ]);

        EventDispatcher::dispatch(new StockTransfert($articleId, $qte, $sourceId, $destId, $userId, $etablissementId));
    }

    public function ajuster(int $articleId, int $emplacementId, float $nouvelleQte, string $motif, int $userId, int $etablissementId): void
    {
        $avant  = $this->stocks->totalDisponible($articleId);
        $delta  = $nouvelleQte - $avant;
        $this->stocks->upsert($articleId, $emplacementId, $delta, $etablissementId);
        $apres  = $this->stocks->totalDisponible($articleId);

        $this->mouvements->create([
            ':article_id'            => $articleId,
            ':type'                  => 'ajustement',
            ':quantite'              => abs($delta),
            ':quantite_avant'        => $avant,
            ':quantite_apres'        => $apres,
            ':emplacement_source_id' => null,
            ':emplacement_dest_id'   => $emplacementId,
            ':reference_type'        => 'ajustement',
            ':reference_id'          => 0,
            ':notes'                 => $motif,
            ':created_by'            => $userId,
            ':etablissement_id'      => $etablissementId,
        ]);

        AuditService::log('stock_adjust', 'inv_articles', $articleId, $userId, ['avant' => $avant, 'apres' => $apres]);
        EventDispatcher::dispatch(new StockAjustement($articleId, $avant, $apres, $motif, $userId, $etablissementId));
        $this->articleService->verifierAlertes($articleId, $apres, $etablissementId);
    }

    public function getByArticle(int $articleId): array
    {
        return $this->stocks->getByArticle($articleId);
    }

    public function etatGlobal(int $etablissementId): array
    {
        return $this->stocks->globalStock($etablissementId);
    }

    public function mouvements(int $articleId, array $filters = []): array
    {
        return $this->mouvements->getByArticle($articleId, $filters);
    }

    public function derniersMovements(int $etablissementId, int $limit = 50): array
    {
        return $this->mouvements->getByEtablissement($etablissementId, $limit);
    }
}
