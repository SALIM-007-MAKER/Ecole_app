<?php

declare(strict_types=1);

namespace App\Modules\RH\Documents\Repositories;

use Core\Database;
use PDO;

class HRDocumentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ── Comptage / liste ──────────────────────────────────────────────────────

    public function count(\App\Modules\RH\Documents\DTO\HRDocumentFiltersDTO $f): int
    {
        [$where, $params] = $this->buildWhere($f);
        $sql = "SELECT COUNT(*) FROM rh_documents d
                LEFT JOIN rh_employes e ON e.id = d.employe_id
                WHERE d.deleted_at IS NULL $where";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return (int)$st->fetchColumn();
    }

    public function findAll(\App\Modules\RH\Documents\DTO\HRDocumentFiltersDTO $f): array
    {
        [$where, $params] = $this->buildWhere($f);
        $offset = ($f->page - 1) * $f->perPage;
        $sql = "SELECT d.*,
                       CONCAT(e.prenom,' ',e.nom) AS employe_nom,
                       e.matricule                AS employe_matricule
                FROM rh_documents d
                LEFT JOIN rh_employes e ON e.id = d.employe_id
                WHERE d.deleted_at IS NULL $where
                ORDER BY d.date_expiration IS NULL, d.date_expiration ASC, d.created_at DESC
                LIMIT :limit OFFSET :offset";
        $params[':limit']  = $f->perPage;
        $params[':offset'] = $offset;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildWhere(\App\Modules\RH\Documents\DTO\HRDocumentFiltersDTO $f): array
    {
        $where  = '';
        $params = [];

        if ($f->q !== '') {
            $where .= ' AND (d.titre LIKE :q OR d.reference_externe LIKE :q OR e.nom LIKE :q OR e.prenom LIKE :q)';
            $params[':q'] = '%' . $f->q . '%';
        }
        if ($f->type !== '') {
            $where .= ' AND d.type = :type';
            $params[':type'] = $f->type;
        }
        if ($f->statut !== '') {
            $where .= ' AND d.statut = :statut';
            $params[':statut'] = $f->statut;
        } elseif (!$f->includeArchive) {
            $where .= ' AND d.statut != \'archive\'';
        }
        if ($f->employeId > 0) {
            $where .= ' AND d.employe_id = :emp';
            $params[':emp'] = $f->employeId;
        }
        if ($f->confidentialite !== '') {
            $where .= ' AND d.confidentialite = :conf';
            $params[':conf'] = $f->confidentialite;
        }
        if ($f->expirationAvant !== '') {
            $where .= ' AND d.date_expiration <= :exp_avant';
            $params[':exp_avant'] = $f->expirationAvant;
        }
        if ($f->excludeSecret) {
            $where .= " AND d.confidentialite != 'secret'";
        }

        return [$where, $params];
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    public function findById(int $id): ?array
    {
        $sql = "SELECT d.*,
                       CONCAT(e.prenom,' ',e.nom) AS employe_nom,
                       e.matricule                AS employe_matricule,
                       c.numero_contrat           AS contrat_numero,
                       s.code_session             AS session_code,
                       s.formation_id             AS formation_id_fk
                FROM rh_documents d
                LEFT JOIN rh_employes e           ON e.id = d.employe_id
                LEFT JOIN rh_contrats c           ON c.id = d.contrat_id
                LEFT JOIN rh_formations_sessions s ON s.id = d.formation_session_id
                WHERE d.id = :id AND d.deleted_at IS NULL";
        $st = $this->pdo->prepare($sql);
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findVersions(int $documentId): array
    {
        $sql = "SELECT * FROM rh_document_versions
                WHERE document_id = :id ORDER BY version DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute([':id' => $documentId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findHistorique(int $documentId): array
    {
        $sql = "SELECT * FROM rh_document_historique
                WHERE document_id = :id ORDER BY created_at DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute([':id' => $documentId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findExpiring(int $daysBefore, bool $includeSecret = false): array
    {
        $dateLimit    = date('Y-m-d', strtotime("+$daysBefore days"));
        $secretClause = $includeSecret ? '' : "AND d.confidentialite != 'secret'";
        $sql = "SELECT d.*,
                       CONCAT(e.prenom,' ',e.nom) AS employe_nom,
                       e.matricule                AS employe_matricule
                FROM rh_documents d
                LEFT JOIN rh_employes e ON e.id = d.employe_id
                WHERE d.deleted_at IS NULL
                  AND d.statut = 'actif'
                  AND d.date_expiration IS NOT NULL
                  AND d.date_expiration <= :date_limit
                  $secretClause
                ORDER BY d.date_expiration ASC";
        $st = $this->pdo->prepare($sql);
        $st->execute([':date_limit' => $dateLimit]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findExpired(): array
    {
        $sql = "SELECT * FROM rh_documents
                WHERE deleted_at IS NULL
                  AND statut = 'actif'
                  AND date_expiration IS NOT NULL
                  AND date_expiration < :today";
        $st = $this->pdo->prepare($sql);
        $st->execute([':today' => date('Y-m-d')]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByEmploye(int $employeId): array
    {
        $sql = "SELECT * FROM rh_documents
                WHERE employe_id = :emp AND deleted_at IS NULL
                ORDER BY date_emission DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute([':emp' => $employeId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Écriture ──────────────────────────────────────────────────────────────

    public function insert(array $data): int
    {
        $sql = "INSERT INTO rh_documents
                    (employe_id, type, titre, reference_externe, statut, confidentialite,
                     version_courante, date_emission, date_expiration, alerte_jours,
                     emetteur, notes, metadata, contrat_id, formation_session_id,
                     evaluation_id, created_by)
                VALUES
                    (:employe_id, :type, :titre, :reference_externe, :statut, :confidentialite,
                     :version_courante, :date_emission, :date_expiration, :alerte_jours,
                     :emetteur, :notes, :metadata, :contrat_id, :formation_session_id,
                     :evaluation_id, :created_by)";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':employe_id'          => $data['employe_id'],
            ':type'                => $data['type'],
            ':titre'               => $data['titre'],
            ':reference_externe'   => $data['reference_externe'] ?? null,
            ':statut'              => $data['statut'] ?? 'actif',
            ':confidentialite'     => $data['confidentialite'] ?? 'confidentiel',
            ':version_courante'    => $data['version_courante'] ?? 1,
            ':date_emission'       => $data['date_emission'],
            ':date_expiration'     => $data['date_expiration'] ?? null,
            ':alerte_jours'        => $data['alerte_jours'] ?? 30,
            ':emetteur'            => $data['emetteur'] ?? null,
            ':notes'               => $data['notes'] ?? null,
            ':metadata'            => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            ':contrat_id'          => $data['contrat_id'] ?? null,
            ':formation_session_id'=> $data['formation_session_id'] ?? null,
            ':evaluation_id'       => $data['evaluation_id'] ?? null,
            ':created_by'          => $data['created_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $allowed = ['titre','reference_externe','date_expiration','alerte_jours',
                    'emetteur','notes','confidentialite','statut','version_courante',
                    'contrat_id','formation_session_id','evaluation_id',
                    'archived_by','archived_at'];
        $params  = [':id' => $id];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "$col = :$col";
                $params[":$col"] = $data[$col];
            }
        }
        if (empty($fields)) return;
        $this->pdo->prepare("UPDATE rh_documents SET " . implode(', ', $fields) . " WHERE id = :id")
                  ->execute($params);
    }

    public function updateStatut(int $id, string $statut, array $extra = []): void
    {
        $this->update($id, array_merge(['statut' => $statut], $extra));
    }

    public function insertVersion(array $data): int
    {
        $sql = "INSERT INTO rh_document_versions
                    (document_id, version, reference_externe, notes_version, created_by, created_by_nom)
                VALUES (:doc_id, :version, :ref, :notes, :by, :by_nom)";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':doc_id'  => $data['document_id'],
            ':version' => $data['version'],
            ':ref'     => $data['reference_externe'] ?? null,
            ':notes'   => $data['notes_version'] ?? null,
            ':by'      => $data['created_by'],
            ':by_nom'  => $data['created_by_nom'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function insertHistorique(array $data): void
    {
        $sql = "INSERT INTO rh_document_historique
                    (document_id, action, ancien_statut, nouveau_statut, notes, created_by, created_by_nom)
                VALUES (:doc_id, :action, :ancien, :nouveau, :notes, :by, :by_nom)";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':doc_id'  => $data['document_id'],
            ':action'  => $data['action'],
            ':ancien'  => $data['ancien_statut'] ?? null,
            ':nouveau' => $data['nouveau_statut'] ?? null,
            ':notes'   => $data['notes'] ?? null,
            ':by'      => $data['created_by'],
            ':by_nom'  => $data['created_by_nom'] ?? null,
        ]);
    }

    // ── Statistiques ──────────────────────────────────────────────────────────

    public function statistiques(): array
    {
        $today = date('Y-m-d');
        $in30  = date('Y-m-d', strtotime('+30 days'));

        $row = $this->pdo->query(
            "SELECT
                COUNT(*)                                                         AS total,
                SUM(statut = 'actif')                                           AS actifs,
                SUM(statut = 'expire')                                          AS expires,
                SUM(statut = 'archive')                                         AS archives,
                SUM(statut = 'en_attente')                                      AS en_attente,
                SUM(date_expiration IS NOT NULL AND date_expiration < '$today' AND statut = 'actif') AS a_expirer,
                SUM(date_expiration BETWEEN '$today' AND '$in30' AND statut = 'actif')              AS expirent_bientot
             FROM rh_documents WHERE deleted_at IS NULL"
        )->fetch(PDO::FETCH_ASSOC);

        $parType = $this->pdo->query(
            "SELECT type, COUNT(*) AS nb FROM rh_documents
             WHERE deleted_at IS NULL AND statut != 'archive'
             GROUP BY type ORDER BY nb DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_merge($row ?? [], ['par_type' => $parType]);
    }
}
