<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\DocumentModel;
use App\Modules\Documents\Models\StorageResult;

class StorageService
{
    private string $baseDir;

    public function __construct()
    {
        $this->baseDir = ROOT_PATH . '/storage/documents/';
    }

    public function stocker(array $file, string $module, string $prefix = ''): StorageResult
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Erreur lors de l\'upload (code ' . ($file['error'] ?? 99) . ').');
        }

        $tmpPath = $file['tmp_name'] ?? '';
        if (!is_file($tmpPath)) {
            throw new \RuntimeException('Fichier temporaire inaccessible.');
        }

        $realMime = mime_content_type($tmpPath);
        if (!DocumentModel::isMimeAllowed($realMime, $module)) {
            throw new \RuntimeException("Type de fichier non autorisé : {$realMime}.");
        }

        $taille = (int)($file['size'] ?? filesize($tmpPath));
        if ($taille > DocumentModel::TAILLE_MAX_OCTETS) {
            $mb = round(DocumentModel::TAILLE_MAX_OCTETS / 1048576, 0);
            throw new \RuntimeException("Le fichier dépasse la taille maximale ({$mb} Mo).");
        }

        $ext     = $this->mimeToExt($realMime);
        $annee   = date('Y');
        $mois    = date('m');
        $dir     = $this->baseDir . $module . '/' . $annee . '/' . $mois . '/';

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \RuntimeException("Impossible de créer le répertoire de stockage.");
        }

        $filename = ($prefix !== '' ? $prefix . '_' : '') . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest     = $dir . $filename;

        if (!move_uploaded_file($tmpPath, $dest)) {
            throw new \RuntimeException('Impossible de déplacer le fichier uploadé.');
        }

        $checksum = hash_file('sha256', $dest);
        $chemin   = 'storage/documents/' . $module . '/' . $annee . '/' . $mois . '/' . $filename;

        return new StorageResult(
            chemin:         $chemin,
            mimeType:       $realMime,
            extension:      $ext,
            tailleOctets:   $taille,
            checksumSha256: $checksum,
        );
    }

    public function supprimer(string $cheminRelatif): bool
    {
        if (!str_starts_with($cheminRelatif, 'storage/documents/')) {
            return false;
        }
        $abs = ROOT_PATH . '/' . $cheminRelatif;
        return is_file($abs) && unlink($abs);
    }

    public function url(string $cheminRelatif): string
    {
        $parts    = explode('/', $cheminRelatif);
        $filename = array_pop($parts);
        return BASE_URL . '/v2/documents/file/' . urlencode($filename);
    }

    public function existe(string $cheminRelatif): bool
    {
        return is_file(ROOT_PATH . '/' . $cheminRelatif);
    }

    public function taille(string $cheminRelatif): int
    {
        $abs = ROOT_PATH . '/' . $cheminRelatif;
        return is_file($abs) ? (int)filesize($abs) : 0;
    }

    public function verifierIntegrite(string $cheminRelatif, string $checksumAttendu): bool
    {
        $abs = ROOT_PATH . '/' . $cheminRelatif;
        if (!is_file($abs)) return false;
        return hash_file('sha256', $abs) === $checksumAttendu;
    }

    private function mimeToExt(string $mime): string
    {
        $map = [
            'application/pdf'   => 'pdf',
            'image/jpeg'        => 'jpg',
            'image/png'         => 'png',
            'image/webp'        => 'webp',
            'image/gif'         => 'gif',
            'application/msword'=> 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel'                                                => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'       => 'xlsx',
            'text/csv'          => 'csv',
            'text/plain'        => 'txt',
        ];
        return $map[$mime] ?? 'bin';
    }
}
