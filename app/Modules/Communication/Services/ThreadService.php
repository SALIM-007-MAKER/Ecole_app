<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\DTO\ThreadDTO;
use App\Modules\Communication\DTO\ThreadMessageDTO;
use App\Modules\Communication\Events\ThreadCreated;
use App\Modules\Communication\Events\ThreadMessageSent;
use App\Modules\Communication\Repositories\ThreadRepository;
use App\Modules\Communication\Repositories\ThreadMessageRepository;
use App\Modules\Communication\Repositories\ThreadParticipantRepository;
use Core\Database;
use Core\EventDispatcher;

class ThreadService
{
    private ThreadRepository            $threadRepo;
    private ThreadMessageRepository     $msgRepo;
    private ThreadParticipantRepository $partRepo;

    public function __construct()
    {
        $this->threadRepo = new ThreadRepository();
        $this->msgRepo    = new ThreadMessageRepository();
        $this->partRepo   = new ThreadParticipantRepository();
    }

    /**
     * Ne garde que les destinataires réellement contactables : comptes actifs
     * du même établissement que l'expéditeur. Corrige une absence totale de
     * vérification (ThreadDTO ne valide que la présence des champs) qui
     * permettait jusqu'ici d'ajouter n'importe quel ID utilisateur, y compris
     * d'un autre établissement — trouvé le 22/08/2026.
     *
     * @param int[] $participantIds
     * @return int[]
     */
    private function filtrerDestinatairesValides(array $participantIds, int $etablissementId): array
    {
        if (empty($participantIds)) {
            return [];
        }
        $pdo         = Database::getInstance()->getConnection();
        $placeholders = implode(',', array_fill(0, count($participantIds), '?'));
        $st = $pdo->prepare(
            "SELECT id FROM users WHERE id IN ({$placeholders}) AND etablissement_id = ? AND actif = 1"
        );
        $st->execute([...$participantIds, $etablissementId]);
        return array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function creer(ThreadDTO $dto, int $userId, int $etablissementId = 1): int
    {
        $participantIds = array_unique(array_filter($dto->participantIds, fn(int $id) => $id !== $userId));
        $participantIds = $this->filtrerDestinatairesValides($participantIds, $etablissementId);
        if (empty($participantIds)) {
            throw new \RuntimeException("Aucun destinataire valide (compte inexistant, inactif, ou d'un autre établissement).");
        }

        $threadId = $this->threadRepo->insert([
            'sujet'           => $dto->sujet,
            'type'            => $dto->type,
            'module_source'   => $dto->moduleSource,
            'entite_type'     => $dto->entiteType,
            'entite_id'       => $dto->entiteId,
            'created_by'      => $userId,
            'etablissement_id'=> $etablissementId,
        ]);

        // Ajouter le créateur comme modérateur
        $this->partRepo->insert($threadId, $userId, 'moderateur');

        // Ajouter les autres participants (déjà filtrés : même établissement, comptes actifs)
        foreach ($participantIds as $pid) {
            $this->partRepo->insert($threadId, $pid, 'membre');
        }

        // Message initial
        if (!empty($dto->corpsInitial)) {
            $this->msgRepo->insert([
                'thread_id' => $threadId,
                'user_id'   => $userId,
                'corps'     => $dto->corpsInitial,
                'type'      => 'texte',
            ]);
            $this->threadRepo->updateDernierMessage($threadId);
        }

        EventDispatcher::dispatch(new ThreadCreated($threadId, $userId, array_merge([$userId], $participantIds)));
        return $threadId;
    }

    public function reply(ThreadMessageDTO $dto, int $userId): int
    {
        $thread = $this->threadRepo->findById($dto->threadId);
        if ($thread === null) {
            throw new \RuntimeException("Thread #{$dto->threadId} introuvable.");
        }
        if (!$this->partRepo->isMember($dto->threadId, $userId)) {
            throw new \RuntimeException("Vous n'êtes pas membre de ce fil.");
        }

        $metadata = !empty($dto->documentIds) ? ['document_ids' => $dto->documentIds] : null;
        $msgId = $this->msgRepo->insert([
            'thread_id' => $dto->threadId,
            'user_id'   => $userId,
            'corps'     => $dto->corps,
            'type'      => $dto->type,
            'metadata'  => $metadata,
        ]);

        $this->threadRepo->updateDernierMessage($dto->threadId);
        $participantIds = $this->partRepo->findUserIds($dto->threadId);

        EventDispatcher::dispatch(new ThreadMessageSent($dto->threadId, $msgId, $userId, $participantIds));
        return $msgId;
    }

    public function archiver(int $threadId, int $userId): void
    {
        $this->threadRepo->archive($threadId, $userId);
    }

    public function quitter(int $threadId, int $userId): void
    {
        $this->partRepo->remove($threadId, $userId);
    }

    public function listerPourUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        return $this->threadRepo->findForUser($userId, $page, $perPage);
    }

    public function messagesDuThread(int $threadId, int $userId, int $page = 1): array
    {
        if (!$this->partRepo->isMember($threadId, $userId)) {
            throw new \RuntimeException("Accès refusé à ce fil.");
        }
        $this->partRepo->updateLuAt($threadId, $userId);
        return $this->msgRepo->findByThread($threadId, $page);
    }

    public function marquerLu(int $threadId, int $userId): void
    {
        if ($this->partRepo->isMember($threadId, $userId)) {
            $this->partRepo->updateLuAt($threadId, $userId);
        }
    }

    public function compterNonLus(int $userId): int
    {
        // Threads où lu_at < dernier_message_at
        $threads = $this->threadRepo->findForUser($userId, 1, 100);
        $count   = 0;
        foreach ($threads as $t) {
            if ($t['participant_lu_at'] === null || $t['participant_lu_at'] < ($t['dernier_message_at'] ?? '')) {
                $count++;
            }
        }
        return $count;
    }
}
