<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Repositories;

use Core\Database;
use PDO;

class InventaireRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function insertSession(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO biblio_inventaires
             (nom, description, date_debut, date_fin_prevue, statut, created_by, etablissement_id)
             VALUES (:nom, :desc, :debut, :fin, 'en_cours', :created_by, :etab)"
        );
        $st->execute([
            ':nom'        => $data['nom'],
            ':desc'       => $data['description'] ?? null,
            ':debut'      => $data['date_debut'],
            ':fin'        => $data['date_fin_prevue'] ?? null,
            ':created_by' => $data['created_by'] ?? null,
            ':etab'       => $data['etablissement_id'] ?? 1,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function terminerSession(int $id): void
    {
        $this->pdo->prepare(
            "UPDATE biblio_inventaires SET statut='termine', date_fin_effective=CURDATE() WHERE id=:id"
        )->execute([':id' => $id]);
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM biblio_inventaires WHERE id=:id");
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findActive(int $etablissementId): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM biblio_inventaires WHERE statut='en_cours' AND etablissement_id=:etab LIMIT 1"
        );
        $st->execute([':etab' => $etablissementId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAll(int $etablissementId): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM biblio_inventaires WHERE etablissement_id=:etab ORDER BY created_at DESC"
        );
        $st->execute([':etab' => $etablissementId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function upsertLigne(int $inventaireId, int $exemplaireId, string $statut, ?string $notes, int $scannedBy): void
    {
        $st = $this->pdo->prepare(
            "INSERT INTO biblio_inventaire_lignes (inventaire_id, exemplaire_id, statut_constate, notes, scanned_by)
             VALUES (:inv, :ex, :statut, :notes, :by)
             ON DUPLICATE KEY UPDATE statut_constate=:statut, notes=:notes, scanned_at=NOW(), scanned_by=:by"
        );
        $st->execute([
            ':inv'    => $inventaireId,
            ':ex'     => $exemplaireId,
            ':statut' => $statut,
            ':notes'  => $notes,
            ':by'     => $scannedBy,
        ]);
    }

    public function findLignes(int $inventaireId): array
    {
        $st = $this->pdo->prepare(
            "SELECT il.*, ex.numero_inventaire, ex.code_barre, o.titre AS ouvrage_titre
             FROM biblio_inventaire_lignes il
             JOIN biblio_exemplaires ex ON ex.id = il.exemplaire_id
             JOIN biblio_ouvrages o     ON o.id  = ex.ouvrage_id
             WHERE il.inventaire_id=:id ORDER BY il.scanned_at DESC"
        );
        $st->execute([':id' => $inventaireId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function rapport(int $inventaireId): array
    {
        $st = $this->pdo->prepare(
            "SELECT statut_constate, COUNT(*) AS total
             FROM biblio_inventaire_lignes WHERE inventaire_id=:id GROUP BY statut_constate"
        );
        $st->execute([':id' => $inventaireId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $r    = ['present' => 0, 'manquant' => 0, 'deteriore' => 0, 'perdu' => 0];
        foreach ($rows as $row) {
            $r[$row['statut_constate']] = (int)$row['total'];
        }
        return $r;
    }
}
