<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Repositories;

use App\Modules\Bibliotheque\DTO\OuvrageFiltersDTO;
use Core\Database;
use PDO;

class OuvrageRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO biblio_ouvrages
             (isbn, isbn13, titre, sous_titre, resume, annee_edition, nombre_pages,
              langue, image_couverture, type, cote, localisation_defaut,
              editeur_id, statut, etablissement_id, created_by)
             VALUES
             (:isbn, :isbn13, :titre, :sous_titre, :resume, :annee_edition, :nombre_pages,
              :langue, :image_couverture, :type, :cote, :localisation_defaut,
              :editeur_id, 'actif', :etab, :created_by)"
        );
        $st->execute([
            ':isbn'               => $data['isbn'],
            ':isbn13'             => $data['isbn13'] ?? $data['isbn'],
            ':titre'              => $data['titre'],
            ':sous_titre'         => $data['sous_titre'] ?? null,
            ':resume'             => $data['resume'] ?? null,
            ':annee_edition'      => $data['annee_edition'] ?? null,
            ':nombre_pages'       => $data['nombre_pages'] ?? null,
            ':langue'             => $data['langue'] ?? 'fr',
            ':image_couverture'   => $data['image_couverture'] ?? null,
            ':type'               => $data['type'] ?? 'livre',
            ':cote'               => $data['cote'] ?? null,
            ':localisation_defaut'=> $data['localisation_defaut'] ?? null,
            ':editeur_id'         => $data['editeur_id'] ?? null,
            ':etab'               => $data['etablissement_id'] ?? 1,
            ':created_by'         => $data['created_by'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $st = $this->pdo->prepare(
            "UPDATE biblio_ouvrages SET
             isbn=:isbn, isbn13=:isbn13, titre=:titre, sous_titre=:sous_titre,
             resume=:resume, annee_edition=:annee_edition, nombre_pages=:nombre_pages,
             langue=:langue, image_couverture=:image_couverture, type=:type,
             cote=:cote, localisation_defaut=:localisation_defaut, editeur_id=:editeur_id
             WHERE id=:id AND deleted_at IS NULL"
        );
        $st->execute([
            ':isbn'               => $data['isbn'],
            ':isbn13'             => $data['isbn13'] ?? $data['isbn'],
            ':titre'              => $data['titre'],
            ':sous_titre'         => $data['sous_titre'] ?? null,
            ':resume'             => $data['resume'] ?? null,
            ':annee_edition'      => $data['annee_edition'] ?? null,
            ':nombre_pages'       => $data['nombre_pages'] ?? null,
            ':langue'             => $data['langue'] ?? 'fr',
            ':image_couverture'   => $data['image_couverture'] ?? null,
            ':type'               => $data['type'] ?? 'livre',
            ':cote'               => $data['cote'] ?? null,
            ':localisation_defaut'=> $data['localisation_defaut'] ?? null,
            ':editeur_id'         => $data['editeur_id'] ?? null,
            ':id'                 => $id,
        ]);
    }

    public function softDelete(int $id): void
    {
        $this->pdo->prepare("UPDATE biblio_ouvrages SET deleted_at=NOW(), statut='archive' WHERE id=:id")
            ->execute([':id' => $id]);
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT o.*,
                    e.nom AS editeur_nom,
                    COUNT(DISTINCT ex.id) AS nb_exemplaires,
                    SUM(CASE WHEN ex.statut='disponible' THEN 1 ELSE 0 END) AS nb_disponibles
             FROM biblio_ouvrages o
             LEFT JOIN biblio_editeurs e  ON e.id = o.editeur_id
             LEFT JOIN biblio_exemplaires ex ON ex.ouvrage_id = o.id AND ex.deleted_at IS NULL
             WHERE o.id=:id AND o.deleted_at IS NULL
             GROUP BY o.id"
        );
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByIsbn(string $isbn): ?array
    {
        $st = $this->pdo->prepare(
            "SELECT * FROM biblio_ouvrages WHERE (isbn=:isbn OR isbn13=:isbn) AND deleted_at IS NULL LIMIT 1"
        );
        $st->execute([':isbn' => $isbn]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function search(OuvrageFiltersDTO $filters, int $etablissementId): array
    {
        $where  = ['o.deleted_at IS NULL', 'o.etablissement_id = :etab'];
        $params = [':etab' => $etablissementId];

        if ($filters->type !== null) {
            $where[]        = 'o.type = :type';
            $params[':type']= $filters->type;
        }
        if ($filters->langue !== null) {
            $where[]           = 'o.langue = :langue';
            $params[':langue'] = $filters->langue;
        }
        if ($filters->editeurId !== null) {
            $where[]              = 'o.editeur_id = :editeur_id';
            $params[':editeur_id']= $filters->editeurId;
        }
        if ($filters->anneeMin !== null) {
            $where[]               = 'o.annee_edition >= :annee_min';
            $params[':annee_min']  = $filters->anneeMin;
        }
        if ($filters->anneeMax !== null) {
            $where[]               = 'o.annee_edition <= :annee_max';
            $params[':annee_max']  = $filters->anneeMax;
        }

        $termeCond = '';
        if ($filters->terme !== null && $filters->terme !== '') {
            $termeCond           = "MATCH(o.titre, o.sous_titre, o.resume) AGAINST (:terme IN BOOLEAN MODE)";
            $where[]             = "($termeCond OR o.titre LIKE :terme_like)";
            $params[':terme']    = $filters->terme . '*';
            $params[':terme_like']= '%' . $filters->terme . '%';
        }

        if ($filters->categorieId !== null) {
            $where[]                = 'EXISTS (SELECT 1 FROM biblio_ouvrage_categories oc WHERE oc.ouvrage_id=o.id AND oc.categorie_id=:cat_id)';
            $params[':cat_id']      = $filters->categorieId;
        }
        if ($filters->auteurId !== null) {
            $where[]                = 'EXISTS (SELECT 1 FROM biblio_ouvrage_auteurs oa WHERE oa.ouvrage_id=o.id AND oa.auteur_id=:aut_id)';
            $params[':aut_id']      = $filters->auteurId;
        }

        $orderBy = match ($filters->tri) {
            'pertinence' => $termeCond !== '' ? "$termeCond DESC" : 'o.titre ASC',
            'date'       => 'o.annee_edition DESC',
            default      => 'o.titre ASC',
        };

        $whereStr = implode(' AND ', $where);
        $offset   = ($filters->page - 1) * $filters->perPage;

        $countSt = $this->pdo->prepare("SELECT COUNT(DISTINCT o.id) FROM biblio_ouvrages o WHERE $whereStr");
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $sql = "SELECT o.*,
                       e.nom AS editeur_nom,
                       COUNT(DISTINCT ex.id) AS nb_exemplaires,
                       SUM(CASE WHEN ex.statut='disponible' THEN 1 ELSE 0 END) AS nb_disponibles
                FROM biblio_ouvrages o
                LEFT JOIN biblio_editeurs e   ON e.id = o.editeur_id
                LEFT JOIN biblio_exemplaires ex ON ex.ouvrage_id = o.id AND ex.deleted_at IS NULL
                WHERE $whereStr
                GROUP BY o.id";

        if ($filters->disponibleSeulement) {
            $sql .= " HAVING nb_disponibles > 0";
        }
        $sql .= " ORDER BY $orderBy LIMIT :limit OFFSET :offset";

        $st = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) $st->bindValue($k, $v);
        $st->bindValue(':limit',  $filters->perPage, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset,           PDO::PARAM_INT);
        $st->execute();

        return [
            'data'      => $st->fetchAll(PDO::FETCH_ASSOC),
            'total'     => $total,
            'page'      => $filters->page,
            'per_page'  => $filters->perPage,
            'last_page' => (int)ceil($total / $filters->perPage),
        ];
    }

    public function findWithDetails(int $id): ?array
    {
        $ouvrage = $this->findById($id);
        if ($ouvrage === null) return null;

        $st = $this->pdo->prepare(
            "SELECT a.*, oa.ordre, oa.type_contribution
             FROM biblio_auteurs a
             JOIN biblio_ouvrage_auteurs oa ON oa.auteur_id = a.id
             WHERE oa.ouvrage_id = :id AND a.deleted_at IS NULL
             ORDER BY oa.ordre"
        );
        $st->execute([':id' => $id]);
        $ouvrage['auteurs'] = $st->fetchAll(PDO::FETCH_ASSOC);

        $st2 = $this->pdo->prepare(
            "SELECT c.* FROM biblio_categories c
             JOIN biblio_ouvrage_categories oc ON oc.categorie_id = c.id
             WHERE oc.ouvrage_id = :id AND c.deleted_at IS NULL"
        );
        $st2->execute([':id' => $id]);
        $ouvrage['categories'] = $st2->fetchAll(PDO::FETCH_ASSOC);

        $st3 = $this->pdo->prepare(
            "SELECT t.* FROM biblio_tags t
             JOIN biblio_ouvrage_tags ot ON ot.tag_id = t.id
             WHERE ot.ouvrage_id = :id"
        );
        $st3->execute([':id' => $id]);
        $ouvrage['tags'] = $st3->fetchAll(PDO::FETCH_ASSOC);

        return $ouvrage;
    }

    public function syncAuteurs(int $ouvrageId, array $auteurIds): void
    {
        $this->pdo->prepare("DELETE FROM biblio_ouvrage_auteurs WHERE ouvrage_id=:id")->execute([':id' => $ouvrageId]);
        $st = $this->pdo->prepare("INSERT INTO biblio_ouvrage_auteurs (ouvrage_id, auteur_id, ordre) VALUES (:oid, :aid, :ordre)");
        foreach ($auteurIds as $i => $aid) {
            $st->execute([':oid' => $ouvrageId, ':aid' => $aid, ':ordre' => $i + 1]);
        }
    }

    public function syncCategories(int $ouvrageId, array $categorieIds): void
    {
        $this->pdo->prepare("DELETE FROM biblio_ouvrage_categories WHERE ouvrage_id=:id")->execute([':id' => $ouvrageId]);
        $st = $this->pdo->prepare("INSERT INTO biblio_ouvrage_categories (ouvrage_id, categorie_id) VALUES (:oid, :cid)");
        foreach ($categorieIds as $cid) {
            $st->execute([':oid' => $ouvrageId, ':cid' => $cid]);
        }
    }

    public function syncTags(int $ouvrageId, array $tagIds): void
    {
        $this->pdo->prepare("DELETE FROM biblio_ouvrage_tags WHERE ouvrage_id=:id")->execute([':id' => $ouvrageId]);
        $st = $this->pdo->prepare("INSERT INTO biblio_ouvrage_tags (ouvrage_id, tag_id) VALUES (:oid, :tid)");
        foreach ($tagIds as $tid) {
            $st->execute([':oid' => $ouvrageId, ':tid' => $tid]);
        }
    }

    public function suggestions(string $terme, int $etablissementId, int $limit = 5): array
    {
        $st = $this->pdo->prepare(
            "SELECT id, titre, isbn13 FROM biblio_ouvrages
             WHERE deleted_at IS NULL AND etablissement_id=:etab
               AND (titre LIKE :q OR isbn13 LIKE :q)
             LIMIT :limit"
        );
        $st->bindValue(':etab',  $etablissementId, PDO::PARAM_INT);
        $st->bindValue(':q',     '%' . $terme . '%');
        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
