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
        ) ?: null;
    }

    /**
     * @param bool $sansConduite true : écarte la matière de Conduite (listes d'affectation d'enseignement et
     *                           d'emploi du temps — la Conduite n'a ni heures de cours ni enseignant affecté)
     */
    public function findForSelect(bool $sansConduite = false): array
    {
        return $this->query(
            "SELECT id, CONCAT(" . \App\Services\ProgrammeSql::nom('matieres') . ", ' (coef. ', coefficient, ')') AS label
             FROM `matieres`
             WHERE etablissement_id = ?" . ($sansConduite ? " AND est_conduite IS NULL" : "") . $this->filtreActive() . "
             ORDER BY nom",
            [$this->tenantId()]
        );
    }

    /** Filtre SQL « matière active » : vide tant que la migration T076 n'est pas appliquée. */
    private function filtreActive(): string
    {
        return \App\Services\ProgrammeSql::colonneMatiere('actif') ? ' AND actif = 1' : '';
    }

    /** La matière est-elle active ? (toujours vrai tant que la migration T076 n'est pas appliquée) */
    public function estActive(int $id): bool
    {
        if (!\App\Services\ProgrammeSql::colonneMatiere('actif')) {
            return true;
        }
        $row = $this->queryOne("SELECT actif FROM `matieres` WHERE id = ? AND etablissement_id = ?", [$id, $this->tenantId()]);
        return $row !== false && $row !== null && (int)$row->actif === 1;
    }

    /**
     * Raisons pour lesquelles la matière ne peut PAS être supprimée physiquement (elle est déjà utilisée), sous forme de libellés ;
     * liste vide = jamais utilisée. Les tables absentes sont ignorées.
     *
     * @return list<string>
     */
    public function usages(int $id): array
    {
        $sources = [
            'enseignement(s)'          => "SELECT COUNT(*) AS n FROM `enseignements` WHERE matiere_id = ?",
            'évaluation(s)'            => "SELECT COUNT(*) AS n FROM `evaluations` WHERE matiere_id = ?",
            'contrôle(s)'              => "SELECT COUNT(*) AS n FROM `controles` WHERE matiere_id = ?",
            'moyenne(s) enregistrée(s)' => "SELECT COUNT(*) AS n FROM `moyennes_matieres` WHERE matiere_id = ?",
            'programme(s) de classe'   => "SELECT COUNT(*) AS n FROM `classe_matieres` WHERE matiere_id = ?",
        ];
        $raisons = [];
        foreach ($sources as $libelle => $sql) {
            try {
                $row = $this->queryOne($sql, [$id]);
            } catch (\Throwable) {
                continue;   // table absente de cette installation
            }
            if ($row && (int)$row->n > 0) {
                $raisons[] = (int)$row->n . ' ' . $libelle;
            }
        }
        return $raisons;
    }

    /**
     * Classes de l'établissement qui (1) ont au moins un bulletin PUBLIÉ ou ARCHIVÉ, (2) ont des évaluations dans cette matière et
     * (3) n'ont PAS de ligne de programme pour elle : leur calcul en direct lit donc le coefficient PAR DÉFAUT de la matière. Changer ce
     * coefficient ferait diverger classements et analyses en direct des bulletins figés.
     *
     * @return list<array{id:int, nom:string, niveau:string}>
     */
    public function classesFigeesDependantDuDefaut(int $id): array
    {
        $horsProgramme = \App\Services\ProgrammeClasseService::disponible()
            ? ' AND NOT EXISTS (SELECT 1 FROM classe_matieres cm WHERE cm.classe_id = c.id AND cm.matiere_id = ?)' : '';
        $params = [$this->tenantId(), $id];
        if ($horsProgramme !== '') { $params[] = $id; }
        try {
            $rows = $this->query(
                "SELECT c.id, c.nom, c.niveau FROM classes c
                 WHERE c.etablissement_id = ?
                   AND EXISTS (SELECT 1 FROM evaluations ev WHERE ev.classe_id = c.id AND ev.matiere_id = ?)
                   AND EXISTS (SELECT 1 FROM bulletins_v2 b WHERE b.classe_id = c.id AND b.statut IN ('publie', 'archive'))" . $horsProgramme . "
                 ORDER BY c.niveau, c.nom",
                $params
            );
        } catch (\Throwable) {
            return [];   // tables d'évaluations/bulletins absentes de cette installation
        }
        return array_map(fn($r) => ['id' => (int)$r->id, 'nom' => (string)$r->nom, 'niveau' => (string)$r->niveau], $rows);
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
