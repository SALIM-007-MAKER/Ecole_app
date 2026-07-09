<?php

namespace App\Controllers;

use Core\Backup\BackupService;
use Core\Platform\PlatformAuth;
use Core\Platform\PlatformController;
use Core\Platform\PlatformEtablissementService;
use Core\Storage\StorageManager;
use Core\Session;

/**
 * Sauvegardes & reprise après incident — Phase 14.11.
 * Réservé exclusivement au niveau 'super_admin' (pas 'admin', contrairement
 * au reste du portail Platform) — exigence explicite de cette phase :
 * "Ces endpoints doivent être réservés au rôle SaaS Super Admin."
 */
class PlatformBackupController extends PlatformController
{
    private BackupService $backups;
    private PlatformEtablissementService $etabs;

    public function __construct()
    {
        parent::__construct();
        $this->backups = BackupService::make();
        $this->etabs = PlatformEtablissementService::make();
    }

    private function operatorUserId(): int
    {
        return (int)(PlatformAuth::current()['user_id'] ?? 0);
    }

    public function index(): void
    {
        $this->requirePlatformLevel('super_admin');

        $this->render('platform/backups/index', [
            'title'          => 'Sauvegardes & Reprise après incident',
            'stats'          => $this->backups->stats(),
            'history'        => $this->backups->history(null, 30),
            'restoreHistory' => $this->backups->restoreHistory(20),
            'etablissements' => $this->etabs->search([], 200),
        ], 'platform');
    }

    public function api(): void
    {
        $this->requirePlatformLevel('super_admin');

        $this->json([
            'stats'           => $this->backups->stats(),
            'history'         => $this->backups->history(null, 50),
            'restore_history' => $this->backups->restoreHistory(50),
        ]);
    }

    public function createGlobal(): void
    {
        $this->requirePlatformLevel('super_admin');
        $this->verifyCsrf();

        try {
            $result = $this->backups->createGlobal($this->operatorUserId(), 'manual');
            Session::flash('success', "Sauvegarde globale créée (id={$result['id']}, {$result['table_count']} tables).");
        } catch (\Throwable $e) {
            Session::flash('errors', ['Échec de la sauvegarde : ' . $e->getMessage()]);
        }
        $this->redirect(BASE_URL . '/platform/backups');
    }

    public function createTenant(): void
    {
        $this->requirePlatformLevel('super_admin');
        $this->verifyCsrf();

        $etabId = (int)$this->request->post('etablissement_id', 0);
        try {
            $result = $this->backups->createTenant($etabId, $this->operatorUserId());
            Session::flash('success', "Sauvegarde tenant créée (id={$result['id']}).");
        } catch (\Throwable $e) {
            Session::flash('errors', ['Échec de la sauvegarde : ' . $e->getMessage()]);
        }
        $this->redirect(BASE_URL . '/platform/backups');
    }

    public function createDifferential(): void
    {
        $this->requirePlatformLevel('super_admin');
        $this->verifyCsrf();

        $etabId = $this->request->post('etablissement_id') ?: null;
        $sinceDays = max(1, (int)$this->request->post('since_days', 1));

        try {
            $result = $this->backups->createDifferential(
                $etabId !== null ? (int)$etabId : null,
                new \DateTimeImmutable("-{$sinceDays} days"),
                $this->operatorUserId()
            );
            Session::flash('success', "Sauvegarde différentielle créée (id={$result['id']}).");
        } catch (\Throwable $e) {
            Session::flash('errors', ['Échec de la sauvegarde : ' . $e->getMessage()]);
        }
        $this->redirect(BASE_URL . '/platform/backups');
    }

    public function verifyIntegrity(int $id): void
    {
        $this->requirePlatformLevel('super_admin');
        $this->verifyCsrf();

        $ok = $this->backups->verifyIntegrity($id);
        Session::flash($ok ? 'success' : 'errors', $ok ? 'Intégrité vérifiée : le checksum correspond au contenu stocké.' : ['Échec de la vérification d\'intégrité — checksum invalide ou fichier introuvable.']);
        $this->redirect(BASE_URL . '/platform/backups');
    }

    public function restoreVerify(int $id): void
    {
        $this->requirePlatformLevel('super_admin');
        $this->verifyCsrf();

        try {
            $result = $this->backups->restoreGlobalVerify($id, $this->operatorUserId());
            Session::flash('success', "Vérification réussie : {$result['tables_created']} tables, {$result['rows_restored']} lignes restaurées dans une base temporaire (supprimée immédiatement après vérification).");
        } catch (\Throwable $e) {
            Session::flash('errors', ['Échec de la vérification : ' . $e->getMessage()]);
        }
        $this->redirect(BASE_URL . '/platform/backups');
    }

    public function restoreTenant(int $id): void
    {
        $this->requirePlatformLevel('super_admin');
        $this->verifyCsrf();

        $etabId = (int)$this->request->post('etablissement_id', 0);
        $confirmation = trim($this->request->post('confirmation', ''));

        try {
            $result = $this->backups->restoreTenantLive($id, $etabId, $this->operatorUserId(), $confirmation);
            Session::flash('success', "Restauration réussie : {$result['rows_restored']} lignes fusionnées (upsert) pour cet établissement.");
        } catch (\Throwable $e) {
            Session::flash('errors', ['Échec de la restauration : ' . $e->getMessage()]);
        }
        $this->redirect(BASE_URL . '/platform/backups');
    }

    public function download(int $id): void
    {
        $this->requirePlatformLevel('super_admin');

        $row = $this->findBackupRow($id);
        if ($row === null || $row['statut'] !== 'success') {
            http_response_code(404);
            exit('Sauvegarde introuvable.');
        }

        $content = StorageManager::driver()->get($row['storage_path']);

        \Core\Logger::security('PLATFORM_BACKUP_DOWNLOADED', "backup_id={$id} par operator_user_id=" . $this->operatorUserId());

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($row['storage_path']) . '"');
        header('Content-Length: ' . strlen($content));
        header('X-Content-Type-Options: nosniff');
        echo $content;
        exit;
    }

    private function findBackupRow(int $id): ?array
    {
        $pdo = \Core\Database::getInstance()->getConnection();
        $stmt = $pdo->prepare("SELECT * FROM platform_backups WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
