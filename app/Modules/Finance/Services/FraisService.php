<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\DTO\CategorieFraisDTO;
use App\Modules\Finance\DTO\FraisTypeDTO;
use App\Modules\Finance\Events\FeeActivated;
use App\Modules\Finance\Events\FeeArchived;
use App\Modules\Finance\Events\FeeCreated;
use App\Modules\Finance\Events\FeeDeactivated;
use App\Modules\Finance\Events\FeeUpdated;
use App\Modules\Finance\Models\CategorieFraisModel;
use App\Modules\Finance\Models\FraisTypeModel;
use App\Modules\Finance\Repositories\FraisRepository;
use App\Services\AuditService;
use Core\EventDispatcher;

class FraisService
{
    private FraisTypeModel     $model;
    private CategorieFraisModel $categorieModel;
    private FraisRepository    $repo;
    private AuditService       $audit;

    public function __construct(FraisRepository $repo)
    {
        $this->model          = new FraisTypeModel();
        $this->categorieModel = new CategorieFraisModel();
        $this->repo           = $repo;
        $this->audit          = new AuditService();
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TYPES DE FRAIS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Créer un nouveau type de frais.
     *
     * Règle métier #1 : un même frais ne peut pas être créé deux fois
     * pour la même année scolaire, le même niveau et la même catégorie.
     */
    public function creer(FraisTypeDTO $dto, int $userId): int
    {
        // Unicité du code
        if ($this->model->codeExists($dto->code)) {
            throw new \RuntimeException(
                "Un frais avec le code « {$dto->code} » existe déjà."
            );
        }

        // Règle métier #1 — doublon catégorie/année/niveaux
        if ($this->repo->existeDoublon($dto->categorieId, $dto->anneeScolaire, $dto->niveauxCibles)) {
            throw new \RuntimeException(
                "Un frais de même catégorie pour cette année scolaire et ces niveaux existe déjà. " .
                "Veuillez modifier l'existant ou choisir une catégorie / des niveaux différents."
            );
        }

        $data           = $dto->toArray();
        $data['statut'] = 'actif';

        $fraisTypeId = $this->model->insert($data);

        $this->repo->logHistorique('frais_type', $fraisTypeId, 'create', null, $data, $userId);

        EventDispatcher::dispatch(new FeeCreated(
            $fraisTypeId,
            $dto->code,
            $dto->nom,
            $dto->montantDefaut,
            $dto->periodicite,
            $dto->estObligatoire,
            $dto->anneeScolaire,
            $userId
        ));

        return $fraisTypeId;
    }

    /**
     * Modifier un type de frais.
     * Le code est immuable après création.
     * Toute modification est tracée dans l'audit (règle métier #4).
     */
    public function modifier(int $fraisTypeId, FraisTypeDTO $dto, int $userId): void
    {
        $frais = $this->model->findById($fraisTypeId);
        if (!$frais) {
            throw new \RuntimeException("Type de frais introuvable (id=$fraisTypeId).");
        }

        if ($frais->statut === 'archive') {
            throw new \RuntimeException(
                "Impossible de modifier un frais archivé. " .
                "Créez un nouveau frais pour l'année scolaire concernée."
            );
        }

        // Règle métier #1 — doublon sur modification (exclure soi-même)
        if ($this->repo->existeDoublon($dto->categorieId, $dto->anneeScolaire, $dto->niveauxCibles, $fraisTypeId)) {
            throw new \RuntimeException(
                "Un autre frais de même catégorie pour cette année scolaire et ces niveaux existe déjà."
            );
        }

        $avant = $this->model->findById($fraisTypeId);
        $data  = $dto->toArray();
        unset($data['code']); // Le code est immuable

        $changedFields = [];
        foreach ($data as $field => $newVal) {
            $oldVal = (string)($avant->$field ?? '');
            if ($oldVal !== (string)($newVal ?? '')) {
                $changedFields[$field] = ['avant' => $avant->$field, 'apres' => $newVal];
            }
        }

        if (empty($changedFields)) {
            return; // Aucune modification réelle
        }

        $this->model->update($fraisTypeId, $data);

        $this->repo->logHistorique(
            'frais_type',
            $fraisTypeId,
            'update',
            array_map(fn($v) => $v['avant'], $changedFields),
            array_map(fn($v) => $v['apres'], $changedFields),
            $userId
        );

        EventDispatcher::dispatch(new FeeUpdated(
            $fraisTypeId,
            $dto->nom,
            $changedFields,
            $userId
        ));
    }

    /**
     * Activer un frais inactif.
     */
    public function activer(int $fraisTypeId, int $userId): void
    {
        $frais = $this->model->findById($fraisTypeId);
        if (!$frais) {
            throw new \RuntimeException("Type de frais introuvable (id=$fraisTypeId).");
        }

        if ($frais->statut === 'archive') {
            throw new \RuntimeException("Un frais archivé ne peut pas être réactivé.");
        }

        if ($frais->statut === 'actif') {
            return; // Déjà actif
        }

        $this->model->update($fraisTypeId, ['statut' => 'actif']);

        $this->repo->logHistorique(
            'frais_type', $fraisTypeId, 'activate',
            ['statut' => 'inactif'], ['statut' => 'actif'], $userId
        );

        EventDispatcher::dispatch(new FeeActivated($fraisTypeId, $frais->nom, $userId));
    }

    /**
     * Désactiver un frais actif (sans archiver).
     */
    public function desactiver(int $fraisTypeId, int $userId): void
    {
        $frais = $this->model->findById($fraisTypeId);
        if (!$frais) {
            throw new \RuntimeException("Type de frais introuvable (id=$fraisTypeId).");
        }

        if ($frais->statut === 'archive') {
            throw new \RuntimeException("Un frais archivé ne peut pas être désactivé.");
        }

        if ($frais->statut === 'inactif') {
            return; // Déjà inactif
        }

        $this->model->update($fraisTypeId, ['statut' => 'inactif']);

        $this->repo->logHistorique(
            'frais_type', $fraisTypeId, 'deactivate',
            ['statut' => 'actif'], ['statut' => 'inactif'], $userId
        );

        EventDispatcher::dispatch(new FeeDeactivated($fraisTypeId, $frais->nom, $userId));
    }

    /**
     * Archiver un frais.
     * Un frais archivé reste consultable dans l'historique (règle métier #3).
     * Un frais déjà utilisé dans une facture ne peut pas être supprimé (règle #2)
     * mais peut être archivé.
     */
    public function archiver(int $fraisTypeId, string $motif, int $userId): void
    {
        $frais = $this->model->findById($fraisTypeId);
        if (!$frais) {
            throw new \RuntimeException("Type de frais introuvable (id=$fraisTypeId).");
        }

        if ($frais->statut === 'archive') {
            return; // Déjà archivé
        }

        $this->model->update($fraisTypeId, ['statut' => 'archive']);

        $this->repo->logHistorique(
            'frais_type', $fraisTypeId, 'archive',
            ['statut' => $frais->statut],
            ['statut' => 'archive', 'motif' => $motif],
            $userId
        );

        EventDispatcher::dispatch(new FeeArchived($fraisTypeId, $frais->nom, $motif, $userId));
    }

    /**
     * Supprimer physiquement un frais.
     * Règle métier #2 : impossible si utilisé dans une facture.
     */
    public function supprimer(int $fraisTypeId, int $userId): void
    {
        $frais = $this->model->findById($fraisTypeId);
        if (!$frais) {
            throw new \RuntimeException("Type de frais introuvable (id=$fraisTypeId).");
        }

        $nbFactures = $this->repo->countUsageInFactures($fraisTypeId);
        if ($nbFactures > 0) {
            throw new \RuntimeException(
                "Impossible de supprimer : ce frais est utilisé dans $nbFactures facture(s). " .
                "Utilisez l'archivage à la place."
            );
        }

        $this->repo->logHistorique(
            'frais_type', $fraisTypeId, 'delete',
            ['nom' => $frais->nom, 'code' => $frais->code, 'statut' => $frais->statut],
            null,
            $userId
        );

        $this->model->delete($fraisTypeId);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // TARIFS PAR NIVEAU / CLASSE
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Définir ou mettre à jour un tarif pour un niveau/classe spécifique.
     * Utilise REPLACE INTO via delete+insert pour garder la contrainte UNIQUE.
     */
    public function definirTarif(int $fraisTypeId, array $params, int $userId): void
    {
        $frais = $this->model->findById($fraisTypeId);
        if (!$frais) {
            throw new \RuntimeException("Type de frais introuvable (id=$fraisTypeId).");
        }

        if ($frais->statut === 'archive') {
            throw new \RuntimeException("Impossible de modifier le tarif d'un frais archivé.");
        }

        $montant      = (float)str_replace(',', '.', $params['montant'] ?? 0);
        $anneeScolaire = trim($params['annee_scolaire'] ?? '');
        $niveau       = trim($params['niveau'] ?? '') ?: null;
        $classeId     = !empty($params['classe_id']) ? (int)$params['classe_id'] : null;
        $devise       = strtoupper(trim($params['devise'] ?? $frais->devise));

        if ($montant <= 0) {
            throw new \RuntimeException("Le montant du tarif doit être supérieur à 0.");
        }
        if (!preg_match('/^\d{4}-\d{4}$/', $anneeScolaire)) {
            throw new \RuntimeException("Format d'année scolaire invalide (attendu : AAAA-AAAA).");
        }

        $existing = $this->repo->findTarif($fraisTypeId, $anneeScolaire, $niveau, $classeId);
        $avant    = null;

        if ($existing) {
            $avant = ['montant' => $existing->montant, 'devise' => $existing->devise];
            $this->repo->updateTarif($existing->id, $montant, $devise);
        } else {
            $this->repo->insertTarif($fraisTypeId, $anneeScolaire, $niveau, $classeId, $montant, $devise);
        }

        $this->repo->logHistorique(
            'tarif', $fraisTypeId, 'upsert',
            $avant,
            compact('anneeScolaire', 'niveau', 'classeId', 'montant', 'devise'),
            $userId
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // CATÉGORIES
    // ═══════════════════════════════════════════════════════════════════════════

    public function creerCategorie(CategorieFraisDTO $dto, int $userId): int
    {
        if ($this->categorieModel->codeExists($dto->code)) {
            throw new \RuntimeException(
                "Une catégorie avec le code « {$dto->code} » existe déjà."
            );
        }

        $data = $dto->toArray();
        $id   = $this->categorieModel->insert($data);

        $this->audit->logCreate($userId, 'finance', 'categorie_frais', $id, $data);
        $this->repo->logHistorique('categorie', $id, 'create', null, $data, $userId);

        return $id;
    }

    public function modifierCategorie(int $id, CategorieFraisDTO $dto, int $userId): void
    {
        $categorie = $this->categorieModel->findById($id);
        if (!$categorie) {
            throw new \RuntimeException("Catégorie introuvable (id=$id).");
        }

        if ($dto->code !== $categorie->code && $this->categorieModel->codeExists($dto->code, $id)) {
            throw new \RuntimeException(
                "Une autre catégorie avec le code « {$dto->code} » existe déjà."
            );
        }

        $avant = ['nom' => $categorie->nom, 'code' => $categorie->code, 'couleur' => $categorie->couleur];
        $data  = $dto->toArray();
        $this->categorieModel->update($id, $data);

        $this->audit->log($userId, 'update', 'finance', 'categorie_frais', $id, $avant, $data);
        $this->repo->logHistorique('categorie', $id, 'update', $avant, $data, $userId);
    }

    public function toggleCategorie(int $id, int $userId): void
    {
        $categorie = $this->categorieModel->findById($id);
        if (!$categorie) {
            throw new \RuntimeException("Catégorie introuvable (id=$id).");
        }

        $nouvelActif = $categorie->actif ? 0 : 1;
        $this->categorieModel->update($id, ['actif' => $nouvelActif]);

        $this->audit->log(
            $userId, $nouvelActif ? 'activer' : 'desactiver',
            'finance', 'categorie_frais', $id,
            ['actif' => $categorie->actif], ['actif' => $nouvelActif]
        );
    }
}
