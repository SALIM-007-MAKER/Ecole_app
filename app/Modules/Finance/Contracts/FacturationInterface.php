<?php

namespace App\Modules\Finance\Contracts;

use App\Modules\Finance\DTO\InvoiceDTO;
use App\Modules\Finance\DTO\LigneFactureDTO;
use App\Modules\Finance\DTO\RemiseDTO;

interface FacturationInterface
{
    public function creerFacture(InvoiceDTO $dto, array $lignes, int $userId): int;
    public function ajouterLigne(int $factureId, LigneFactureDTO $ligne, int $userId): void;
    public function supprimerLigne(int $factureId, int $ligneId, int $userId): void;
    public function appliquerRemise(int $factureId, RemiseDTO $remise, int $userId): void;
    public function appliquerPenalite(int $factureId, float $montant, string $motif, int $userId): void;
    public function creerEcheancier(int $factureId, array $echeances, int $userId): void;
    public function emettre(int $factureId, int $userId): void;
    public function annuler(int $factureId, string $motif, int $userId): void;
    public function archiver(int $factureId, int $userId): void;
    public function genererPourClasse(int $classeId, array $fraisTypeIds, string $annee, int $userId): array;
    public function genererMasse(string $annee, ?string $niveau, array $fraisTypeIds, int $userId): array;
}
