<?php

declare(strict_types=1);

namespace App\Modules\RH\Evaluations\Services;

use Core\EventDispatcher;
use App\Modules\RH\Evaluations\DTO\EvaluationDTO;
use App\Modules\RH\Evaluations\DTO\CampagneDTO;
use App\Modules\RH\Evaluations\Models\EvaluationModel;
use App\Modules\RH\Evaluations\Repositories\EvaluationRepository;
use App\Modules\RH\Evaluations\Events\EvaluationCreated;
use App\Modules\RH\Evaluations\Events\EvaluationUpdated;
use App\Modules\RH\Evaluations\Events\EvaluationValidated;
use App\Modules\RH\Evaluations\Events\EvaluationPublished;
use App\Modules\RH\Evaluations\Events\DevelopmentPlanCreated;

class EvaluationService
{
    private EvaluationRepository $repo;

    public function __construct()
    {
        $this->repo = new EvaluationRepository();
    }

    // ── Campagnes ─────────────────────────────────────────────────────────────

    public function creerCampagne(CampagneDTO $dto, int $userId): int
    {
        $errors = $dto->validate();
        if ($errors) throw new \InvalidArgumentException(implode(' ', $errors));

        $id = $this->repo->insertCampagne([
            'code'                  => $dto->code,
            'libelle'               => $dto->libelle,
            'description'           => $dto->description,
            'annee'                 => $dto->annee,
            'periode'               => $dto->periode,
            'date_debut'            => $dto->dateDebut,
            'date_fin'              => $dto->dateFin,
            'date_limite_auto_eval' => $dto->dateLimiteAutoEval,
            'date_limite_eval'      => $dto->dateLimiteEval,
            'created_by'            => $userId,
        ]);

        foreach ($dto->critereIds as $ordre => $entry) {
            $this->repo->insertCampagneCritere(
                $id,
                (int)$entry['critere_id'],
                isset($entry['poids_override']) ? (float)$entry['poids_override'] : null,
                (int)($entry['obligatoire'] ?? 1),
                $ordre
            );
        }

        return $id;
    }

    public function activerCampagne(int $id, int $userId): void
    {
        $campagne = $this->repo->findCampagneById($id);
        if (!$campagne) throw new \RuntimeException('Campagne introuvable.');
        if ($campagne['statut'] !== 'brouillon') {
            throw new \RuntimeException('Seule une campagne en brouillon peut être activée.');
        }
        $this->repo->updateCampagneStatut($id, 'active');
    }

    public function cloturerCampagne(int $id, int $userId): void
    {
        $campagne = $this->repo->findCampagneById($id);
        if (!$campagne) throw new \RuntimeException('Campagne introuvable.');
        if ($campagne['statut'] !== 'active') {
            throw new \RuntimeException('Seule une campagne active peut être clôturée.');
        }
        $this->repo->updateCampagneStatut($id, 'cloturee');
    }

    // ── Évaluations ───────────────────────────────────────────────────────────

    public function creer(EvaluationDTO $dto, int $userId, string $userName): int
    {
        $errors = $dto->validate();
        if ($errors) throw new \InvalidArgumentException(implode(' ', $errors));

        $campagne = $this->repo->findCampagneById($dto->campagneId);
        if (!$campagne) throw new \RuntimeException('Campagne introuvable.');
        if ($campagne['statut'] !== 'active') {
            throw new \RuntimeException('La campagne doit être active pour créer une évaluation.');
        }

        $evalId = $this->repo->insertEvaluation([
            'campagne_id'    => $dto->campagneId,
            'employe_id'     => $dto->employeId,
            'affectation_id' => $dto->affectationId,
            'evaluateur_id'  => $dto->evaluateurId,
            'evaluateur_nom' => null,
            'created_by'     => $userId,
        ]);

        $this->repo->insertHistorique(
            $evalId, null, 'brouillon', 'creation', null, $userId, $userName
        );

        EventDispatcher::dispatch(new EvaluationCreated(
            $evalId, $dto->campagneId, $dto->employeId, $campagne['code'], $userId
        ));

        return $evalId;
    }

    public function demarrerAutoEval(int $id, int $userId, string $userName): void
    {
        $eval = $this->requireEval($id);
        $this->assertTransition($eval, 'demarrer_auto_eval');

        $this->repo->updateStatut($id, 'en_auto_evaluation', [], $userId);
        $this->repo->insertHistorique($id, $eval['statut'], 'en_auto_evaluation', 'demarrer_auto_eval', null, $userId, $userName);

        EventDispatcher::dispatch(new EvaluationUpdated(
            $id, (int)$eval['employe_id'], 'demarrer_auto_eval',
            $eval['statut'], 'en_auto_evaluation', $userId
        ));
    }

    public function soumettreAutoEval(int $id, array $notes, string $commentaire, int $userId, string $userName): void
    {
        $eval = $this->requireEval($id);
        $this->assertTransition($eval, 'soumettre_auto_eval');

        foreach ($notes as $critereId => $note) {
            $this->repo->upsertCritereScore(
                $id, (int)$critereId, 'auto_eval', (float)$note['note'], $note['commentaire'] ?? null
            );
        }

        $score = $this->calculerScore($id, 'auto_eval');
        $this->repo->updateScoreAutoEval($id, $score, $commentaire, $userId);
        $this->repo->updateStatut($id, 'en_evaluation', [], $userId);
        $this->repo->insertHistorique($id, $eval['statut'], 'en_evaluation', 'soumettre_auto_eval', $commentaire, $userId, $userName);

        EventDispatcher::dispatch(new EvaluationUpdated(
            $id, (int)$eval['employe_id'], 'soumettre_auto_eval',
            $eval['statut'], 'en_evaluation', $userId
        ));
    }

