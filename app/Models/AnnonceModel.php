<?php

namespace App\Models;

use Core\Model;

class AnnonceModel extends Model
{
    protected string $table = 'annonces';

    const AUDIENCES = [
        'tous'         => ['label' => 'Tous',                'icon' => 'people-fill',      'color' => 'primary'],
        'parents'      => ['label' => 'Parents',              'icon' => 'person-heart',     'color' => 'secondary'],
        'eleves'       => ['label' => 'Élèves',               'icon' => 'mortarboard',      'color' => 'info'],
        'enseignants'  => ['label' => 'Enseignants',          'icon' => 'person-workspace', 'color' => 'warning'],
        'classe'       => ['label' => 'Une classe',           'icon' => 'building',         'color' => 'success'],
        'utilisateurs' => ['label' => 'Utilisateurs précis',  'icon' => 'person-check',     'color' => 'dark'],
    ];

    /**
     * @param int[] $classeIds  Classes de l'utilisateur courant (l'élève lui-même,
     *                          ou celles de ses enfants pour un parent) — utilisées
     *                          pour la visibilité des annonces audience='classe'.
     */
    public function findPubliees(
        string $roleAudience = '',
        string $q = '',
        string $audFilter = '',
        ?int $userId = null,
        array $classeIds = []
    ): array {
        $where  = ['a.actif = 1'];
        $params = [];

        if ($roleAudience && $roleAudience !== 'tous') {
            $visibility = ["a.audience = 'tous'", "a.audience = ?"];
            $params[]   = $roleAudience;

            if (!empty($classeIds)) {
                $in = implode(',', array_fill(0, count($classeIds), '?'));
                $visibility[] = "(a.audience = 'classe' AND a.classe_id IN ({$in}))";
                array_push($params, ...$classeIds);
            }
            if ($userId !== null) {
                $visibility[] = "(a.audience = 'utilisateurs' AND JSON_CONTAINS(a.destinataires_ids, ?))";
                $params[]     = (string)$userId;
            }

            $where[] = '(' . implode(' OR ', $visibility) . ')';
        }

        if ($audFilter !== '') {
            $where[]  = "a.audience = ?";
            $params[] = $audFilter;
        }

        if ($q !== '') {
            $where[]  = "(a.titre LIKE ? OR a.contenu LIKE ?)";
            $like     = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT a.*, CONCAT(COALESCE(u.prenom,''), ' ', COALESCE(u.nom,'')) AS auteur_nom,
                       c.nom AS classe_nom
                FROM `annonces` a
                LEFT JOIN `users` u ON u.id = a.publie_par
                LEFT JOIN `classes` c ON c.id = a.classe_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.published_at DESC";

        return $this->query($sql, $params);
    }

    public function findAllWithAuteur(): array
    {
        return $this->query(
            "SELECT a.*, CONCAT(COALESCE(u.prenom,''), ' ', COALESCE(u.nom,'')) AS auteur_nom,
                    c.nom AS classe_nom
             FROM `annonces` a
             LEFT JOIN `users` u ON u.id = a.publie_par
             LEFT JOIN `classes` c ON c.id = a.classe_id
             ORDER BY a.published_at DESC"
        );
    }

    public function findWithAuteur(int $id): object|false
    {
        return $this->queryOne(
            "SELECT a.*, CONCAT(COALESCE(u.prenom,''), ' ', COALESCE(u.nom,'')) AS auteur_nom,
                    c.nom AS classe_nom
             FROM `annonces` a
             LEFT JOIN `users` u ON u.id = a.publie_par
             LEFT JOIN `classes` c ON c.id = a.classe_id
             WHERE a.id = ?",
            [$id]
        );
    }

    public function countRecentes(int $days = 7): int
    {
        $r = $this->queryOne(
            'SELECT COUNT(*) AS n FROM `annonces`
             WHERE `actif` = 1 AND `published_at` >= DATE_SUB(NOW(), INTERVAL ? DAY)',
            [$days]
        );
        return (int)($r?->n ?? 0);
    }
}
