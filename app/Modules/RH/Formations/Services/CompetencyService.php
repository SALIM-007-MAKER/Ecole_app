<?php

declare(strict_types=1);

namespace App\Modules\RH\Formations\Services;

use Core\EventDispatcher;
use App\Modules\RH\Formations\Repositories\TrainingRepository;
use App\Modules\RH\Formations\Events\CompetencyValidated;

class CompetencyService
{
    private TrainingRepository $repo;

    public function __construct()
    {
        $this->repo = new TrainingRepository();
    }

    public function validerCompetence(
        int     $employeId,
        int     $competenceId,
        string  $niveau,
        ?int    $sessionId,
        int     $validePar,
        string  $valideParNom,
        ?string $notes
    ): void {
        $niveaux = ['debutant', 'intermediaire', 'avance', 'expert'];
        if (!in_array($niveau, $niveaux, true)) {
            throw new \InvalidArgumentException("Niveau invalide : $niveau.");
        }

        $competences = $this->repo->findAllCompetences();
        $comp = null;
        foreach ($competences as $c) {
            if ((int)$c['id'] === $competenceId) { $comp = $c; break; }
        }
        if (!$comp) throw new \RuntimeException('Compétence introuvable.');

        $this->repo->upsertEmployeCompetence(
            $employeId, $competenceId, $niveau,
            $sessionId, $validePar, $valideParNom, $notes
        );

        EventDispatcher::dispatch(new CompetencyValidated(
            $employeId, $competenceId, $comp['code'], $niveau, $validePar
        ));
    }

    public function getCompetencesEmploye(int $employeId): array
    {
        return $this->repo->findCompetencesByEmploye($employeId);
    }

    public function accorderCompetencesDepuisSession(int $sessionId, int $validePar, string $valideParNom): void
    {
        $session = null;
        // Find session to get formation_id
        $repo = $this->repo;
        $insc = $repo->findInscriptionsBySession($sessionId);
        // find formation competences via session's formation_id (need session)
        // This is called after session terminee to auto-grant competences to validated inscriptions
        foreach ($insc as $i) {
            if ($i['statut'] === 'valide') {
                // competences will be granted per formation in controller
                // This stub is for future auto-grant logic
            }
        }
    }
}
