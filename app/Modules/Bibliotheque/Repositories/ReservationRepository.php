<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Repositories;

use Core\Database;
use PDO;

class ReservationRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO biblio_reservations
             (ouvrage_id, user_id, position_file, statut, date_disponibilite, date_expiration, notes, etablissement_id)
             VALUES (:ouvrage_id, :user_id, :position, :statut, :dispo, :expiration, :notes, :etab)"
        );
        $st->execute([
            ':ouvrage_id' => $data['ouvrage_id'],
            ':user_id'    => $data['user_id'],
            ':position'   => $data['position_file'] ?? 1,
            ':statut'     => $data['statut'] ?? 'en_attente',
            ':dispo'      => $data['date_disponibilite'] ?? null,
            ':expiration' => $data['date_expiration'] ?? null,
            ':notes'      => $data['notes'] ?? null,
            ':etab'       => $data['etablissement_id'] ?? 1,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateStatut(int $id, string $statut, ?string $dateExpiration = null): void
    {
        if ($dateExpiration !== null) {
            $this->pdo->prepare(
                "UPDATE biblio_reservations SET statut=:statut, date_expiration=:exp, date_disponibilite=NOW() WHERE id=:id"
            )->execute([':statut' => $statut, ':exp' => $dateExpiration, ':id' => $id]);
        } else {
            $this->pdo->prepare("UPDATE biblio_reservations SET statut=:statut WHERE id=:id")
                ->execute([':statut' => $statut, ':id' => $id]);
        }
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT r.*, o.titre AS ouvrage_titre, u.prenom, u.nom AS user_nom
             FROM biblio_reservations r
             JOIN biblio_ouvrages o ON o.id = r.ouvrage_id
             JOIN users u           ON u.id = r.user_id
             WHERE r.id=:id"
        );
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByUser(int $userId): array
    {
        $st = $this->pdo->prepare(
            "SELECT r.*, o.titre AS ouvrage_titre
             FROM biblio_reservations r
             JOIN biblio_ouvrages o ON o.id = r.ouvrage_id
             WHERE r.user_id=:uid AND r.statut IN ('en_attente','disponible','confirmee')
             ORDER BY r.created_at DESC"
        );
        $st->execute([':uid' => $userId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByOuvrage(int $ouvrageId): array
    {
        $st = $this->pdo->prepare(
            "SELECT r.*, u.prenom, u.nom AS user_nom
             FROM biblio_reservations r
             JOIN users u ON u.id = r.user_id
             WHERE r.ouvrage_id=:oid AND r.statut IN ('en_attente','disponible')
             ORDER BY r.position_file ASC"
        );
        $st->execute([':oid' => $ouvrageId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findNextInQueue(int $ouvrageId): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM biblio_reservations
             WHERE ouvrage_id=:oid AND statut='en_attente'
             ORDER BY position_file ASC LIMIT 1"
        );
        $st->execute([':oid' => $ouvrageId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function countInQueue(int $ouvrageId): int
    {
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM biblio_reservations WHERE ouvrage_id=:oid AND statut='en_attente'"
        );
        $st->execute([':oid' => $ouvrageId]);
        return (int)$st->fetchColumn();
    }

    public function hasActiveReservation(int $ouvrageId, int $userId): bool
    {
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM biblio_reservations
             WHERE ouvrage_id=:oid AND user_id=:uid AND statut IN ('en_attente','disponible')"
        );
        $st->execute([':oid' => $ouvrageId, ':uid' => $userId]);
        return (int)$st->fetchColumn() > 0;
    }

    public function findExpired(int $etablissementId): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM biblio_reservations
             WHERE statut='disponible' AND date_expiration < NOW() AND etablissement_id=:etab"
        );
        $st->execute([':etab' => $etablissementId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function lister(int $etablissementId, ?string $statut = null, int $page = 1, int $perPage = 30): array
    {
        $where  = ['r.etablissement_id=:etab'];
        $params = [':etab' => $etablissementId];
        if ($statut !== null) {
            $where[]         = 'r.statut=:statut';
            $params[':statut']= $statut;
        }
        $whereStr = implode(' AND ', $where);
        $offset   = ($page - 1) * $perPage;
        $st = $this->pdo->prepare(
            "SELECT r.*, o.titre AS ouvrage_titre, u.prenom, u.nom AS user_nom
             FROM biblio_reservations r
             JOIN biblio_ouvrages o ON o.id = r.ouvrage_id
             JOIN users u           ON u.id = r.user_id
             WHERE $whereStr ORDER BY r.created_at DESC LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
