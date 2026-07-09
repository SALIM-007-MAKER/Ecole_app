<?php

namespace App\Modules\VieScolaire\Activites\Services;

use App\Modules\VieScolaire\Activites\DTO\ActivityDTO;
use App\Modules\VieScolaire\Activites\DTO\ActivityFiltersDTO;
use App\Modules\VieScolaire\Activites\DTO\InscriptionActivityDTO;
use App\Modules\VieScolaire\Activites\Events\ActivityCancelled;
use App\Modules\VieScolaire\Activites\Events\ActivityCreated;
use App\Modules\VieScolaire\Activites\Events\ActivityPublished;
use App\Modules\VieScolaire\Activites\Events\ActivityUpdated;
use App\Modules\VieScolaire\Activites\Events\StudentRegisteredToActivity;
use App\Modules\VieScolaire\Activites\Repositories\ActivityRepository;
use Core\EventDispatcher;

class ActivityService
{
    private ActivityRepository $repo;

    public function __construct()
    {
        $this->repo = new ActivityRepository();
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    public function findActivity(int $id): ?array
    {
        $activity = $this->repo->findById($id);
        if ($activity === null) return null;

        $activity['classes']      = $this->repo->findClasses($id);
        $activity['responsables'] = $this->repo->findResponsables($id);
        return $activity;
    }

    public function paginateActivities(ActivityFiltersDTO $f): array
    {
        $total = $this->repo->countAll($f);
        return [
            'data'  => $this->repo->findAll($f),
            'total' => $total,
            'page'  => $f->page,
            'pages' => max(1, (int)ceil($total / $f->perPage)),
        ];
    }

    public function categories(): array
    {
        return $this->repo->findAllCategories();
    }

    public function inscriptions(int $activiteId): array
    {
        return $this->repo->findInscriptions($activiteId);
    }

    public function historique(int $activiteId): array
    {
        return $this->repo->findHistorique($activiteId);
    }

    // ── Détection de conflits EDT (advisory) ──────────────────────────────────

    public function checkConflitsEdt(array $activite, array $classeIds, array $responsableIds): array
    {
        return $this->repo->checkConflitsEdt($activite, $classeIds, $responsableIds);
    }

    // ── Création ──────────────────────────────────────────────────────────────

    public function creerActivite(ActivityDTO $dto, int $userId): array
    {
        $data          = $dto->toArray();
        $data['cree_par'] = $userId;

        $id = $this->repo->insert($data);
        $this->repo->insertClasses($id, $dto->classeIds);
        $this->repo->insertResponsables($id, $dto->responsableIds);

        $this->repo->insertHistorique([
            'activite_id' => $id,
            'action'      => 'created',
            'description' => 'Activité créée',
            'data'        => $dto->toArray(),
            'user_id'     => $userId,
        ]);

        EventDispatcher::dispatch(new ActivityCreated(
            activityId:    $id,
            categorieId:   $dto->categorieId,
            titre:         $dto->titre,
            dateActivite:  $dto->dateActivite,
            anneeScolaire: $dto->anneeScolaire,
            creeParId:     $userId,
        ));

        return $this->repo->findById($id);
    }

    // ── Modification ──────────────────────────────────────────────────────────

    public function modifierActivite(int $id, ActivityDTO $dto, int $userId): void
    {
        $existing = $this->repo->findById($id);
        if ($existing === null) {
            throw new \RuntimeException('Activité introuvable.');
        }
        if (in_array($existing['statut'], ['annule', 'termine'], true)) {
            throw new \RuntimeException('Impossible de modifier une activité annulée ou terminée.');
        }

        $this->repo->update($id, $dto->toArray());
        $this->repo->deleteClasses($id);
        $this->repo->insertClasses($id, $dto->classeIds);
        $this->repo->deleteResponsables($id);
        $this->repo->insertResponsables($id, $dto->responsableIds);

        $this->repo->insertHistorique([
            'activite_id' => $id,
            'action'      => 'updated',
            'description' => 'Activité modifiée',
            'data'        => $dto->toArray(),
            'user_id'     => $userId,
        ]);

        EventDispatcher::dispatch(new ActivityUpdated(
            activityId:    $id,
            anneeScolaire: $dto->anneeScolaire,
            action:        'updated',
            modifieParId:  $userId,
        ));
    }

    // ── Publication ───────────────────────────────────────────────────────────

    public function publierActivite(int $id, int $userId): void
    {
        $activity = $this->repo->findById($id);
        if ($activity === null) {
            throw new \RuntimeException('Activité introuvable.');
        }
        if ($activity['statut'] !== 'brouillon') {
            throw new \RuntimeException('Seul un brouillon peut être publié.');
        }

        $this->repo->updateStatut($id, 'publie', ['publie_par' => $userId]);

        $this->repo->insertHistorique([
            'activite_id' => $id,
            'action'      => 'published',
            'description' => 'Activité publiée',
            'user_id'     => $userId,
        ]);

        EventDispatcher::dispatch(new ActivityPublished(
            activityId:    $id,
            titre:         $activity['titre'],
            dateActivite:  $activity['date_activite'],
            anneeScolaire: $activity['annee_scolaire'],
            publieParId:   $userId,
        ));
    }

    // ── Annulation ────────────────────────────────────────────────────────────

    public function annulerActivite(int $id, string $motif, int $userId): void
    {
        $activity = $this->repo->findById($id);
        if ($activity === null) {
            throw new \RuntimeException('Activité introuvable.');
        }
        if (in_array($activity['statut'], ['annule', 'termine'], true)) {
            throw new \RuntimeException('L\'activité est déjà annulée ou terminée.');
        }
        if (mb_strlen(trim($motif)) < 10) {
            throw new \RuntimeException('Le motif d\'annulation doit contenir au moins 10 caractères.');
        }

        $this->repo->updateStatut($id, 'annule', [
            'annule_par'      => $userId,
            'motif_annulation'=> $motif,
        ]);

        $this->repo->insertHistorique([
            'activite_id' => $id,
            'action'      => 'cancelled',
            'description' => 'Activité annulée : ' . mb_substr($motif, 0, 100),
            'user_id'     => $userId,
        ]);

        EventDispatcher::dispatch(new ActivityCancelled(
            activityId:    $id,
            titre:         $activity['titre'],
            anneeScolaire: $activity['annee_scolaire'],
            motif:         $motif,
            annuleParId:   $userId,
        ));
    }

    // ── Inscriptions ──────────────────────────────────────────────────────────

    public function inscrireEleve(InscriptionActivityDTO $dto, int $userId): array
    {
        $activity = $this->repo->findById($dto->activiteId);
        if ($activity === null) {
            throw new \RuntimeException('Activité introuvable.');
        }
        if (in_array($activity['statut'], ['annule', 'termine'], true)) {
            throw new \RuntimeException('Les inscriptions sont fermées pour cette activité.');
        }

        $existing = $this->repo->findInscriptionByEleveAndActivite($dto->eleveId, $dto->activiteId);
        if ($existing !== null && $existing['statut'] !== 'annule') {
            throw new \RuntimeException('Cet élève est déjà inscrit ou en liste d\'attente.');
        }

        $nbInscrits = $this->repo->countInscrits($dto->activiteId);
        $statut = ($nbInscrits >= $activity['capacite_max']) ? 'liste_attente' : 'inscrit';

        $inscriptionId = $this->repo->insertInscription([
            'activite_id' => $dto->activiteId,
            'eleve_id'    => $dto->eleveId,
            'statut'      => $statut,
            'inscrit_par' => $userId,
            'note'        => $dto->note,
        ]);

        $this->repo->insertHistorique([
            'activite_id' => $dto->activiteId,
            'action'      => 'inscription_added',
            'description' => "Élève #{$dto->eleveId} inscrit ({$statut})",
            'user_id'     => $userId,
        ]);

        EventDispatcher::dispatch(new StudentRegisteredToActivity(
            inscriptionId: $inscriptionId,
            activityId:    $dto->activiteId,
            eleveId:       $dto->eleveId,
            statut:        $statut,
            anneeScolaire: $activity['annee_scolaire'],
            inscritParId:  $userId,
        ));

        return ['id' => $inscriptionId, 'statut' => $statut];
    }

    public function annulerInscription(int $inscriptionId, int $userId): void
    {
        $inscription = $this->repo->findInscriptionById($inscriptionId);
        if ($inscription === null) {
            throw new \RuntimeException('Inscription introuvable.');
        }
        if ($inscription['statut'] === 'annule') {
            throw new \RuntimeException('Inscription déjà annulée.');
        }

        $wasInscrit = $inscription['statut'] === 'inscrit';

        $this->repo->updateInscriptionStatut($inscriptionId, 'annule', $userId);

        $this->repo->insertHistorique([
            'activite_id' => $inscription['activite_id'],
            'action'      => 'inscription_cancelled',
            'description' => "Inscription #{$inscriptionId} annulée",
            'user_id'     => $userId,
        ]);

        // Promouvoir le premier de la liste d'attente si une place se libère
        if ($wasInscrit) {
            $suivant = $this->repo->findFirstListeAttente($inscription['activite_id']);
            if ($suivant !== null) {
                $this->repo->updateInscriptionStatut($suivant['id'], 'inscrit');
                $this->repo->insertHistorique([
                    'activite_id' => $inscription['activite_id'],
                    'action'      => 'inscription_promoted',
                    'description' => "Élève #{$suivant['eleve_id']} promu depuis la liste d'attente",
                    'user_id'     => $userId,
                ]);
            }
        }
    }

    public function marquerPresences(int $activiteId, array $presences, int $userId): void
    {
        $activity = $this->repo->findById($activiteId);
        if ($activity === null) {
            throw new \RuntimeException('Activité introuvable.');
        }

        $this->repo->updatePresences($activiteId, $presences);

        // Passer l'activité en "termine" si elle l'est
        if ($activity['statut'] === 'en_cours' || $activity['statut'] === 'publie') {
            $this->repo->updateStatut($activiteId, 'termine');
        }

        $this->repo->insertHistorique([
            'activite_id' => $activiteId,
            'action'      => 'presences_marked',
            'description' => count($presences) . ' présence(s) marquée(s)',
            'user_id'     => $userId,
        ]);

        EventDispatcher::dispatch(new ActivityUpdated(
            activityId:    $activiteId,
            anneeScolaire: $activity['annee_scolaire'],
            action:        'presences_marked',
            modifieParId:  $userId,
        ));
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function statistiques(string $annee): array
    {
        return $this->repo->statsByAnnee($annee);
    }
}
