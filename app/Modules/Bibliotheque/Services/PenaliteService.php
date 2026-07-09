<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Services;

use App\Modules\Bibliotheque\Events\PenaliteCreee;
use App\Modules\Bibliotheque\Events\PenalitePayee;
use App\Modules\Bibliotheque\Repositories\PenaliteRepository;
use Core\EventDispatcher;

class PenaliteService
{
    private PenaliteRepository $repo;

    private const TARIF_RETARD_JOUR  = 0.50;
    private const VALEUR_REMPLACEMENT = 25.00;

    public function __construct()
    {
        $this->repo = new PenaliteRepository();
    }

    public function creerPenaliteRetard(int $empruntId, int $userId, int $joursRetard, int $createdBy, int $etablissementId): int
    {
        $montant = round($joursRetard * self::TARIF_RETARD_JOUR, 2);

        $id = $this->repo->insert([
            'emprunt_id'       => $empruntId,
            'user_id'          => $userId,
            'type'             => 'retard',
            'montant'          => $montant,
            'jours_retard'     => $joursRetard,
            'etablissement_id' => $etablissementId,
            'created_by'       => $createdBy,
        ]);

        EventDispatcher::dispatch(new PenaliteCreee($id, $empruntId, $userId, 'retard', $montant, $etablissementId));

        return $id;
    }

    public function creerPenalitePerte(int $empruntId, int $userId, int $createdBy, int $etablissementId): int
    {
        $id = $this->repo->insert([
            'emprunt_id'       => $empruntId,
            'user_id'          => $userId,
            'type'             => 'perte',
            'montant'          => self::VALEUR_REMPLACEMENT,
            'etablissement_id' => $etablissementId,
            'created_by'       => $createdBy,
        ]);

        EventDispatcher::dispatch(new PenaliteCreee($id, $empruntId, $userId, 'perte', self::VALEUR_REMPLACEMENT, $etablissementId));

        return $id;
    }

    public function payerManuellement(int $penaliteId, int $userId): void
    {
        $p = $this->repo->findById($penaliteId);
        if ($p === null) return;
        $this->repo->updateStatut($penaliteId, 'payee');
        EventDispatcher::dispatch(new PenalitePayee($penaliteId, (int)$p['emprunt_id'], (int)$p['user_id'], (float)$p['montant'], 'manuel'));
    }

    public function marquerPayeeViaFinance(int $penaliteId): void
    {
        $p = $this->repo->findById($penaliteId);
        if ($p === null) return;
        $this->repo->updateStatut($penaliteId, 'payee');
        EventDispatcher::dispatch(new PenalitePayee($penaliteId, (int)$p['emprunt_id'], (int)$p['user_id'], (float)$p['montant'], 'finance'));
    }

    public function annuler(int $penaliteId, int $userId): void
    {
        $this->repo->updateStatut($penaliteId, 'annulee');
    }

    public function listerParUser(int $userId): array
    {
        return $this->repo->findByUser($userId);
    }

    public function listerImpayees(int $etablissementId): array
    {
        return $this->repo->findImpayees($etablissementId);
    }

    public function countImpayees(int $userId): int
    {
        return $this->repo->countImpayeesUser($userId);
    }

    public function totalImpayees(int $etablissementId): float
    {
        return $this->repo->totalImpayees($etablissementId);
    }

    public function trouver(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function findByFactureId(int $factureId): ?array
    {
        return $this->repo->findByFactureId($factureId);
    }
}
