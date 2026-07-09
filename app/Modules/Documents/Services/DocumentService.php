<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\DTO\DocumentDTO;
use App\Modules\Documents\DTO\DocumentFiltersDTO;
use App\Modules\Documents\Models\DocumentModel;
use App\Modules\Documents\Repositories\DocumentRepository;
use App\Modules\Documents\Repositories\HistoriqueRepository;
use App\Modules\Documents\Repositories\TrashRepository;
use App\Modules\Documents\Events\DocumentUploaded;
use App\Modules\Documents\Events\DocumentVersioned;
use App\Modules\Documents\Events\DocumentArchived;
use App\Modules\Documents\Events\DocumentRestored;
use App\Modules\Documents\Events\DocumentTrashed;
use App\Modules\Documents\Events\DocumentPurged;
use App\Modules\Documents\Events\DocumentMoved;
use App\Modules\Documents\Events\DocumentExpired;
use Core\EventDispatcher;

class DocumentService
{
    private DocumentRepository   $repo;
    private HistoriqueRepository $historiqueRepo;
    private TrashRepository      $trash;
    private StorageService       $storage;
    private VersioningService    $versioning;
    private QuotaService         $quota;

    public function __construct()
    {
        $this->repo           = new DocumentRepository();
        $this->historiqueRepo = new HistoriqueRepository();
        $this->trash          = new TrashRepository();
        $this->storage        = new StorageService();
        $this->versioning     = new VersioningService();
        $this->quota          = new QuotaService();
    }

    // ── Upload ────────────────────────────────────────────────────────────────

    public function uploader(DocumentDTO $dto, array $file, int $userId): int
    {
        $errors = $dto->validate();
        if ($errors) throw new \InvalidArgumentException(implode(' ', array_values($errors)));

        $etablissementId = 1;
        $this->quota->verifier($dto->moduleSource, (int)($file['size'] ?? 0), $etablissementId);

        $stored = $this->storage->stocker($file, $dto->moduleSource, substr($dto->moduleSource, 0, 3));

        $data = array_merge($dto->toArray(), [
            'statut'           => 'actif',
            'version_courante' => 1,
            'chemin_stockage'  => $stored->chemin,
            'mime_type'        => $stored->mimeType,
            'extension'        => $stored->extension,
            'taille_octets'    => $stored->tailleOctets,
            'checksum_sha256'  => $stored->checksumSha256,
            'etablissement_id' => $etablissementId,
            'created_by'       => $userId,
        ]);

        $id = $this->repo->insert($data);

        $this->versioning->creerVersion($id, $stored, 'Création du document', $userId);

        $this->historiqueRepo->insert([
            'document_id' => $id,
            'action'      => 'upload',
            'details'     => ['titre' => $dto->titre, 'version' => 1, 'taille' => $stored->tailleOctets],
            'created_by'  => $userId,
        ]);

        EventDispatcher::dispatch(new DocumentUploaded(
            $id, $dto->moduleSource, $dto->entiteType, $dto->entiteId,
            $stored->mimeType, $stored->tailleOctets, $userId
        ));

        return $id;
    }

    // ── Nouvelle version ──────────────────────────────────────────────────────

    public function nouvelleVersion(int $documentId, array $file, ?string $notes, int $userId): int
    {
        $doc = $this->requireDocument($documentId);
        if (in_array($doc['statut'], ['archive', 'corbeille'], true)) {
            throw new \RuntimeException('Ce document ne peut pas recevoir de nouvelle version.');
        }

        $this->quota->verifier($doc['module_source'], (int)($file['size'] ?? 0));
        $stored = $this->storage->stocker($file, $doc['module_source'], substr($doc['module_source'], 0, 3));

        $oldVersion = (int)$doc['version_courante'];
        $versionId  = $this->versioning->creerVersion($documentId, $stored, $notes ?? '', $userId);
        $newVersion = $oldVersion + 1;

        $this->historiqueRepo->insert([
            'document_id' => $documentId,
            'action'      => 'nouvelle_version',
            'details'     => ['old_version' => $oldVersion, 'new_version' => $newVersion, 'notes' => $notes],
            'created_by'  => $userId,
        ]);

        EventDispatcher::dispatch(new DocumentVersioned(
            $documentId, $oldVersion, $newVersion, $stored->tailleOctets, $userId
        ));

        return $versionId;
    }

