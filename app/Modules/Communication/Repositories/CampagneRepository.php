<?php

declare(strict_types=1);

namespace App\Modules\Communication\Repositories;

use Core\Database;
use PDO;

class CampagneRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO com_campagnes
             (nom, description, template_id, canaux, cible_type, cible_id,
              planifie_at, variables_json, etablissement_id, created_by)
             VALUES
             (:nom, :desc, :tpl, :canaux, :ctype, :cid,
              :plan, :vars, :etab, :by)"
        );
        $st->execute([
            ':nom'    => $data['nom'],
            ':desc'   => $data['description'] ?? null,
            ':tpl'    => $data['template_id'] ?? null,
            ':canaux' => json_encode($data['canaux'] ?? ['internal']),
            ':ctype'  => $data['cible_type'],
            ':cid'    => $data['cible_id']    ?? null,
            ':plan'   => $data['planifie_at'] ?? null,
            ':vars'   => isset($data['variables_json']) ? json_encode($data['variables_json']) : null,
            ':etab'   => $data['etablissement_id'] ?? 1,
            ':by'     => $data['created_by']  ?? 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE com_campagnes
             SET nom = :nom, description = :desc, statut = :statut, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL"
        );
        return $st->execute([
            ':id'     => $id,
            ':nom'    => $data['nom'],
            ':desc'   => $data['description'] ?? null,
            ':statut' => $data['statut']      ?? 'brouillon',
        ]);
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM com_campagnes WHERE id = :id AND deleted_at IS NULL"
        );
        $st->execute([':id' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAll(int $etablissementId = 1, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $st = $this->pdo->prepare(
            "SELECT * FROM com_campagnes WHERE etablissement_id = :etab AND deleted_at IS NULL
             ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
        );
        $st->bindValue(':etab',   $etablissementId, PDO::PARAM_INT);
        $st->bindValue(':limit',  $perPage,         PDO::PARAM_INT);
        $st->bindValue(':offset', $offset,          PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatut(int $id, string $statut, ?string $field = null): void
    {
        $cols = 'statut = :statut, updated_at = NOW()';
        if ($statut === 'en_cours') {
            $cols .= ', lance_at = NOW()';
        } elseif ($statut === 'terminee') {
            $cols .= ', termine_at = NOW()';
        }
        $st = $this->pdo->prepare("UPDATE com_campagnes SET {$cols} WHERE id = :id");
        $st->execute([':statut' => $statut, ':id' => $id]);
    }

    public function updateStats(int $id, int $envoyes, int $echecs): void
    {
        $st = $this->pdo->prepare(
            "UPDATE com_campagnes
             SET total_envoyes = total_envoyes + :env, total_echecs = total_echecs + :ech
             WHERE id = :id"
        );
        $st->execute([':env' => $envoyes, ':ech' => $echecs, ':id' => $id]);
    }

    public function setTotalDestinataires(int $id, int $total): void
    {
        $st = $this->pdo->prepare(
            "UPDATE com_campagnes SET total_destinataires = :total WHERE id = :id"
        );
        $st->execute([':total' => $total, ':id' => $id]);
    }

    public function insertDestinataire(int $campagneId, int $userId, string $canal): void
    {
        $st = $this->pdo->prepare(
            "INSERT IGNORE INTO com_campagne_destinataires (campagne_id, user_id, canal)
             VALUES (:cid, :uid, :canal)"
        );
        $st->execute([':cid' => $campagneId, ':uid' => $userId, ':canal' => $canal]);
    }

    public function updateDestinataireStatut(int $campagneId, int $userId, string $statut): void
    {
        $st = $this->pdo->prepare(
            "UPDATE com_campagne_destinataires
             SET statut = :statut, envoi_at = NOW()
             WHERE campagne_id = :cid AND user_id = :uid"
        );
        $st->execute([':statut' => $statut, ':cid' => $campagneId, ':uid' => $userId]);
    }

    public function statsDestinataires(int $campagneId): array
    {
        $st = $this->pdo->prepare(
            "SELECT statut, COUNT(*) AS total
             FROM com_campagne_destinataires
             WHERE campagne_id = :cid
             GROUP BY statut"
        );
        $st->execute([':cid' => $campagneId]);
        return $st->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function findMessages(int $campagneId, int $limit = 20): array
    {
        $st = $this->pdo->prepare(
            "SELECT cd.user_id, cd.canal, cd.statut,
                    q.sujet, q.destinataire_email, q.traite_at AS sent_at, q.erreur
             FROM com_campagne_destinataires cd
             LEFT JOIN com_queue q ON q.user_id = cd.user_id AND q.canal = cd.canal
             WHERE cd.campagne_id = :cid
             ORDER BY cd.envoi_at DESC NULLS LAST
             LIMIT :limit"
        );
        $st->bindValue(':cid',   $campagneId, PDO::PARAM_INT);
        $st->bindValue(':limit', $limit,      PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function softDelete(int $id): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE com_campagnes SET deleted_at = NOW() WHERE id = :id"
        );
        return $st->execute([':id' => $id]);
    }
}
