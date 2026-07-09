<?php

declare(strict_types=1);

namespace App\Modules\Communication\Repositories;

use Core\Database;
use PDO;

class LogRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO com_logs
             (queue_id, canal, type, user_id, destinataire, sujet, statut,
              module_source, entite_type, entite_id, metadata, etablissement_id)
             VALUES
             (:qid, :canal, :type, :uid, :dest, :sujet, :statut,
              :module, :etype, :eid, :meta, :etab)"
        );
        $st->execute([
            ':qid'    => $data['queue_id']       ?? null,
            ':canal'  => $data['canal'],
            ':type'   => $data['type'],
            ':uid'    => $data['user_id']        ?? null,
            ':dest'   => $data['destinataire']   ?? null,
            ':sujet'  => $data['sujet']          ?? null,
            ':statut' => $data['statut'],
            ':module' => $data['module_source']  ?? null,
            ':etype'  => $data['entite_type']    ?? null,
            ':eid'    => $data['entite_id']      ?? null,
            ':meta'   => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            ':etab'   => $data['etablissement_id'] ?? 1,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findForReport(int $etablissementId = 1, int $page = 1, int $perPage = 50): array
    {
        $offset = ($page - 1) * $perPage;
        $st = $this->pdo->prepare(
            "SELECT * FROM com_logs
             WHERE etablissement_id = :etab
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        $st->bindValue(':etab',   $etablissementId, PDO::PARAM_INT);
        $st->bindValue(':limit',  $perPage,         PDO::PARAM_INT);
        $st->bindValue(':offset', $offset,          PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function statistiques(int $etablissementId = 1, int $jours = 30): array
    {
        $st = $this->pdo->prepare(
            "SELECT canal, statut, COUNT(*) AS total
             FROM com_logs
             WHERE etablissement_id = :etab
               AND created_at >= DATE_SUB(NOW(), INTERVAL :jours DAY)
             GROUP BY canal, statut"
        );
        $st->execute([':etab' => $etablissementId, ':jours' => $jours]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function statsByCanal(int $etablissementId = 1): array
    {
        $st = $this->pdo->prepare(
            "SELECT canal,
                    SUM(statut = 'sent')   AS sent,
                    SUM(statut = 'failed') AS failed
             FROM com_logs
             WHERE etablissement_id = :etab
             GROUP BY canal"
        );
        $st->execute([':etab' => $etablissementId]);
        $rows   = $st->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['canal']] = ['sent' => (int) $row['sent'], 'failed' => (int) $row['failed']];
        }
        return $result;
    }
}
