<?php

declare(strict_types=1);

namespace App\Modules\RH\Formations\Listeners;

use Core\Event;
use Core\Listener;

// Stub — compteurs analytiques Phase 6.12
// TrainingCompleted → mise à jour taux completion par formation
// CertificationGranted → compteur certifications actives
// CompetencyValidated → compteur compétences par département
class StatisticsListener implements Listener
{
    public function handle(Event $event): void {}
}
