<?php

declare(strict_types=1);

namespace App\Modules\RH\Contrats\Services;

use App\Modules\RH\Contrats\DTO\ContractDTO;
use App\Modules\RH\Contrats\DTO\ContractFiltersDTO;
use App\Modules\RH\Contrats\DTO\AvenantDTO;
use App\Modules\RH\Contrats\Models\ContractModel;
use App\Modules\RH\Contrats\Repositories\ContractRepository;
use App\Modules\RH\Contrats\Events\ContractCreated;
use App\Modules\RH\Contrats\Events\ContractUpdated;
use App\Modules\RH\Contrats\Events\ContractRenewed;
use App\Modules\RH\Contrats\Events\ContractExpired;
use App\Modules\RH\Contrats\Events\ContractTerminated;
use Core\EventDispatcher;

class ContractService
{
    private ContractRepository $repo;

    public function __construct()
    {
        $this->repo = new ContractRepository();
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    public function paginate(ContractFiltersDTO $f): array
    {
        $total = $this->repo->count($f);
        return [
            'items' => $this->repo->findAll($f),
            'total' => $total,
            'page'  => $f->page,
            'pages' => (int)ceil(max(1, $total) / $f->perPage),
        ];
    }

    public function findById(int $id): array
    {
        $c = $this->repo->findById($id);
        if ($c === null) {
            throw new \RuntimeException('Contrat introuvable.');
        }
        return $c;
    }

    public function findByEmploye(int $employeId): array
    {
        return $this->repo->findByEmploye($employeId);
    }

    public function findAvenants(int $contratId): array
    {
        return $this->repo->findAvenants($contratId);
    }

    public function echeances(int $jours = 90): array
    {
        return $this->repo->findEcheances($jours);
    }

    public function statistiques(): array
    {
        return $this->repo->statistiques();
    }

    public function referentiels(): array
    {
        return [
            'employes'     => $this->repo->findEmployes(),
            'postes'       => $this->repo->findPostes(),
            'departements' => $this->repo->findDepartements(),
        ];
    }

    // ── Création ──────────────────────────────────────────────────────────────

    public function creer(ContractDTO $dto, int $userId): int
    {
        $errors = $dto->validate();
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }

        // Règle : un seul contrat actif/brouillon simultané (sauf explicitement multi)
        if ($dto->statut === 'actif' && $this->repo->hasContratActif($dto->employeId)) {
            throw new \RuntimeException(
                "Cet employé a déjà un contrat actif. Résiliez ou terminez le contrat en cours avant d'en créer un nouveau."
            );
        }

        // Contrôle de chevauchement des dates
        if ($dto->dateFin !== null) {
            if ($this->repo->hasOverlap($dto->employeId, $dto->dateDebut, $dto->dateFin)) {
                throw new \RuntimeException(
                    "Les dates de ce contrat chevauchent un contrat existant pour cet employé."
                );
            }
        }

        // Avertissement CDI avec date de fin (non bloquant)
        // (Le DTO accepte la combinaison, la validation métier est documentée via commentaire)

        $numero = $this->repo->genererNumero();

        $data = array_merge($dto->toArray(), [
            'numero_contrat' => $numero,
            'created_by'     => $userId,
            'updated_by'     => $userId,
        ]);

        $id = $this->repo->insert($data);

        EventDispatcher::dispatch(new ContractCreated($id, $numero, $dto->employeId, $dto->type, $dto->dateDebut, $userId));

        return $id;
    }

    // ── Modification ──────────────────────────────────────────────────────────

    public function modifier(int $id, ContractDTO $dto, int $userId): void
    {
        $existing = $this->findById($id);

        // Seuls les contrats en brouillon ou actif sont modifiables
        if (!in_array($existing['statut'], ['brouillon', 'actif'], true)) {
            throw new \RuntimeException(
                "Seuls les contrats en brouillon ou actifs peuvent être modifiés directement. Utilisez un avenant pour les modifications sur un contrat en cours."
            );
        }

        $errors = $dto->validate();
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }

        // Contrôle chevauchement (en excluant le contrat courant)
        if ($dto->dateFin !== null) {
            if ($this->repo->hasOverlap($dto->employeId, $dto->dateDebut, $dto->dateFin, $id)) {
                throw new \RuntimeException("Les dates de ce contrat chevauchent un contrat existant.");
            }
        }

        $new  = array_merge($dto->toArray(), ['updated_by' => $userId]);
        $diff = array_filter($new, fn($v, $k) => ($existing[$k] ?? null) != $v, ARRAY_FILTER_USE_BOTH);