    // ── Mise à jour métadonnées ───────────────────────────────────────────────

    public function modifier(int $documentId, DocumentDTO $dto, int $userId): void
    {
        $this->requireDocument($documentId);
        $errors = $dto->validate();
        if ($errors) throw new \InvalidArgumentException(implode(' ', array_values($errors)));

        $this->repo->update($documentId, array_merge($dto->toArray(), ['updated_by' => $userId]));

        $this->historiqueRepo->insert([
            'document_id' => $documentId,
            'action'      => 'metadonnees',
            'details'     => ['titre' => $dto->titre],
            'created_by'  => $userId,
        ]);
    }

    // ── Archivage ─────────────────────────────────────────────────────────────

    public function archiver(int $documentId, int $userId, ?string $raison = null): void
    {
        $doc = $this->requireDocument($documentId);
        if ($doc['statut'] === 'archive') throw new \RuntimeException('Ce document est déjà archivé.');
        if ($doc['statut'] === 'corbeille') throw new \RuntimeException('Restaurez le document avant de l\'archiver.');

        $ancienStatut = $doc['statut'];
        $this->repo->updateStatut($documentId, 'archive', [
            'archived_by' => $userId,
            'archived_at' => date('Y-m-d H:i:s'),
        ]);

        $this->historiqueRepo->insert([
            'document_id' => $documentId,
            'action'      => 'archive',
            'details'     => ['ancien_statut' => $ancienStatut, 'raison' => $raison],
            'created_by'  => $userId,
        ]);

        EventDispatcher::dispatch(new DocumentArchived(
            $documentId, $doc['module_source'], $ancienStatut, $userId
        ));
    }

    // ── Corbeille ─────────────────────────────────────────────────────────────

    public function mettreEnCorbeille(int $documentId, int $userId, ?string $raison = null): void
    {
        $doc = $this->requireDocument($documentId);
        if ($doc['statut'] === 'corbeille') throw new \RuntimeException('Ce document est déjà dans la corbeille.');

        $this->repo->updateStatut($documentId, 'corbeille');
        $this->trash->insert([
            'document_id' => $documentId,
            'raison'      => $raison,
            'created_by'  => $userId,
        ]);

        $this->historiqueRepo->insert([
            'document_id' => $documentId,
            'action'      => 'corbeille',
            'details'     => ['raison' => $raison],
            'created_by'  => $userId,
        ]);

        EventDispatcher::dispatch(new DocumentTrashed($documentId, $doc['module_source'], $userId));
    }

    // ── Restauration ──────────────────────────────────────────────────────────

    public function restaurer(int $documentId, int $userId): void
    {
        $doc = $this->requireDocument($documentId);
        if (!in_array($doc['statut'], ['archive', 'corbeille'], true)) {
            throw new \RuntimeException('Ce document n\'est pas archivé ni dans la corbeille.');
        }

        $ancienStatut  = $doc['statut'];
        $nouveauStatut = DocumentModel::isExpired($doc['date_expiration'] ?? null) ? 'expire' : 'actif';

        $this->repo->updateStatut($documentId, $nouveauStatut, [
            'archived_by' => null,
            'archived_at' => null,
        ]);

        if ($ancienStatut === 'corbeille') {
            $this->trash->remove($documentId);
        }

        $this->historiqueRepo->insert([
            'document_id' => $documentId,
            'action'      => 'restaurer',
            'details'     => ['ancien_statut' => $ancienStatut, 'nouveau_statut' => $nouveauStatut],
            'created_by'  => $userId,
        ]);

        EventDispatcher::dispatch(new DocumentRestored(
            $documentId, $doc['module_source'], $ancienStatut, $nouveauStatut, $userId
        ));
    }

    // ── Purge définitive ──────────────────────────────────────────────────────

    public function purger(int $documentId, int $userId): void
    {
        $doc = $this->requireDocument($documentId);

        $cheminSupprime = $doc['chemin_stockage'];
        $this->storage->supprimer($cheminSupprime);
        $this->repo->softDelete($documentId);
        $this->trash->remove($documentId);

        EventDispatcher::dispatch(new DocumentPurged(
            $documentId, $doc['module_source'], $cheminSupprime, $userId
        ));
    }

    // ── Vider la corbeille ────────────────────────────────────────────────────

