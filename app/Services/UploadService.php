<?php

namespace App\Services;

class UploadService
{
    public const TYPES = [
        'avatar' => [
            'dir'      => 'storage/uploads/avatars/',
            'mimes'    => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'max_size' => 2097152,
            'resize'   => [200, 200],
        ],
        'photo_eleve' => [
            'dir'      => 'storage/uploads/eleves/',
            'mimes'    => ['image/jpeg', 'image/png', 'image/webp'],
            'max_size' => 2097152,
            'resize'   => [300, 400],
        ],
        'photo_professeur' => [
            'dir'      => 'storage/uploads/professeurs/',
            'mimes'    => ['image/jpeg', 'image/png', 'image/webp'],
            'max_size' => 2097152,
            'resize'   => [300, 400],
        ],
        'logo_etablissement' => [
            'dir'      => 'storage/uploads/etablissement/',
            'mimes'    => ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'],
            'max_size' => 5242880,
            'resize'   => [800, 800],
        ],
        'justification' => [
            'dir'      => 'storage/uploads/justifications/',
            'mimes'    => ['image/jpeg', 'image/png', 'application/pdf'],
            'max_size' => 5242880,
            'resize'   => null,
        ],
        'import_csv' => [
            'dir'      => 'storage/uploads/imports/',
            'mimes'    => ['text/csv', 'text/plain', 'application/vnd.ms-excel'],
            'max_size' => 10485760,
            'resize'   => null,
        ],
    ];

    private static array $extensionMap = [
        'image/jpeg'       => 'jpg',
        'image/png'        => 'png',
        'image/gif'        => 'gif',
        'image/webp'       => 'webp',
        'image/svg+xml'    => 'svg',
        'application/pdf'  => 'pdf',
        'text/csv'         => 'csv',
        'text/plain'       => 'txt',
        'application/vnd.ms-excel' => 'csv',
    ];

    /**
     * Valide et stocke un fichier uploadé.
     *
     * @param  array  $file    $_FILES['champ']
     * @param  string $type    Clé dans self::TYPES
     * @param  string $prefix  Préfixe du nom de fichier final (ex: 'eleve_42_')
     * @return string          Chemin relatif depuis ROOT_PATH
     * @throws \RuntimeException
     */
    public function upload(array $file, string $type, string $prefix = ''): string
    {
        $validation = $this->validate($file, $type);
        if (!$validation['ok']) {
            throw new \RuntimeException(implode(' ', $validation['errors']));
        }

        $conf    = self::TYPES[$type];
        $absDir  = ROOT_PATH . '/' . $conf['dir'];

        if (!is_dir($absDir)) {
            mkdir($absDir, 0755, true);
        }

        $mime   = mime_content_type($file['tmp_name']);
        $ext    = self::$extensionMap[$mime] ?? 'bin';
        $name   = $prefix . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest   = $absDir . $name;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('Impossible de déplacer le fichier uploadé.');
        }

        if ($conf['resize'] !== null && $this->isResizableImage($mime)) {
            $this->resizeImage($dest, $conf['resize'][0], $conf['resize'][1], $mime);
        }

        return $conf['dir'] . $name;
    }

    public function delete(string $relativePath): bool
    {
        // Protection path traversal : le chemin doit commencer par storage/
        if (!str_starts_with($relativePath, 'storage/') && !str_starts_with($relativePath, 'public/uploads/')) {
            return false;
        }

        $abs = ROOT_PATH . '/' . $relativePath;
        if (is_file($abs)) {
            return unlink($abs);
        }
        return false;
    }

    /**
     * Retourne l'URL publique via la route sécurisée /uploads/serve/{type}/{filename}.
     */
    public function url(string $relativePath): string
    {
        $parts    = explode('/', $relativePath);
        $filename = array_pop($parts);
        $typeDir  = array_pop($parts);
        return BASE_URL . '/uploads/serve/' . $typeDir . '/' . $filename;
    }

    /**
     * Valide uniquement (sans stocker).
     * @return array ['ok' => bool, 'errors' => string[]]
     */
    public function validate(array $file, string $type): array
    {
        $errors = [];

        if (!isset(self::TYPES[$type])) {
            return ['ok' => false, 'errors' => ["Type d'upload inconnu : {$type}"]];
        }

        $conf = self::TYPES[$type];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'Erreur lors de l\'upload (code ' . ($file['error'] ?? 99) . ').';
            return ['ok' => false, 'errors' => $errors];
        }

        if (($file['size'] ?? 0) > $conf['max_size']) {
            $mb       = round($conf['max_size'] / 1048576, 1);
            $errors[] = "Le fichier dépasse la taille maximale ({$mb} Mo).";
        }

        if (isset($file['tmp_name']) && is_file($file['tmp_name'])) {
            $realMime = mime_content_type($file['tmp_name']);
            if (!in_array($realMime, $conf['mimes'], true)) {
                $errors[] = "Type de fichier non autorisé ({$realMime}).";
            }
        } else {
            $errors[] = 'Fichier temporaire inaccessible.';
        }

        return ['ok' => empty($errors), 'errors' => $errors];
    }

    // ── Privé ─────────────────────────────────────────────────────────────────

    private function isResizableImage(string $mime): bool
    {
        return in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
    }

    private function resizeImage(string $path, int $maxW, int $maxH, string $mime): void
    {
        if (!extension_loaded('gd')) {
            return;
        }

        $src = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png'  => imagecreatefrompng($path),
            'image/gif'  => imagecreatefromgif($path),
            'image/webp' => imagecreatefromwebp($path),
            default      => null,
        };

        if ($src === null || $src === false) {
            return;
        }

        $origW = imagesx($src);
        $origH = imagesy($src);

        if ($origW <= $maxW && $origH <= $maxH) {
            imagedestroy($src);
            return;
        }

        $ratio   = min($maxW / $origW, $maxH / $origH);
        $newW    = (int)round($origW * $ratio);
        $newH    = (int)round($origH * $ratio);
        $dst     = imagecreatetruecolor($newW, $newH);

        if ($mime === 'image/png') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        } else {
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefill($dst, 0, 0, $white);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        match ($mime) {
            'image/jpeg' => imagejpeg($dst, $path, 85),
            'image/png'  => imagepng($dst, $path, 7),
            'image/gif'  => imagegif($dst, $path),
            'image/webp' => imagewebp($dst, $path, 85),
            default      => null,
        };

        imagedestroy($src);
        imagedestroy($dst);
    }
}
