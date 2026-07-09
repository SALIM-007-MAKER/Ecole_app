<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Repositories;

use Core\Database;

class AlerteRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function active(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT al.*, a.designation, a.reference
               FROM inv_alertes al
               JOIN inv_articles a ON a.id = al.article_id
              WHERE al.etablissement_id = :etab AND al.statut = "active"
           ORDER BY al.created_at DESC'
        );
        $stmt->execute([':etab' => $etablissementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function all(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT al.*, a.designation, a.reference
               FROM inv_alertes al
               JOIN inv_articles a ON a.id = al.article_id
              WHERE al.etablissement_id = :etab
           ORDER BY al.created_at DESC LIMIT 200'
        );
        $stmt->execute([':etab' => $etablissementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inv_alertes (article_id, type, valeur_seuil, valeur_actuelle, statut, etablissement_id)
             VALUES (:article_id, :type, :valeur_seuil, :valeur_actuelle, :statut, :etablissement_id)'
        );
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function acquitter(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE inv_alertes SET statut="acquittee", acquittee_by=:uid, acquittee_at=NOW() WHERE id=:id'
        );
        $stmt->execute([':uid' => $userId, ':id' => $id]);
    }

    public function resoudre(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE inv_alertes SET statut="resolue" WHERE id=:id');
        $stmt->execute([':id' => $id]);
    }

    public function alreadyActive(int $articleId, string $type): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM inv_alertes WHERE article_id=:a AND type=:t AND statut="active"'
        );
        $stmt->execute([':a' => $articleId, ':t' => $type]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function countActive(int $etablissementId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM inv_alertes WHERE etablissement_id=:etab AND statut="active"'
        );
        $stmt->execute([':etab' => $etablissementId]);
        return (int)$stmt->fetchColumn();
    }
}
