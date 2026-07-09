<?php

declare(strict_types=1);

namespace App\Modules\RH\Formations\Services;

use App\Modules\RH\Formations\Repositories\TrainingRepository;

class LearningPathService
{
    private TrainingRepository $repo;

    public function __construct()
    {
        $this->repo = new TrainingRepository();
    }

    /**
     * Retourne le parcours de formation complet d'un employé.
     */
    public function getParcours(int $employeId): array
    {
        $inscriptions = $this->repo->findInscriptionsByEmploye($employeId);
        $competences  = $this->repo->findCompetencesByEmploye($employeId);
        $certifications = $this->repo->findCertificationsByEmploye($employeId);

        $validees = array_filter($inscriptions, fn($i) => $i['statut'] === 'valide');
        $heuresTotal = array_reduce(array_values($validees), function ($carry, $i) {
            return $carry; // duree_heures would need a join — stub
        }, 0.0);

        return [
            'inscriptions'    => $inscriptions,
            'validees'        => array_values($validees),
            'competences'     => $competences,
            'certifications'  => $certifications,
            'nb_formations'   => count($validees),
            'nb_competences'  => count($competences),
            'nb_certifications'=> count($certifications),
            'certifications_actives' => count(array_filter($certifications, fn($c) => $c['statut'] === 'valide')),
        ];
    }

    /**
     * Recommande des formations basées sur les compétences manquantes après évaluation.
     * Prépare l'intégration V3 — les liens évaluation→formation sont enrichis ici.
     */
    public function recommanderFormations(int $employeId, array $competencesManquantes): array
    {
        if (empty($competencesManquantes)) return [];

        $all      = $this->repo->findAllFormations();
        $results  = [];

        foreach ($all as $f) {
            $fComps = $this->repo->findFormationCompetences((int)$f['id']);
            $fCompIds = array_column($fComps, 'id');
            $matching = array_intersect($competencesManquantes, $fCompIds);
            if (!empty($matching)) {
                $f['competences_matchees'] = count($matching);
                $results[] = $f;
            }
        }

        usort($results, fn($a, $b) => $b['competences_matchees'] <=> $a['competences_matchees']);
        return array_slice($results, 0, 5);
    }

    /**
     * Dashboard global formations (pour reporting).
     */
    public function getDashboard(): array
    {
        return $this->repo->statistiques();
    }
}
