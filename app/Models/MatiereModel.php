<?php

namespace App\Models;

use Core\Model;

class MatiereModel extends Model
{
    protected string $table = 'matieres';
    protected bool $tenantScoped = true;

    public const CATEGORIES = [
        'litteraire'   => 'Littéraire',
        'scientifique' => 'Scientifique',
        'autre'        => 'Autre',
    ];

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

    /**
     * @param bool $sansConduite true : écarte la matière de Conduite (listes d'affectation d'enseignement et
     *                           d'emploi du temps — la Conduite n'a ni heures de cours ni enseignant affecté)
     */
    public function findForSelect(bool $sansConduite = false): array
    {
        return $this->query(
            "SELECT id, CONCAT(nom, ' (coef. ', coefficient, ')') AS label
             FROM `matieres`
             WHERE etablissement_id = ?" . ($sansConduite ? " AND est_conduite IS NULL" : "") . "
             ORDER BY nom",
            [$this->tenantId()]
        );
    }

    /** La matière est-elle la matière de Conduite (marqueur est_conduite) ? */
    public function estConduite(int $id): bool
    {
        $row = $this->queryOne("SELECT est_conduite FROM `matieres` WHERE id = ?", [$id]);
        return $row !== false && $row !== null && (int)$row->est_conduite === 1;
    }

    /** La Conduite (marqueur est_conduite = 1) de l'établissement courant, ou false. */
    public function findConduite(): object|false
    {
        return $this->queryOne(
            "SELECT * FROM `matieres` WHERE etablissement_id = ? AND est_conduite = 1 LIMIT 1",
            [$this->tenantId()]
        );
    }

    public function nomExists(string $nom, int $excludeId = 0): bool
    {
        $row = $this->queryOne(
            "SELECT id FROM `matieres` WHERE nom = ? AND id != ? AND etablissement_id = ?",
            [$nom, $excludeId, $this->tenantId()]
        );
        return $row !== false;
    }
}
