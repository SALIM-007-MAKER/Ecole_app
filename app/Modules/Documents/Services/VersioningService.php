<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\StorageResult;
use App\Modules\Documents\Repositories\DocumentRepository;
use App\Modules\Documents\Repositories\VersionRepository;

class VersioningService
{
    private VersionRepository  $versions;
    private DocumentRepository $docs;
    private StorageService     $storage;

    public function __construct()
    {
        $this->versions = new VersionRepository();
        $this->docs     = new DocumentRepository();
        $this->storage  = new StorageService();
    }

    public function creerVersion(int $documentId, StorageResult $storage, string $notes, int $userId): int
    {
        $numero = $this->versions->maxNumero($documentId) + 1;

        $versionId = $this->versions->insert([
            'document_id'     => $documentId,
            'numero'          => $numero,
            'chemin_stockage' => $storage->chemin,
            'taille_octets'   => $storage->tailleOctets,
            'checksum_sha256' => $storage->checksumSha256,
            'mime_type'       => $storage->mimeType,
            'notes'           => $notes ?: "Version $numero",
            'created_by'      => $userId,
        ]);

        $this->docs->update($documentId, [
            'version_courante' => $numero,
            'chemin_stockage'  => $storage->chemin,
            'mime_type'        => $storage->mimeType,
            'extension'        => $storage->extension,
            'taille_octets'    => $storage->tailleOctets,
            'checksum_sha256'  => $storage->checksumSha256,
            'updated_by'       => $userId,
        ]);

        return $versionId;
    }

    public function cheminVersion(int $documentId, int $numeroVersion): string
    {
        $v = $this->versions->findVersion($documentId, $numeroVersion);
        if (!$v) throw new \RuntimeException("Version $numeroVersion introuvable.");
        return $v['chemin_stockage'];
    }

    public function restaurerVersion(int $documentId, int $numeroVersion, int $userId): int
    {
        $v = $this->versions->findVersion($documentId, $numeroVersion);
        if (!$v) throw new \RuntimeException("Version $numeroVersion introuvable.");

        $result = new StorageResult(
            chemin:         $v['chemin_stockage'],
            mimeType:       $v['mime_type'],
            extension:      pathinfo($v['chemin_stockage'], PATHINFO_EXTENSION),
            tailleOctets:   (int)$v['taille_octets'],
            checksumSha256: $v['checksum_sha256'] ?? '',
        );

        return $this->creerVersion($documentId, $result, "Restauration depuis version $numeroVersion", $userId);
    }

    public function listerVersions(int $documentId): array
    {
        return $this->versions->findByDocument($documentId);
    }

    public function purgerVersionsAnciennes(int $documentId, int $garder = 5): int
    {
        return $this->versions->deleteOldVersions($documentId, $garder);
    }
}
