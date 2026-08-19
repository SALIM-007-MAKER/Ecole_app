<?php

namespace App\Models;

use Core\Model;

class ReinscriptionModel extends Model
{
    protected string $table = 'reinscriptions';
    protected bool $tenantScoped = true;

    /** Années scolaires ayant au moins une classe, les plus récentes d'abord. */
    public function anneesDisponibles(): array
    {
        $rows = $this->query(
            "SELECT DISTINCT annee_scolaire FROM classes WHERE etablissement_id = ? ORDER BY annee_scolaire DESC",
            [$this->tenantId()]
        );
        return array_map(fn($r) => $r->annee_scolaire, $rows);
    }

    /** Classes d'une année, avec leur effectif actif. */
    public function classesAvecEffectif(string $annee): array
    {
        return $this->query(
            "SELECT c.*, COUNT(e.id) AS nb_eleves
             FROM `classes` c
             LEFT JOIN `eleves` e ON e.classe_id = c.id AND e.actif = 1
             WHERE c.etablissement_id = ? AND c.annee_scolaire = ?
             GROUP BY c.id
             ORDER BY " . \App\Models\ClasseModel::ordreNiveauSql('c.niveau') . ", c.nom",
            [$this->tenantId(), $annee]
        );
    }

    /**
     * Suggère la classe de destination « naturelle » pour une classe source :
     * même nom, niveau suivant dans l'ordre pédagogique. Retourne null si le
     * niveau source est le dernier du cursus (ex: Terminale) — l'élève est
     * alors un sortant par défaut — ou si aucune classe destination
     * correspondante n'existe encore.
     */
    public function suggestDestination(object $classeSource, array $classesDestination): ?object
    {
        $ordre = \App\Models\ClasseModel::NIVEAUX_ORDRE;
        $pos   = array_search($classeSource->niveau, $ordre, true);
        if ($pos === false || $pos + 1 >= count($ordre)) {
            return null;
        }
        $niveauSuivant = $ordre[$pos + 1];
        foreach ($classesDestination as $c) {
            if ($c->niveau === $niveauSuivant && $c->nom === $classeSource->nom) {
                return $c;
            }
        }
        return null;
    }

    /** Nombre d'élèves déjà traités pour ce couple d'années (détection de ré-exécution). */
    public function dejaTraites(string $anneeSource, string $anneeDestination): int
    {
        return (int)($this->queryOne(
            "SELECT COUNT(*) AS n FROM reinscriptions
             WHERE etablissement_id = ? AND annee_source = ? AND annee_destination = ?",
            [$this->tenantId(), $anneeSource, $anneeDestination]
        )->n ?? 0);
    }

    /**
     * Exécute la campagne : déplace chaque élève actif des classes sources
     * mappées vers leur classe destination (ou le marque sortant), journalise
     * chaque mouvement, respecte la capacité de la classe destination.
     *
     * @param array $mapping [classeSourceId => ['destination_id' => int|null, 'sortant' => bool]]
     * @return array{deplaces:int, sortants:int, ignores:array} résumé d'exécution
     */
    public function executer(array $mapping, string $anneeSource, string $anneeDestination, int $userId): array
    {
        $tenantId = $this->tenantId();
        $deplaces = 0;
        $sortants = 0;
        $ignores  = [];

        // Capacité restante par classe destination, pré-calculée une fois.
        $capaciteRestante = [];

        foreach ($mapping as $classeSourceId => $regle) {
            $classeSourceId = (int)$classeSourceId;
            $destinationId  = $regle['destination_id'] ?? null;
            $estSortant     = !empty($regle['sortant']) || $destinationId === null;

            $eleves = $this->query(
                "SELECT id FROM eleves WHERE classe_id = ? AND etablissement_id = ? AND actif = 1",
                [$classeSourceId, $tenantId]
            );

            if (!$estSortant && $destinationId !== null && !isset($capaciteRestante[$destinationId])) {
                $classe = $this->queryOne(
                    "SELECT max_eleves, (SELECT COUNT(*) FROM eleves WHERE classe_id = c.id AND actif = 1) AS occupes
                     FROM classes c WHERE c.id = ?",
                    [$destinationId]
                );
                $capaciteRestante[$destinationId] = $classe
                    ? (((int)$classe->max_eleves > 0) ? (int)$classe->max_eleves - (int)$classe->occupes : PHP_INT_MAX)
                    : 0;
            }

            foreach ($eleves as $eleve) {
                if ($estSortant) {
                    $this->execute(
                        "UPDATE eleves SET classe_id = NULL, actif = 0, updated_at = NOW() WHERE id = ?",
                        [$eleve->id]
                    );
                    $this->logMouvement($eleve->id, $classeSourceId, null, $anneeSource, $anneeDestination, true, $userId);
                    $sortants++;
                    continue;
                }

                if ($capaciteRestante[$destinationId] <= 0) {
                    $ignores[] = ['eleve_id' => $eleve->id, 'raison' => 'Capacité de la classe destination atteinte'];
                    continue;
                }

                $this->execute(
                    "UPDATE eleves SET classe_id = ?, updated_at = NOW() WHERE id = ?",
                    [$destinationId, $eleve->id]
                );
                $this->logMouvement($eleve->id, $classeSourceId, $destinationId, $anneeSource, $anneeDestination, false, $userId);
                $capaciteRestante[$destinationId]--;
                $deplaces++;
            }
        }

        return ['deplaces' => $deplaces, 'sortants' => $sortants, 'ignores' => $ignores];
    }

    private function logMouvement(
        int $eleveId, int $classeSourceId, ?int $classeDestinationId,
        string $anneeSource, string $anneeDestination, bool $sortant, int $userId
    ): void {
        $this->execute(
            "INSERT INTO reinscriptions
                (etablissement_id, eleve_id, classe_source_id, classe_destination_id, annee_source, annee_destination, sortant, executee_par, created_at)
             VALUES (?,?,?,?,?,?,?,?,NOW())",
            [$this->tenantId(), $eleveId, $classeSourceId, $classeDestinationId, $anneeSource, $anneeDestination, $sortant ? 1 : 0, $userId]
        );
    }
}