    public function viderCorbeille(int $userId): int
    {
        $filters = new DocumentFiltersDTO(statut: 'corbeille', page: 1, perPage: 500);
        $docs    = $this->repo->findAll($filters);
        $count   = 0;

        foreach ($docs as $doc) {
            $this->purger((int)$doc['id'], $userId);
            $count++;
        }

        return $count;
    }

    // ── Déplacement ───────────────────────────────────────────────────────────

    public function deplacer(int $documentId, ?int $newFolderId, int $userId): void
    {
        $doc         = $this->requireDocument($documentId);
        $oldFolderId = isset($doc['folder_id']) ? (int)$doc['folder_id'] : null;

        $this->repo->update($documentId, ['folder_id' => $newFolderId, 'updated_by' => $userId]);

        $this->historiqueRepo->insert([
            'document_id' => $documentId,
            'action'      => 'deplacer',
            'details'     => ['old_folder' => $oldFolderId, 'new_folder' => $newFolderId],
            'created_by'  => $userId,
        ]);

        EventDispatcher::dispatch(new DocumentMoved($documentId, $oldFolderId, $newFolderId, $userId));
    }

    // ── Téléchargement ────────────────────────────────────────────────────────

    public function telecharger(array $doc): never
    {
        $chemin = $doc['chemin_stockage'];
        $abs    = ROOT_PATH . '/' . $chemin;

        if (!file_exists($abs)) {
            http_response_code(404);
            exit('Fichier introuvable.');
        }

        $mime = $doc['mime_type'] ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($abs));
        header('Content-Disposition: attachment; filename="' . rawurlencode($doc['titre']) . '.' . $doc['extension'] . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($abs);
        exit;
    }

    // ── Expirations (batch) ───────────────────────────────────────────────────

    public function verifierExpirations(): int
    {
        $docs  = $this->repo->findExpired();
        $count = 0;

        foreach ($docs as $doc) {
            $this->repo->updateStatut((int)$doc['id'], 'expire');
            $this->historiqueRepo->insert([
                'document_id' => (int)$doc['id'],
                'action'      => 'expiration_auto',
                'details'     => ['date_expiration' => $doc['date_expiration']],
                'created_by'  => 0,
            ]);
            EventDispatcher::dispatch(new DocumentExpired(
                (int)$doc['id'], $doc['module_source'],
                $doc['entite_type'] ?? null,
                isset($doc['entite_id']) ? (int)$doc['entite_id'] : null,
                $doc['date_expiration']
            ));
            $count++;
        }

        return $count;
    }

    public function expirationProchaine(int $jours): array
    {
        return $this->repo->findExpiring($jours);
    }

    public function documentsExpires(): array
    {
        return $this->repo->findExpired();
    }

    // ── Lectures ──────────────────────────────────────────────────────────────

    public function trouver(int $documentId): ?array
    {
        return $this->repo->findById($documentId) ?: null;
    }

    public function paginer(DocumentFiltersDTO $filters): array
    {
        $data  = $this->repo->findAll($filters);
        $total = $this->repo->count($filters);

        return [
            'data'         => $data,
            'total'        => $total,
            'page'         => $filters->page,
            'per_page'     => $filters->perPage,
            'total_pages'  => $filters->perPage > 0 ? (int)ceil($total / $filters->perPage) : 1,
        ];
    }

    public function historique(int $documentId): array
    {
        return $this->historiqueRepo->findByDocument($documentId);
    }

    public function listerVersions(int $documentId): array
    {
        return $this->versioning->listerVersions($documentId);
    }

    public function corbeille(): array
    {
        $filters = new DocumentFiltersDTO(statut: 'corbeille', page: 1, perPage: 100);
        return $this->repo->findInTrash($filters);
    }

    public function statistiques(string $moduleSource = ''): array
    {
        return $this->repo->statistiques($moduleSource);
    }

    public function categories(): array
    {
        $pdo = \Core\Database::getInstance()->getConnection();
        return $pdo->query("SELECT * FROM doc_categories WHERE actif=1 ORDER BY ordre,libelle")
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Privé ─────────────────────────────────────────────────────────────────

    private function requireDocument(int $id): array
    {
        $doc = $this->repo->findById($id);
        if (!$doc) throw new \RuntimeException('Document introuvable.');
        return $doc;
    }
}
