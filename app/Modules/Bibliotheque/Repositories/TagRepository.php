<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Repositories;

use Core\Database;
use PDO;

class TagRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function findOrCreate(string $nom, int $etablissementId): int
    {
        $nom = strtolower(trim($nom));
        $st  = $this->pdo->prepare(
            "SELECT id FROM biblio_tags WHERE nom=:nom AND etablissement_id=:etab LIMIT 1"
        );
        $st->execute([':nom' => $nom, ':etab' => $etablissementId]);
        $id = $st->fetchColumn();
        if ($id) return (int)$id;

        $st2 = $this->pdo->prepare("INSERT INTO biblio_tags (nom, etablissement_id) VALUES (:nom, :etab)");
        $st2->execute([':nom' => $nom, ':etab' => $etablissementId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function findAll(int $etablissementId): array
    {
        $st = $this->pdo->prepare(
            "SELECT t.*, COUNT(ot.ouvrage_id) AS nb_ouvrages
             FROM biblio_tags t
             LEFT JOIN biblio_ouvrage_tags ot ON ot.tag_id = t.id
             WHERE t.etablissement_id=:etab GROUP BY t.id ORDER BY t.nom"
        );
        $st->execute([':etab' => $etablissementId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(array $data): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO biblio_tags (nom, couleur, etablissement_id) VALUES (:nom, :couleur, :etab)
             ON DUPLICATE KEY UPDATE couleur=:couleur"
        );
        $st->execute([
            ':nom'    => strtolower(trim($data['nom'])),
            ':couleur'=> $data['couleur'] ?? null,
            ':etab'   => $data['etablissement_id'] ?? 1,
        ]);
        return (int)$this->pdo->lastInsertId();
    }
}
