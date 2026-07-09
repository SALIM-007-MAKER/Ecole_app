<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Repositories;

use Core\Database;

class AmortissementRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function all(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT am.*, a.designation, a.reference
               FROM inv_amortissements am
               JOIN inv_articles a ON a.id = am.article_id
              WHERE am.etablissement_id = :etab
           ORDER BY a.designation ASC'
        );
        $stmt->execute([':etab' => $etablissementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findByArticle(int $articleId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM inv_amortissements WHERE article_id = :id'
        );
        $stmt->execute([':id' => $articleId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inv_amortissements
             (article_id, valeur_achat, date_achat, duree_amortissement_mois, methode, valeur_residuelle, etablissement_id)
             VALUES (:article_id, :valeur_achat, :date_achat, :duree_amortissement_mois, :methode, :valeur_residuelle, :etablissement_id)'
        );
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateVnc(int $articleId, float $vnc): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE inv_amortissements SET valeur_nette_comptable=:vnc WHERE article_id=:id'
        );
        $stmt->execute([':vnc' => $vnc, ':id' => $articleId]);
    }

    public function updateStatut(int $articleId, string $statut): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE inv_amortissements SET statut=:s WHERE article_id=:id'
        );
        $stmt->execute([':s' => $statut, ':id' => $articleId]);
    }

    public function totalValeurNette(int $etablissementId): float
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(valeur_nette_comptable),0) FROM inv_amortissements WHERE etablissement_id=:etab'
        );
        $stmt->execute([':etab' => $etablissementId]);
        return (float)$stmt->fetchColumn();
    }
}
