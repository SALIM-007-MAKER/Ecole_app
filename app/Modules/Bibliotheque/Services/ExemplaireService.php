<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Services;

use App\Modules\Bibliotheque\Contracts\BarcodeInterface;
use App\Modules\Bibliotheque\Contracts\QrCodeInterface;
use App\Modules\Bibliotheque\DTO\ExemplaireDTO;
use App\Modules\Bibliotheque\Events\ExemplaireAjoute;
use App\Modules\Bibliotheque\Events\ExemplaireStatutChange;
use App\Modules\Bibliotheque\Models\ExemplaireModel;
use App\Modules\Bibliotheque\Repositories\ExemplaireRepository;
use Core\EventDispatcher;

class ExemplaireService
{
    private ExemplaireRepository $repo;
    private BarcodeInterface     $barcode;
    private QrCodeInterface      $qr;

    public function __construct()
    {
        $this->repo    = new ExemplaireRepository();
        $this->barcode = new SimpleBarcodeService();
        $this->qr      = new SimpleQrCodeService();
    }

    public function ajouter(ExemplaireDTO $dto, int $userId, int $etablissementId): int
    {
        $seq          = $this->repo->nextSequence($etablissementId);
        $numInventaire = $dto->numeroInventaire !== ''
            ? $dto->numeroInventaire
            : ExemplaireModel::genererNumeroInventaire($etablissementId, $seq);

        $id = $this->repo->insert([
            'ouvrage_id'       => $dto->ouvrageId,
            'numero_inventaire'=> $numInventaire,
            'code_barre'       => $dto->codeBarre,
            'localisation'     => $dto->localisation,
            'etat'             => $dto->etat,
            'notes'            => $dto->notes,
            'etablissement_id' => $etablissementId,
            'created_by'       => $userId,
        ]);

        $qrData = $this->genererQrData($id, $dto->ouvrageId, $numInventaire);
        $this->repo->updateQrData($id, $qrData);

        EventDispatcher::dispatch(new ExemplaireAjoute($id, $dto->ouvrageId, $numInventaire, $userId, $etablissementId));

        return $id;
    }

    public function modifier(int $id, ExemplaireDTO $dto, int $userId): void
    {
        $exemplaire = $this->repo->findById($id);
        if ($exemplaire === null) return;

        $this->repo->update($id, [
            'code_barre'  => $dto->codeBarre,
            'localisation'=> $dto->localisation,
            'etat'        => $dto->etat,
            'notes'       => $dto->notes,
        ]);
    }

    public function archiver(int $id, int $userId): void
    {
        $exemplaire = $this->repo->findById($id);
        if ($exemplaire === null) return;
        $this->changerStatut($id, 'retire', $userId);
        $this->repo->softDelete($id);
    }

    public function changerStatut(int $id, string $nouveauStatut, int $userId, ?string $notes = null): void
    {
        $exemplaire = $this->repo->findById($id);
        if ($exemplaire === null) return;
        $ancienStatut = $exemplaire['statut'];
        if ($ancienStatut === $nouveauStatut) return;
        $this->repo->updateStatut($id, $nouveauStatut);
        EventDispatcher::dispatch(new ExemplaireStatutChange($id, $exemplaire['ouvrage_id'], $ancienStatut, $nouveauStatut, $userId));
    }

    public function trouver(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function trouverParBarcode(string $code): ?array
    {
        return $this->repo->findByBarcode($code);
    }

    public function listerParOuvrage(int $ouvrageId): array
    {
        return $this->repo->findByOuvrage($ouvrageId);
    }

    public function compterDisponibles(int $ouvrageId): int
    {
        return $this->repo->countDisponibles($ouvrageId);
    }

    public function genererBarcodeSvg(int $exemplaireId): string
    {
        $exemplaire = $this->repo->findById($exemplaireId);
        if ($exemplaire === null) return '';
        $code = $exemplaire['code_barre'] ?? $exemplaire['numero_inventaire'];
        return $this->barcode->generateSvg($code);
    }

    public function genererQrSvg(int $exemplaireId): string
    {
        $exemplaire = $this->repo->findById($exemplaireId);
        if ($exemplaire === null) return '';
        return $this->qr->generateSvg($exemplaire['qr_data'] ?? $exemplaire['numero_inventaire']);
    }

    private function genererQrData(int $exemplaireId, int $ouvrageId, string $numeroInventaire): string
    {
        return json_encode([
            'module'           => 'bibliotheque',
            'exemplaire_id'    => $exemplaireId,
            'ouvrage_id'       => $ouvrageId,
            'numero_inventaire'=> $numeroInventaire,
        ], JSON_UNESCAPED_UNICODE);
    }
}
