<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Models;

use Core\Database;

class InvAmortissementModel
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function findByArticle(int $articleId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT am.*, a.designation, a.reference
               FROM inv_amortissements am
               JOIN inv_articles a ON a.id = am.article_id
              WHERE am.article_id = :id'
        );
        $stmt->execute([':id' => $articleId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function moisEcoules(int $articleId): int
    {
        $row = $this->findByArticle($articleId);
        if (!$row) return 0;
        $debut = new \DateTimeImmutable($row['date_achat']);
        $now   = new \DateTimeImmutable();
        return (int)$debut->diff($now)->m + ((int)$debut->diff($now)->y * 12);
    }
}
