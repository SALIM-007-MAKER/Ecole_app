<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Repositories;

use Core\Database;

class MouvementRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inv_mouvements
             (article_id, type, quantite, quantite_avant, quantite_apres,
              emplacement_source_id, emplacement_dest_id, reference_type, reference_id,
              notes, created_by, etablissement_id)
             VALUES
             (:article_id, :type, :quantite, :quantite_avant, :quantite_apres,
              :emplacement_source_id, :emplacement_dest_id, :reference_type, :reference_id,
              :notes, :created_by, :etablissement_id)'
        );
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function getByArticle(int $articleId, array $filters = []): array
    {
        $where  = ['m.article_id = :aid'];
        $params = [':aid' => $articleId];

        if (!empty($filters['type'])) {
            $where[]         = 'm.type = :type';
            $params[':type'] = $filters['type'];
        }
        if (!empty($filters['from'])) {
            $where[]         = 'DATE(m.created_at) >= :from';
            $params[':from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[]       = 'DATE(m.created_at) <= :to';
            $params[':to'] = $filters['to'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT m.*, a.designation, a.reference,
                    es.nom AS source_nom, ed.nom AS dest_nom
               FROM inv_mouvements m
               JOIN inv_articles a     ON a.id  = m.article_id
          LEFT JOIN inv_emplacements es ON es.id = m.emplacement_source_id
          LEFT JOIN inv_emplacements ed ON ed.id = m.emplacement_dest_id
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY m.created_at DESC
              LIMIT 200'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getByEtablissement(int $etablissementId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*, a.designation, a.reference
               FROM inv_mouvements m
               JOIN inv_articles a ON a.id = m.article_id
              WHERE m.etablissement_id = :etab
           ORDER BY m.created_at DESC
              LIMIT :lim'
        );
        $stmt->bindValue(':etab', $etablissementId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function statsByType(int $etablissementId, string $from, string $to): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT type, SUM(quantite) AS total, COUNT(*) AS nb
               FROM inv_mouvements
              WHERE etablissement_id = :etab
                AND DATE(created_at) BETWEEN :from AND :to
           GROUP BY type'
        );
        $stmt->execute([':etab' => $etablissementId, ':from' => $from, ':to' => $to]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
