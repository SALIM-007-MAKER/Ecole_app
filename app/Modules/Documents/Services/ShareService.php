<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\DTO\ShareDTO;
use App\Modules\Documents\Repositories\ShareRepository;
use App\Modules\Documents\Events\DocumentShared;
use App\Modules\Documents\Events\DocumentShareRevoked;
use Core\EventDispatcher;

class ShareService
{
    private ShareRepository $repo;

    public function __construct()
    {
        $this->repo = new ShareRepository();
    }

    public function partager(ShareDTO $dto, int $userId): array
    {
        $errors = $dto->validate();
        if ($errors) throw new \InvalidArgumentException(implode(' ', $errors));

        $token = null;
        if ($dto->destinataireType === 'externe') {
            $token = bin2hex(random_bytes(32));
        }

        $id = $this->repo->insert([
            'document_id'       => $dto->documentId,
            'destinataire_type' => $dto->destinataireType,
            'destinataire_id'   => $dto->destinataireId,
            'destinataire_email'=> $dto->destinataireEmail,
            'permission'        => $dto->permission,
            'token_acces'       => $token,
            'date_expiration'   => $dto->dateExpiration,
            'notifie'           => $dto->notifier,
            'created_by'        => $userId,
        ]);

        EventDispatcher::dispatch(new DocumentShared(
            $dto->documentId, $id, $dto->destinataireType,
            $dto->destinataireId, $dto->permission, $token, $userId
        ));

        return ['id' => $id, 'token' => $token];
    }

    public function revoquer(int $partageId, int $userId): void
    {
        $partage = $this->repo->findById($partageId);
        if (!$partage) throw new \RuntimeException('Partage introuvable.');
        if ($partage['revoked_at'] !== null) throw new \RuntimeException('Ce partage est déjà révoqué.');

        $this->repo->revoke($partageId, $userId);
        EventDispatcher::dispatch(new DocumentShareRevoked(
            (int)$partage['document_id'], $partageId, $userId
        ));
    }

    public function revoquerTous(int $documentId, int $userId): void
    {
        $this->repo->revokeByDocument($documentId, $userId);
        EventDispatcher::dispatch(new DocumentShareRevoked($documentId, 0, $userId));
    }

    public function listerActifs(int $documentId): array
    {
        return $this->repo->findActifsByDocument($documentId);
    }

    public function verifierToken(string $token): ?array
    {
        return $this->repo->findByToken($token);
    }

    public function listerPartages(int $documentId): array
    {
        return $this->repo->findByDocument($documentId);
    }

    public function trouverPartage(int $partageId): ?array
    {
        return $this->repo->findById($partageId) ?: null;
    }

    public function accederParToken(string $token): ?array
    {
        $partage = $this->repo->findByToken($token);
        if (!$partage) return null;

        $pdo = \Core\Database::getInstance()->getConnection();
        $doc = $pdo->prepare("SELECT * FROM doc_documents WHERE id=:id AND deleted_at IS NULL");
        $doc->execute(['id' => $partage['document_id']]);
        $document = $doc->fetch(\PDO::FETCH_ASSOC);

        return $document ? ['document' => $document, 'partage' => $partage] : null;
    }

    public function revoquerExpires(): int
    {
        return $this->repo->revokeExpired();
    }
}