    public function evaluerResponsable(int $id, array $notes, string $commentaire, int $userId, string $userName): void
    {
        $eval = $this->requireEval($id);
        if ($eval['statut'] !== 'en_evaluation') {
            throw new \RuntimeException('L\'évaluation n\'est pas dans l\'état "en évaluation".');
        }

        foreach ($notes as $critereId => $note) {
            $this->repo->upsertCritereScore(
                $id, (int)$critereId, 'evaluateur', (float)$note['note'], $note['commentaire'] ?? null
            );
        }

        $score = $this->calculerScore($id, 'evaluateur');
        $this->repo->updateScoreEvaluateur($id, $score, $commentaire, $userId);
    }

    public function soumettre(int $id, int $userId, string $userName): void
    {
        $eval = $this->requireEval($id);
        $this->assertTransition($eval, 'soumettre');

        if ($eval['score_evaluateur'] === null) {
            throw new \RuntimeException('Aucun score évaluateur. Veuillez d\'abord évaluer les critères.');
        }

        $this->repo->updateStatut($id, 'soumise', [], $userId);
        $this->repo->insertHistorique($id, $eval['statut'], 'soumise', 'soumettre', null, $userId, $userName);

        EventDispatcher::dispatch(new EvaluationUpdated(
            $id, (int)$eval['employe_id'], 'soumettre',
            $eval['statut'], 'soumise', $userId
        ));
    }

    public function valider(int $id, string $commentaire, int $userId, string $userName): void
    {
        $eval = $this->requireEval($id);
        $this->assertTransition($eval, 'valider');

        $scoreFinal = (float)($eval['score_evaluateur'] ?? $eval['score_auto_eval'] ?? 0.0);
        $mention    = EvaluationModel::getMentionFromScore($scoreFinal);

        $this->repo->updateStatut($id, 'validee', [
            'score_final'          => $scoreFinal,
            'mention'              => $mention,
            'commentaire_validation' => $commentaire,
            'date_validation'      => date('Y-m-d H:i:s'),
            'valide_par'           => $userId,
            'valide_par_nom'       => $userName,
        ], $userId);

        $this->repo->insertHistorique($id, $eval['statut'], 'validee', 'valider', $commentaire, $userId, $userName);

        EventDispatcher::dispatch(new EvaluationValidated(
            $id, (int)$eval['employe_id'], $scoreFinal, $mention, $userId
        ));
    }

    public function publier(int $id, int $userId, string $userName): void
    {
        $eval = $this->requireEval($id);
        $this->assertTransition($eval, 'publier');

        $this->repo->updateStatut($id, 'publiee', [
            'date_publication' => date('Y-m-d H:i:s'),
            'publie_par'       => $userId,
            'publie_par_nom'   => $userName,
        ], $userId);

        $this->repo->insertHistorique($id, $eval['statut'], 'publiee', 'publier', null, $userId, $userName);

        EventDispatcher::dispatch(new EvaluationPublished(
            $id, (int)$eval['employe_id'],
            (float)($eval['score_final'] ?? 0),
            (string)($eval['mention'] ?? ''),
            $userId
        ));
    }

    public function archiver(int $id, int $userId, string $userName): void
    {
        $eval = $this->requireEval($id);
        $this->assertTransition($eval, 'archiver');

        $this->repo->updateStatut($id, 'archivee', [], $userId);
        $this->repo->insertHistorique($id, $eval['statut'], 'archivee', 'archiver', null, $userId, $userName);
    }

    public function creerPlanDeveloppement(int $evalId, array $data, int $userId): int
    {
        $eval = $this->requireEval($evalId);
        if (!in_array($eval['statut'], ['validee', 'publiee'], true)) {
            throw new \RuntimeException('Un plan ne peut être créé que sur une évaluation validée ou publiée.');
        }
        if (empty(trim($data['objectif'] ?? ''))) {
            throw new \InvalidArgumentException('L\'objectif est requis.');
        }

        $planId = $this->repo->insertPlan([
            'evaluation_id' => $evalId,
            'employe_id'    => $eval['employe_id'],
            'objectif'      => trim($data['objectif']),
            'actions'       => trim($data['actions']    ?? ''),
            'ressources'    => trim($data['ressources'] ?? ''),
            'echeance'      => ($data['echeance'] ?? '') !== '' ? $data['echeance'] : null,
            'created_by'    => $userId,
        ]);

        EventDispatcher::dispatch(new DevelopmentPlanCreated(
            $planId, $evalId, (int)$eval['employe_id'], trim($data['objectif']), $userId
        ));

        return $planId;
    }

    // ── Calcul score ──────────────────────────────────────────────────────────

    public function calculerScore(int $evaluationId, string $type): float
    {
        $criteres = $this->repo->findCritereScores($evaluationId);
        if (empty($criteres)) return 0.0;

        $noteCol = $type === 'auto_eval' ? 'note_auto_eval' : 'note_evaluateur';
        $somme      = 0.0;
        $sommePoids = 0.0;

        foreach ($criteres as $c) {
            if ($c[$noteCol] === null) continue;
            $poids       = (float)$c['poids_effectif'];
            $somme      += (float)$c[$noteCol] * $poids;
            $sommePoids += $poids;
        }

        return $sommePoids > 0 ? round($somme / $sommePoids, 2) : 0.0;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function requireEval(int $id): array
    {
        $eval = $this->repo->findById($id);
        if (!$eval) throw new \RuntimeException('Évaluation introuvable.');
        return $eval;
    }

    private function assertTransition(array $eval, string $action): void
    {
        if (!EvaluationModel::canTransition($eval['statut'], $action)) {
            throw new \RuntimeException(
                "Transition \"$action\" interdite depuis le statut \"{$eval['statut']}\"."
            );
        }
    }
}
