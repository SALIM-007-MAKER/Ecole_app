<?php

declare(strict_types=1);

namespace App\Modules\Communication\Controllers;

use App\Modules\Communication\DTO\ThreadDTO;
use App\Modules\Communication\DTO\ThreadMessageDTO;
use App\Modules\Communication\Policies\ThreadPolicy;
use App\Modules\Communication\Services\ThreadService;
use Core\Controller;

class ThreadController extends Controller
{
    private ThreadService $service;
    private ThreadPolicy  $policy;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ThreadService();
        $this->policy  = new ThreadPolicy();
    }

    public function index(): void
    {
        $this->requirePermission('communication.view');
        $page    = (int) ($_GET['page'] ?? 1);
        $threads = $this->service->listerPourUser((int) $this->user['id'], $page);
        $unread  = $this->service->compterNonLus((int) $this->user['id']);

        $this->render('Communication::messages/index', [
            'threads' => $threads,
            'page'    => $page,
            'unread'  => $unread,
            'titre'   => 'Messagerie',
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('communication.send');
        $this->verifyCsrf();

        $dto    = ThreadDTO::fromRequest($_POST);
        $errors = $dto->validate();
        if (!empty($errors)) {
            $this->json(['success' => false, 'errors' => $errors], 422);
            return;
        }

        try {
            $threadId = $this->service->creer($dto, (int) $this->user['id'], (int) ($this->user['etablissement_id'] ?? 1));
        } catch (\RuntimeException $e) {
            $this->json(['success' => false, 'errors' => [$e->getMessage()]], 422);
            return;
        }
        $this->json(['success' => true, 'thread_id' => $threadId]);
    }

    public function show(int $threadId): void
    {
        $this->requirePermission('communication.view');
        $page     = (int) ($_GET['page'] ?? 1);
        $messages = $this->service->messagesDuThread($threadId, (int) $this->user['id'], $page);

        $this->render('Communication::messages/show', [
            'thread_id' => $threadId,
            'messages'  => $messages,
            'page'      => $page,
            'titre'     => 'Conversation',
        ]);
    }

    public function reply(int $threadId): void
    {
        $this->requirePermission('communication.send');
        $this->verifyCsrf();

        $data   = array_merge($_POST, ['thread_id' => $threadId]);
        $dto    = ThreadMessageDTO::fromRequest($data);
        $errors = $dto->validate();
        if (!empty($errors)) {
            $this->json(['success' => false, 'errors' => $errors], 422);
            return;
        }

        $msgId = $this->service->reply($dto, (int) $this->user['id']);
        $this->json(['success' => true, 'message_id' => $msgId]);
    }

    public function markRead(int $threadId): void
    {
        $this->requirePermission('communication.view');
        $this->verifyCsrf();
        $this->service->marquerLu($threadId, (int) $this->user['id']);
        $this->json(['success' => true]);
    }

    public function archive(int $threadId): void
    {
        $this->requirePermission('communication.view');
        $this->verifyCsrf();
        $this->service->archiver($threadId, (int) $this->user['id']);
        $this->json(['success' => true]);
    }

    public function destroy(int $threadId): void
    {
        $this->requirePermission('communication.admin');
        $this->verifyCsrf();
        $this->service->quitter($threadId, (int) $this->user['id']);
        $this->json(['success' => true]);
    }

    public function unreadCount(): void
    {
        $this->requirePermission('communication.view');
        $count = $this->service->compterNonLus((int) $this->user['id']);
        $this->json(['unread' => $count]);
    }

    public function create(): void
    {
        $this->requirePermission('communication.send');
        $this->render('Communication::messages/create', [
            'titre' => 'Nouveau message',
        ]);
    }
}
