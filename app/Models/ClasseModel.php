<?php

namespace App\Models;

use Core\Model;

class ClasseModel extends Model
{
    protected string $table = 'classes';
    protected bool $tenantScoped = true;

    // Nomenclature officielle du système éducatif nigérien (Niger) — voir
    // database/migrations/T019_niveaux_nigeriens.php pour la migration des
    // données existantes vers cette nomenclature.
    public const NIVEAUX = [
        'Préscolaire' => ['PS', 'MS', 'GS'],
        'Primaire'    => ['CI', 'CP', 'CE1', 'CE2', 'CM1', 'CM2'],
        'Collège'     => ['6e', '5e', '4e', '3e'],
        'Lycée'       => ['Seconde', 'Première', 'Terminale'],
    ];

    /**
     * Ordre pédagogique à plat (Préscolaire → Lycée). Nécessaire car un tri
     * SQL alphabétique classique (`ORDER BY niveau`) ne correspond PLUS à
     * l'ordre pédagogique avec cette nomenclature (ex: 'CE1' < 'CI'
     * alphabétiquement, mais CI précède CE1 pédagogiquement ; '3e' < '6e'
     * alphabétiquement, inversé par rapport à l'ordre réel). Toute requête
     * SQL qui trie des classes par niveau doit utiliser {@see ordreNiveauSql()}
     * au lieu d'un simple `ORDER BY {colonne}` — voir
     * NIGER_EDUCATION_SYSTEM_STANDARDIZATION_REPORT.md.
     */
    public const NIVEAUX_ORDRE = [
        'PS', 'MS', 'GS',
        'CI', 'CP', 'CE1', 'CE2', 'CM1', 'CM2',
        '6e', '5e', '4e', '3e',
        'Seconde', 'Première', 'Terminale',
    ];

    /** Fragment SQL `FIELD(...)` imposant l'ordre pédagogique plutôt qu'alphabétique pour un ORDER BY sur la colonne niveau donnée (ex: 'c.niveau'). */
    public static function ordreNiveauSql(string $colonne = 'niveau'): string
    {
        $valeurs = implode(',', array_map(fn(string $n) => "'" . addslashes($n) . "'", self::NIVEAUX_ORDRE));
        return "FIELD({$colonne},{$valeurs})";
    }

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
             ORDER BY " . self::ordreNiveauSql('c.niveau') . ", c.nom",
            [$this->tenantId()]
        );
    }

    public function findForSelect(): array
    {
        return $this->query(
            "SELECT id, CONCAT(niveau, ' — ', nom) AS label
             FROM `classes`
             WHERE etablissement_id = ?
             ORDER BY " . self::ordreNiveauSql() . ", nom",
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
