<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Repositories;

use Core\Database;

class SnapshotRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function upsert(string $domaine, string $metrique, float $valeur, string $periode, int $etablissementId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO bi_kpi_snapshots (domaine, metrique, valeur, periode, etablissement_id)
             VALUES (:dom, :met, :val, :per, :etab)
             ON DUPLICATE KEY UPDATE valeur = :val2'
        );
        $stmt->execute([
            ':dom'  => $domaine,
            ':met'  => $metrique,
            ':val'  => $valeur,
            ':per'  => $periode,
            ':etab' => $etablissementId,
            ':val2' => $valeur,
        ]);
    }

    public function getByDomaine(string $domaine, int $etablissementId, int $nbPeriodes = 12): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT metrique, valeur, periode
             FROM bi_kpi_snapshots
             WHERE domaine = :dom AND etablissement_id = :etab
             ORDER BY periode DESC
             LIMIT :n'
        );
        $stmt->bindValue(':dom',  $domaine,          \PDO::PARAM_STR);
        $stmt->bindValue(':etab', $etablissementId,  \PDO::PARAM_INT);
        $stmt->bindValue(':n',    $nbPeriodes,        \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getMetrique(string $domaine, string $metrique, int $etablissementId, int $nbPeriodes = 12): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT valeur, periode
             FROM bi_kpi_snapshots
             WHERE domaine = :dom AND metrique = :met AND etablissement_id = :etab
             ORDER BY periode DESC
             LIMIT :n'
        );
        $stmt->bindValue(':dom',  $domaine,         \PDO::PARAM_STR);
        $stmt->bindValue(':met',  $metrique,        \PDO::PARAM_STR);
        $stmt->bindValue(':etab', $etablissementId, \PDO::PARAM_INT);
        $stmt->bindValue(':n',    $nbPeriodes,      \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getSnapshotPeriode(string $domaine, string $metrique, string $periode, int $etablissementId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM bi_kpi_snapshots
             WHERE domaine = :dom AND metrique = :met AND periode = :per AND etablissement_id = :etab
             LIMIT 1'
        );
        $stmt->execute([':dom' => $domaine, ':met' => $metrique, ':per' => $periode, ':etab' => $etablissementId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDernierSnapshot(string $domaine, string $metrique, int $etablissementId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM bi_kpi_snapshots
             WHERE domaine = :dom AND metrique = :met AND etablissement_id = :etab
             ORDER BY periode DESC LIMIT 1'
        );
        $stmt->execute([':dom' => $domaine, ':met' => $metrique, ':etab' => $etablissementId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
