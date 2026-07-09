<?php

namespace App\Modules\Scolarite\Services;

use App\Models\EleveModel;
use App\Modules\Scolarite\DTO\InscriptionDTO;
use App\Modules\Scolarite\Events\ClasseChanged;
use App\Modules\Scolarite\Events\InscriptionCancelled;
use App\Modules\Scolarite\Events\InscriptionCreated;
use App\Modules\Scolarite\Events\InscriptionUpdated;
use App\Modules\Scolarite\Events\InscriptionValidee;
use App\Modules\Scolarite\Events\ReinscriptionCreated;
use App\Modules\Scolarite\Events\SchoolYearChanged;
use App\Modules\Scolarite\Models\InscriptionModel;
use App\Modules\Scolarite\Repositories\ClasseRepository;
use App\Modules\Scolarite\Repositories\InscriptionRepository;
use App\Services\AuditService;
use Core\EventDispatcher;

class InscriptionService
{
    private InscriptionModel     $model;
    private EleveModel           $eleveModel;
    private ClasseRepository     $classeRepo;
    private InscriptionRepository $repo;
    private AuditService         $audit;

    public function __construct()
    {
        $this->model      = new InscriptionModel();
        $this->eleveModel = new EleveModel();
        $this->classeRepo = new ClasseRepository();
        $this->repo       = new InscriptionRepository();
        $this->audit      = new AuditService();
    }

    /**
     * Inscrire un élève pour une année scolaire.
     *
     * Règles métier :
     *   - L'élève doit exister et être actif.
     *   - Pas d'inscription active (en_attente/validee) pour la même année.
     *   - Si classe_id fourni, vérifier la capacité.
     *
     * @throws \RuntimeException Si règle métier violée.
     */
    public function inscrire(InscriptionDTO $dto, int $userId): int
    {
        $eleve = $this->eleveModel->findById($dto->eleveId);
        if (!$eleve) {
            throw new \RuntimeException("Élève introuvable (id={$dto->eleveId}).");
        }

        $existing = $this->model->findActiveByEleve($dto->eleveId, $dto->anneeScolaire);
        if ($existing) {
            $statutLabel = $existing->statut === 'validee' ? 'validée' : 'en attente';
            throw new \RuntimeException(
                "Cet élève possède déjà une inscription {$statutLabel} pour l'année {$dto->anneeScolaire}."
            );
        }

        if ($dto->classeId !== null) {
            $this->verifierCapaciteClasse($dto->classeId);
        }

        $data = [
            'eleve_id'        => $dto->eleveId,
            'annee_scolaire'  => $dto->anneeScolaire,
            'classe_id'       => $dto->classeId,
            'statut'          => 'en_attente',
            'notes'           => $dto->notes,
            'inscription_par' => $userId,
        ];

        $inscriptionId = $this->model->insert($data);
        if ($inscriptionId === 0) {
            throw new \RuntimeException("Erreur lors de la création de l'inscription.");
        }

        EventDispatcher::dispatch(new InscriptionCreated(
            inscriptionId: $inscriptionId,
            eleveId:       $dto->eleveId,
            anneeScolaire: $dto->anneeScolaire,
            classeId:      $dto->classeId,
            createdById:   $userId,
        ));

        return $inscriptionId;
    }

    /**
     * Valider une inscription → statut validee + mise à jour eleve.classe_id.
     *
     * @throws \RuntimeException Si inscription introuvable ou déjà traitée.
     */
    public function valider(int $inscriptionId, int $userId): void
    {
        $inscription = $this->model->findById($inscriptionId);
        if (!$inscription) {
            throw new \RuntimeException("Inscription introuvable.");
        }
        if ($inscription->statut !== 'en_attente') {
            throw new \RuntimeException(
                "Seules les inscriptions en attente peuvent être validées (statut actuel : {$inscription->statut})."
            );
        }

        if ($inscription->classe_id) {
            $this->verifierCapaciteClasse((int)$inscription->classe_id);
        }

        $this->model->update($inscriptionId, [
            'statut'    => 'validee',
            'valide_par'=> $userId,
            'valide_le' => date('Y-m-d H:i:s'),
        ]);

        if ($inscription->classe_id) {
            $this->eleveModel->update((int)$inscription->eleve_id, [
                'classe_id' => $inscription->classe_id,
            ]);
        }

        EventDispatcher::dispatch(new InscriptionValidee(
            inscriptionId: $inscriptionId,
            eleveId:       (int)$inscription->eleve_id,
            classeId:      (int)($inscription->classe_id ?? 0),
            valideParId:   $userId,
        ));
    }

