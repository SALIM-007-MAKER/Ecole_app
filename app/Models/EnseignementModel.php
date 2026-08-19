<?php

namespace App\Models;

use Core\Model;

class EnseignementModel extends Model
{
    protected string $table = 'enseignements';
    protected bool $tenantScoped = true;

    public function findByClasseId(int $classeId): array
    {
        return $this->query(
            "SELECT e.*,
                    CONCAT(p.prenom, ' ', p.nom) AS prof_nom,
                    p.specialite                 AS prof_specialite,
                    m.nom                        AS matiere_nom,
                    m.coefficient,
                    m.volume_horaire
             FROM `enseignements` e
             INNER JOIN `professeurs` p ON p.id = e.professeur_id
             INNER JOIN `matieres`    m ON m.id = e.matiere_id
             WHERE e.classe_id = ? AND e.etablissement_id = ?
             ORDER BY m.nom, p.nom",
            [$classeId, $this->tenantId()]
        );
    }

    public function findByMatiereId(int $matiereId): array
    {
        return $this->query(
            "SELECT e.*,
                    CONCAT(p.prenom, ' ', p.nom) AS prof_nom,
                    p.specialite,
                    c.nom   AS classe_nom,
                    c.niveau AS classe_niveau
             FROM `enseignements` e
             INNER JOIN `professeurs` p ON p.id = e.professeur_id
             INNER JOIN `classes`     c ON c.id = e.classe_id
             WHERE e.matiere_id = ? AND e.etablissement_id = ?
             ORDER BY " . \App\Models\ClasseModel::ordreNiveauSql('c.niveau') . ", c.nom, p.nom",
            [$matiereId, $this->tenantId()]
        );
    }

    public function exists(int $profId, int $matId, int $classeId, string $annee): bool
    {
        $row = $this->queryOne(
            "SELECT id FROM `enseignements`
             WHERE professeur_id = ? AND matiere_id = ? AND classe_id = ? AND annee_scolaire = ? AND etablissement_id = ?",
            [$profId, $matId, $classeId, $annee, $this->tenantId()]
        );
        return $row !== false;
    }

    public function findByProfesseurId(int $profId, ?string $annee = null): array
    {
        $where  = $annee ? 'AND e.annee_scolaire = ?' : '';
        $params = $annee ? [$profId, $annee, $this->tenantId()] : [$profId, $this->tenantId()];

        return $this->query(
            "SELECT e.*,
                    m.nom            AS matiere_nom,
                    m.coefficient,
                    m.volume_horaire,
                    c.nom            AS classe_nom,
                    c.niveau         AS classe_niveau,
                    COUNT(DISTINCT el.id) AS nb_eleves
             FROM `enseignements` e
             INNER JOIN `matieres` m  ON m.id  = e.matiere_id
             INNER JOIN `classes`  c  ON c.id  = e.classe_id
             LEFT JOIN  `eleves`   el ON el.classe_id = c.id
             WHERE e.professeur_id = ? $where AND e.etablissement_id = ?
             GROUP BY e.id
             ORDER BY e.annee_scolaire DESC, " . \App\Models\ClasseModel::ordreNiveauSql('c.niveau') . ", c.nom, m.nom",
            $params
        );
    }

    public function findHistoriqueByProfesseurId(int $profId): array
    {
        $rows = $this->findByProfesseurId($profId);
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r->annee_scolaire][] = $r;
        }
        return $grouped;
    }
}
