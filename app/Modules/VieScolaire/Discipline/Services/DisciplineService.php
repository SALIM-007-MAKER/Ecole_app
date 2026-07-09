<?php

namespace App\Modules\VieScolaire\Discipline\Services;

use App\Modules\VieScolaire\Discipline\DTO\AppealDTO;
use App\Modules\VieScolaire\Discipline\DTO\DisciplineDTO;
use App\Modules\VieScolaire\Discipline\DTO\DisciplineFiltersDTO;
use App\Modules\VieScolaire\Discipline\DTO\SanctionDTO;
use App\Modules\VieScolaire\Discipline\Events\DisciplineAppealSubmitted;
use App\Modules\VieScolaire\Discipline\Events\DisciplineCaseClosed;
use App\Modules\VieScolaire\Discipline\Events\DisciplineCaseCreated;
use App\Modules\VieScolaire\Discipline\Events\DisciplinaryActionAssigned;
use App\Modules\VieScolaire\Discipline\Repositories\DisciplineRepository;
use Core\EventDispatcher;

class DisciplineService
{
    private DisciplineRepository $repo;

    public function __construct()
    {
        $this->repo = new DisciplineRepository();
    }

    // ── Dossiers ──────────────────────────────────────────────────────────────

    public function findDossier(int $id): ?array
    {
        return $this->repo->findDossierById($id);
    }

    public function findOrCreateDossier(int $eleveId, int $classeId, string $annee, int $userId): array
    {
        $dossier = $this->repo->findDossierByEleveAndAnnee($eleveId, $annee);
        if ($dossier !== null) {
            return $dossier;
        }

        $dossierId = $this->repo->insertDossier([
            'eleve_id'      => $eleveId,
            'classe_id'     => $classeId,
            'annee_scolaire'=> $annee,
            'cree_par'      => $userId,
        ]);

        return $this->repo->findDossierById($dossierId);
    }

    public function paginateDossiers(DisciplineFiltersDTO $f): array
    {
        return [
            'data'  => $this->repo->findDossiers($f),
            'total' => $this->repo->countDossiers($f),
            'page'  => $f->page,
            'pages' => (int)ceil($this->repo->countDossiers($f) / $f->perPage),
        ];
    }

    // ── Incidents ─────────────────────────────────────────────────────────────

    public function signalerIncident(DisciplineDTO $dto, int $userId): array
    {
        $dossier = $this->findOrCreateDossier(
            $dto->eleveId, $dto->classeId, $dto->anneeScolaire, $userId
        );

        if ($dossier['statut'] === 'clos') {
            throw new \RuntimeException('Le dossier disciplinaire est clos.');
        }

        $incidentId = $this->repo->insertIncident(array_merge($dto->toArray(), [
            'dossier_id'  => $dossier['id'],
            'signale_par' => $userId,
        ]));

        $premierIncident = ($dossier['nb_incidents'] === 0);
        $this->repo->incrementNbIncidents($dossier['id']);

        $incident   = $this->repo->findIncidentById($incidentId);
        $categories = $this->repo->findAllCategories();
        $catCode    = '';
        foreach ($categories as $c) {
            if ((int)$c['id'] === (int)$dto->categorieId) {
                $catCode = $c['code'];
                break;
            }
        }

        EventDispatcher::dispatch(new DisciplineCaseCreated(
            incidentId:     $incidentId,
            dossierId:      $dossier['id'],
            eleveId:        $dto->eleveId,
            classeId:       $dto->classeId,
            gravite:        $dto->gravite,
            categorieCode:  $catCode,
            anneeScolaire:  $dto->anneeScolaire,
            signaleParId:   $userId,
            premierIncident: $premierIncident,
        ));

        return $incident;
    }

    public function traiterIncident(int $incidentId, int $userId): void
    {
        $incident = $this->repo->findIncidentById($incidentId);
        if ($incident === null) {
            throw new \RuntimeException('Incident introuvable.');
        }
        if ($incident['statut'] !== 'ouvert') {
            throw new \RuntimeException('Seul un incident ouvert peut être traité.');
        }
        $this->repo->updateIncidentStatut($incidentId, 'traite');
    }

    public function classerIncident(int $incidentId, int $userId): void
    {
        $incident = $this->repo->findIncidentById($incidentId);
        if ($incident === null) {
            throw new \RuntimeException('Incident introuvable.');
        }
        if ($incident['statut'] === 'classe') {
            throw new \RuntimeException('Incident déjà classé.');
        }
        $this->repo->updateIncidentStatut($incidentId, 'classe');
    }

    // ── Sanctions ─────────────────────────────────────────────────────────────

