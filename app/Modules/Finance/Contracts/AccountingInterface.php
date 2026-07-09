<?php

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\DTO\ExerciceDTO;
use App\Modules\Finance\Events\PaymentCompleted;
use App\Modules\Finance\Events\PaymentRefunded;
use App\Modules\Finance\Events\CashMovementCreated;
use App\Modules\Finance\Events\ExpenseValidated;
use App\Modules\Finance\Events\InvoiceCancelled;

interface AccountingInterface
{
    public function enregistrerDepuisPaiement(PaymentCompleted $event): void;

    public function enregistrerDepuisRemboursement(PaymentRefunded $event): void;

    public function enregistrerDepuisMouvementCaisse(CashMovementCreated $event): void;

    public function enregistrerDepuisDepense(ExpenseValidated $event): void;

    public function enregistrerDepuisAnnulationFacture(InvoiceCancelled $event): void;

    public function extourner(int $ecritureId, string $motif, int $userId): int;

    public function creerExercice(ExerciceDTO $dto, int $userId): int;

    public function cloturerPeriode(int $periodeId, int $userId): void;

    public function cloturerExercice(int $exerciceId, int $userId): void;
}
