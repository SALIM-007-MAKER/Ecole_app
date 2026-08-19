<?php

namespace App\Modules\Academique\Repositories;

use Core\Database;

class AppreciationMatiereRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /** Texte d'appréciation saisi par le professeur, ou null si aucune saisie. */
    public function find(int $eleveId, int $matiereId, int $periodeId): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT texte FROM appreciations_matiere
              WHERE eleve_id = :e AND matiere_id = :m AND periode_id = :p"
        );
        $stmt->execute([':e' => $eleveId, ':m' => $matiereId, ':p' => $periodeId]);
        $texte = $stmt->fetchColumn();
        return $texte !== false && $texte !== null && $texte !== '' ? $texte : null;
    }

    /**
     * Élèves d'une classe pour une matière/période donnée, avec leur
     * appréciation existante le cas échéant (LEFT JOIN — même principe que
     * NoteRepository::listElevesAvecNotes()).
     */
    public function listElevesAvecAppreciation(int $classeId, int $matiereId, int $periodeId): array
    {
        $sql = "SELECT e.id AS eleve_id, e.nom, e.prenom, e.matricule,
                       a.id AS appreciation_id, a.texte, a.updated_at
                FROM eleves e
                LEFT JOIN appreciations_matiere a
                    ON a.eleve_id = e.id
                   AND a.matiere_id = :matiere_id
                   AND a.periode_id = :periode_id
                WHERE e.classe_id = :classe_id
                ORDER BY e.nom, e.prenom";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':classe_id'  => $classeId,
            ':matiere_id' => $matiereId,
            ':periode_id' => $periodeId,
        ]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    /** Crée ou met à jour l'appréciation d'un élève. Retourne ['id' => int, 'created' => bool]. */
    public function upsert(
        int     $eleveId,
        int     $matiereId,
        int     $periodeId,
        int     $classeId,
        int     $etablissementId,
        ?string $texte,
        ?int    $professeurId,
        int     $userId
    ): array {
        $existing = $this->pdo->prepare(
            "SELECT id FROM appreciations_matiere
              WHERE eleve_id = :e AND matiere_id = :m AND periode_id = :p"
        );
        $existing->execute([':e' => $eleveId, ':m' => $matiereId, ':p' => $periodeId]);
        $id = $existing->fetchColumn();

        if ($id !== false) {
            $stmt = $this->pdo->prepare(
                "UPDATE appreciations_matiere
                    SET texte = :texte, professeur_id = :prof
                  WHERE id = :id"
            );
            $stmt->execute([':texte' => $texte, ':prof' => $professeurId, ':id' => $id]);
            return ['id' => (int)$id, 'created' => false];
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO appreciations_matiere
                (eleve_id, matiere_id, periode_id, classe_id, professeur_id, texte, etablissement_id, created_by)
             VALUES (:eleve_id, :matiere_id, :periode_id, :classe_id, :prof, :texte, :etab, :created_by)"
        );
        $stmt->execute([
            ':eleve_id'   => $eleveId,
            ':matiere_id' => $matiereId,
            ':periode_id' => $periodeId,
            ':classe_id'  => $classeId,
            ':prof'       => $professeurId,
            ':texte'      => $texte,
            ':etab'       => $etablissementId,
            ':created_by' => $userId,
        ]);
        return ['id' => (int)$this->pdo->lastInsertId(), 'created' => true];
    }
}
