<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Repositories;

use Core\Database;
use PDO;

class PenaliteRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO biblio_penalites
             (emprunt_id, user_id, type, montant, statut, jours_retard, facture_id, notes, etablissement_id, created_by)
             VALUES (:emprunt_id, :user_id, :type, :montant, 'en_attente', :jours, :facture_id, :notes, :etab, :created_by)"
        );
        $st->execute([
            ':emprunt_id' => $data['emprunt_id'],
            ':user_id'    => $data['user_id'],
            ':type'       => $data['type'],
            ':montant'    => $data['montant'],
            ':jours'      => $data['jours_retard'] ?? null,
            ':facture_id' => $data['facture_id'] ?? null,
            ':notes'      => $data['notes'] ?? null,
            ':etab'       => $data['etablissement_id'] ?? 1,
            ':created_by' => $data['created_by'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateStatut(int $id, string $statut): void
    {
        $this->pdo->prepare("UPDATE biblio_penalites SET statut=:statut WHERE id=:id")
            ->execute([':statut' => $statut, ':id' => $id]);
    }

    public function linkFacture(int $id, int $factureId): void
    {
        $this->pdo->prepare("UPDATE biblio_penalites SET facture_id=:fid WHERE id=:id")
            ->execute([':fid' => $factureId, ':id' => $id]);
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT p.*, e.date_emprunt, e.date_retour_prevue, e.date_retour_effectif,
                    u.prenom, u.nom AS user_nom
             FROM biblio_penalites p
             JOIN biblio_emprunts e ON e.id = p.emprunt_id
             JOIN users u           ON u.id = p.user_id
             WHERE p.id=:id"
        );
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByUser(int $userId): array
    {
        $st = $this->pdo->prepare(
            "SELECT p.*, o.titre AS ouvrage_titre
             FROM biblio_penalites p
             JOIN biblio_emprunts e    ON e.id  = p.emprunt_id
             JOIN biblio_exemplaires ex ON ex.id = e.exemplaire_id
             JOIN biblio_ouvrages o     ON o.id  = ex.ouvrage_id
             WHERE p.user_id=:uid ORDER BY p.created_at DESC"
        );
        $st->execute([':uid' => $userId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findImpayees(int $etablissementId): array
    {
        $st = $this->pdo->prepare(
            "SELECT p.*, u.prenom, u.nom AS user_nom, o.titre AS ouvrage_titre
             FROM biblio_penalites p
             JOIN users u              ON u.id  = p.user_id
             JOIN biblio_emprunts e    ON e.id  = p.emprunt_id
             JOIN biblio_exemplaires ex ON ex.id = e.exemplaire_id
             JOIN biblio_ouvrages o     ON o.id  = ex.ouvrage_id
             WHERE p.statut='en_attente' AND p.etablissement_id=:etab
             ORDER BY p.created_at DESC"
        );
        $st->execute([':etab' => $etablissementId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countImpayeesUser(int $userId): int
    {
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM biblio_penalites WHERE user_id=:uid AND statut='en_attente'"
        );
        $st->execute([':uid' => $userId]);
        return (int)$st->fetchColumn();
    }

    public function totalImpayeesUser(int $userId): float
    {
        $st = $this->pdo->prepare(
            "SELECT COALESCE(SUM(montant),0) FROM biblio_penalites WHERE user_id=:uid AND statut='en_attente'"
        );
        $st->execute([':uid' => $userId]);
        return (float)$st->fetchColumn();
    }

    public function totalImpayees(int $etablissementId): float
    {
        $st = $this->pdo->prepare(
            "SELECT COALESCE(SUM(montant),0) FROM biblio_penalites WHERE statut='en_attente' AND etablissement_id=:etab"
        );
        $st->execute([':etab' => $etablissementId]);
        return (float)$st->fetchColumn();
    }

    public function findByFactureId(int $factureId): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM biblio_penalites WHERE facture_id=:fid LIMIT 1");
        $st->execute([':fid' => $factureId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
