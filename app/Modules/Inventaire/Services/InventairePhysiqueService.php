<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Services;

use App\Modules\Inventaire\DTO\InventaireDTO;
use App\Modules\Inventaire\Repositories\InventairePhysiqueRepository;
use App\Modules\Inventaire\Events\InventaireTermine;
use App\Modules\Inventaire\Events\StockAjustement;
use App\Services\AuditService;
use Core\EventDispatcher;

class InventairePhysiqueService
{
    private InventairePhysiqueRepository $inventaires;
    private StockService                 $stocks;

    public function __construct()
    {
        $this->inventaires = new InventairePhysiqueRepository();
        $this->stocks      = new StockService();
    }

    public function lister(int $etablissementId): array
    {
        return $this->inventaires->all($etablissementId);
    }

    public function trouver(int $id): ?array
    {
        return $this->inventaires->findById($id);
    }

    public function ouvrir(InventaireDTO $dto, int $userId, int $etablissementId): int
    {
        $id = $this->inventaires->create([
            ':nom'              => $dto->nom,
            ':description'      => $dto->description,
            ':statut'           => 'en_cours',
            ':date_debut'       => $dto->dateDebut,
            ':created_by'       => $userId,
            ':etablissement_id' => $etablissementId,
        ]);

        $nb = $this->inventaires->initLignes($id, $etablissementId);
        AuditService::logCreate('inv_inventaires', $id, $userId, ['nom' => $dto->nom, 'nb_lignes' => $nb]);
        return $id;
    }

    public function lignes(int $id): array
    {
        return $this->inventaires->lignes($id);
    }

    public function saisirComptage(int $inventaireId, int $ligneId, float $qte, int $userId): void
    {
        $inv = $this->inventaires->findById($inventaireId);
        if (!$inv || $inv['statut'] !== 'en_cours') throw new \RuntimeException("Session inventaire non active.");
        $this->inventaires->updateLigne($ligneId, $qte, $userId);
    }

    public function cloture(int $id, int $userId, int $etablissementId): void
    {
        $inv = $this->inventaires->findById($id);
        if (!$inv || $inv['statut'] !== 'en_cours') throw new \RuntimeException("Session inventaire non active.");

        $lignes = $this->inventaires->lignes($id);
        $nbAjustements = 0;

        foreach ($lignes as $ligne) {
            if ($ligne['quantite_comptee'] === null) continue;
            $ecart = (float)$ligne['quantite_comptee'] - (float)$ligne['quantite_theorique'];
            if (abs($ecart) > 0.001) {
                $this->stocks->ajuster(
                    (int)$ligne['article_id'],
                    (int)$ligne['emplacement_id'],
                    (float)$ligne['quantite_comptee'],
                    "Ajustement inventaire #{$id}",
                    $userId,
                    $etablissementId,
                );
                $nbAjustements++;
            }
        }

        $summary = $this->inventaires->ecartsSummary($id);
        $this->inventaires->updateStatut($id, 'termine');

        AuditService::log('cloture', 'inv_inventaires', $id, $userId, ['nb_ajustements' => $nbAjustements]);
        EventDispatcher::dispatch(new InventaireTermine($id, $inv['nom'], (int)($summary['nb_ecarts'] ?? 0), $nbAjustements, $userId, $etablissementId));
    }
}
