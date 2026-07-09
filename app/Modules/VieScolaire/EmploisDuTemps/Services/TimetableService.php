<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\Services;

use App\Modules\VieScolaire\EmploisDuTemps\DTO\CreneauDTO;
use App\Modules\VieScolaire\EmploisDuTemps\DTO\RemplacementDTO;
use App\Modules\VieScolaire\EmploisDuTemps\DTO\TimetableDTO;
use App\Modules\VieScolaire\EmploisDuTemps\DTO\TimetableFiltersDTO;
use App\Modules\VieScolaire\EmploisDuTemps\Events\TeacherReplacementAssigned;
use App\Modules\VieScolaire\EmploisDuTemps\Events\TimetableConflictDetected;
use App\Modules\VieScolaire\EmploisDuTemps\Events\TimetableCreated;
use App\Modules\VieScolaire\EmploisDuTemps\Events\TimetablePublished;
use App\Modules\VieScolaire\EmploisDuTemps\Events\TimetableUpdated;
use App\Modules\VieScolaire\EmploisDuTemps\Repositories\TimetableRepository;
use Core\EventDispatcher;

class TimetableService
{
    private TimetableRepository $repo;

    public function __construct()
    {
        $this->repo = new TimetableRepository();
    }

    // ── Emplois du temps ──────────────────────────────────────────────────────

    public function findEdt(int $id): ?array
    {
        return $this->repo->findEdtById($id);
    }

    public function paginateEdts(TimetableFiltersDTO $f): array
    {
        $total = $this->repo->countAllEdts($f);
        return [
            'data'  => $this->repo->findAllEdts($f),
            'total' => $total,
            'page'  => $f->page,
            'pages' => max(1, (int)ceil($total / $f->perPage)),
        ];
    }

    public function findOrCreateEdt(TimetableDTO $dto, int $userId): array
    {
        $existing = $this->repo->findEdtByClasse(
            $dto->classeId, $dto->anneeScolaire, $dto->periodeId, $dto->semaineType
        );
        if ($existing !== null) {
            return $existing;
        }

        $edtId = $this->repo->insertEdt([
            'classe_id'      => $dto->classeId,
            'annee_scolaire' => $dto->anneeScolaire,
            'periode_id'     => $dto->periodeId,
            'semaine_type'   => $dto->semaineType,
            'cree_par'       => $userId,
        ]);

        EventDispatcher::dispatch(new TimetableCreated(
            edtId:         $edtId,
            classeId:      $dto->classeId,
            anneeScolaire: $dto->anneeScolaire,
            semaineType:   $dto->semaineType,
            creeParId:     $userId,
        ));

        return $this->repo->findEdtById($edtId);
    }

    public function publierEdt(int $edtId, int $userId): void
    {
        $edt = $this->repo->findEdtById($edtId);
        if ($edt === null) {
            throw new \RuntimeException('Emploi du temps introuvable.');
        }
        if ($edt['statut'] !== 'brouillon') {
            throw new \RuntimeException('Seul un brouillon peut être publié.');
        }

        $creneaux = $this->repo->findCreneauxByEdt($edtId);
        if (empty($creneaux)) {
            throw new \RuntimeException('Impossible de publier un emploi du temps sans créneaux.');
        }

        $newVersion = $this->repo->incrementVersion($edtId);

        $this->repo->insertVersion([
            'emploi_du_temps_id' => $edtId,
            'version'            => $newVersion,
            'snapshot'           => json_encode($creneaux, JSON_UNESCAPED_UNICODE),
            'motif'              => 'Publication v' . $newVersion,
            'modifie_par'        => $userId,
        ]);

        $this->repo->updateEdtStatut($edtId, 'publie', $userId);

        EventDispatcher::dispatch(new TimetablePublished(
            edtId:         $edtId,
            classeId:      $edt['classe_id'],
            anneeScolaire: $edt['annee_scolaire'],
            version:       $newVersion,
            publieParId:   $userId,
        ));
    }

    public function archiverEdt(int $edtId, int $userId): void
    {
        $edt = $this->repo->findEdtById($edtId);
        if ($edt === null) {
            throw new \RuntimeException('Emploi du temps introuvable.');
        }
        $this->repo->updateEdtStatut($edtId, 'archive');
    }

    // ── Créneaux ──────────────────────────────────────────────────────────────

    public function grille(int $edtId): array
    {
        $creneaux = $this->repo->findCreneauxByEdt($edtId);
        $plages   = $this->repo->findAllPlages();

        $grille = [];
        foreach ($creneaux as $cr) {
            $grille[$cr['jour']][$cr['plage_id']] = $cr;
        }

        return ['creneaux' => $creneaux, 'grille' => $grille, 'plages' => $plages];
    }

    public function ajouterCreneau(CreneauDTO $dto, int $userId): array
    {
        $edt = $this->repo->findEdtById($dto->emploiDuTempsId);
        if ($edt === null || $edt['statut'] === 'archive') {
            throw new \RuntimeException('Emploi du temps inaccessible.');
        }

        $conflits = $this->detecterConflits($dto);
        if (!empty($conflits)) {
            foreach ($conflits as $conflit) {
                EventDispatcher::dispatch(new TimetableConflictDetected(
                    typeConflit:   $conflit['type'],
                    edtId:         $dto->emploiDuTempsId,
                    classeId:      $dto->classeId,
                    anneeScolaire: $dto->anneeScolaire,
                    jour:          $dto->jour,
                    plageId:       $dto->plageId,
                    details:       $conflit['message'],
                    detecteParId:  $userId,
                ));
            }
            throw new \RuntimeException(
                'Conflits détectés : ' . implode(' | ', array_column($conflits, 'message'))
            );
        }

        $creneauId = $this->repo->insertCreneau($dto->toArray());

        EventDispatcher::dispatch(new TimetableUpdated(
            edtId:         $dto->emploiDuTempsId,
            classeId:      $dto->classeId,
            anneeScolaire: $dto->anneeScolaire,
            action:        'creneau_ajoute',
            modifieParId:  $userId,
        ));

        return $this->repo->findCreneauById($creneauId);
    }