        if ($diff !== []) {
            $this->repo->update($id, $new);
            EventDispatcher::dispatch(new ContractUpdated($id, (int)$existing['employe_id'], 'modification', $diff, $userId));
        }
    }

    // ── Ajout d'avenant ───────────────────────────────────────────────────────

    public function ajouterAvenant(int $contratId, AvenantDTO $dto, int $userId): int
    {
        $contrat = $this->findById($contratId);

        if (!in_array($contrat['statut'], ['actif', 'suspendu'], true)) {
            throw new \RuntimeException("Les avenants ne peuvent être ajoutés qu'à un contrat actif ou suspendu.");
        }

        $errors = $dto->validate();
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }

        $numero = $this->repo->nextAvenantNumero($contratId);
        $avenantData = [
            'type'           => $dto->type,
            'objet'          => $dto->objet,
            'description'    => $dto->description,
            'date_effet'     => $dto->dateEffet,
            'ancienne_valeur'=> $dto->ancienneValeur,
            'nouvelle_valeur'=> $dto->nouvelleValeur,
        ];

        $avenantId = $this->repo->insertAvenant($contratId, $avenantData, $numero, $userId);

        EventDispatcher::dispatch(new ContractUpdated(
            $contratId, (int)$contrat['employe_id'], 'avenant',
            ['avenant_id' => $avenantId, 'objet' => $dto->objet, 'type' => $dto->type],
            $userId
        ));

        return $avenantId;
    }

    // ── Renouvellement ────────────────────────────────────────────────────────

    public function renouveler(int $id, string $nouvelleDateDebut, ?string $nouvelleDateFin, int $userId): int
    {
        $ancien = $this->findById($id);

        if (!in_array($ancien['statut'], ['actif', 'expire'], true)) {
            throw new \RuntimeException("Seuls les contrats actifs ou expirés peuvent être renouvelés.");
        }
        if (!$this->isValidDate($nouvelleDateDebut)) {
            throw new \InvalidArgumentException("La nouvelle date de début est invalide.");
        }
        if ($nouvelleDateFin !== null && $nouvelleDateFin <= $nouvelleDateDebut) {
            throw new \InvalidArgumentException("La date de fin doit être postérieure à la date de début.");
        }

        // Marquer l'ancien contrat comme expiré
        $this->repo->updateStatut($id, 'expire');

        // Créer le nouveau contrat en reprenant les mêmes paramètres
        $numero = $this->repo->genererNumero();
        $data = [
            'employe_id'       => $ancien['employe_id'],
            'poste_id'         => $ancien['poste_id'],
            'departement_id'   => $ancien['departement_id'],
            'numero_contrat'   => $numero,
            'type'             => $ancien['type'],
            'statut'           => 'actif',
            'date_debut'       => $nouvelleDateDebut,
            'date_fin'         => $nouvelleDateFin,
            'salaire_brut'     => $ancien['salaire_brut'],
            'devise'           => $ancien['devise'],
            'motif_creation'   => 'Renouvellement du contrat ' . $ancien['numero_contrat'],
            'renouvelle_depuis'=> $id,
            'created_by'       => $userId,
            'updated_by'       => $userId,
        ];

        $nouveauId = $this->repo->insert($data);

        EventDispatcher::dispatch(new ContractRenewed(
            $id, $nouveauId, $numero,
            (int)$ancien['employe_id'],
            $nouvelleDateDebut, $nouvelleDateFin, $userId
        ));

        return $nouveauId;
    }

    // ── Résiliation ───────────────────────────────────────────────────────────

    public function resilier(int $id, string $motif, int $userId): void
    {
        $contrat = $this->findById($id);

        if (!in_array($contrat['statut'], ['actif', 'suspendu'], true)) {
            throw new \RuntimeException("Seuls les contrats actifs ou suspendus peuvent être résiliés.");
        }
        if (trim($motif) === '') {
            throw new \InvalidArgumentException("Le motif de résiliation est obligatoire.");
        }

        $this->repo->update($id, [
            'statut'    => 'resilie',
            'date_fin'  => $contrat['date_fin'] ?? date('Y-m-d'),
            'motif_fin' => $motif,
            'updated_by'=> $userId,
        ]);

        EventDispatcher::dispatch(new ContractTerminated(
            $id, $contrat['numero_contrat'],
            (int)$contrat['employe_id'],
            $motif, date('Y-m-d'), $userId
        ));
    }

    // ── Suspension ────────────────────────────────────────────────────────────

    public function suspendre(int $id, int $userId): void
    {
        $contrat = $this->findById($id);

        if ($contrat['statut'] !== 'actif') {
            throw new \RuntimeException("Seul un contrat actif peut être suspendu.");
        }

        $this->repo->updateStatut($id, 'suspendu');
        EventDispatcher::dispatch(new ContractUpdated(
            $id, (int)$contrat['employe_id'], 'suspension', ['statut' => 'suspendu'], $userId
        ));
    }

    // ── Réactivation ─────────────────────────────────────────────────────────

    public function reactiver(int $id, int $userId): void
    {
        $contrat = $this->findById($id);

        if ($contrat['statut'] !== 'suspendu') {
            throw new \RuntimeException("Seul un contrat suspendu peut être réactivé.");
        }
        if ($this->repo->hasContratActif((int)$contrat['employe_id'], $id)) {
            throw new \RuntimeException("L'employé a déjà un autre contrat actif.");
        }

        $this->repo->updateStatut($id, 'actif');
        EventDispatcher::dispatch(new ContractUpdated(
            $id, (int)$contrat['employe_id'], 'reactivation', ['statut' => 'actif'], $userId
        ));
    }

    // ── Activation brouillon ──────────────────────────────────────────────────

    public function activer(int $id, int $userId): void
    {
        $contrat = $this->findById($id);

        if ($contrat['statut'] !== 'brouillon') {
            throw new \RuntimeException("Seul un contrat en brouillon peut être activé.");
        }
        if ($this->repo->hasContratActif((int)$contrat['employe_id'], $id)) {
            throw new \RuntimeException("L'employé a déjà un contrat actif. Résiliez-le d'abord.");
        }

        $this->repo->updateStatut($id, 'actif');
        EventDispatcher::dispatch(new ContractUpdated(
            $id, (int)$contrat['employe_id'], 'activation', ['statut' => 'actif'], $userId
        ));
    }

    // ── Archivage ─────────────────────────────────────────────────────────────

    public function archiver(int $id, int $userId): void
    {
        $contrat = $this->findById($id);

        if (in_array($contrat['statut'], ['actif', 'suspendu'], true)) {
            throw new \RuntimeException("Impossible d'archiver un contrat actif ou suspendu. Résiliez-le d'abord.");
        }

        $this->repo->softDelete($id);
        EventDispatcher::dispatch(new ContractUpdated(
            $id, (int)$contrat['employe_id'], 'archivage', ['archived' => true], $userId
        ));
    }

    // ── Traitement des contrats expirés (batch) ───────────────────────────────

    /**
     * À appeler via un cron ou un hook request.
     * Passe automatiquement en 'expire' les contrats dont date_fin < aujourd'hui.
     */
    public function traiterExpirations(): int
    {
        $expired = $this->repo->findExpiredToProcess();
        $count   = 0;

        foreach ($expired as $c) {
            $this->repo->updateStatut((int)$c['id'], 'expire', 'Expiration automatique à échéance');
            EventDispatcher::dispatch(new ContractExpired(
                (int)$c['id'], $c['numero_contrat'], (int)$c['employe_id'], $c['date_fin']
            ));
            $count++;
        }

        return $count;
    }

    // ── Export CSV ────────────────────────────────────────────────────────────

    public function exporterCsv(ContractFiltersDTO $f): string
    {
        $allFilters = new ContractFiltersDTO(
            q: $f->q, type: $f->type, statut: $f->statut,
            employeId: $f->employeId, departementId: $f->departementId,
            echeanceDans: $f->echeanceDans, includeArch: $f->includeArch,
            page: 1, perPage: 9999
        );
        $rows = $this->repo->findAll($allFilters);

        $bom  = "\xEF\xBB\xBF";
        $cols = ['Numéro', 'Employé', 'Matricule', 'Type', 'Statut', 'Début', 'Fin', 'Salaire brut', 'Département', 'Poste'];
        $lines = [implode(';', $cols)];

        foreach ($rows as $r) {
            $lines[] = implode(';', [
                $r['numero_contrat'],
                $r['employe_nom'],
                $r['employe_matricule'],
                ContractModel::typeLabel($r['type']),
                ContractModel::statutLabel($r['statut']),
                $r['date_debut'],
                $r['date_fin'] ?? 'Indéterminé',
                number_format((float)($r['salaire_brut'] ?? 0), 2, ',', ' '),
                $r['departement_nom'] ?? '',
                $r['poste_intitule'] ?? '',
            ]);
        }

        return $bom . implode("\n", $lines);
    }

    private function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
        [$y, $m, $d] = explode('-', $date);
        return checkdate((int)$m, (int)$d, (int)$y);
    }
}
