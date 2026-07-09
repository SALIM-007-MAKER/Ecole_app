<?php

namespace App\Modules\Scolarite\Services;

use App\Models\EleveModel;
use App\Models\EnseignementModel;
use App\Modules\Scolarite\Events\EleveAssignedToClasse;
use App\Modules\Scolarite\Events\EleveRemovedFromClasse;
use App\Modules\Scolarite\Repositories\ClasseRepository;
use App\Services\AuditService;
use Core\EventDispatcher;

class AffectationService
{
    private EleveModel       $eleveModel;
    private EnseignementModel $enseignementModel;
    private ClasseRepository $classeRepo;
    private AuditService     $audit;

    public function __construct()
    {
        $this->eleveModel        = new EleveModel();
        $this->enseignementModel = new EnseignementModel();
        $this->classeRepo        = new ClasseRepository();
        $this->audit             = new AuditService();
    }

    /**
     * Affecter un enseignant à une matière dans une classe.
     *
     * @throws \RuntimeException si l'affectation existe déjà
     */
    public function affecterEnseignant(
        int    $classeId,
        int    $professeurId,
        int    $matiereId,
        string $anneeScolaire,
        int    $parUserId,
    ): void {
        if ($this->enseignementModel->exists($professeurId, $matiereId, $classeId, $anneeScolaire)) {
            throw new \RuntimeException(
                "Cet enseignant est déjà affecté à cette matière dans cette classe pour l'année $anneeScolaire."
            );
        }

        $data = [
            'professeur_id'  => $professeurId,
            'matiere_id'     => $matiereId,
            'classe_id'      => $classeId,
            'annee_scolaire' => $anneeScolaire,
        ];

        $id = $this->enseignementModel->insert($data);

        $this->audit->logCreate($parUserId, 'scolarite', 'enseignement', $id, $data);
    }

    /**
     * Retirer un enseignant d'une affectation (par ID d'enseignement).
     *
     * @throws \RuntimeException si l'enseignement a des contrôles liés
     */
    public function retirerEnseignant(int $enseignementId, int $parUserId): void
    {
        $ens = $this->enseignementModel->findById($enseignementId);
        if (!$ens) {
            throw new \RuntimeException("Enseignement introuvable.");
        }

        $this->enseignementModel->delete($enseignementId);

        $this->audit->logDelete($parUserId, 'scolarite', 'enseignement', $enseignementId, (array)$ens);
    }

    /**
     * Affecter un élève à une classe.
     *
     * @throws \RuntimeException si la classe est pleine
     */
    public function affecterEleve(int $eleveId, int $classeId, int $parUserId): void
    {
        $capacite = $this->classeRepo->findWithDetails($classeId);
        if (!$capacite) {
            throw new \RuntimeException("Classe introuvable.");
        }

        $actuel = (int)$capacite->nb_eleves;
        $max    = (int)$capacite->max_eleves;
        if ($max > 0 && $actuel >= $max) {
            throw new \RuntimeException(
                "La classe est pleine ({$actuel}/{$max} élèves). Augmentez la capacité avant d'affecter un nouvel élève."
            );
        }

        $avant = $this->eleveModel->findById($eleveId);
        $this->eleveModel->update($eleveId, ['classe_id' => $classeId]);

        $this->audit->logUpdate(
            $parUserId,
            'scolarite',
            'eleve',
            $eleveId,
            ['classe_id' => $avant->classe_id ?? null],
            ['classe_id' => $classeId],
        );

        EventDispatcher::dispatch(new EleveAssignedToClasse($eleveId, $classeId, $parUserId));
    }

    /**
     * Retirer un élève de sa classe (classe_id = NULL).
     */
    public function retirerEleve(int $eleveId, int $parUserId): void
    {
        $avant = $this->eleveModel->findById($eleveId);
        if (!$avant) {
            throw new \RuntimeException("Élève introuvable.");
        }

        $classeId = (int)($avant->classe_id ?? 0);
        $this->eleveModel->update($eleveId, ['classe_id' => null]);

        $this->audit->logUpdate(
            $parUserId,
            'scolarite',
            'eleve',
            $eleveId,
            ['classe_id' => $classeId],
            ['classe_id' => null],
        );

        if ($classeId > 0) {
            EventDispatcher::dispatch(new EleveRemovedFromClasse($eleveId, $classeId, $parUserId));
        }
    }
}
