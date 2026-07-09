<?php

declare(strict_types=1);

namespace App\Modules\Documents\Repositories;

use Core\Database;
use PDO;

class ShareRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM doc_partages WHERE id = :id");
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByToken(string $token): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT p.*, d.titre, d.chemin_stockage, d.mime_type, d.confidentialite
             FROM doc_partages p
             JOIN doc_documents d ON d.id = p.document_id
             WHERE p.token_acces = :token
               AND p.revoked_at IS NULL
               AND (p.date_expiration IS NULL OR p.date_expiration > NOW())
               AND d.deleted_at IS NULL"
        );
        $st->execute([':token' => $token]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByDocument(int $documentId): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM doc_partages WHERE document_id = :id ORDER BY created_at DESC"
        );
        $st->execute([':id' => $documentId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findActifsByDocument(int $documentId): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM doc_partages
             WHERE document_id = :id AND revoked_at IS NULL
               AND (date_expiration IS NULL OR date_expiration > NOW())
             ORDER BY created_at DESC"
        );
        $st->execute([':id' => $documentId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO doc_partages
                (document_id, destinataire_type, destinataire_id, destinataire_email,
                 permission, token_acces, date_expiration, notifie, created_by)
             VALUES (:doc,:dtype,:did,:demail,:perm,:token,:exp,:notif,:by)"
        );
        $st->execute([
            ':doc'    => $data['document_id'],
            ':dtype'  => $data['destinataire_type'],
            ':did'    => $data['destinataire_id'] ?? null,
            ':demail' => $data['destinataire_email'] ?? null,
            ':perm'   => $data['permission'] ?? 'lecture',
            ':token'  => $data['token_acces'] ?? null,
            ':exp'    => $data['date_expiration'] ?? null,
            ':notif'  => $data['notifie'] ? 1 : 0,
            ':by'     => $data['created_by'] ?? 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function revoke(int $id, int $revokedBy): void
    {
        $this->pdo->prepare(
            "UPDATE doc_partages SET revoked_at = NOW(), revoked_by = :by WHERE id = :id"
        )->execute([':by' => $revokedBy, ':id' => $id]);
    }

    public function revokeByDocument(int $documentId, int $revokedBy): int
    {
        $st = $this->pdo->prepare(
            "UPDATE doc_partages SET revoked_at = NOW(), revoked_by = :by
             WHERE document_id = :doc AND revoked_at IS NULL"
        );
        $st->execute([':by' => $revokedBy, ':doc' => $documentId]);
        return $st->rowCount();
    }

    public function revokeExpired(): int
    {
        $st = $this->pdo->prepare(
            "UPDATE doc_partages SET revoked_at = NOW(), revoked_by = 0
             WHERE revoked_at IS NULL AND date_expiration IS NOT NULL AND date_expiration <= NOW()"
        );
        $st->execute();
        return $st->rowCount();
    }
}
