<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Services;

use App\Modules\Inventaire\Repositories\AlerteRepository;
use App\Modules\Inventaire\Repositories\ArticleRepository;
use App\Services\AuditService;

class AlerteService
{
    private AlerteRepository  $alertes;
    private ArticleRepository $articles;

    public function __construct()
    {
        $this->alertes  = new AlerteRepository();
        $this->articles = new ArticleRepository();
    }

    public function listerActives(int $etablissementId): array
    {
        return $this->alertes->active($etablissementId);
    }

    public function listerToutes(int $etablissementId): array
    {
        return $this->alertes->all($etablissementId);
    }

    public function acquitter(int $id, int $userId): void
    {
        $this->alertes->acquitter($id, $userId);
        AuditService::log('acquit', 'inv_alertes', $id, $userId, []);
    }

    public function scanner(int $etablissementId): int
    {
        $enAlerte = $this->articles->articlesEnAlerte($etablissementId);
        $created  = 0;
        foreach ($enAlerte as $article) {
            $stock = (float)$article['stock_total'];
            $seuil = (float)$article['seuil_alerte'];
            if (!$this->alertes->alreadyActive($article['id'], 'stock_min')) {
                $this->alertes->create([
                    ':article_id'       => $article['id'],
                    ':type'             => 'stock_min',
                    ':valeur_seuil'     => $seuil,
                    ':valeur_actuelle'  => $stock,
                    ':statut'           => 'active',
                    ':etablissement_id' => $etablissementId,
                ]);
                $created++;
            }
        }
        return $created;
    }

    public function countActives(int $etablissementId): int
    {
        return $this->alertes->countActive($etablissementId);
    }
}
