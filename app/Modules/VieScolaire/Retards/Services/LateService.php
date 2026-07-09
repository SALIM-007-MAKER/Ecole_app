<?php

namespace App\Modules\VieScolaire\Retards\Services;

use App\Modules\VieScolaire\Retards\DTO\LateDTO;
use App\Modules\VieScolaire\Retards\DTO\LateFiltersDTO;
use App\Modules\VieScolaire\Retards\DTO\LateJustificationDTO;
use App\Modules\VieScolaire\Retards\Events\LateJustified;
use App\Modules\VieScolaire\Retards\Events\LateRejected;
use App\Modules\VieScolaire\Retards\Events\LateThresholdReached;
use App\Modules\VieScolaire\Retards\Events\StudentLate;
use App\Modules\VieScolaire\Retards\Repositories\LateRepository;
use App\Services\AuditService;
use Core\EventDispatcher;

class LateService
{
    const SEUIL_ALERTE = 3;

    private LateRepository $repo;
    private AuditService   $audit;

    public function __construct()
    {
        $this->repo  = new LateRepository();
        $this->audit = new AuditService();
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    public function findById(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    public function paginate(LateFiltersDTO $filters): array
    {
        $rows  = $this->repo->findAll($filters);
        $total = $this->repo->countAll($filters);

        return [
            'data'      => $rows,
            'total'     => $total,
            'page'      => $filters->page,
            'per_page'  => $filters->perPage,
            'last_page' => (int)ceil($total / max($filters->perPage, 1)),
        ];
    }

    public function statistiquesEleve(int $eleveId, string $anneeScolaire): array
    {
        return $this->repo->statsByEleve($eleveId, $anneeScolaire);
    }

    public function statistiquesClasse(int $classeId, string $anneeScolaire): array
    {
        return $this->repo->statsByClasse($classeId, $anneeScolaire);
    }

    // ── Création manuelle ─────────────────────────────────────────────────────

    public function enregistrerManuellement(LateDTO $dto, int $userId): int
    {
        $errors = $dto->validate();
        if (!empty($errors)) {
            throw new \InvalidArgumentException(
                implode(' | ', array_merge(...array_values($errors)))
            );
        }

        $data       = array_merge($dto->toArray(), ['saisie_par' => $userId]);
        $retardId   = $this->repo->insert($data);

        $this->audit->logCreate($userId, 'vie_scolaire', 'retard', $retardId, $data);

        EventDispatcher::dispatch(new StudentLate(
            retardId:      $retardId,
            eleveId:       $dto->eleveId,
            classeId:      $dto->classeId,
            dateRetard:    $dto->dateRetard,
            retardMinutes: $dto->dureeMinutes,
            heureArrivee:  $dto->heureArrivee,
            anneeScolaire: $dto->anneeScolaire,
            saisieParId:   $userId,
        ));

        $this->verifierSeuil($dto->eleveId, $dto->anneeScolaire, $retardId, $dto->classeId);

        return $retardId;
    }

    /**
     * Vérifie si le seuil d'alerte est atteint après un nouveau retard.
     * Appelé par LateService::enregistrerManuellement() et par StatisticsListener (Retards).
     */
    public function verifierSeuil(int $eleveId, string $anneeScolaire, int $retardId, int $classeId): void
    {
        $total = $this->repo->countByEleveAndAnnee($eleveId, $anneeScolaire);
        if ($total >= self::SEUIL_ALERTE) {
            EventDispatcher::dispatch(new LateThresholdReached(
                eleveId:       $eleveId,
                classeId:      $classeId,
                retardId:      $retardId,
                totalRetards:  $total,
                seuilAtteint:  self::SEUIL_ALERTE,
                anneeScolaire: $anneeScolaire,
            ));
        }
    }

    // ── Mise à jour des champs horaires ──────────────────────────────────────

    public function mettreAJour(int $retardId, array $fields, int $userId): void
    {
        $retard = $this->repo->findById($retardId);
        if ($retard === null) {
            throw new \RuntimeException("Retard introuvable.");
        }
        if ($retard['statut'] === 'justifie') {
            throw new \RuntimeException("Un retard justifié ne peut plus être modifié.");
        }

        $heurePrevue  = !empty($fields['heure_prevue'])  ? $fields['heure_prevue']  : null;
        $heureArrivee = $fields['heure_arrivee'] ?? $retard['heure_arrivee'];
        $duree        = 0;
        if ($heurePrevue !== null && $heureArrivee) {
            $diffSec = max(0, strtotime($heureArrivee) - strtotime($heurePrevue));
            $duree   = (int)floor($diffSec / 60);
        }

        $this->repo->update($retardId, [
            'heure_prevue'  => $heurePrevue,
            'heure_arrivee' => $heureArrivee,
            'duree_minutes' => $duree,
            'observation'   => $fields['observation'] ?? null,
        ]);

        $this->audit->log($userId, 'update', 'vie_scolaire', 'retard', $retardId);
    }

    // ── Justification ─────────────────────────────────────────────────────────

    public function soumettreJustification(int $retardId, LateJustificationDTO $dto, int $userId): int
    {
        $errors = $dto->validate();
        if (!empty($errors)) {
            throw new \InvalidArgumentException(
                implode(' | ', array_merge(...array_values($errors)))
            );
        }

        $retard = $this->repo->findById($retardId);
        if ($retard === null) {
            throw new \RuntimeException("Retard introuvable.");
        }

        $existing = $this->repo->findJustificationByRetard($retardId);
        if ($existing !== null) {
            throw new \RuntimeException("Une justification a déjà été soumise pour ce retard.");
        }

        $justifId = $this->repo->insertJustification([
            'retard_id'           => $retardId,
            'motif_description'   => $dto->motifDescription,
            'fichier_justificatif'=> $dto->fichierJustificatif,
            'soumis_par'          => $userId,
        ]);

        $this->repo->updateStatut($retardId, 'en_attente');
        $this->audit->log($userId, 'justify', 'vie_scolaire', 'retard', $retardId);

        return $justifId;
    }

    public function validerJustification(int $retardId, int $userId): void
    {
        $retard = $this->repo->findById($retardId);
        if ($retard === null) {
            throw new \RuntimeException("Retard introuvable.");
        }

        $justif = $this->repo->findJustificationByRetard($retardId);
        if ($justif === null || $justif['statut'] !== 'en_attente') {
            throw new \RuntimeException("Aucune justification en attente pour ce retard.");
        }

        $ok = $this->repo->validerJustification((int)$justif['id'], $userId);
        if (!$ok) {
            throw new \RuntimeException("La validation a échoué.");
        }

        $this->repo->updateStatut($retardId, 'justifie');
        $this->audit->log($userId, 'validate', 'vie_scolaire', 'retard_justification', (int)$justif['id']);

        EventDispatcher::dispatch(new LateJustified(
            retardId:        $retardId,
            eleveId:         (int)$retard['eleve_id'],
            classeId:        (int)$retard['classe_id'],
            anneeScolaire:   (string)$retard['annee_scolaire'],
            justificationId: (int)$justif['id'],
            valideParId:     $userId,
        ));
    }

    public function refuserJustification(int $retardId, string $motifRefus, int $userId): void
    {
        if (empty(trim($motifRefus))) {
            throw new \InvalidArgumentException("Le motif de refus est obligatoire.");
        }

        $retard = $this->repo->findById($retardId);
        if ($retard === null) {
            throw new \RuntimeException("Retard introuvable.");
        }

        $justif = $this->repo->findJustificationByRetard($retardId);
        if ($justif === null || $justif['statut'] !== 'en_attente') {
            throw new \RuntimeException("Aucune justification en attente pour ce retard.");
        }

        $ok = $this->repo->refuserJustification((int)$justif['id'], $userId, $motifRefus);
        if (!$ok) {
            throw new \RuntimeException("Le refus a échoué.");
        }

        $this->repo->updateStatut($retardId, 'refuse');
        $this->audit->log($userId, 'reject', 'vie_scolaire', 'retard_justification', (int)$justif['id']);

        EventDispatcher::dispatch(new LateRejected(
            retardId:        $retardId,
            eleveId:         (int)$retard['eleve_id'],
            classeId:        (int)$retard['classe_id'],
            anneeScolaire:   (string)$retard['annee_scolaire'],
            justificationId: (int)$justif['id'],
            motifRefus:      $motifRefus,
            rejeteParId:     $userId,
        ));
    }

    // ── Archivage (soft delete) ───────────────────────────────────────────────

    public function archiver(int $retardId, int $userId): void
    {
        $retard = $this->repo->findById($retardId);
        if ($retard === null) {
            throw new \RuntimeException("Retard introuvable.");
        }
        if ($retard['statut'] === 'justifie') {
            throw new \RuntimeException("Un retard justifié ne peut pas être supprimé.");
        }

        $this->repo->softDelete($retardId);
        $this->audit->logDelete($userId, 'vie_scolaire', 'retard', $retardId, $retard);
    }
}
