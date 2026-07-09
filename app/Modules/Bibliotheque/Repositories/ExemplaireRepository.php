<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Repositories;

use Core\Database;
use PDO;

class ExemplaireRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO biblio_exemplaires
             (ouvrage_id, numero_inventaire, code_barre, qr_data, localisation, statut, etat, notes, etablissement_id, created_by)
             VALUES (:ouvrage_id, :num_inv, :code_barre, :qr_data, :localisation, 'disponible', :etat, :notes, :etab, :created_by)"
        );
        $st->execute([
            ':ouvrage_id'  => $data['ouvrage_id'],
            ':num_inv'     => $data['numero_inventaire'],
            ':code_barre'  => $data['code_barre'] ?? null,
            ':qr_data'     => $data['qr_data'] ?? null,
            ':localisation'=> $data['localisation'] ?? null,
            ':etat'        => $data['etat'] ?? 'bon',
            ':notes'       => $data['notes'] ?? null,
            ':etab'        => $data['etablissement_id'] ?? 1,
            ':created_by'  => $data['created_by'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $st = $this->pdo->prepare(
            "UPDATE biblio_exemplaires SET
             code_barre=:code_barre, localisation=:localisation, etat=:etat, notes=:notes
             WHERE id=:id AND deleted_at IS NULL"
        );
        $st->execute([
            ':code_barre'  => $data['code_barre'] ?? null,
            ':localisation'=> $data['localisation'] ?? null,
            ':etat'        => $data['etat'] ?? 'bon',
            ':notes'       => $data['notes'] ?? null,
            ':id'          => $id,
        ]);
    }

    public function updateStatut(int $id, string $statut): void
    {
        $this->pdo->prepare("UPDATE biblio_exemplaires SET statut=:statut WHERE id=:id")
            ->execute([':statut' => $statut, ':id' => $id]);
    }

    public function updateQrData(int $id, string $qrData): void
    {
        $this->pdo->prepare("UPDATE biblio_exemplaires SET qr_data=:qr WHERE id=:id")
            ->execute([':qr' => $qrData, ':id' => $id]);
    }

    public function softDelete(int $id): void
    {
        $this->pdo->prepare("UPDATE biblio_exemplaires SET deleted_at=NOW(), statut='retire' WHERE id=:id")
            ->execute([':id' => $id]);
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT ex.*, o.titre AS ouvrage_titre, o.isbn13
             FROM biblio_exemplaires ex
             JOIN biblio_ouvrages o ON o.id = ex.ouvrage_id
             WHERE ex.id=:id AND ex.deleted_at IS NULL"
        );
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByOuvrage(int $ouvrageId): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM biblio_exemplaires WHERE ouvrage_id=:oid AND deleted_at IS NULL ORDER BY numero_inventaire"
        );
        $st->execute([':oid' => $ouvrageId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByBarcode(string $code): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT ex.*, o.titre AS ouvrage_titre, o.isbn13
             FROM biblio_exemplaires ex
             JOIN biblio_ouvrages o ON o.id = ex.ouvrage_id
             WHERE (ex.code_barre=:code OR ex.numero_inventaire=:code) AND ex.deleted_at IS NULL LIMIT 1"
        );
        $st->execute([':code' => $code]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findDisponibles(int $ouvrageId): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM biblio_exemplaires WHERE ouvrage_id=:oid AND statut='disponible' AND deleted_at IS NULL"
        );
        $st->execute([':oid' => $ouvrageId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countDisponibles(int $ouvrageId): int
    {
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM biblio_exemplaires WHERE ouvrage_id=:oid AND statut='disponible' AND deleted_at IS NULL"
        );
        $st->execute([':oid' => $ouvrageId]);
        return (int)$st->fetchColumn();
    }

    public function nextSequence(int $etablissementId): int
    {
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM biblio_exemplaires WHERE etablissement_id=:etab"
        );
        $st->execute([':etab' => $etablissementId]);
        return (int)$st->fetchColumn() + 1;
    }

    public function statutDistribution(int $etablissementId): array
    {
        $st = $this->pdo->prepare(
            "SELECT statut, COUNT(*) AS total FROM biblio_exemplaires
             WHERE etablissement_id=:etab AND deleted_at IS NULL GROUP BY statut"
        );
        $st->execute([':etab' => $etablissementId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
