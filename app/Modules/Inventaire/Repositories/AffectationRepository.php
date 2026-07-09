<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Repositories;

use Core\Database;

class AffectationRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function all(int $etablissementId, ?string $statut = null): array
    {
        $where  = ['af.etablissement_id = :etab', 'af.deleted_at IS NULL'];
        $params = [':etab' => $etablissementId];
        if ($statut) {
            $where[]          = 'af.statut = :statut';
            $params[':statut'] = $statut;
        }
        $stmt = $this->pdo->prepare(
            'SELECT af.*, a.designation, a.reference, u.nom AS user_nom, u.prenom AS user_prenom
               FROM inv_affectations af
               JOIN inv_articles a ON a.id = af.article_id
               JOIN users u        ON u.id = af.user_id
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY af.date_affectation DESC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT af.*, a.designation, a.reference, u.nom AS user_nom, u.prenom AS user_prenom
               FROM inv_affectations af
               JOIN inv_articles a ON a.id = af.article_id
               JOIN users u        ON u.id = af.user_id
              WHERE af.id = :id AND af.deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inv_affectations
             (article_id, user_id, quantite, date_affectation, date_retour_prevue, emplacement_id, notes, created_by, etablissement_id)
             VALUES (:article_id, :user_id, :quantite, :date_affectation, :date_retour_prevue, :emplacement_id, :notes, :created_by, :etablissement_id)'
        );
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateStatut(int $id, string $statut, array $extra = []): void
    {
        $sets   = ['statut = :statut'];
        $params = [':statut' => $statut, ':id' => $id];
        foreach ($extra as $col => $val) {
            $sets[]          = "$col = :$col";
            $params[":$col"] = $val;
        }
        $this->pdo->prepare(
            'UPDATE inv_affectations SET ' . implode(', ', $sets) . ' WHERE id = :id'
        )->execute($params);
    }

    public function byUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT af.*, a.designation, a.reference
               FROM inv_affectations af
               JOIN inv_articles a ON a.id = af.article_id
              WHERE af.user_id = :uid AND af.deleted_at IS NULL
           ORDER BY af.date_affectation DESC'
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function byArticle(int $articleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT af.*, u.nom AS user_nom, u.prenom AS user_prenom
               FROM inv_affectations af
               JOIN users u ON u.id = af.user_id
              WHERE af.article_id = :aid AND af.deleted_at IS NULL
           ORDER BY af.date_affectation DESC'
        );
        $stmt->execute([':aid' => $articleId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
