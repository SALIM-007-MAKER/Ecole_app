<?php

namespace App\Modules\Scolarite\Models;

use Core\Model;

/**
 * Modèle V2 — Classe scolaire.
 */
class ClasseModel extends Model
{
    protected string $table      = 'classes';
    protected string $primaryKey = 'id';

    public function findByAnnee(string $anneeScolaire): array
    {
        return $this->query(
            'SELECT * FROM `classes` WHERE `annee_scolaire` = ? ORDER BY ' . \App\Models\ClasseModel::ordreNiveauSql() . ', `nom`',
            [$anneeScolaire]
        );
    }

    public function countEleves(int $classeId): int
    {
        return $this->count('classe_id = ?', [$classeId]);
    }

    // TODO: findWithEffectif(), findWithEnseignants() via ClasseRepository
}
