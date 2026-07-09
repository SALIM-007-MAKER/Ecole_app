<?php

declare(strict_types=1);

namespace App\Modules\Communication\Repositories;

use Core\Database;
use PDO;

class QueueRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO com_queue
             (canal, type, user_id, destinataire_email, destinataire_tel, push_token,
              sujet, corps, template_id, variables_json, statut, priorite,
              max_tentatives, planifie_at, etablissement_id)
             VALUES
             (:canal, :type, :uid, :email, :tel, :token,
              :sujet, :corps, :tpl, :vars, 'pending', :prio,
              :max, :plan, :etab)"
        );
        $st->execute([
            ':canal'  => $data['canal'],
            ':type'   => $data['type'],
            ':uid'    => $data['user_id']            ?? null,
            ':email'  => $data['destinataire_email'] ?? null,
            ':tel'    => $data['destinataire_tel']   ?? null,
            ':token'  => $data['push_token']         ?? null,
            ':sujet'  => $data['sujet']              ?? null,
            ':corps'  => $data['corps']              ?? null,
            ':tpl'    => $data['template_id']        ?? null,
            ':vars'   => isset($data['variables_json'])
                            ? (is_string($data['variables_json']) ? $data['variables_json'] : json_encode($data['variables_json']))
                            : null,
            ':prio'   => $data['priorite']           ?? 'normale',
            ':max'    => $data['max_tentatives']     ?? 3,
            ':plan'   => $data['planifie_at']        ?? null,
            ':etab'   => $data['etablissement_id']   ?? 1,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findPending(int $limit = 50): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM com_queue
             WHERE statut = 'pending'
               AND (planifie_at IS NULL OR planifie_at <= NOW())
               AND (prochain_essai_at IS NULL OR prochain_essai_at <= NOW())
             ORDER BY
               FIELD(priorite, 'critique','haute','normale','basse'),
               created_at ASC
             LIMIT :limit"
        );
        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findFailed(int $limit = 20, int $etablissementId = 1): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM com_queue
             WHERE statut = 'failed' AND etablissement_id = :etab
             ORDER BY created_at DESC LIMIT :limit"
        );
        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->bindValue(':etab', $etablissementId, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resetForRetry(int $id): void
    {
        $st = $this->pdo->prepare(
            "UPDATE com_queue
             SET statut = 'pending', tentatives = 0, erreur = NULL, prochain_essai_at = NULL
             WHERE id = :id AND statut IN ('failed','cancelled')"
        );
        $st->execute([':id' => $id]);
    }

    public function resetAllFailed(int $etablissementId = 1): int
    {
        $st = $this->pdo->prepare(
            "UPDATE com_queue
             SET statut = 'pending', tentatives = 0, erreur = NULL, prochain_essai_at = NULL
             WHERE statut = 'failed' AND etablissement_id = :etab"
        );
        $st->execute([':etab' => $etablissementId]);
        return $st->rowCount();
    }

    public function findForRetry(): array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM com_queue
             WHERE statut = 'pending'
               AND prochain_essai_at IS NOT NULL
               AND prochain_essai_at <= NOW()
               AND tentatives < max_tentatives
             LIMIT 50"
        );
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatut(int $id, string $statut, ?string $erreur = null): void
    {
        $st = $this->pdo->prepare(
            "UPDATE com_queue
             SET statut = :statut, traite_at = NOW(), erreur = :err
             WHERE id = :id"
        );
        $st->execute([':statut' => $statut, ':err' => $erreur, ':id' => $id]);
    }

    public function markProcessing(int $id): void
    {
        $st = $this->pdo->prepare(
            "UPDATE com_queue SET statut = 'processing' WHERE id = :id"
        );
        $st->execute([':id' => $id]);
    }

    public function incrementTentatives(int $id, int $nextDelaySeconds): void
    {
        $st = $this->pdo->prepare(
            "UPDATE com_queue
             SET tentatives = tentatives + 1,
                 statut = 'pending',
                 prochain_essai_at = DATE_ADD(NOW(), INTERVAL :delay SECOND)
             WHERE id = :id"
        );
        $st->execute([':delay' => $nextDelaySeconds, ':id' => $id]);
    }

    public function cancel(int $id): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE com_queue SET statut = 'cancelled' WHERE id = :id AND statut = 'pending'"
        );
        return $st->execute([':id' => $id]);
    }

    public function countByStatut(int $etablissementId = 1): array
    {
        $st = $this->pdo->prepare(
            "SELECT statut, COUNT(*) AS total
             FROM com_queue
             WHERE etablissement_id = :etab
             GROUP BY statut"
        );
        $st->execute([':etab' => $etablissementId]);
        return $st->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
