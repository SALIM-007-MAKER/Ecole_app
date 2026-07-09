<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1;

use App\Modules\Api\Controllers\ApiBaseController;
use App\Modules\Api\Exceptions\ValidationException;
use Core\Database;

class UploadController extends ApiBaseController
{
    private const ALLOWED_MIME = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/csv',
    ];
    private const MAX_SIZE_MB = 10;

    /** POST /api/v1/upload */
    public function store(): void
    {
        $ctx = $this->requireApiAuth();
        $this->requireApiPermission('documents.upload');
        $this->checkRateLimit('upload');

        if (empty($_FILES['file'])) {
            throw new ValidationException(['file' => 'Fichier requis.']);
        }

        $file   = $_FILES['file'];
        $maxB   = self::MAX_SIZE_MB * 1024 * 1024;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new ValidationException(['file' => 'Erreur upload: ' . $file['error']]);
        }
        if ($file['size'] > $maxB) {
            throw new ValidationException(['file' => 'Taille max: ' . self::MAX_SIZE_MB . ' Mo.']);
        }

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, self::ALLOWED_MIME, true)) {
            throw new ValidationException(['file' => "Type MIME non autorisé: $mimeType"]);
        }

        $uploadDir = ROOT_PATH . '/storage/uploads/' . date('Y/m/');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $slug     = bin2hex(random_bytes(12));
        $filename = $slug . '.' . strtolower($ext);
        $path     = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $path)) {
            throw new \RuntimeException('Impossible de déplacer le fichier uploadé.');
        }

        $pdo = Database::getInstance()->getConnection();
        $pdo->prepare(
            'INSERT INTO api_uploads (slug, original_name, stored_path, mime_type, size, uploaded_by, etablissement_id, created_at)
             VALUES (:slug, :orig, :path, :mime, :size, :by, :etab, NOW())'
        )->execute([
            ':slug' => $slug,
            ':orig' => $file['name'],
            ':path' => $path,
            ':mime' => $mimeType,
            ':size' => $file['size'],
            ':by'   => $ctx->userId,
            ':etab' => $ctx->etablissementId,
        ]);

        $this->apiCreated([
            'slug'          => $slug,
            'original_name' => $file['name'],
            'mime_type'     => $mimeType,
            'size'          => $file['size'],
            'url'           => '/api/v1/files/' . $slug,
        ], 'Fichier uploadé avec succès.');
    }

    /** GET /api/v1/files/{slug} — télécharge le fichier */
    public function download(string $slug): void
    {
        $this->requireApiAuth();

        if (!preg_match('/^[0-9a-f]{24}$/', $slug)) {
            http_response_code(400);
            echo json_encode(['error' => 'Slug invalide.']);
            exit;
        }

        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM api_uploads WHERE slug=:slug AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row || !file_exists($row['stored_path'])) {
            http_response_code(404);
            echo json_encode(['error' => 'Fichier introuvable.']);
            exit;
        }

        header('Content-Type: ' . $row['mime_type']);
        header('Content-Disposition: attachment; filename="' . rawurlencode($row['original_name']) . '"');
        header('Content-Length: ' . filesize($row['stored_path']));
        readfile($row['stored_path']);
        exit;
    }
}
