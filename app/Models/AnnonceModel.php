<?php

namespace App\Models;

use Core\Model;

class AnnonceModel extends Model
{
    protected string $table = 'annonces';

    const AUDIENCES = [
        'tous'        => ['label' => 'Tous',        'icon' => 'people-fill',      'color' => 'primary'],
        'parents'     => ['label' => 'Parents',     'icon' => 'person-heart',     'color' => 'secondary'],
        'eleves'      => ['label' => 'Élèves',      'icon' => 'mortarboard',      'color' => 'info'],
        'enseignants' => ['label' => 'Enseignants', 'icon' => 'person-workspace', 'color' => 'warning'],
    ];

    public function findPubliees(string $roleAudience = '', string $q = '', string $audFilter = ''): array
    {
        $where  = ['a.actif = 1'];
        $params = [];

        if ($roleAudience && $roleAudience !== 'tous') {
            $where[]  = "(a.audience = 'tous' OR a.audience = ?)";
            $params[] = $roleAudience;
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

        $sql = "SELECT a.*, CONCAT(COALESCE(u.prenom,''), ' ', COALESCE(u.nom,'')) AS auteur_nom
                FROM `annonces` a
                LEFT JOIN `users` u ON u.id = a.publie_par
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.published_at DESC";

        return $this->query($sql, $params);
    }

    public function findAllWithAuteur(): array
    {
        return $this->query(
            "SELECT a.*, CONCAT(COALESCE(u.prenom,''), ' ', COALESCE(u.nom,'')) AS auteur_nom
             FROM `annonces` a
             LEFT JOIN `users` u ON u.id = a.publie_par
             ORDER BY a.published_at DESC"
        );
    }

    public function findWithAuteur(int $id): object|false
    {
        return $this->queryOne(
            "SELECT a.*, CONCAT(COALESCE(u.prenom,''), ' ', COALESCE(u.nom,'')) AS auteur_nom
             FROM `annonces` a
             LEFT JOIN `users` u ON u.id = a.publie_par
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
