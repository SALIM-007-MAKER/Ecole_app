<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Repositories;

use Core\Database;

class PlanificationRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function paginate(int $etablissementId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            'SELECT * FROM bi_rapports_planifies
             WHERE etablissement_id = :etab AND deleted_at IS NULL
             ORDER BY created_at DESC
             LIMIT :pp OFFSET :off'
        );
        $stmt->bindValue(':etab', $etablissementId, \PDO::PARAM_INT);
        $stmt->bindValue(':pp',   $perPage,         \PDO::PARAM_INT);
        $stmt->bindValue(':off',  $offset,          \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $count = $this->pdo->prepare(
            'SELECT COUNT(*) FROM bi_rapports_planifies
             WHERE etablissement_id = :etab AND deleted_at IS NULL'
        );
        $count->execute([':etab' => $etablissementId]);

        return [
            'data'       => $rows,
            'total'      => (int)$count->fetchColumn(),
            'page'       => $page,
            'per_page'   => $perPage,
        ];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM bi_rapports_planifies WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO bi_rapports_planifies
             (nom, domaine, type_export, filtres, frequence, jour_execution, heure_execution, destinataires, actif, created_by, etablissement_id)
             VALUES (:nom, :dom, :tex, :fil, :frq, :jour, :heure, :dest, :actif, :by, :etab)'
        );
        $stmt->execute([
            ':nom'   => $data['nom'],
            ':dom'   => $data['domaine'],
            ':tex'   => $data['type_export'],
            ':fil'   => isset($data['filtres'])       ? json_encode($data['filtres'])       : null,
            ':frq'   => $data['frequence'],
            ':jour'  => $data['jour_execution']       ?? null,
            ':heure' => $data['heure_execution']      ?? '06:00:00',
            ':dest'  => isset($data['destinataires']) ? json_encode($data['destinataires']) : null,
            ':actif' => (int)($data['actif']          ?? 1),
            ':by'    => $data['created_by'],
            ':etab'  => $data['etablissement_id'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE bi_rapports_planifies
             SET nom = :nom, domaine = :dom, type_export = :tex,
                 filtres = :fil, frequence = :frq, jour_execution = :jour,
                 heure_execution = :heure, destinataires = :dest, actif = :actif
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            ':nom'   => $data['nom'],
            ':dom'   => $data['domaine'],
            ':tex'   => $data['type_export'],
            ':fil'   => isset($data['filtres'])       ? json_encode($data['filtres'])       : null,
            ':frq'   => $data['frequence'],
            ':jour'  => $data['jour_execution']       ?? null,
            ':heure' => $data['heure_execution']      ?? '06:00:00',
            ':dest'  => isset($data['destinataires']) ? json_encode($data['destinataires']) : null,
            ':actif' => (int)($data['actif']          ?? 1),
            ':id'    => $id,
        ]);
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE bi_rapports_planifies SET deleted_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    public function getActifsDus(string $frequence): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM bi_rapports_planifies
             WHERE actif = 1 AND frequence = :frq AND deleted_at IS NULL'
        );
        $stmt->execute([':frq' => $frequence]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
