<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Repositories;

use Core\Database;

class ExportRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO bi_exports
             (domaine, type_export, filtres, fichier_path, fichier_nom, nb_lignes, expire_at, user_id, etablissement_id)
             VALUES (:dom, :tex, :fil, :fp, :fn, :nb, :exp, :uid, :etab)'
        );
        $stmt->execute([
            ':dom'  => $data['domaine'],
            ':tex'  => $data['type_export'],
            ':fil'  => isset($data['filtres']) ? json_encode($data['filtres']) : null,
            ':fp'   => $data['fichier_path'],
            ':fn'   => $data['fichier_nom'],
            ':nb'   => $data['nb_lignes'] ?? null,
            ':exp'  => $data['expire_at'] ?? null,
            ':uid'  => $data['user_id'],
            ':etab' => $data['etablissement_id'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM bi_exports WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listeParUser(int $userId, int $etablissementId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM bi_exports
             WHERE user_id = :uid AND etablissement_id = :etab
             ORDER BY created_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':uid',  $userId,          \PDO::PARAM_INT);
        $stmt->bindValue(':etab', $etablissementId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim',  $limit,           \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function purgerExpires(): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM bi_exports WHERE expire_at IS NOT NULL AND expire_at < NOW()'
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function createExecution(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO bi_rapport_executions
             (rapport_planifie_id, domaine, type_export, statut, filtres, user_id, etablissement_id)
             VALUES (:pid, :dom, :tex, :stat, :fil, :uid, :etab)'
        );
        $stmt->execute([
            ':pid'  => $data['rapport_planifie_id'] ?? null,
            ':dom'  => $data['domaine'],
            ':tex'  => $data['type_export'],
            ':stat' => $data['statut'] ?? 'pending',
            ':fil'  => isset($data['filtres']) ? json_encode($data['filtres']) : null,
            ':uid'  => $data['user_id'] ?? null,
            ':etab' => $data['etablissement_id'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateExecution(int $id, string $statut, array $extra = []): void
    {
        $set = 'statut = :stat';
        $params = [':stat' => $statut, ':id' => $id];
        if ($statut === 'en_cours') {
            $set .= ', debut_execution = NOW()';
        }
        if (in_array($statut, ['termine', 'erreur'], true)) {
            $set .= ', fin_execution = NOW()';
        }
        if (isset($extra['fichier_path'])) {
            $set .= ', fichier_path = :fp';
            $params[':fp'] = $extra['fichier_path'];
        }
        if (isset($extra['nb_lignes'])) {
            $set .= ', nb_lignes = :nb';
            $params[':nb'] = $extra['nb_lignes'];
        }
        if (isset($extra['erreur_message'])) {
            $set .= ', erreur_message = :em';
            $params[':em'] = $extra['erreur_message'];
        }
        $this->pdo->prepare("UPDATE bi_rapport_executions SET $set WHERE id = :id")->execute($params);
    }
}