    public function prononcerSanction(int $dossierId, SanctionDTO $dto, int $userId): array
    {
        $dossier = $this->repo->findDossierById($dossierId);
        if ($dossier === null) {
            throw new \RuntimeException('Dossier introuvable.');
        }
        if ($dossier['statut'] === 'clos') {
            throw new \RuntimeException('Impossible de sanctionner sur un dossier clos.');
        }

        $sanctionId = $this->repo->insertSanction(array_merge($dto->toArray(), [
            'dossier_id'  => $dossierId,
            'prononce_par'=> $userId,
        ]));

        $this->repo->insertSanctionHistorique([
            'sanction_id'   => $sanctionId,
            'ancien_statut' => null,
            'nouveau_statut'=> 'prononcee',
            'motif'         => $dto->motif,
            'modifie_par'   => $userId,
        ]);

        EventDispatcher::dispatch(new DisciplinaryActionAssigned(
            sanctionId:    $sanctionId,
            dossierId:     $dossierId,
            eleveId:       $dossier['eleve_id'],
            typeSanction:  $dto->typeSanction,
            dateSanction:  $dto->dateSanction,
            anneeScolaire: $dossier['annee_scolaire'],
            prononceParId: $userId,
        ));

        return $this->repo->findSanctionById($sanctionId);
    }

    public function validerSanction(int $sanctionId, int $userId): void
    {
        $sanction = $this->repo->findSanctionById($sanctionId);
        if ($sanction === null) {
            throw new \RuntimeException('Sanction introuvable.');
        }
        if ($sanction['statut'] !== 'prononcee') {
            throw new \RuntimeException('Seule une sanction prononcée peut être validée.');
        }

        $this->repo->insertSanctionHistorique([
            'sanction_id'   => $sanctionId,
            'ancien_statut' => 'prononcee',
            'nouveau_statut'=> 'effective',
            'motif'         => 'Validation administrative',
            'modifie_par'   => $userId,
        ]);
        $this->repo->updateSanctionStatut($sanctionId, 'effective', $userId);
    }

    public function leverSanction(int $sanctionId, string $motif, int $userId): void
    {
        $sanction = $this->repo->findSanctionById($sanctionId);
        if ($sanction === null) {
            throw new \RuntimeException('Sanction introuvable.');
        }
        if (!in_array($sanction['statut'], ['prononcee', 'effective', 'executee'], true)) {
            throw new \RuntimeException('Sanction ne peut plus être levée.');
        }

        $this->repo->insertSanctionHistorique([
            'sanction_id'   => $sanctionId,
            'ancien_statut' => $sanction['statut'],
            'nouveau_statut'=> 'levee',
            'motif'         => $motif,
            'modifie_par'   => $userId,
        ]);
        $this->repo->updateSanctionStatut($sanctionId, 'levee');
    }

    // ── Appels ────────────────────────────────────────────────────────────────

    public function soumettreAppel(int $sanctionId, AppealDTO $dto, int $userId): array
    {
        $sanction = $this->repo->findSanctionById($sanctionId);
        if ($sanction === null) {
            throw new \RuntimeException('Sanction introuvable.');
        }
        if (!in_array($sanction['statut'], ['prononcee', 'effective'], true)) {
            throw new \RuntimeException('Cette sanction ne peut plus faire l\'objet d\'un appel.');
        }
        if ($this->repo->findAppelBySanction($sanctionId) !== null) {
            throw new \RuntimeException('Un appel existe déjà pour cette sanction.');
        }

        $dossier = $this->repo->findDossierById($sanction['dossier_id']);

        $appelId = $this->repo->insertAppel([
            'sanction_id'  => $sanctionId,
            'dossier_id'   => $sanction['dossier_id'],
            'description'  => $dto->description,
            'piece_jointe' => $dto->pieceJointe,
            'depose_par'   => $userId,
        ]);

        EventDispatcher::dispatch(new DisciplineAppealSubmitted(
            appelId:      $appelId,
            sanctionId:   $sanctionId,
            dossierId:    $sanction['dossier_id'],
            eleveId:      $dossier['eleve_id'],
            anneeScolaire:$dossier['annee_scolaire'],
            deposePar:    $userId,
        ));

        return $this->repo->findAppelBySanction($sanctionId);
    }

    public function traiterAppel(int $appelId, string $decision, string $statut, int $userId): void
    {
        $valid = ['accepte', 'rejete'];
        if (!in_array($statut, $valid, true)) {
            throw new \InvalidArgumentException('Statut appel invalide.');
        }
        $this->repo->updateAppel($appelId, [
            'statut'      => $statut,
            'examine_par' => $userId,
            'decision'    => $decision,
        ]);
    }

    // ── Clôture ───────────────────────────────────────────────────────────────

    public function clotureDossier(int $dossierId, int $userId): void
    {
        $dossier = $this->repo->findDossierById($dossierId);
        if ($dossier === null) {
            throw new \RuntimeException('Dossier introuvable.');
        }
        if ($dossier['statut'] === 'clos') {
            throw new \RuntimeException('Dossier déjà clos.');
        }

        $this->repo->updateDossierStatut($dossierId, 'clos', $userId);

        EventDispatcher::dispatch(new DisciplineCaseClosed(
            dossierId:       $dossierId,
            eleveId:         $dossier['eleve_id'],
            classeId:        $dossier['classe_id'],
            anneeScolaire:   $dossier['annee_scolaire'],
            nbIncidentsTotal:(int)$dossier['nb_incidents'],
            closParId:       $userId,
        ));
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function statistiquesEleve(int $eleveId, string $annee): array
    {
        return $this->repo->statsByEleve($eleveId, $annee);
    }

    public function statistiquesClasse(int $classeId, string $annee): array
    {
        return $this->repo->statsByClasse($classeId, $annee);
    }

    public function categories(): array
    {
        return $this->repo->findAllCategories();
    }
}
