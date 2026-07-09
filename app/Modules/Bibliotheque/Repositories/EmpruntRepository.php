<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Repositories;

use Core\Database;
use PDO;

class EmpruntRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO biblio_emprunts
             (exemplaire_id, user_id, date_emprunt, date_retour_prevue, statut, notes, created_by, etablissement_id)
             VALUES (:exemplaire_id, :user_id, :date_emprunt, :date_retour_prevue, 'en_cours', :notes, :created_by, :etab)"
        );
        $st->execute([
            ':exemplaire_id'    => $data['exemplaire_id'],
            ':user_id'          => $data['user_id'],
            ':date_emprunt'     => $data['date_emprunt'],
            ':date_retour_prevue'=> $data['date_retour_prevue'],
            ':notes'            => $data['notes'] ?? null,
            ':created_by'       => $data['created_by'] ?? null,
            ':etab'             => $data['etablissement_id'] ?? 1,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateStatut(int $id, string $statut): void
    {
        $this->pdo->prepare("UPDATE biblio_emprunts SET statut=:statut WHERE id=:id")
            ->execute([':statut' => $statut, ':id' => $id]);
    }

    public function updateRetour(int $id, string $dateRetour): void
    {
        $this->pdo->prepare(
            "UPDATE biblio_emprunts SET statut='retourne', date_retour_effectif=:date WHERE id=:id"
        )->execute([':date' => $dateRetour, ':id' => $id]);
    }

    public function incrementProlongation(int $id, string $nouvelleDateRetour): void
    {
        $this->pdo->prepare(
            "UPDATE biblio_emprunts SET prolongations=prolongations+1, date_retour_prevue=:date WHERE id=:id"
        )->execute([':date' => $nouvelleDateRetour, ':id' => $id]);
    }

    public function markOverdue(int $etablissementId): array
    {
        $st = $this->pdo->prepare(
            "SELECT e.*, ex.ouvrage_id FROM biblio_emprunts e
             JOIN biblio_exemplaires ex ON ex.id = e.exemplaire_id
             WHERE e.statut='en_cours' AND e.date_retour_prevue < CURDATE() AND e.etablissement_id=:etab"
        );
        $st->execute([':etab' => $etablissementId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            $ids = implode(',', array_map('intval', array_column($rows, 'id')));
            $this->pdo->exec("UPDATE biblio_emprunts SET statut='en_retard' WHERE id IN ($ids)");
        }
        return $rows;
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT e.*, ex.ouvrage_id, ex.numero_inventaire, o.titre AS ouvrage_titre,
                    u.prenom, u.nom AS user_nom
             FROM biblio_emprunts e
             JOIN biblio_exemplaires ex ON ex.id = e.exemplaire_id
             JOIN biblio_ouvrages o     ON o.id  = ex.ouvrage_id
             JOIN users u               ON u.id  = e.user_id
             WHERE e.id=:id"
        );
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByUser(int $userId, ?string $statut = null): array
    {
        $where  = 'e.user_id=:uid';
        $params = [':uid' => $userId];
        if ($statut !== null) {
            $where        .= ' AND e.statut=:statut';
            $params[':statut'] = $statut;
        }
        $st = $this->pdo->prepare(
            "SELECT e.*, ex.ouvrage_id, ex.numero_inventaire, o.titre AS ouvrage_titre
             FROM biblio_emprunts e
             JOIN biblio_exemplaires ex ON ex.id = e.exemplaire_id
             JOIN biblio_ouvrages o     ON o.id  = ex.ouvrage_id
             WHERE $where ORDER BY e.created_at DESC"
        );
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByExemplaire(int $exemplaireId, string $statut = 'en_cours'): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM biblio_emprunts WHERE exemplaire_id=:eid AND statut=:statut ORDER BY id DESC LIMIT 1"
        );
        $st->execute([':eid' => $exemplaireId, ':statut' => $statut]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findEnCours(int $etablissementId, int $page = 1, int $perPage = 30): array
    {
        $offset = ($page - 1) * $perPage;
        $st = $this->pdo->prepare(
            "SELECT e.*, ex.ouvrage_id, ex.numero_inventaire, o.titre AS ouvrage_titre,
                    u.prenom, u.nom AS user_nom
             FROM biblio_emprunts e
             JOIN biblio_exemplaires ex ON ex.id = e.exemplaire_id
             JOIN biblio_ouvrages o     ON o.id  = ex.ouvrage_id
             JOIN users u               ON u.id  = e.user_id
             WHERE e.statut IN ('en_cours','en_retard') AND e.etablissement_id=:etab
             ORDER BY e.date_retour_prevue ASC
             LIMIT :limit OFFSET :offset"
        );
        $st->bindValue(':etab',   $etablissementId, PDO::PARAM_INT);
        $st->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findEnRetard(int $etablissementId): array
    {
        $st = $this->pdo->prepare(
            "SELECT e.*, ex.ouvrage_id, o.titre AS ouvrage_titre, u.prenom, u.nom AS user_nom,
                    DATEDIFF(CURDATE(), e.date_retour_prevue) AS jours_retard
             FROM biblio_emprunts e
             JOIN biblio_exemplaires ex ON ex.id = e.exemplaire_id
             JOIN biblio_ouvrages o     ON o.id  = ex.ouvrage_id
             JOIN users u               ON u.id  = e.user_id
             WHERE e.statut='en_retard' AND e.etablissement_id=:etab
             ORDER BY e.date_retour_prevue ASC"
        );
        $st->execute([':etab' => $etablissementId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countEnCoursUser(int $userId): int
    {
        $st = $this->pdo->prepare(
            "SELECT COUNT(*) FROM biblio_emprunts WHERE user_id=:uid AND statut IN ('en_cours','en_retard')"
        );
        $st->execute([':uid' => $userId]);
        return (int)$st->fetchColumn();
    }

    public function findRappels(int $etablissementId, int $joursAvantEcheance = 3): array
    {
        $dateTarget = date('Y-m-d', strtotime("+$joursAvantEcheance days"));
        $st = $this->pdo->prepare(
            "SELECT e.*, u.prenom, u.nom AS user_nom, o.titre AS ouvrage_titre
             FROM biblio_emprunts e
             JOIN biblio_exemplaires ex ON ex.id = e.exemplaire_id
             JOIN biblio_ouvrages o     ON o.id  = ex.ouvrage_id
             JOIN users u               ON u.id  = e.user_id
             WHERE e.statut='en_cours' AND e.date_retour_prevue=:date AND e.etablissement_id=:etab"
        );
        $st->execute([':date' => $dateTarget, ':etab' => $etablissementId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
