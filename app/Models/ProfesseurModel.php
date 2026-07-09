<?php

namespace App\Models;

use Core\Model;

class ProfesseurModel extends Model
{
    protected string $table = 'professeurs';
    protected bool $tenantScoped = true;

    public const GRADES = [
        'Professeur certifié (PES)',
        "Professeur de l'enseignement moyen (PEM)",
        "Professeur de l'enseignement primaire (PEP)",
        'Maître formateur',
        'Maître assistant',
        'Professeur contractuel',
        'Vacataire',
    ];

    // ─── Requêtes de sélection ───────────────────────────────────────────────────

    public function findForSelect(): array
    {
        return $this->query(
            "SELECT id,
                    CONCAT(prenom, ' ', nom, ' (', specialite, ')') AS label
             FROM `professeurs`
             WHERE actif = 1 AND etablissement_id = ?
             ORDER BY nom, prenom",
            [$this->tenantId()]
        );
    }

    public function findWithStats(): array
    {
        return $this->query(
            "SELECT p.*,
                    COUNT(DISTINCT en.id)         AS nb_enseignements,
                    COUNT(DISTINCT en.classe_id)  AS nb_classes,
                    COUNT(DISTINCT en.matiere_id) AS nb_matieres
             FROM `professeurs` p
             LEFT JOIN `enseignements` en ON en.professeur_id = p.id
             WHERE p.etablissement_id = ?
             GROUP BY p.id
             ORDER BY p.nom, p.prenom",
            [$this->tenantId()]
        );
    }

    public function findWithDetails(int $id): ?\stdClass
    {
        return $this->queryOne(
            "SELECT p.*,
                    u.email            AS user_email,
                    u.role             AS user_role,
                    u.derniere_connexion,
                    COUNT(DISTINCT en.id)         AS nb_enseignements,
                    COUNT(DISTINCT en.classe_id)  AS nb_classes,
                    COUNT(DISTINCT en.matiere_id) AS nb_matieres
             FROM `professeurs` p
             LEFT JOIN `users`         u  ON u.id  = p.user_id
             LEFT JOIN `enseignements` en ON en.professeur_id = p.id
             WHERE p.id = ? AND p.etablissement_id = ?
             GROUP BY p.id",
            [$id, $this->tenantId()]
        );
    }

    // ─── Pagination et filtres ───────────────────────────────────────────────────

    public function paginateFiltered(int $page, int $perPage, array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $countRow = $this->queryOne(
            "SELECT COUNT(*) AS n FROM `professeurs` p $where",
            $params
        );
        $total = $countRow ? (int)$countRow->n : 0;

        $offset = max(0, ($page - 1) * $perPage);
        $data   = $this->query(
            "SELECT p.*,
                    COUNT(DISTINCT en.classe_id)  AS nb_classes,
                    COUNT(DISTINCT en.matiere_id) AS nb_matieres
             FROM `professeurs` p
             LEFT JOIN `enseignements` en ON en.professeur_id = p.id
             $where
             GROUP BY p.id
             ORDER BY p.nom, p.prenom
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return [
            'data'       => $data,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
        ];
    }

    public function buildWhere(array $filters): array
    {
        $conditions = ["p.etablissement_id = ?"];
        $params     = [$this->tenantId()];

        if (!empty($filters['q'])) {
            $conditions[] = "(p.nom LIKE ? OR p.prenom LIKE ? OR p.specialite LIKE ? OR p.email LIKE ?)";
            $q = '%' . $filters['q'] . '%';
            array_push($params, $q, $q, $q, $q);
        }

        if (!empty($filters['grade'])) {
            $conditions[] = 'p.grade = ?';
            $params[]     = $filters['grade'];
        }

        if (isset($filters['actif']) && $filters['actif'] !== '') {
            $conditions[] = 'p.actif = ?';
            $params[]     = (int)$filters['actif'];
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        return [$where, $params];
    }

    // ─── Contraintes d'intégrité ─────────────────────────────────────────────────

    public function emailExists(string $email, int $excludeId = 0): bool
    {
        $row = $this->queryOne(
            "SELECT id FROM `professeurs` WHERE email = ? AND id != ? AND etablissement_id = ?",
            [$email, $excludeId, $this->tenantId()]
        );
        return $row !== null;
    }

    // ─── Dashboard enseignant ────────────────────────────────────────────────────

    public function findByUserId(int $userId): ?\stdClass
    {
        $row = $this->queryOne(
            "SELECT p.*,
                    COUNT(DISTINCT en.classe_id)  AS nb_classes,
                    COUNT(DISTINCT en.matiere_id) AS nb_matieres
             FROM `professeurs` p
             LEFT JOIN `enseignements` en ON en.professeur_id = p.id
             WHERE p.user_id = ? AND p.etablissement_id = ?
             GROUP BY p.id",
            [$userId, $this->tenantId()]
        );
        return $row ?: null;
    }

    public function countElevesForUser(int $userId, string $annee): int
    {
        $row = $this->queryOne(
            "SELECT COUNT(DISTINCT el.id) AS n
             FROM `enseignements`  en
             INNER JOIN `professeurs` p  ON p.id       = en.professeur_id AND p.user_id = ?
             INNER JOIN `eleves`     el ON el.classe_id = en.classe_id
             WHERE en.annee_scolaire = ? AND en.etablissement_id = ?",
            [$userId, $annee, $this->tenantId()]
        );
        return $row ? (int)$row->n : 0;
    }

    // ─── Utilitaire ─────────────────────────────────────────────────────────────

    public function fullName(\stdClass $prof): string
    {
        return trim($prof->prenom . ' ' . $prof->nom);
    }
}
