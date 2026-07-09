<?php

declare(strict_types=1);

namespace App\Modules\Documents\Repositories;

use App\Modules\Documents\DTO\DocumentFiltersDTO;
use Core\Database;
use PDO;

class DocumentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ── Écriture ──────────────────────────────────────────────────────────────

    public function insert(array $data): int
    {
        $sql = "INSERT INTO doc_documents
            (folder_id, categorie_id, titre, description, module_source, entite_type, entite_id,
             chemin_stockage, mime_type, extension, taille_octets, checksum_sha256,
             confidentialite, statut, date_emission, date_expiration, alerte_jours,
             reference_externe, emetteur, notes, metadata, version_courante,
             etablissement_id, created_by)
        VALUES
            (:folder_id, :categorie_id, :titre, :description, :module_source, :entite_type, :entite_id,
             :chemin, :mime, :ext, :taille, :checksum,
             :conf, :statut, :date_emission, :date_expiration, :alerte_jours,
             :ref_ext, :emetteur, :notes, :metadata, :version,
             :etab, :created_by)";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':folder_id'    => $data['folder_id'] ?? null,
            ':categorie_id' => $data['categorie_id'] ?? null,
            ':titre'        => $data['titre'],
            ':description'  => $data['description'] ?? null,
            ':module_source'=> $data['module_source'],
            ':entite_type'  => $data['entite_type'] ?? null,
            ':entite_id'    => $data['entite_id'] ?? null,
            ':chemin'       => $data['chemin_stockage'],
            ':mime'         => $data['mime_type'],
            ':ext'          => $data['extension'] ?? '',
            ':taille'       => $data['taille_octets'] ?? 0,
            ':checksum'     => $data['checksum_sha256'] ?? null,
            ':conf'         => $data['confidentialite'] ?? 'interne',
            ':statut'       => $data['statut'] ?? 'actif',
            ':date_emission'=> $data['date_emission'] ?? null,
            ':date_expiration' => $data['date_expiration'] ?? null,
            ':alerte_jours' => $data['alerte_jours'] ?? 30,
            ':ref_ext'      => $data['reference_externe'] ?? null,
            ':emetteur'     => $data['emetteur'] ?? null,
            ':notes'        => $data['notes'] ?? null,
            ':metadata'     => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            ':version'      => $data['version_courante'] ?? 1,
            ':etab'         => $data['etablissement_id'] ?? 1,
            ':created_by'   => $data['created_by'] ?? 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $allowed = [
            'titre','description','confidentialite','statut','folder_id','categorie_id',
            'date_emission','date_expiration','alerte_jours','reference_externe','emetteur',
            'notes','metadata','version_courante','archived_by','archived_at',
            'updated_by','signature_requise','signature_statut',
            'chemin_stockage','mime_type','extension','taille_octets','checksum_sha256',
        ];
        $fields = [];
        $params = [':id' => $id];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "$col = :$col";
                $params[":$col"] = $col === 'metadata' && is_array($data[$col])
                    ? json_encode($data[$col]) : $data[$col];
            }
        }
        if (empty($fields)) return;
        $this->pdo->prepare("UPDATE doc_documents SET " . implode(', ', $fields) . " WHERE id = :id")
                  ->execute($params);
    }

    public function updateStatut(int $id, string $statut, array $extra = []): void
    {
        $this->update($id, array_merge(['statut' => $statut], $extra));
    }

    public function softDelete(int $id): void
    {
        $this->pdo->prepare("UPDATE doc_documents SET deleted_at = NOW() WHERE id = :id")
                  ->execute([':id' => $id]);
    }

    // ── Lecture ───────────────────────────────────────────────────────────────

    public function findById(int $id): ?array
    {
        $sql = "SELECT d.*,
                       c.libelle AS categorie_libelle, c.icone AS categorie_icone,
                       f.nom    AS folder_nom
                FROM doc_documents d
                LEFT JOIN doc_categories c ON c.id = d.categorie_id
                LEFT JOIN doc_folders    f ON f.id = d.folder_id
                WHERE d.id = :id AND d.deleted_at IS NULL";
        $st = $this->pdo->prepare($sql);
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findAll(DocumentFiltersDTO $f): array
    {
        [$where, $params] = $this->buildWhere($f);
        $offset = ($f->page - 1) * $f->perPage;
        $order  = in_array($f->tri, ['created_at','titre','date_expiration','taille_octets'], true) ? $f->tri : 'created_at';
        $dir    = $f->ordre === 'ASC' ? 'ASC' : 'DESC';
        $sql = "SELECT d.*, c.libelle AS categorie_libelle, c.icone AS categorie_icone,
                       f.nom AS folder_nom
                FROM doc_documents d
                LEFT JOIN doc_categories c ON c.id = d.categorie_id
                LEFT JOIN doc_folders    f ON f.id = d.folder_id
                WHERE d.deleted_at IS NULL {$where}
                ORDER BY d.{$order} {$dir}
                LIMIT :limit OFFSET :offset";
        $params[':limit']  = $f->perPage;
        $params[':offset'] = $offset;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(DocumentFiltersDTO $f): int
    {
        [$where, $params] = $this->buildWhere($f);
        $sql = "SELECT COUNT(*) FROM doc_documents d WHERE d.deleted_at IS NULL {$where}";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return (int)$st->fetchColumn();
    }

    public function findByEntite(string $moduleSource, string $entiteType, int $entiteId, int $etablissementId = 1): array
    {
        $sql = "SELECT d.*, c.libelle AS categorie_libelle
                FROM doc_documents d
                LEFT JOIN doc_categories c ON c.id = d.categorie_id
                WHERE d.deleted_at IS NULL
                  AND d.module_source    = :module
                  AND d.entite_type      = :etype
                  AND d.entite_id        = :eid
                  AND d.etablissement_id = :etab
                  AND d.statut NOT IN ('corbeille')
                ORDER BY d.created_at DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute([':module' => $moduleSource, ':etype' => $entiteType, ':eid' => $entiteId, ':etab' => $etablissementId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findExpired(): array
    {
        $sql = "SELECT * FROM doc_documents
                WHERE deleted_at IS NULL
                  AND statut = 'actif'
                  AND date_expiration IS NOT NULL
                  AND date_expiration < :today";
        $st = $this->pdo->prepare($sql);
        $st->execute([':today' => date('Y-m-d')]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findExpiring(int $daysBefore, string $moduleSource = ''): array
    {
        $dateLimit    = date('Y-m-d', strtotime("+{$daysBefore} days"));
        $moduleClause = $moduleSource !== '' ? ' AND d.module_source = :module' : '';
        $params       = [':today' => date('Y-m-d'), ':date_limit' => $dateLimit];
        if ($moduleSource !== '') $params[':module'] = $moduleSource;
        $sql = "SELECT d.* FROM doc_documents d
                WHERE d.deleted_at IS NULL
                  AND d.statut = 'actif'
                  AND d.date_expiration IS NOT NULL
                  AND d.date_expiration >= :today
                  AND d.date_expiration <= :date_limit
                  {$moduleClause}
                ORDER BY d.date_expiration ASC";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findInTrash(DocumentFiltersDTO $f): array
    {
        $params = [':etab' => $f->etablissementId];
        $where  = '';
        if ($f->moduleSource !== '') {
            $where .= ' AND d.module_source = :module';
            $params[':module'] = $f->moduleSource;
        }
        if ($f->q !== '') {
            $where .= ' AND d.titre LIKE :q';
            $params[':q'] = '%' . $f->q . '%';
        }
        $offset = ($f->page - 1) * $f->perPage;
        $sql = "SELECT d.*, cb.raison AS corbeille_raison, cb.purge_avant,
                       cb.created_at AS corbeille_date
                FROM doc_documents d
                JOIN doc_corbeille cb ON cb.document_id = d.id
                WHERE d.deleted_at IS NULL
                  AND d.statut = 'corbeille'
                  AND d.etablissement_id = :etab
                  {$where}
                ORDER BY cb.created_at DESC
                LIMIT :limit OFFSET :offset";
        $params[':limit']  = $f->perPage;
        $params[':offset'] = $offset;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function statistiques(string $moduleSource = '', int $etablissementId = 1): array
    {
        $moduleClause = $moduleSource !== '' ? ' AND module_source = :module' : '';
        $params       = [':etab' => $etablissementId];
        if ($moduleSource !== '') $params[':module'] = $moduleSource;
        $today = date('Y-m-d');
        $in30  = date('Y-m-d', strtotime('+30 days'));

        $st = $this->pdo->prepare(
            "SELECT
                COUNT(*)                                              AS total,
                SUM(statut = 'actif')                                AS actifs,
                SUM(statut = 'expire')                               AS expires,
                SUM(statut = 'archive')                              AS archives,
                SUM(statut = 'brouillon')                            AS brouillons,
                SUM(statut = 'corbeille')                            AS corbeille,
                SUM(date_expiration IS NOT NULL
                    AND date_expiration < :today
                    AND statut = 'actif')                            AS a_expirer,
                SUM(date_expiration BETWEEN :today2 AND :in30
                    AND statut = 'actif')                            AS expirent_bientot,
                COALESCE(SUM(taille_octets), 0)                      AS taille_totale
             FROM doc_documents
             WHERE deleted_at IS NULL AND etablissement_id = :etab {$moduleClause}"
        );
        $params[':today']  = $today;
        $params[':today2'] = $today;
        $params[':in30']   = $in30;
        $st->execute($params);
        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // ── Privé ─────────────────────────────────────────────────────────────────

    private function buildWhere(DocumentFiltersDTO $f): array
    {
        $where  = ' AND d.etablissement_id = :etab';
        $params = [':etab' => $f->etablissementId];

        if ($f->q !== '') {
            $where .= ' AND MATCH(d.titre, d.description, d.reference_externe, d.notes) AGAINST(:q IN BOOLEAN MODE)';
            $params[':q'] = $f->q . '*';
        }
        if ($f->moduleSource !== '') {
            $where .= ' AND d.module_source = :module';
            $params[':module'] = $f->moduleSource;
        }
        if ($f->entiteType !== '') {
            $where .= ' AND d.entite_type = :etype';
            $params[':etype'] = $f->entiteType;
        }
        if ($f->entiteId > 0) {
            $where .= ' AND d.entite_id = :eid';
            $params[':eid'] = $f->entiteId;
        }
        if ($f->folderId > 0) {
            $where .= ' AND d.folder_id = :folder';
            $params[':folder'] = $f->folderId;
        }
        if ($f->categorieId > 0) {
            $where .= ' AND d.categorie_id = :cat';
            $params[':cat'] = $f->categorieId;
        }
        if ($f->statut !== '') {
            $where .= ' AND d.statut = :statut';
            $params[':statut'] = $f->statut;
        } elseif (!$f->includeArchive) {
            $where .= " AND d.statut NOT IN ('archive','corbeille')";
        } else {
            $where .= " AND d.statut != 'corbeille'";
        }
        if ($f->confidentialite !== '') {
            $where .= ' AND d.confidentialite = :conf';
            $params[':conf'] = $f->confidentialite;
        }
        if ($f->excludeSecret) {
            $where .= " AND d.confidentialite != 'secret'";
        }
        if ($f->dateEmissionMin !== '') {
            $where .= ' AND d.date_emission >= :date_min';
            $params[':date_min'] = $f->dateEmissionMin;
        }
        if ($f->dateEmissionMax !== '') {
            $where .= ' AND d.date_emission <= :date_max';
            $params[':date_max'] = $f->dateEmissionMax;
        }
        if ($f->dateExpirationAvant !== '') {
            $where .= ' AND d.date_expiration <= :exp_avant';
            $params[':exp_avant'] = $f->dateExpirationAvant;
        }
        if (!empty($f->tagIds)) {
            $placeholders = implode(',', array_map(fn($i) => ":t{$i}", array_keys($f->tagIds)));
            $where .= " AND d.id IN (SELECT document_id FROM doc_document_tags WHERE tag_id IN ({$placeholders}))";
            foreach ($f->tagIds as $i => $tid) {
                $params[":t{$i}"] = $tid;
            }
        }

        return [$where, $params];
    }
}
