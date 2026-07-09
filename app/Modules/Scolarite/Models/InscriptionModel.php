<?php

namespace App\Modules\Scolarite\Models;

use Core\Model;

/**
 * Modèle V2 — Inscription scolaire.
 *
 * Table : `inscriptions`  (créée via migration_inscriptions.sql)
 * Statuts : 'en_attente' | 'validee' | 'rejetee' | 'annulee'
 */
class InscriptionModel extends Model
{
    protected string $table      = 'inscriptions';
    protected string $primaryKey = 'id';

    public const STATUTS = ['en_attente', 'validee', 'rejetee', 'annulee'];

    public const STATUTS_ACTIFS = ['en_attente', 'validee'];

    public function findByEleve(int $eleveId): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE `eleve_id` = ? ORDER BY `annee_scolaire` DESC",
            [$eleveId]
        );
    }

    public function findByAnnee(string $anneeScolaire): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE `annee_scolaire` = ? ORDER BY `created_at` ASC",
            [$anneeScolaire]
        );
    }

    public function findEnAttente(): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE `statut` = 'en_attente' ORDER BY `created_at` ASC"
        );
    }

    /**
     * Inscription active (en_attente ou validee) d'un élève pour une année donnée.
     */
    public function findActiveByEleve(int $eleveId, string $anneeScolaire): ?object
    {
        $row = $this->queryOne(
            "SELECT * FROM `{$this->table}`
             WHERE `eleve_id` = ? AND `annee_scolaire` = ?
               AND `statut` IN ('en_attente', 'validee')
             LIMIT 1",
            [$eleveId, $anneeScolaire]
        );
        return $row ?: null;
    }

    public function findByStatut(string $statut): array
    {
        return $this->query(
            "SELECT * FROM `{$this->table}` WHERE `statut` = ? ORDER BY `created_at` DESC",
            [$statut]
        );
    }
}
