<?php

namespace App\Controllers;

use Core\Controller;
use Core\Session;
use App\Services\UploadService;

class UploadController extends Controller
{
    private UploadService $uploadService;

    public function __construct()
    {
        parent::__construct();
        $this->uploadService = new UploadService();
    }

    /**
     * Sert un fichier uploadé de façon sécurisée.
     *
     * Route : GET /uploads/serve/{type}/{filename}
     *
     * - Vérifie l'authentification.
     * - Valide que le type est autorisé.
     * - Valide que le fichier est dans le bon répertoire (anti path-traversal).
     * - Envoie le fichier avec le bon Content-Type.
     */
    public function serve(string $type, string $filename): void
    {
        // ── Authentification obligatoire ─────────────────────────────────────
        $this->requireAuth();

        // ── Valider le type ───────────────────────────────────────────────────
        $types    = UploadService::TYPES;
        $typeDir  = $this->resolveTypeDir($type, $types);

        if ($typeDir === null) {
            http_response_code(404);
            exit('Ressource non trouvée.');
        }

        // ── Valider le nom de fichier (anti path-traversal) ───────────────────
        $filename = basename($filename); // Supprime tout chemin relatif
        if (!preg_match('/^[\w\-\.]+$/', $filename)) {
            http_response_code(400);
            exit('Nom de fichier invalide.');
        }

        // ── Construire le chemin absolu ───────────────────────────────────────
        $absPath = ROOT_PATH . '/' . $typeDir . $filename;

        if (!is_file($absPath)) {
            // Essai legacy : public/uploads/{type}/{filename}
            $legacyPath = ROOT_PATH . '/public/uploads/' . $type . '/' . $filename;
            if (is_file($legacyPath)) {
                $absPath = $legacyPath;
            } else {
                http_response_code(404);
                exit('Fichier non trouvé.');
            }
        }

        // ── Vérification permissions spécifiques ─────────────────────────────
        // Les justifications requièrent une permission supplémentaire
        if ($type === 'justifications' && !$this->can('absences.view') && !$this->can('absences.view.own')) {
            http_response_code(403);
            exit('Accès non autorisé.');
        }
        if ($type === 'retards' && !$this->can('late.validate') && !$this->can('late.justify')) {
            http_response_code(403);
            exit('Accès non autorisé.');
        }
        if ($type === 'discipline' && !$this->can('discipline.view')) {
            http_response_code(403);
            exit('Accès non autorisé.');
        }

        // ── Déterminer le MIME réel ───────────────────────────────────────────
        $mime = mime_content_type($absPath) ?: 'application/octet-stream';

        // ── Envoyer le fichier ────────────────────────────────────────────────
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($absPath));
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');

        readfile($absPath);
        exit;
    }

    // ─── Privé ───────────────────────────────────────────────────────────────

    private function resolveTypeDir(string $type, array $types): ?string
    {
        // Mapper le segment d'URL vers le nom de type UploadService
        $urlToType = [
            'avatars'       => 'avatar',
            'eleves'        => 'photo_eleve',
            'professeurs'   => 'photo_professeur',
            'etablissement' => 'logo_etablissement',
            'justifications'=> 'justification',
            'retards'       => 'justification_retard',
            'discipline'    => 'piece_jointe_discipline',
            'imports'       => 'import_csv',
        ];

        $resolvedType = $urlToType[$type] ?? $type;

        if (!isset($types[$resolvedType])) {
            return null;
        }

        return $types[$resolvedType]['dir'];
    }
}
