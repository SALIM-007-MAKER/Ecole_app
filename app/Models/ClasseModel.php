<?php

namespace App\Models;

use Core\Model;

class ClasseModel extends Model
{
    protected string $table = 'classes';
    protected bool $tenantScoped = true;

    public const NIVEAUX = [
        'Primaire'    => ['1AP', '2AP', '3AP', '4AP', '5AP'],
        'Moyen (CEM)' => ['1AM', '2AM', '3AM', '4AM'],
        'Lycée'       => ['1AS', '2AS', '3AS'],
    ];

    public function findWithStats(): array
    {
        return $this->query(
            "SELECT c.*,
                    COUNT(DISTINCT e.id)  AS nb_eleves,
                    COUNT(DISTINCT en.id) AS nb_enseignements
             FROM `classes` c
             LEFT JOIN `eleves`        e  ON e.classe_id  = c.id
             LEFT JOIN `enseignements` en ON en.classe_id = c.id
             WHERE c.etablissement_id = ?
             GROUP BY c.id
             ORDER BY c.niveau, c.nom",
            [$this->tenantId()]
        );
    }

    public function findForSelect(): array
    {
        return $this->query(
            "SELECT id, CONCAT(niveau, ' — ', nom) AS label
             FROM `classes`
             WHERE etablissement_id = ?
             ORDER BY niveau, nom",
            [$this->tenantId()]
        );
    }

    public function findWithDetails(int $id): ?\stdClass
    {
        return $this->queryOne(
            "SELECT c.*,
                    COUNT(DISTINCT e.id)  AS nb_eleves,
                    COUNT(DISTINCT en.id) AS nb_enseignements
             FROM `classes` c
             LEFT JOIN `eleves`        e  ON e.classe_id  = c.id
             LEFT JOIN `enseignements` en ON en.classe_id = c.id
             WHERE c.id = ? AND c.etablissement_id = ?
             GROUP BY c.id",
            [$id, $this->tenantId()]
        );
    }

    public function getEleves(int $classeId): array
    {
        return $this->query(
            "SELECT e.id, e.nom, e.prenom, e.matricule, e.sexe, e.actif
             FROM `eleves` e
             WHERE e.classe_id = ? AND e.etablissement_id = ?
             ORDER BY e.nom, e.prenom",
            [$classeId, $this->tenantId()]
        );
    }

    public function getElevesDisponibles(int $classeId): array
    {
        return $this->query(
            "SELECT e.id, e.nom, e.prenom, e.matricule, e.sexe,
                    c.nom    AS classe_actuelle,
                    c.niveau AS niveau_actuel
             FROM `eleves` e
             LEFT JOIN `classes` c ON c.id = e.classe_id
             WHERE (e.classe_id IS NULL OR e.classe_id != ?) AND e.etablissement_id = ?
             ORDER BY e.nom, e.prenom",
            [$classeId, $this->tenantId()]
        );
    }

    public function nomComplet(\stdClass $classe): string
    {
        return $classe->niveau . ' — ' . $classe->nom;
    }
}
