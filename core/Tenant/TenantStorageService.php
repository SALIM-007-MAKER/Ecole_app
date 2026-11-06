<?php

declare(strict_types=1);

namespace Core\Tenant;

/**
 * Stockage des ressources graphiques par établissement (logo, favicon,
 * image de connexion). Interface conforme à MULTI_TENANT_V2_BLUEPRINT.md
 * §12.1 (StorageInterface), implémentation disque local uniquement pour
 * cette phase — l'interface est conçue pour qu'un futur adaptateur S3
 * (Phase 14.8) puisse remplacer put()/url()/delete() sans toucher aux
 * appelants (BrandingService, SettingsController).
 *
 * Convention de chemin : adaptée de §10.3 du blueprint
 * (storage/{etab}/branding/...) à la convention déjà établie dans cette
 * application pour les fichiers uploadés servis publiquement
 * (public/uploads/{module}/..., voir ProfesseurController::handlePhoto) —
 * nécessaire car les logos doivent être directement accessibles par URL,
 * contrairement à storage/ qui est hors du webroot.
 *
 * Isolation stricte : chaque chemin est préfixé par l'etablissement_id,
 * aucune méthode n'accepte de chemin construit ailleurs que par ce service.
 *
 * Quota (Phase 14.8) : chaque écriture est vérifiée contre
 * TenantQuotaService::checkUploadAllowed() puis journalisée dans le ledger
 * `api_uploads` (recordUpload/recordDeletion) — les logos/favicons/images de
 * connexion comptent désormais dans l'usage de stockage du tenant, au même
 * titre que tout futur module de stockage utilisant Core\Storage.
 */
final class TenantStorageService
{
    private const BASE_DIR = 'public/uploads/branding';
    /** Types acceptés et extension de stockage : l'extension est déduite du CONTENU détecté, jamais du nom fourni par le client. */
    private const EXT_PAR_MIME = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
        'image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico', 'image/svg+xml' => 'svg',
    ];
    private const MAX_SIZE = 2 * 1024 * 1024; // 2 Mo, conforme §10.3
    private const MAX_DIMENSION = 4096;        // px, côté le plus long d'une image matricielle

    /**
     * Valide une image envoyée (logo, favicon, image de connexion) et retourne son type MIME réel.
     * Refuse : fichier trop lourd, type non autorisé, image illisible ou démesurée, SVG contenant du code actif ou des références externes.
     *
     * @throws \RuntimeException message affichable à l'utilisateur
     */
    public static function validerImage(string $chemin, int $taille): string
    {
        if ($taille > self::MAX_SIZE) {
            throw new \RuntimeException('Le fichier dépasse la taille maximale de 2 Mo.');
        }
        $mime = (string)@mime_content_type($chemin);
        if (!isset(self::EXT_PAR_MIME[$mime])) {
            throw new \RuntimeException('Type de fichier non autorisé (image PNG, JPEG, WEBP, SVG ou ICO uniquement).');
        }
        if ($mime === 'image/svg+xml') {
            $svg = (string)file_get_contents($chemin);
            if (preg_match('/<\s*(script|foreignObject|iframe|object|embed)\b|\son[a-z]+\s*=|javascript:|<!ENTITY|<!DOCTYPE[^>]*\[|(?:xlink:)?href\s*=\s*["\']\s*(?!#|data:image\/(?:png|jpeg|webp);)/i', $svg)) {
                throw new \RuntimeException('Ce fichier SVG contient du code actif ou des références externes : il est refusé.');
            }
        } elseif (!str_contains($mime, 'icon')) {
            $dim = @getimagesize($chemin);
            if ($dim === false) {
                throw new \RuntimeException('Image illisible ou corrompue.');
            }
            if ($dim[0] > self::MAX_DIMENSION || $dim[1] > self::MAX_DIMENSION) {
                throw new \RuntimeException('Image trop grande (' . self::MAX_DIMENSION . ' px maximum de côté).');
            }
        }
        return $mime;
    }

    private readonly TenantQuotaService $quota;

    public function __construct(?TenantQuotaService $quota = null)
    {
        $this->quota = $quota ?? TenantQuotaService::make();
    }

    /**
     * Stocke un fichier uploadé ($_FILES[...]) pour un établissement et
     * retourne l'URL web relative (à préfixer par BASE_URL par l'appelant).
     *
     * @param array{tmp_name:string,name:string,size:int,error:int} $uploadedFile
     * @throws \RuntimeException si le fichier est invalide
     * @throws StorageQuotaExceededException si le quota de stockage est dépassé
     */
    public function put(int $etablissementId, string $context, array $uploadedFile): string
    {
        if (empty($uploadedFile['tmp_name']) || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Fichier invalide.');
        }
        $mimeType = self::validerImage($uploadedFile['tmp_name'], (int)$uploadedFile['size']);

        $this->quota->checkUploadAllowed($etablissementId, (int)$uploadedFile['size']);

        $dir = ROOT_PATH . '/' . self::BASE_DIR . '/' . $etablissementId;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Impossible de créer le répertoire de stockage.');
        }

        $ext = self::EXT_PAR_MIME[$mimeType];   // jamais l'extension fournie par le client
        $filename = preg_replace('/[^a-z0-9]/', '', $context) . '_' . uniqid() . '.' . $ext;
        $dest = $dir . '/' . $filename;

        if (!move_uploaded_file($uploadedFile['tmp_name'], $dest)) {
            throw new \RuntimeException('Échec de l\'enregistrement du fichier.');
        }

        $relativeUrl = '/uploads/branding/' . $etablissementId . '/' . $filename;
        $this->quota->recordUpload($etablissementId, 'branding', $context, $relativeUrl, (int)$uploadedFile['size'], $mimeType);

        return $relativeUrl;
    }

    /** Supprime un fichier précédemment stocké via put() pour cet établissement. Sans effet si absent ou hors de son propre dossier tenant (isolation). */
    public function delete(int $etablissementId, ?string $relativeUrl): void
    {
        if ($relativeUrl === null || $relativeUrl === '') {
            return;
        }
        $expectedPrefix = '/uploads/branding/' . $etablissementId . '/';
        if (!str_starts_with($relativeUrl, $expectedPrefix)) {
            return; // ne jamais supprimer un fichier hors du dossier de CE tenant
        }
        $path = ROOT_PATH . '/public' . $relativeUrl;
        if (is_file($path)) {
            unlink($path);
        }
        $this->quota->recordDeletion($etablissementId, $relativeUrl);
    }
}
