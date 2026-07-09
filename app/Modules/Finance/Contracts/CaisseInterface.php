<?php

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\DTO\CashRegisterDTO;
use App\Modules\Finance\DTO\CashMovementDTO;

interface CaisseInterface
{
    public function ouvrir(CashRegisterDTO $dto, int $userId): int;
    public function fermer(int $sessionId, float $soldeReel, ?string $note, int $userId): void;
    public function enregistrerMouvement(int $sessionId, CashMovementDTO $dto, int $userId): int;
    public function annulerMouvement(int $mouvementId, string $motif, int $userId): void;
    public function getSessionActive(int $userId): ?object;
}