    /**
     * Rejeter une inscription → statut rejetee.
     */
    public function rejeter(int $inscriptionId, int $userId, string $motif = ''): void
    {
        $inscription = $this->model->findById($inscriptionId);
        if (!$inscription) {
            throw new \RuntimeException("Inscription introuvable.");
        }
        if ($inscription->statut !== 'en_attente') {
            throw new \RuntimeException(
                "Seules les inscriptions en attente peuvent être rejetées (statut actuel : {$inscription->statut})."
            );
        }

        $avant = (array)$inscription;

        $this->model->update($inscriptionId, [
            'statut'       => 'rejetee',
            'motif_rejet'  => $motif,
            'valide_par'   => $userId,
            'valide_le'    => date('Y-m-d H:i:s'),
        ]);

        EventDispatcher::dispatch(new InscriptionUpdated(
            inscriptionId: $inscriptionId,
            updatedById:   $userId,
            changedFields: [
                'avant' => ['statut' => 'en_attente'],
                'apres' => ['statut' => 'rejetee', 'motif_rejet' => $motif],
            ],
        ));
    }

    /**
     * Annuler une inscription (en_attente ou validee).
     * Si validée, reset eleve.classe_id.
     */
    public function annuler(int $inscriptionId, int $userId, string $motif = ''): void
    {
        $inscription = $this->model->findById($inscriptionId);
        if (!$inscription) {
            throw new \RuntimeException("Inscription introuvable.");
        }
        if ($inscription->statut === 'annulee') {
            throw new \RuntimeException("Cette inscription est déjà annulée.");
        }
        if ($inscription->statut === 'rejetee') {
            throw new \RuntimeException("Impossible d'annuler une inscription rejetée.");
        }

        $etaitValidee = $inscription->statut === 'validee';

        $this->model->update($inscriptionId, [
            'statut'      => 'annulee',
            'motif_rejet' => $motif,
        ]);

        if ($etaitValidee && $inscription->classe_id) {
            $eleve = $this->eleveModel->findById((int)$inscription->eleve_id);
            if ($eleve && (int)$eleve->classe_id === (int)$inscription->classe_id) {
                $this->eleveModel->update((int)$inscription->eleve_id, ['classe_id' => null]);
            }
        }

        EventDispatcher::dispatch(new InscriptionCancelled(
            inscriptionId: $inscriptionId,
            eleveId:       (int)$inscription->eleve_id,
            cancelledById: $userId,
            motif:         $motif,
        ));
    }

    /**
     * Réinscrire un élève pour une nouvelle année scolaire.
     *
     * Crée une nouvelle inscription (statut en_attente) pour la nouvelle année
     * en reprenant la classe de l'inscription précédente si disponible.
     */
    public function reinscrire(
        int    $eleveId,
        string $nouvelleAnnee,
        ?int   $classeId,
        int    $userId
    ): int {
        $eleve = $this->eleveModel->findById($eleveId);
        if (!$eleve) {
            throw new \RuntimeException("Élève introuvable.");
        }

        $existing = $this->model->findActiveByEleve($eleveId, $nouvelleAnnee);
        if ($existing) {
            throw new \RuntimeException(
                "Cet élève a déjà une inscription active pour l'année {$nouvelleAnnee}."
            );
        }

        // Déterminer l'ancienne année (dernière inscription validée)
        $historique   = $this->model->findByEleve($eleveId);
        $ancienneAnnee = '';
        foreach ($historique as $h) {
            if ($h->statut === 'validee' && $h->annee_scolaire !== $nouvelleAnnee) {
                $ancienneAnnee = $h->annee_scolaire;
                break;
            }
        }

        if ($classeId !== null) {
            $this->verifierCapaciteClasse($classeId);
        }

        $data = [
            'eleve_id'        => $eleveId,
            'annee_scolaire'  => $nouvelleAnnee,
            'classe_id'       => $classeId,
            'statut'          => 'en_attente',
            'inscription_par' => $userId,
        ];

        $inscriptionId = $this->model->insert($data);
        if ($inscriptionId === 0) {
            throw new \RuntimeException("Erreur lors de la réinscription.");
        }

        EventDispatcher::dispatch(new ReinscriptionCreated(
            inscriptionId: $inscriptionId,
            eleveId:       $eleveId,
            ancienneAnnee: $ancienneAnnee,
            nouvelleAnnee: $nouvelleAnnee,
            createdById:   $userId,
        ));

        return $inscriptionId;
    }

