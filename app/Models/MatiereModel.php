<?php

namespace App\Models;

use Core\Model;

class MatiereModel extends Model
{
    protected string $table = 'matieres';
    protected bool $tenantScoped = true;

    public function findWithStats(): array
    {
        return $this->query(
            "SELECT m.*,
                    CONCAT(p.prenom, ' ', p.nom) AS responsable_nom,
                    COUNT(DISTINCT en.id)         AS nb_enseignements
             FROM `matieres` m
             LEFT JOIN `professeurs`  p  ON p.id  = m.responsable_id
             LEFT JOIN `enseignements` en ON en.matiere_id = m.id
             WHERE m.etablissement_id = ?
             GROUP BY m.id
             ORDER BY m.nom",
            [$this->tenantId()]
        );
    }

    public function findWithDetails(int $id): ?\stdClass
    {
        return $this->queryOne(
            "SELECT m.*,
                    CONCAT(p.prenom, ' ', p.nom) AS responsable_nom,
                    p.specialite                 AS responsable_specialite,
                    p.telephone                  AS responsable_telephone,
                    COUNT(DISTINCT en.id)         AS nb_enseignements
             FROM `matieres` m
             LEFT JOIN `professeurs`   p  ON p.id  = m.responsable_id
             LEFT JOIN `enseignements` en ON en.matiere_id = m.id
             WHERE m.id = ? AND m.etablissement_id = ?
             GROUP BY m.id",
            [$id, $this->tenantId()]
        );
    }

    public function findForSelect(): array
    {
        return $this->query(
            "SELECT id, CONCAT(nom, ' (coef. ', coefficient, ')') AS label
             FROM `matieres`
             WHERE etablissement_id = ?
             ORDER BY nom",
            [$this->tenantId()]
        );
    }

    public function nomExists(string $nom, int $excludeId = 0): bool
    {
        $row = $this->queryOne(
            "SELECT id FROM `matieres` WHERE nom = ? AND id != ? AND etablissement_id = ?",
            [$nom, $excludeId, $this->tenantId()]
        );
        return $row !== null;
    }
}
