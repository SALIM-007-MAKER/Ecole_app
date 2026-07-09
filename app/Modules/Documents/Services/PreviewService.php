<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

class PreviewService
{
    private string $previewDir;

    const PREVIEWABLES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    const THUMB_W = 200;
    const THUMB_H = 280;

    public function __construct()
    {
        $this->previewDir = ROOT_PATH . '/storage/documents/previews/';
    }

    public function generer(array $doc): mixed
    {
        return $this->genererParChamps((int)$doc['id'], $doc['chemin_stockage'], $doc['mime_type'] ?? '');
    }

    public function cheminSurveilleAcces(string $filename, array $user): ?string
    {
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) return null;
        $base = ROOT_PATH . '/storage/documents/';
        $chemin = realpath($base . $filename) ?: '';

        if (strncmp($chemin, realpath($base), strlen(realpath($base))) !== 0) return null;
        if (!is_file($chemin)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base)) as $f) {
                if ($f->getFilename() === $filename) { $chemin = $f->getRealPath(); break; }
            }
        }
        return is_file($chemin) ? $chemin : null;
    }

    private function genererParChamps(int $documentId, string $cheminRelatif, string $mimeType): ?string
    {
        if (!in_array($mimeType, self::PREVIEWABLES, true)) return null;
        if (!extension_loaded('gd')) return null;

        $src = ROOT_PATH . '/' . $cheminRelatif;
        if (!is_file($src)) return null;

        if (!is_dir($this->previewDir)) mkdir($this->previewDir, 0755, true);

        $destName = 'thumb_' . $documentId . '.png';
        $dest     = $this->previewDir . $destName;

        $image = match($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($src),
            'image/png'  => imagecreatefrompng($src),
            'image/webp' => imagecreatefromwebp($src),
            'image/gif'  => imagecreatefromgif($src),
            default      => false,
        };

        if ($image === false) return null;

        $origW = imagesx($image);
        $origH = imagesy($image);
        $ratio = min(self::THUMB_W / $origW, self::THUMB_H / $origH);
        $newW  = (int)round($origW * $ratio);
        $newH  = (int)round($origH * $ratio);

        $thumb = imagecreatetruecolor($newW, $newH);
        $white = imagecolorallocate($thumb, 255, 255, 255);
        imagefill($thumb, 0, 0, $white);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        imagepng($thumb, $dest, 7);
        imagedestroy($image);
        imagedestroy($thumb);

        return 'storage/documents/previews/' . $destName;
    }

    public function url(int $documentId): string
    {
        $path = $this->previewDir . 'thumb_' . $documentId . '.png';
        if (is_file($path)) {
            return BASE_URL . '/v2/documents/' . $documentId . '/preview';
        }
        return BASE_URL . '/assets/img/document-placeholder.png';
    }

    public function supprimer(int $documentId): void
    {
        $path = $this->previewDir . 'thumb_' . $documentId . '.png';
        if (is_file($path)) unlink($path);
    }
}
