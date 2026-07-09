<?php

namespace App\Modules\Scolarite\Models;

use Core\Model;

/**
 * Modèle V2 — Élève.
 *
 * Étend Core\Model pour les opérations génériques sur la table `eleves`.
 * Les requêtes complexes (jointures multi-tables) sont déléguées à EleveRepository.
 */
class EleveModel extends Model
{
    protected string $table      = 'eleves';
    protected string $primaryKey = 'id';

    // TODO: méthodes métier V2 (archiver, réinscrire, fusionner doublons)

    public function findActifs(): array
    {
        // TODO: filtrer sur statut = 'actif' (colonne V2 à ajouter)
        return $this->findAll('nom');
    }

    public function findByClasse(int $classeId): array
    {
        return $this->findBy('classe_id', $classeId);
    }

    public function findByFamille(int $familleId): array
    {
        // TODO: table familles_eleves (relation N-N) en V2
        return [];
    }

    public function archiver(int $id): bool
    {
        // TODO: passer statut = 'archive' plutôt que supprimer
        return false;
    }
}
