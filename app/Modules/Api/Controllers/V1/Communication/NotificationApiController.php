<?php
declare(strict_types=1);

namespace App\Modules\Api\Controllers\V1\Communication;

use App\Modules\Api\Controllers\ResourceApiController;
use App\Modules\Api\Exceptions\NotFoundException;
use Core\Database;

class NotificationApiController extends ResourceApiController
{
    /** GET /api/v1/notifications — notifications de l'utilisateur courant */
    public function index(): void
    {
        $ctx = $this->requireApiAuth();
        $this->checkRateLimit();

        $paging  = $this->parsePagination();
        $lu      = isset($_GET['lu']) ? (int)$_GET['lu'] : null;

        $pdo        = Database::getInstance()->getConnection();
        $conditions = ['n.destinataire_id = ?', 'n.etablissement_id = ?'];
        $bindings   = [$ctx->userId, $ctx->etablissementId];

        if ($lu !== null) { $conditions[] = 'n.lu = ?'; $bindings[] = $lu; }

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $cnt   = $pdo->prepare("SELECT COUNT(*) FROM comm_notifications n $where");
        $cnt->execute($bindings);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT n.* FROM comm_notifications n
             $where ORDER BY n.created_at DESC LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($bindings);
        $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $items = array_map(fn($r) => [
            'id'         => (int)$r['id'],
            'titre'      => $r['titre'],
            'message'    => $r['message'],
            'type'       => $r['type'],
            'lu'         => (bool)$r['lu'],
            'lu_le'      => $r['lu_le'],
            'created_at' => $r['created_at'],
        ], $rows);

        $this->respondCollection($items, $total, $paging, '/api/v1/notifications');
    }

    /** GET /api/v1/notifications/non-lues — compteur non lues */
    public function nonLues(): void
    {
        $ctx = $this->requireApiAuth();

        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM comm_notifications WHERE destinataire_id=:uid AND etablissement_id=:etab AND lu=0');
        $stmt->execute([':uid' => $ctx->userId, ':etab' => $ctx->etablissementId]);
        $this->apiSuccess(['non_lues' => (int)$stmt->fetchColumn()]);
    }

    /** PATCH /api/v1/notifications/{id}/lire */
    public function marquerLue(string $id): void
    {
        $ctx = $this->requireApiAuth();

        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT id FROM comm_notifications WHERE id=:id AND destinataire_id=:uid AND etablissement_id=:etab LIMIT 1');
        $stmt->execute([':id' => (int)$id, ':uid' => $ctx->userId, ':etab' => $ctx->etablissementId]);
        if (!$stmt->fetch()) throw new NotFoundException('Notification');

        $pdo->prepare('UPDATE comm_notifications SET lu=1, lu_le=NOW() WHERE id=:id')
            ->execute([':id' => (int)$id]);

        $this->apiSuccess(['message' => 'Notification marquée comme lue.']);
    }

    /** PATCH /api/v1/notifications/lire-tout */
    public function marquerToutesLues(): void
    {
        $ctx = $this->requireApiAuth();

        $pdo  = Database::getInstance()->getConnection();
        $pdo->prepare('UPDATE comm_notifications SET lu=1, lu_le=NOW() WHERE destinataire_id=:uid AND etablissement_id=:etab AND lu=0')
            ->execute([':uid' => $ctx->userId, ':etab' => $ctx->etablissementId]);

        $this->apiSuccess(['message' => 'Toutes les notifications marquées comme lues.']);
    }
}
