<?php

namespace App\Modules\Scolarite\Services;

use Core\Database;
use App\Modules\Scolarite\DTO\FamilleDTO;
use App\Modules\Scolarite\Models\FamilleModel;
use App\Modules\Scolarite\Repositories\FamilleRepository;
use App\Modules\Scolarite\Events\ParentCreated;
use App\Modules\Scolarite\Events\ParentUpdated;
use App\Modules\Scolarite\Events\ParentLinkedToStudent;
use App\Modules\Scolarite\Events\ParentUnlinkedFromStudent;
use App\Modules\Scolarite\Events\EmergencyContactUpdated;
use Core\EventDispatcher;

class FamilleService
{
    private FamilleModel      $model;
    private FamilleRepository $repo;

    public function __construct(FamilleRepository $repo)
    {
        $this->model = new FamilleModel();
        $this->repo  = $repo;
    }

    // ─── Créer ────────────────────────────────────────────────────────────────

    public function creer(FamilleDTO $dto, int $userId): int
    {
        $data             = $dto->toArray();
        $data['actif']    = 1;
        $data['created_by'] = $userId;

        $familleId = $this->model->insert($data);

        EventDispatcher::dispatch(new ParentCreated($familleId, $dto->nom, $userId));

        return $familleId;
    }

    // ─── Modifier ─────────────────────────────────────────────────────────────

    public function modifier(int $familleId, FamilleDTO $dto, int $userId): void
    {
        $famille = $this->model->findById($familleId);
        if (!$famille) {
            throw new \RuntimeException("Famille introuvable (id=$familleId).");
        }

        $data = $dto->toArray();
        $this->model->update($familleId, $data);

        // Détecter les champs modifiés
        $changed = [];
        foreach ($data as $field => $newVal) {
            if ((string)($famille->$field ?? '') !== (string)($newVal ?? '')) {
                $changed[] = $field;
            }
        }

        EventDispatcher::dispatch(new ParentUpdated($familleId, $userId, $changed));

        // Si le contact d'urgence a changé, déclencher l'événement dédié
        $urgenceFields = ['contact_urgence_nom', 'contact_urgence_telephone', 'contact_urgence_lien'];
        if (array_intersect($changed, $urgenceFields)) {
            EventDispatcher::dispatch(new EmergencyContactUpdated($familleId, $userId));
        }
    }

    // ─── Archiver ─────────────────────────────────────────────────────────────

    public function archiver(int $familleId, int $userId): void
    {
        $famille = $this->model->findById($familleId);
        if (!$famille) {
            throw new \RuntimeException("Famille introuvable (id=$familleId).");
        }

        $this->model->update($familleId, ['actif' => 0]);

        EventDispatcher::dispatch(new ParentUpdated($familleId, $userId, ['actif']));
    }

    // ─── Lier un élève ────────────────────────────────────────────────────────

    public function lierEleve(
        int    $familleId,
        int    $eleveId,
        string $lienParente,
        bool   $estContactPrincipal,
        bool   $estContactUrgence,
        int    $userId
    ): void {
        $famille = $this->model->findById($familleId);
        if (!$famille) {
            throw new \RuntimeException("Famille introuvable (id=$familleId).");
        }

        if ($this->repo->lienExiste($familleId, $eleveId)) {
            throw new \RuntimeException("Cet élève est déjà rattaché à cette famille.");
        }

        $liens = ['pere', 'mere', 'tuteur', 'autre'];
        if (!in_array($lienParente, $liens, true)) {
            throw new \InvalidArgumentException("Lien de parenté invalide : $lienParente.");
        }

        $pdo = Database::getInstance()->getConnection();
        $pdo->prepare(
            "INSERT INTO `familles_eleves`
             (famille_id, eleve_id, lien_parente, est_contact_principal, est_contact_urgence, ordre)
             VALUES (?, ?, ?, ?, ?, (
                 SELECT COALESCE(MAX(ordre), 0) + 1
                 FROM `familles_eleves` fe2 WHERE fe2.famille_id = ?
             ))"
        )->execute([
            $familleId, $eleveId, $lienParente,
            $estContactPrincipal ? 1 : 0,
            $estContactUrgence   ? 1 : 0,
            $familleId,
        ]);

        EventDispatcher::dispatch(new ParentLinkedToStudent($familleId, $eleveId, $lienParente, $userId));
    }

    // ─── Délier un élève ──────────────────────────────────────────────────────

    public function delierEleve(int $familleId, int $eleveId, int $userId): void
    {
        if (!$this->repo->lienExiste($familleId, $eleveId)) {
            throw new \RuntimeException("Aucun lien trouvé entre cette famille et cet élève.");
        }

        $pdo = Database::getInstance()->getConnection();
        $pdo->prepare(
            "DELETE FROM `familles_eleves` WHERE famille_id = ? AND eleve_id = ?"
        )->execute([$familleId, $eleveId]);

        EventDispatcher::dispatch(new ParentUnlinkedFromStudent($familleId, $eleveId, $userId));
    }

    // ─── Mettre à jour contact d'urgence ──────────────────────────────────────

    public function mettreAJourContactUrgence(int $familleId, array $data, int $userId): void
    {
        $famille = $this->model->findById($familleId);
        if (!$famille) {
            throw new \RuntimeException("Famille introuvable (id=$familleId).");
        }

        $allowed = ['contact_urgence_nom', 'contact_urgence_telephone', 'contact_urgence_lien'];
        $update  = array_intersect_key($data, array_flip($allowed));

        if (empty($update)) {
            return;
        }

        $this->model->update($familleId, $update);

        EventDispatcher::dispatch(new EmergencyContactUpdated($familleId, $userId));
    }
}
