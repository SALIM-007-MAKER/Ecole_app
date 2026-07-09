<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Services;

use App\Modules\Bibliotheque\DTO\InventaireDTO;
use App\Modules\Bibliotheque\Events\InventaireTermine;
use App\Modules\Bibliotheque\Repositories\ExemplaireRepository;
use App\Modules\Bibliotheque\Repositories\InventaireRepository;
use Core\EventDispatcher;

class InventaireService
{
    private InventaireRepository $repo;
    private ExemplaireRepository $exemplaires;

    public function __construct()
    {
        $this->repo        = new InventaireRepository();
        $this->exemplaires = new ExemplaireRepository();
    }

    public function lancerSession(InventaireDTO $dto, int $userId, int $etablissementId): int
    {
        if ($this->repo->findActive($etablissementId) !== null) {
            throw new \RuntimeException('Une session d\'inventaire est déjà en cours.');
        }

        return $this->repo->insertSession([
            'nom'              => $dto->nom,
            'description'      => $dto->description,
            'date_debut'       => $dto->dateDebut,
            'date_fin_prevue'  => $dto->dateFinPrevue,
            'created_by'       => $userId,
            'etablissement_id' => $etablissementId,
        ]);
    }

    public function scannerExemplaire(int $sessionId, string $codeOuNumero, string $statutConstate, int $userId, ?string $notes = null): void
    {
        $exemplaire = $this->exemplaires->findByBarcode($codeOuNumero);
        if ($exemplaire === null) {
            throw new \RuntimeException("Exemplaire '$codeOuNumero' introuvable.");
        }
        $this->repo->upsertLigne($sessionId, (int)$exemplaire['id'], $statutConstate, $notes, $userId);

        if (in_array($statutConstate, ['perdu', 'deteriore'], true)) {
            $this->exemplaires->updateStatut((int)$exemplaire['id'], $statutConstate === 'perdu' ? 'perdu' : 'disponible');
        }
    }

    public function terminerSession(int $sessionId, int $userId): array
    {
        $session = $this->repo->findById($sessionId);
        if ($session === null) throw new \RuntimeException("Session #$sessionId introuvable.");

        $this->repo->terminerSession($sessionId);
        $rapport = $this->repo->rapport($sessionId);

        EventDispatcher::dispatch(new InventaireTermine(
            $sessionId,
            $session['nom'],
            $rapport['present'],
            $rapport['manquant'],
            $rapport['deteriore'],
            $userId,
            (int)$session['etablissement_id']
        ));

        return $rapport;
    }

    public function trouver(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function lister(int $etablissementId): array
    {
        return $this->repo->findAll($etablissementId);
    }

    public function rapport(int $sessionId): array
    {
        return $this->repo->rapport($sessionId);
    }

    public function lignes(int $sessionId): array
    {
        return $this->repo->findLignes($sessionId);
    }
}
