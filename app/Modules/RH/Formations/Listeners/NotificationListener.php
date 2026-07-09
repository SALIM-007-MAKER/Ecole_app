<?php

declare(strict_types=1);

namespace App\Modules\RH\Formations\Listeners;

use Core\Event;
use Core\Listener;

// Stub — Phase 6.10 notifications (email/push)
// EmployeeEnrolled → notifier l'employé (confirmation inscription)
// TrainingCompleted → notifier l'employé (attestation disponible)
// CertificationGranted → notifier l'employé (certificat disponible)
// CertificationExpired → notifier l'employé + RH (renouvellement requis)
class NotificationListener implements Listener
{
    public function handle(Event $event): void {}
}
