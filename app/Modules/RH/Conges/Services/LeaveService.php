<?php

declare(strict_types=1);

namespace App\Modules\RH\Conges\Services;

use App\Modules\RH\Conges\DTO\LeaveDTO;
use App\Modules\RH\Conges\DTO\LeaveFiltersDTO;
use App\Modules\RH\Conges\DTO\LeaveSoldeDTO;
use App\Modules\RH\Conges\Events\LeaveApproved;
use App\Modules\RH\Conges\Events\LeaveCancelled;
use App\Modules\RH\Conges\Events\LeaveFinished;
use App\Modules\RH\Conges\Events\LeaveRejected;
use App\Modules\RH\Conges\Events\LeaveRequested;
use App\Modules\RH\Conges\Events\LeaveStarted;
use App\Modules\RH\Conges\Models\LeaveModel;
use App\Modules\RH\Conges\Repositories\LeaveRepository;
use Core\EventDispatcher;

class LeaveService
{
    private LeaveRepository $repo;

    public function __construct()
    {
        $this->repo = new LeaveRepository();
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    public function paginate(LeaveFiltersDTO $f): array
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
            throw new \RuntimeException('Demande de congé introuvable.');
        }
        return $c;
    }

    public function findEnAttente(): array
    {
        return $this->repo->findEnAttente();
    }

    public function findHistorique(int $congeId): array
    {
        return $this->repo->findHistorique($congeId);
    }

    public function statistiques(): array
    {
        return $this->repo->statistiques();
    }

    public function referentiels(?int $employeId = null): array
    {
        return [
            'employes'     => $this->repo->findEmployes(),
            'typesConges'  => $this->repo->findTypesConges(),
            'contrats'     => $employeId ? $this->repo->findContratsActifs($employeId) : [],
            'affectations' => $employeId ? $this->repo->findAffectationsActives($employeId) : [],
            'departements' => $this->repo->findDepartements(),
        ];
    }

    // ── Création (brouillon) ──────────────────────────────────────────────────

    public function creer(LeaveDTO $dto, int $userId, string $userName): int
    {
        $errors = $dto->validate();
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }

        // Vérifier chevauchement
        if ($this->repo->findChevauchements($dto->employeId, $dto->dateDebut, $dto->dateFin) > 0) {
            throw new \RuntimeException(
                'Un congé ou une absence est déjà enregistré sur cette période pour cet employé.'
            );
        }

        // Récupérer le type
        $type = $this->repo->findTypeById($dto->typeCongeId);
        if (!$type) {
            throw new \RuntimeException('Type de congé introuvable.');
        }

        // Calculer durée
        $dureeJours = $this->calculerDureeJours($dto->dateDebut, $dto->dateFin);

        // Vérifier durée maximale
        if ($type['duree_max_jours'] !== null && $dureeJours > (int)$type['duree_max_jours']) {
            throw new \RuntimeException(
                "Ce type de congé est limité à {$type['duree_max_jours']} jour(s). "
                . "Durée demandée : {$dureeJours} jour(s)."
            );
        }

        $id = $this->repo->insert(array_merge($dto->toArray(), [
            'duree_jours' => $dureeJours,
            'statut'      => 'brouillon',
            'impact_paie' => (int)($type['is_paye'] ?? 1),
            'created_by'  => $userId,
            'updated_by'  => $userId,
        ]));

        $this->repo->insertHistorique([
            'conge_id'        => $id,
            'statut_avant'    => '',
            'statut_apres'    => 'brouillon',
            'action'          => 'creation',
            'commentaire'     => null,
            'effectue_par'    => $userId,
            'effectue_par_nom'=> $userName,
        ]);

        return $id;
    }

    // ── Modification (brouillon uniquement) ───────────────────────────────────

    public function modifier(int $id, LeaveDTO $dto, int $userId, string $userName): void
    {
        $c = $this->findById($id);

        if ($c['statut'] !== 'brouillon') {
            throw new \RuntimeException(
                'Seules les demandes en brouillon peuvent être modifiées directement.'
            );
        }

        $errors = $dto->validate();
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }

        if ($this->repo->findChevauchements($dto->employeId, $dto->dateDebut, $dto->dateFin, $id) > 0) {
            throw new \RuntimeException('Un congé existe déjà sur cette période pour cet employé.');
        }

        $type = $this->repo->findTypeById($dto->typeCongeId);
        if (!$type) {
            throw new \RuntimeException('Type de congé introuvable.');
        }

        $dureeJours = $this->calculerDureeJours($dto->dateDebut, $dto->dateFin);

        if ($type['duree_max_jours'] !== null && $dureeJours > (int)$type['duree_max_jours']) {
            throw new \RuntimeException(
                "Ce type de congé est limité à {$type['duree_max_jours']} jour(s)."
            );
        }

        $this->repo->update($id, array_merge($dto->toArray(), [
            'duree_jours' => $dureeJours,
            'updated_by'  => $userId,
        ]));

        $this->repo->insertHistorique([
            'conge_id'        => $id,
            'statut_avant'    => 'brouillon',
            'statut_apres'    => 'brouillon',
            'action'          => 'modification',
            'commentaire'     => null,
            'effectue_par'    => $userId,
            'effectue_par_nom'=> $userName,
        ]);
    }

    // ── Soumission → en attente d'approbation ─────────────────────────────────

    public function soumettre(int $id, int $userId, string $userName): void
    {
        $c = $this->findById($id);
        $this->assertTransition($c, 'soumettre');

        // Vérifier le solde avant soumission si le type décompte du solde
        $type = $this->repo->findTypeById((int)$c['type_conge_id']);
        if ($type && $type['debit_solde']) {
            $annee = (int)date('Y', strtotime($c['date_debut']));
            $solde = $this->repo->findSolde((int)$c['employe_id'], (int)$c['type_conge_id'], $annee);
            if ($solde) {
                $restant = LeaveModel::soldeRestant($solde);
                if ($restant < (float)$c['duree_jours']) {
                    throw new \RuntimeException(
                        "Solde insuffisant : {$restant} jour(s) disponible(s), "
                        . "{$c['duree_jours']} demandé(s)."
                    );
                }
            }
        }

        $this->repo->update($id, ['statut' => 'soumis', 'updated_by' => $userId]);

        $this->repo->insertHistorique([
            'conge_id'        => $id,
            'statut_avant'    => 'brouillon',
            'statut_apres'    => 'soumis',
            'action'          => 'soumission',
            'commentaire'     => null,
            'effectue_par'    => $userId,
            'effectue_par_nom'=> $userName,
        ]);

        EventDispatcher::dispatch(new LeaveRequested(
            $id, (int)$c['employe_id'], $type['code'] ?? '',
            $c['date_debut'], $c['date_fin'], (float)$c['duree_jours'], $userId
        ));
    }

    // ── Approbation ───────────────────────────────────────────────────────────

    public function approuver(int $id, int $userId, string $userName): void
    {
        $c = $this->findById($id);
        $this->assertTransition($c, 'approuver');

        $this->repo->update($id, [
            'statut'           => 'approuve',
            'approuve_par'     => $userId,
            'approuve_par_nom' => $userName,
            'date_approbation' => date('Y-m-d H:i:s'),
            'updated_by'       => $userId,
        ]);

        // Mettre à jour solde_en_attente
        $type = $this->repo->findTypeById((int)$c['type_conge_id']);
        if ($type && $type['debit_solde']) {
            $annee = (int)date('Y', strtotime($c['date_debut']));
            $this->repo->incrementSoldeEnAttente(
                (int)$c['employe_id'], (int)$c['type_conge_id'], $annee, (float)$c['duree_jours']
            );
        }

        $this->repo->insertHistorique([
            'conge_id'        => $id,
            'statut_avant'    => 'soumis',
            'statut_apres'    => 'approuve',
            'action'          => 'approbation',
            'commentaire'     => null,
            'effectue_par'    => $userId,
            'effectue_par_nom'=> $userName,
        ]);

        EventDispatcher::dispatch(new LeaveApproved(
            $id, (int)$c['employe_id'], $type['code'] ?? '',
            $c['date_debut'], $c['date_fin'], (float)$c['duree_jours'], $userId
        ));
    }

    // ── Rejet ─────────────────────────────────────────────────────────────────

    public function rejeter(int $id, string $motif, int $userId, string $userName): void
    {
        if (trim($motif) === '') {
            throw new \InvalidArgumentException('Le motif de rejet est obligatoire.');
        }

        $c = $this->findById($id);
        $this->assertTransition($c, 'rejeter');

        $this->repo->update($id, [
            'statut'      => 'rejete',
            'motif_rejet' => $motif,
            'updated_by'  => $userId,
        ]);

        $this->repo->insertHistorique([
            'conge_id'        => $id,
            'statut_avant'    => $c['statut'],
            'statut_apres'    => 'rejete',
            'action'          => 'rejet',
            'commentaire'     => $motif,
            'effectue_par'    => $userId,
            'effectue_par_nom'=> $userName,
        ]);

        $type = $this->repo->findTypeById((int)$c['type_conge_id']);

        EventDispatcher::dispatch(new LeaveRejected(
            $id, (int)$c['employe_id'], $type['code'] ?? '',
            $c['date_debut'], $c['date_fin'], $motif, $userId
        ));
    }

    // ── Annulation ────────────────────────────────────────────────────────────

    public function annuler(int $id, string $motif, int $userId, string $userName): void
    {
        if (trim($motif) === '') {
            throw new \InvalidArgumentException("Le motif d'annulation est obligatoire.");
        }

        $c = $this->findById($id);
        $this->assertTransition($c, 'annuler');

        $ancienStatut = $c['statut'];

        $this->repo->update($id, [
            'statut'            => 'annule',
            'motif_annulation'  => $motif,
            'annule_par'        => $userId,
            'annule_par_nom'    => $userName,
            'date_annulation'   => date('Y-m-d H:i:s'),
            'updated_by'        => $userId,
        ]);

        // Reset solde_en_attente si le congé avait été approuvé
        $type = $this->repo->findTypeById((int)$c['type_conge_id']);
        if ($type && $type['debit_solde'] && in_array($ancienStatut, ['approuve', 'en_cours'], true)) {
            $annee = (int)date('Y', strtotime($c['date_debut']));
            $this->repo->decrementSoldeEnAttente(
                (int)$c['employe_id'], (int)$c['type_conge_id'], $annee, (float)$c['duree_jours']
            );
        }

        $this->repo->insertHistorique([
            'conge_id'        => $id,
            'statut_avant'    => $ancienStatut,
            'statut_apres'    => 'annule',
            'action'          => 'annulation',
            'commentaire'     => $motif,
            'effectue_par'    => $userId,
            'effectue_par_nom'=> $userName,
        ]);

        EventDispatcher::dispatch(new LeaveCancelled(
            $id, (int)$c['employe_id'], $type['code'] ?? '',
            $ancienStatut, $motif, $userId
        ));
    }

    // ── Démarrage ─────────────────────────────────────────────────────────────

    public function demarrer(int $id, int $userId, string $userName): void
    {
        $c = $this->findById($id);
        $this->assertTransition($c, 'demarrer');

        $this->repo->update($id, ['statut' => 'en_cours', 'updated_by' => $userId]);

        $this->repo->insertHistorique([
            'conge_id'        => $id,
            'statut_avant'    => 'approuve',
            'statut_apres'    => 'en_cours',
            'action'          => 'demarrage',
            'commentaire'     => null,
            'effectue_par'    => $userId,
            'effectue_par_nom'=> $userName,
        ]);

        $type = $this->repo->findTypeById((int)$c['type_conge_id']);

        EventDispatcher::dispatch(new LeaveStarted(
            $id, (int)$c['employe_id'], $type['code'] ?? '',
            $c['date_debut'], $c['date_fin'], (float)$c['duree_jours'], $userId
        ));
    }

    // ── Fin de congé ──────────────────────────────────────────────────────────

    public function terminer(int $id, ?string $dateRetour, ?string $commentaire, int $userId, string $userName): void
    {
        $c = $this->findById($id);
        $this->assertTransition($c, 'terminer');

        $dateRetourEffectif = $dateRetour ?: date('Y-m-d');

        $this->repo->update($id, [
            'statut'               => 'termine',
            'date_retour_effectif' => $dateRetourEffectif,
            'commentaire_retour'   => $commentaire ?: null,
            'updated_by'           => $userId,
        ]);

        // Convertir solde_en_attente → solde_pris
        $type = $this->repo->findTypeById((int)$c['type_conge_id']);
        if ($type && $type['debit_solde']) {
            $annee = (int)date('Y', strtotime($c['date_debut']));
            $this->repo->transfertSoldeEnAttendePris(
                (int)$c['employe_id'], (int)$c['type_conge_id'], $annee, (float)$c['duree_jours']
            );
        }

        $this->repo->insertHistorique([
            'conge_id'        => $id,
            'statut_avant'    => 'en_cours',
            'statut_apres'    => 'termine',
            'action'          => 'fin_conge',
            'commentaire'     => $commentaire,
            'effectue_par'    => $userId,
            'effectue_par_nom'=> $userName,
        ]);

        EventDispatcher::dispatch(new LeaveFinished(
            $id, (int)$c['employe_id'], $type['code'] ?? '',
            (float)$c['duree_jours'], $dateRetourEffectif, $userId
        ));
    }

    // ── Archivage ─────────────────────────────────────────────────────────────

    public function archiver(int $id, int $userId): void
    {
        $c = $this->findById($id);
        if ($c['statut'] !== 'brouillon') {
            throw new \RuntimeException("Seuls les brouillons peuvent être supprimés.");
        }
        $this->repo->softDelete($id);
    }

    // ── Gestion des soldes ────────────────────────────────────────────────────

    public function getSoldes(?int $employeId = null, ?int $annee = null): array
    {
        return $this->repo->findSoldes($employeId, $annee);
    }

    public function definirSolde(LeaveSoldeDTO $dto, int $userId): void
    {
        $errors = $dto->validate();
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' | ', $errors));
        }
        $this->repo->upsertSolde($dto->employeId, $dto->typeCongeId, $dto->annee, $dto->soldeInitial);
    }

    // ── Export CSV ────────────────────────────────────────────────────────────

    public function exporterCsv(LeaveFiltersDTO $f): string
    {
        $allFilters = new LeaveFiltersDTO(
            q: $f->q, statut: $f->statut, typeCode: $f->typeCode,
            employeId: $f->employeId, departementId: $f->departementId,
            dateDebut: $f->dateDebut, dateFin: $f->dateFin, annee: $f->annee,
            includeArch: $f->includeArch, page: 1, perPage: 9999
        );
        $rows = $this->repo->findAll($allFilters);

        $bom  = "\xEF\xBB\xBF";
        $cols = ['Employé','Matricule','Type','Début','Fin','Durée (j)','Statut','Payé','Approuvé par','Date approbation'];
        $lines = [implode(';', $cols)];

        foreach ($rows as $r) {
            $lines[] = implode(';', [
                $r['employe_nom_complet'],
                $r['employe_matricule'],
                $r['type_libelle'],
                $r['date_debut'],
                $r['date_fin'],
                number_format((float)$r['duree_jours'], 1, '.', ''),
                $r['statut'],
                $r['is_paye'] ? 'Oui' : 'Non',
                $r['approuve_par_nom'] ?? '',
                $r['date_approbation'] ?? '',
            ]);
        }

        return $bom . implode("\n", $lines);
    }

    // ── Calculs métier ────────────────────────────────────────────────────────

    public function calculerDureeJours(string $dateDebut, string $dateFin): float
    {
        $debut   = new \DateTime($dateDebut);
        $fin     = new \DateTime($dateFin);
        $fin->modify('+1 day');

        $jours   = 0;
        $current = clone $debut;

        while ($current < $fin) {
            $dow = (int)$current->format('N'); // 1=Lundi … 5=Vendredi, 6=Sam, 7=Dim
            if ($dow < 6) {
                $jours++;
            }
            $current->modify('+1 day');
        }

        return (float)$jours;
    }

    // ── Transition guard ──────────────────────────────────────────────────────

    private function assertTransition(array $c, string $action): void
    {
        if (!LeaveModel::canTransition($c['statut'], $action)) {
            throw new \RuntimeException(
                "Action '{$action}' non autorisée pour le statut '{$c['statut']}'."
            );
        }
    }
}
