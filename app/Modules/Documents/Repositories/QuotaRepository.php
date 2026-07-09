<?php

declare(strict_types=1);

namespace App\Modules\Documents\Repositories;

use Core\Database;
use PDO;

class QuotaRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function findByModule(string $moduleSource, int $etablissementId = 1): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM doc_quotas
             WHERE module_source = :mod AND entite_type IS NULL AND entite_id IS NULL
               AND etablissement_id = :etab"
        );
        $st->execute([':mod' => $moduleSource, ':etab' => $etablissementId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAll(int $etablissementId = 1): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM doc_quotas WHERE etablissement_id = :etab ORDER BY module_source, entite_type"
        );
        $st->execute([':etab' => $etablissementId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function upsert(string $moduleSource, ?string $entiteType, ?int $entiteId, int $quotaOctets, int $etablissementId = 1): void
    {
        $this->pdo->prepare(
            "INSERT INTO doc_quotas (module_source, entite_type, entite_id, etablissement_id, quota_octets)
             VALUES (:mod, :et, :eid, :etab, :quota)
             ON DUPLICATE KEY UPDATE quota_octets = VALUES(quota_octets)"
        )->execute([
            ':mod'   => $moduleSource, ':et' => $entiteType, ':eid' => $entiteId,
            ':etab'  => $etablissementId, ':quota' => $quotaOctets,
        ]);
    }

    public function incrementer(string $moduleSource, int $octets, int $etablissementId = 1): void
    {
        $this->pdo->prepare(
            "INSERT INTO doc_quotas (module_source, etablissement_id, quota_octets, utilise_octets)
             VALUES (:mod, :etab, 0, :oct)
             ON DUPLICATE KEY UPDATE utilise_octets = utilise_octets + VALUES(utilise_octets)"
        )->execute([':mod' => $moduleSource, ':etab' => $etablissementId, ':oct' => $octets]);
    }

    public function decrementer(string $moduleSource, int $octets, int $etablissementId = 1): void
    {
        $this->pdo->prepare(
            "UPDATE doc_quotas
             SET utilise_octets = GREATEST(0, utilise_octets - :oct)
             WHERE module_source = :mod AND etablissement_id = :etab
               AND entite_type IS NULL AND entite_id IS NULL"
        )->execute([':oct' => $octets, ':mod' => $moduleSource, ':etab' => $etablissementId]);
    }
}
