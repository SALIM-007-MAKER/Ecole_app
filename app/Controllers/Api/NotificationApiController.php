<?php

namespace App\Controllers\Api;

use Core\Controller;
use Core\Session;
use App\Models\NotificationModel;

class NotificationApiController extends Controller
{
    public function unreadCount(): void
    {
        header('Content-Type: application/json');

        if (!Session::isLogged()) {
            echo json_encode(['count' => 0]);
            return;
        }

        $user  = Session::getUser();
        $model = new NotificationModel();
        $count = $model->countUnread((int)$user['id']);

        echo json_encode(['count' => $count]);
    }

    public function recent(): void
    {
        header('Content-Type: application/json');

        if (!Session::isLogged()) {
            echo json_encode(['notifications' => []]);
            return;
        }

        $user  = Session::getUser();
        $model = new NotificationModel();
        $items = $model->findUnreadForUser((int)$user['id'], 8);

        echo json_encode([
            'notifications' => array_map(fn($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'titre'      => $n->titre,
                'message'    => $n->message,
                'lien'       => $n->lien,
                'created_at' => $n->created_at,
            ], $items),
            'count' => count($items),
        ]);
    }

    /**
     * Endpoint appelé par le Service Worker quand il reçoit un push sans payload.
     * Retourne la dernière notification non lue pour affichage natif.
     */
    public function latest(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if (!Session::isLogged()) {
            http_response_code(401);
            echo json_encode(['error' => 'Non authentifié']);
            return;
        }

        $user  = Session::getUser();
        $model = new NotificationModel();
        $items = $model->findUnreadForUser((int)$user['id'], 1);

        if (empty($items)) {
            echo json_encode([
                'title' => 'Ecole App',
                'body'  => 'Vous avez de nouvelles informations.',
                'url'   => BASE_URL . '/dashboard',
                'tag'   => 'ecole-generic',
            ]);
            return;
        }

        $n = $items[0];
        echo json_encode([
            'title'     => 'Ecole App',
            'body'      => $n->titre . ($n->message ? ' — ' . $n->message : ''),
            'url'       => $n->lien ?: BASE_URL . '/dashboard',
            'tag'       => 'notif-' . $n->id,
            'timestamp' => $n->created_at,
        ]);
    }
}