    /**
     * Changer la classe d'une inscription validée.
     * Met à jour eleve.classe_id en même temps.
     */
    public function changerClasse(int $inscriptionId, int $nouvelleClasseId, int $userId): void
    {
        $inscription = $this->model->findById($inscriptionId);
        if (!$inscription) {
            throw new \RuntimeException("Inscription introuvable.");
        }
        if ($inscription->statut !== 'validee') {
            throw new \RuntimeException(
                "Le changement de classe n'est possible que pour les inscriptions validées."
            );
        }

        $ancienneClasseId = $inscription->classe_id ? (int)$inscription->classe_id : null;

        if ($ancienneClasseId === $nouvelleClasseId) {
            throw new \RuntimeException("L'élève est déjà dans cette classe.");
        }

        $this->verifierCapaciteClasse($nouvelleClasseId);

        $this->model->update($inscriptionId, ['classe_id' => $nouvelleClasseId]);
        $this->eleveModel->update((int)$inscription->eleve_id, ['classe_id' => $nouvelleClasseId]);

        EventDispatcher::dispatch(new ClasseChanged(
            inscriptionId:    $inscriptionId,
            eleveId:          (int)$inscription->eleve_id,
            ancienneClasseId: $ancienneClasseId,
            nouvelleClasseId: $nouvelleClasseId,
            changedById:      $userId,
        ));
    }

    /**
     * Changer l'année scolaire d'une inscription (correction administrative).
     */
    public function changerAnnee(int $inscriptionId, string $nouvelleAnnee, int $userId): void
    {
        $inscription = $this->model->findById($inscriptionId);
        if (!$inscription) {
            throw new \RuntimeException("Inscription introuvable.");
        }

        $ancienneAnnee = $inscription->annee_scolaire;
        if ($ancienneAnnee === $nouvelleAnnee) {
            throw new \RuntimeException("L'année scolaire est déjà '{$nouvelleAnnee}'.");
        }

        $existingDest = $this->model->findActiveByEleve((int)$inscription->eleve_id, $nouvelleAnnee);
        if ($existingDest) {
            throw new \RuntimeException(
                "Cet élève a déjà une inscription active pour l'année {$nouvelleAnnee}."
            );
        }

        $this->model->update($inscriptionId, ['annee_scolaire' => $nouvelleAnnee]);

        EventDispatcher::dispatch(new SchoolYearChanged(
            inscriptionId: $inscriptionId,
            eleveId:       (int)$inscription->eleve_id,
            ancienneAnnee: $ancienneAnnee,
            nouvelleAnnee: $nouvelleAnnee,
            changedById:   $userId,
        ));
    }

    // ─── Helper privé ────────────────────────────────────────────────────────

    /**
     * @throws \RuntimeException Si classe pleine.
     */
    private function verifierCapaciteClasse(int $classeId): void
    {
        $details = $this->classeRepo->findWithDetails($classeId);
        if (!$details) {
            throw new \RuntimeException("Classe introuvable (id={$classeId}).");
        }
        $actuel = (int)$details->nb_eleves;
        $max    = (int)$details->max_eleves;
        if ($max > 0 && $actuel >= $max) {
            throw new \RuntimeException(
                "La classe {$details->niveau} {$details->nom} est pleine ({$actuel}/{$max} élèves)."
            );
        }
    }
}
