<?php

declare(strict_types=1);

namespace App\Modules\Academique\Services;

use Core\Database;
use PDO;

/**
 * Périmètre pédagogique d'un enseignant : ce qu'il a le droit de consulter, d'après ses
 * AFFECTATIONS RÉELLES, calculées côté serveur.
 *
 * Source de vérité : table `enseignements` (professeur_id, classe_id, matiere_id,
 * annee_scolaire), reliée au compte par `professeurs.user_id`. Aucune valeur
 * (classe, matière, année) fournie par le navigateur n'est jamais crue : les appelants
 * transmettent l'identifiant demandé et ce service répond oui/non d'après la base.
 *
 * L'année scolaire est toujours celle de la période/évaluation concernée (jamais l'année
 * civile courante). Tout est filtré sur l'établissement (colonnes etablissement_id) ;
 * un compte sans fiche `professeurs` liée n'a aucun périmètre (deny-by-default).
 */
final class TeacherScope
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    /** Année scolaire d'une période (null si la période n'existe pas). */
    public function anneeDeLaPeriode(int $periodeId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT annee_scolaire FROM `periodes_scolaires` WHERE id = ? LIMIT 1");
        $stmt->execute([$periodeId]);
        $annee = $stmt->fetchColumn();
        return $annee === false ? null : (string)$annee;
    }

    /** Id de la fiche professeur liée au compte (null si aucune, ou fiche inactive). */
    public function professeurId(int $userId, int $etablissementId): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM `professeurs` WHERE user_id = ? AND etablissement_id = ? AND actif = 1 LIMIT 1"
        );
        $stmt->execute([$userId, $etablissementId]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int)$id;
    }

    /** @return int[] classes où l'enseignant a au moins une affectation cette année-là */
    public function classeIds(int $userId, int $etablissementId, string $annee): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT en.classe_id
               FROM `enseignements` en
               JOIN `professeurs` p ON p.id = en.professeur_id
              WHERE p.user_id = ? AND p.actif = 1 AND p.etablissement_id = ?
                AND en.etablissement_id = ? AND en.annee_scolaire = ?"
        );
        $stmt->execute([$userId, $etablissementId, $etablissementId, $annee]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return int[] matières que l'enseignant enseigne dans cette classe cette année-là */
    public function matiereIds(int $userId, int $etablissementId, int $classeId, string $annee): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT en.matiere_id
               FROM `enseignements` en
               JOIN `professeurs` p ON p.id = en.professeur_id
              WHERE p.user_id = ? AND p.actif = 1 AND p.etablissement_id = ?
                AND en.etablissement_id = ? AND en.classe_id = ? AND en.annee_scolaire = ?"
        );
        $stmt->execute([$userId, $etablissementId, $etablissementId, $classeId, $annee]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function enseigneClasse(int $userId, int $etablissementId, int $classeId, string $annee): bool
    {
        return in_array($classeId, $this->classeIds($userId, $etablissementId, $annee), true);
    }

    /** L'élève est-il dans une classe où l'enseignant est affecté cette année-là ? */
    public function enseigneEleve(int $userId, int $etablissementId, int $eleveId, string $annee): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1
               FROM `enseignements` en
               JOIN `professeurs` p ON p.id = en.professeur_id
               JOIN `eleves` el     ON el.classe_id = en.classe_id
              WHERE p.user_id = ? AND p.actif = 1 AND p.etablissement_id = ?
                AND en.etablissement_id = ? AND en.annee_scolaire = ?
                AND el.id = ? AND el.etablissement_id = ?
              LIMIT 1"
        );
        $stmt->execute([$userId, $etablissementId, $etablissementId, $annee, $eleveId, $etablissementId]);
        return $stmt->fetchColumn() !== false;
    }
}
