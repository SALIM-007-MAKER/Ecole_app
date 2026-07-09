<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Contracts;

interface StockMovementInterface
{
    public function enregistrer(array $mouvement): int;
    public function annuler(int $mouvementId, int $userId): void;
    public function getJournal(int $articleId, array $filters = []): array;
}
