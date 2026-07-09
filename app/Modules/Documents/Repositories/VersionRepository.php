<?php

declare(strict_types=1);

namespace App\Modules\Documents\Repositories;

use Core\Database;
use PDO;

class VersionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function findByDocument(int $documentId): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM doc_versions WHERE document_id = :id ORDER BY numero DESC"
        );
        $st->execute([':id' => $documentId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findVersion(int $documentId, int $numero): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM doc_versions WHERE document_id = :id AND numero = :num"
        );
        $st->execute([':id' => $documentId, ':num' => $numero]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO doc_versions
                (document_id, numero, chemin_stockage, taille_octets, checksum_sha256, mime_type, notes, created_by)
             VALUES (:doc_id, :num, :chemin, :taille, :checksum, :mime, :notes, :by)"
        );
        $st->execute([
            ':doc_id'   => $data['document_id'],
            ':num'      => $data['numero'],
            ':chemin'   => $data['chemin_stockage'],
            ':taille'   => $data['taille_octets'] ?? 0,
            ':checksum' => $data['checksum_sha256'] ?? null,
            ':mime'     => $data['mime_type'] ?? '',
            ':notes'    => $data['notes'] ?? null,
            ':by'       => $data['created_by'] ?? 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function deleteOldVersions(int $documentId, int $keep): int
    {
        $st = $this->pdo->prepare(
            "SELECT id, chemin_stockage FROM doc_versions
             WHERE document_id = :id ORDER BY numero DESC LIMIT 999 OFFSET :keep"
        );
        $st->execute([':id' => $documentId, ':keep' => $keep]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) return 0;

        $ids = implode(',', array_column($rows, 'id'));
        $this->pdo->exec("DELETE FROM doc_versions WHERE id IN ($ids)");
        return count($rows);
    }

    public function maxNumero(int $documentId): int
    {
        $st = $this->pdo->prepare(
            "SELECT MAX(numero) FROM doc_versions WHERE document_id = :id"
        );
        $st->execute([':id' => $documentId]);
        return (int)$st->fetchColumn();
    }
}
