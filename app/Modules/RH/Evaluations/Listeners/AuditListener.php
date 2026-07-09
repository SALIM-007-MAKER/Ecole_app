<?php

declare(strict_types=1);

namespace App\Modules\RH\Evaluations\Listeners;

use Core\Event;
use Core\Listener;
use App\Services\AuditService;
use App\Modules\RH\Evaluations\Events\EvaluationCreated;
use App\Modules\RH\Evaluations\Events\EvaluationUpdated;
use App\Modules\RH\Evaluations\Events\EvaluationValidated;
use App\Modules\RH\Evaluations\Events\EvaluationPublished;
use App\Modules\RH\Evaluations\Events\DevelopmentPlanCreated;

class AuditListener implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof EvaluationCreated      => $this->onCreate($event),
            $event instanceof EvaluationUpdated      => $this->onUpdate($event),
            $event instanceof EvaluationValidated    => $this->onValidate($event),
            $event instanceof EvaluationPublished    => $this->onPublish($event),
            $event instanceof DevelopmentPlanCreated => $this->onPlan($event),
            default => null,
        };
    }

    private function onCreate(EvaluationCreated $e): void
    {
        $this->audit->logCreate(
            $e->createdBy, 'rh', 'evaluation', $e->evaluationId, $e->toArray()
        );
    }

    private function onUpdate(EvaluationUpdated $e): void
    {
        $this->audit->log(
            $e->updatedBy, $e->action, 'rh', 'evaluation', $e->evaluationId,
            ['statut' => $e->ancienStatut], ['statut' => $e->nouveauStatut]
        );
    }

    private function onValidate(EvaluationValidated $e): void
    {
        $this->audit->log(
            $e->validePar, 'validation', 'rh', 'evaluation', $e->evaluationId,
            null, ['score_final' => $e->scoreFinal, 'mention' => $e->mention]
        );
    }

    private function onPublish(EvaluationPublished $e): void
    {
        $this->audit->log(
            $e->publiePar, 'publication', 'rh', 'evaluation', $e->evaluationId,
            null, ['score_final' => $e->scoreFinal, 'mention' => $e->mention]
        );
    }

    private function onPlan(DevelopmentPlanCreated $e): void
    {
        $this->audit->logCreate(
            $e->createdBy, 'rh', 'plan_developpement', $e->planId, $e->toArray()
        );
    }
}