    public function modifierCreneau(int $creneauId, CreneauDTO $dto, int $userId): void
    {
        $creneau = $this->repo->findCreneauById($creneauId);
        if ($creneau === null) {
            throw new \RuntimeException('Créneau introuvable.');
        }

        $conflits = $this->detecterConflits($dto, $creneauId);
        if (!empty($conflits)) {
            throw new \RuntimeException(
                'Conflits détectés : ' . implode(' | ', array_column($conflits, 'message'))
            );
        }

        $this->repo->updateCreneau($creneauId, $dto->toArray());

        EventDispatcher::dispatch(new TimetableUpdated(
            edtId:         $dto->emploiDuTempsId,
            classeId:      $dto->classeId,
            anneeScolaire: $dto->anneeScolaire,
            action:        'creneau_modifie',
            modifieParId:  $userId,
        ));
    }

    public function supprimerCreneau(int $creneauId, int $userId): void
    {
        $creneau = $this->repo->findCreneauById($creneauId);
        if ($creneau === null) {
            throw new \RuntimeException('Créneau introuvable.');
        }

        $this->repo->softDeleteCreneau($creneauId);

        EventDispatcher::dispatch(new TimetableUpdated(
            edtId:         $creneau['emploi_du_temps_id'],
            classeId:      $creneau['classe_id'],
            anneeScolaire: $creneau['annee_scolaire'],
            action:        'creneau_supprime',
            modifieParId:  $userId,
        ));
    }

    // ── Remplacements ─────────────────────────────────────────────────────────

    public function assignerRemplacement(RemplacementDTO $dto, int $userId): array
    {
        $creneau = $this->repo->findCreneauById($dto->creneauId);
        if ($creneau === null) {
            throw new \RuntimeException('Créneau introuvable.');
        }

        $remplacementId = $this->repo->insertRemplacement([
            'creneau_id'              => $dto->creneauId,
            'date_remplacement'       => $dto->dateRemplacement,
            'enseignant_absent_id'    => $dto->enseignantAbsentId,
            'remplacant_id'           => $dto->remplacantId,
            'matiere_remplacement_id' => $dto->matiereRemplacementId,
            'salle_remplacement_id'   => $dto->salleRemplacementId,
            'motif_absence'           => $dto->motifAbsence,
            'cree_par'                => $userId,
        ]);

        EventDispatcher::dispatch(new TeacherReplacementAssigned(
            remplacementId:      $remplacementId,
            creneauId:           $dto->creneauId,
            enseignantAbsentId:  $dto->enseignantAbsentId,
            remplacantId:        $dto->remplacantId,
            dateRemplacement:    $dto->dateRemplacement,
            creeParId:           $userId,
        ));

        return ['id' => $remplacementId];
    }

    // ── Référentiels ──────────────────────────────────────────────────────────

    public function plages(): array  { return $this->repo->findAllPlages(); }
    public function salles(): array  { return $this->repo->findAllSalles(); }

    public function edtParEnseignant(int $enseignantId, string $annee): array
    {
        $creneaux = $this->repo->findCreneauxByEnseignant($enseignantId, $annee);
        $plages   = $this->repo->findAllPlages();

        $grille = [];
        foreach ($creneaux as $cr) {
            $grille[$cr['jour']][$cr['plage_id']] = $cr;
        }
        return ['creneaux' => $creneaux, 'grille' => $grille, 'plages' => $plages];
    }

    public function heuresEnseignant(int $enseignantId, string $annee): array
    {
        return $this->repo->countHeuresByEnseignant($enseignantId, $annee);
    }

    public function statistiquesClasse(int $classeId, string $annee): array
    {
        return $this->repo->statsByClasse($classeId, $annee);
    }

    public function versions(int $edtId): array
    {
        return $this->repo->findVersions($edtId);
    }

    public function remplacementsDuJour(string $date): array
    {
        return $this->repo->findRemplacementsByDate($date);
    }

    // ── Détection de conflits (privée) ────────────────────────────────────────

    private function detecterConflits(CreneauDTO $dto, ?int $excludeId = null): array
    {
        $conflits = [];

        $confEns = $this->repo->checkConflitEnseignant(
            $dto->enseignantId, $dto->jour, $dto->plageId, $dto->anneeScolaire, $excludeId
        );
        if ($confEns !== null) {
            $conflits[] = [
                'type'   => 'enseignant',
                'message'=> "L'enseignant est déjà affecté à {$confEns['classe_nom']} ({$confEns['matiere_nom']}).",
            ];
        }

        if ($dto->salleId !== null) {
            $confSalle = $this->repo->checkConflitSalle(
                $dto->salleId, $dto->jour, $dto->plageId, $dto->anneeScolaire, $excludeId
            );
            if ($confSalle !== null) {
                $conflits[] = [
                    'type'   => 'salle',
                    'message'=> "La salle est déjà occupée par {$confSalle['classe_nom']} ({$confSalle['matiere_nom']}).",
                ];
            }
        }

        $confClasse = $this->repo->checkConflitClasse(
            $dto->classeId, $dto->jour, $dto->plageId, $dto->anneeScolaire, $excludeId
        );
        if ($confClasse !== null) {
            $conflits[] = [
                'type'   => 'classe',
                'message'=> "La classe a déjà un cours de {$confClasse['matiere_nom']} sur ce créneau.",
            ];
        }

        return $conflits;
    }
}
