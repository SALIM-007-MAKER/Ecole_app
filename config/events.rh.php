<?php

/**
 * Listeners d'événements du module RH.
 *
 * Chargé par config/events.php UNIQUEMENT si le module « rh » est activé dans
 * config/modules.php (déplacé depuis events.php lors de la simplification V1 —
 * le module est gelé pour la V2). Pour le réactiver : passer 'enabled' à true
 * dans config/modules.php ; ces listeners sont alors enregistrés à nouveau.
 */

return [

    // ── Module RH V2 — Employés ──────────────────────────────────────────────

    \App\Modules\RH\Employes\Events\EmployeeCreated::class => [
        new \App\Modules\RH\Employes\Listeners\AuditListener(),
        new \App\Modules\RH\Employes\Listeners\NotificationListener(),
        new \App\Modules\RH\Employes\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Employes\Events\EmployeeUpdated::class => [
        new \App\Modules\RH\Employes\Listeners\AuditListener(),
        new \App\Modules\RH\Employes\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Employes\Events\EmployeeArchived::class => [
        new \App\Modules\RH\Employes\Listeners\AuditListener(),
        new \App\Modules\RH\Employes\Listeners\NotificationListener(),
        new \App\Modules\RH\Employes\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Employes\Events\EmployeeRestored::class => [
        new \App\Modules\RH\Employes\Listeners\AuditListener(),
        new \App\Modules\RH\Employes\Listeners\NotificationListener(),
        new \App\Modules\RH\Employes\Listeners\StatisticsListener(),
    ],

    // ── Module RH V2 — Enseignants ───────────────────────────────────────────

    \App\Modules\RH\Enseignants\Events\TeacherCreated::class => [
        new \App\Modules\RH\Enseignants\Listeners\AuditListener(),
        new \App\Modules\RH\Enseignants\Listeners\NotificationListener(),
        new \App\Modules\RH\Enseignants\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Enseignants\Events\TeacherUpdated::class => [
        new \App\Modules\RH\Enseignants\Listeners\AuditListener(),
        new \App\Modules\RH\Enseignants\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Enseignants\Events\TeacherAssigned::class => [
        new \App\Modules\RH\Enseignants\Listeners\AuditListener(),
        new \App\Modules\RH\Enseignants\Listeners\NotificationListener(),
        new \App\Modules\RH\Enseignants\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Enseignants\Events\TeacherQualificationUpdated::class => [
        new \App\Modules\RH\Enseignants\Listeners\AuditListener(),
    ],

    // ── Module RH V2 — Organisation ──────────────────────────────────────────

    \App\Modules\RH\Organisation\Events\DepartmentCreated::class => [
        new \App\Modules\RH\Organisation\Listeners\AuditListener(),
        new \App\Modules\RH\Organisation\Listeners\NotificationListener(),
        new \App\Modules\RH\Organisation\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Organisation\Events\DepartmentUpdated::class => [
        new \App\Modules\RH\Organisation\Listeners\AuditListener(),
        new \App\Modules\RH\Organisation\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Organisation\Events\PositionCreated::class => [
        new \App\Modules\RH\Organisation\Listeners\AuditListener(),
        new \App\Modules\RH\Organisation\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Organisation\Events\PositionAssigned::class => [
        new \App\Modules\RH\Organisation\Listeners\AuditListener(),
        new \App\Modules\RH\Organisation\Listeners\NotificationListener(),
    ],

    \App\Modules\RH\Organisation\Events\OrganizationUpdated::class => [
        new \App\Modules\RH\Organisation\Listeners\AuditListener(),
        new \App\Modules\RH\Organisation\Listeners\StatisticsListener(),
    ],

    // ── Module RH V2 — Contrats ──────────────────────────────────────────────

    \App\Modules\RH\Contrats\Events\ContractCreated::class => [
        new \App\Modules\RH\Contrats\Listeners\AuditListener(),
        new \App\Modules\RH\Contrats\Listeners\NotificationListener(),
        new \App\Modules\RH\Contrats\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Contrats\Events\ContractUpdated::class => [
        new \App\Modules\RH\Contrats\Listeners\AuditListener(),
        new \App\Modules\RH\Contrats\Listeners\NotificationListener(),
        new \App\Modules\RH\Contrats\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Contrats\Events\ContractRenewed::class => [
        new \App\Modules\RH\Contrats\Listeners\AuditListener(),
        new \App\Modules\RH\Contrats\Listeners\NotificationListener(),
        new \App\Modules\RH\Contrats\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Contrats\Events\ContractExpired::class => [
        new \App\Modules\RH\Contrats\Listeners\AuditListener(),
        new \App\Modules\RH\Contrats\Listeners\NotificationListener(),
        new \App\Modules\RH\Contrats\Listeners\StatisticsListener(),
        new \App\Modules\Communication\Listeners\CrossModuleListener(),
    ],

    \App\Modules\RH\Contrats\Events\ContractTerminated::class => [
        new \App\Modules\RH\Contrats\Listeners\AuditListener(),
        new \App\Modules\RH\Contrats\Listeners\NotificationListener(),
        new \App\Modules\RH\Contrats\Listeners\StatisticsListener(),
    ],

    // ── Module RH V2 — Affectations ──────────────────────────────────────────

    \App\Modules\RH\Affectations\Events\AssignmentCreated::class => [
        new \App\Modules\RH\Affectations\Listeners\AuditListener(),
        new \App\Modules\RH\Affectations\Listeners\NotificationListener(),
        new \App\Modules\RH\Affectations\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Affectations\Events\AssignmentUpdated::class => [
        new \App\Modules\RH\Affectations\Listeners\AuditListener(),
        new \App\Modules\RH\Affectations\Listeners\NotificationListener(),
        new \App\Modules\RH\Affectations\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Affectations\Events\AssignmentTransferred::class => [
        new \App\Modules\RH\Affectations\Listeners\AuditListener(),
        new \App\Modules\RH\Affectations\Listeners\NotificationListener(),
        new \App\Modules\RH\Affectations\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Affectations\Events\AssignmentArchived::class => [
        new \App\Modules\RH\Affectations\Listeners\AuditListener(),
        new \App\Modules\RH\Affectations\Listeners\NotificationListener(),
        new \App\Modules\RH\Affectations\Listeners\StatisticsListener(),
    ],

    // ── Module RH V2 — Congés ────────────────────────────────────────────────

    \App\Modules\RH\Conges\Events\LeaveRequested::class => [
        new \App\Modules\RH\Conges\Listeners\AuditListener(),
        new \App\Modules\RH\Conges\Listeners\NotificationListener(),
        new \App\Modules\RH\Conges\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Conges\Events\LeaveApproved::class => [
        new \App\Modules\RH\Conges\Listeners\AuditListener(),
        new \App\Modules\RH\Conges\Listeners\NotificationListener(),
        new \App\Modules\RH\Conges\Listeners\StatisticsListener(),
        new \App\Modules\Communication\Listeners\CrossModuleListener(),
    ],

    \App\Modules\RH\Conges\Events\LeaveRejected::class => [
        new \App\Modules\RH\Conges\Listeners\AuditListener(),
        new \App\Modules\RH\Conges\Listeners\NotificationListener(),
        new \App\Modules\RH\Conges\Listeners\StatisticsListener(),
        new \App\Modules\Communication\Listeners\CrossModuleListener(),
    ],

    \App\Modules\RH\Conges\Events\LeaveCancelled::class => [
        new \App\Modules\RH\Conges\Listeners\AuditListener(),
        new \App\Modules\RH\Conges\Listeners\NotificationListener(),
        new \App\Modules\RH\Conges\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Conges\Events\LeaveStarted::class => [
        new \App\Modules\RH\Conges\Listeners\AuditListener(),
        new \App\Modules\RH\Conges\Listeners\NotificationListener(),
        new \App\Modules\RH\Conges\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Conges\Events\LeaveFinished::class => [
        new \App\Modules\RH\Conges\Listeners\AuditListener(),
        new \App\Modules\RH\Conges\Listeners\NotificationListener(),
        new \App\Modules\RH\Conges\Listeners\StatisticsListener(),
    ],

    // ── Module RH V2 — Présences ──────────────────────────────────────────────

    \App\Modules\RH\Presences\Events\AttendanceCreated::class => [
        new \App\Modules\RH\Presences\Listeners\AuditListener(),
        new \App\Modules\RH\Presences\Listeners\NotificationListener(),
        new \App\Modules\RH\Presences\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Presences\Events\AttendanceUpdated::class => [
        new \App\Modules\RH\Presences\Listeners\AuditListener(),
        new \App\Modules\RH\Presences\Listeners\NotificationListener(),
        new \App\Modules\RH\Presences\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Presences\Events\AttendanceValidated::class => [
        new \App\Modules\RH\Presences\Listeners\AuditListener(),
        new \App\Modules\RH\Presences\Listeners\NotificationListener(),
        new \App\Modules\RH\Presences\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Presences\Events\AttendanceLateDetected::class => [
        new \App\Modules\RH\Presences\Listeners\AuditListener(),
        new \App\Modules\RH\Presences\Listeners\NotificationListener(),
        new \App\Modules\RH\Presences\Listeners\StatisticsListener(),
        new \App\Modules\Communication\Listeners\CrossModuleListener(),
    ],

    \App\Modules\RH\Presences\Events\AttendanceOvertimeDetected::class => [
        new \App\Modules\RH\Presences\Listeners\AuditListener(),
        new \App\Modules\RH\Presences\Listeners\NotificationListener(),
        new \App\Modules\RH\Presences\Listeners\StatisticsListener(),
    ],

    // ── Module RH V2 — Évaluations ───────────────────────────────────────────

    \App\Modules\RH\Evaluations\Events\EvaluationCreated::class => [
        new \App\Modules\RH\Evaluations\Listeners\AuditListener(),
        new \App\Modules\RH\Evaluations\Listeners\NotificationListener(),
        new \App\Modules\RH\Evaluations\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Evaluations\Events\EvaluationUpdated::class => [
        new \App\Modules\RH\Evaluations\Listeners\AuditListener(),
        new \App\Modules\RH\Evaluations\Listeners\NotificationListener(),
        new \App\Modules\RH\Evaluations\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Evaluations\Events\EvaluationValidated::class => [
        new \App\Modules\RH\Evaluations\Listeners\AuditListener(),
        new \App\Modules\RH\Evaluations\Listeners\NotificationListener(),
        new \App\Modules\RH\Evaluations\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Evaluations\Events\EvaluationPublished::class => [
        new \App\Modules\RH\Evaluations\Listeners\AuditListener(),
        new \App\Modules\RH\Evaluations\Listeners\NotificationListener(),
        new \App\Modules\RH\Evaluations\Listeners\StatisticsListener(),
        new \App\Modules\Communication\Listeners\CrossModuleListener(),
    ],

    \App\Modules\RH\Evaluations\Events\DevelopmentPlanCreated::class => [
        new \App\Modules\RH\Evaluations\Listeners\AuditListener(),
        new \App\Modules\RH\Evaluations\Listeners\NotificationListener(),
        new \App\Modules\RH\Evaluations\Listeners\StatisticsListener(),
    ],

    // ── Module RH V2 — Formations ────────────────────────────────────────────

    \App\Modules\RH\Formations\Events\TrainingCreated::class => [
        new \App\Modules\RH\Formations\Listeners\AuditListener(),
        new \App\Modules\RH\Formations\Listeners\NotificationListener(),
        new \App\Modules\RH\Formations\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Formations\Events\TrainingSessionOpened::class => [
        new \App\Modules\RH\Formations\Listeners\AuditListener(),
        new \App\Modules\RH\Formations\Listeners\NotificationListener(),
        new \App\Modules\RH\Formations\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Formations\Events\EmployeeEnrolled::class => [
        new \App\Modules\RH\Formations\Listeners\AuditListener(),
        new \App\Modules\RH\Formations\Listeners\NotificationListener(),
        new \App\Modules\RH\Formations\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Formations\Events\TrainingCompleted::class => [
        new \App\Modules\RH\Formations\Listeners\AuditListener(),
        new \App\Modules\RH\Formations\Listeners\NotificationListener(),
        new \App\Modules\RH\Formations\Listeners\StatisticsListener(),
        new \App\Modules\Communication\Listeners\CrossModuleListener(),
    ],

    \App\Modules\RH\Formations\Events\CertificationGranted::class => [
        new \App\Modules\RH\Formations\Listeners\AuditListener(),
        new \App\Modules\RH\Formations\Listeners\NotificationListener(),
        new \App\Modules\RH\Formations\Listeners\StatisticsListener(),
        new \App\Modules\Communication\Listeners\CrossModuleListener(),
    ],

    \App\Modules\RH\Formations\Events\CertificationExpired::class => [
        new \App\Modules\RH\Formations\Listeners\AuditListener(),
        new \App\Modules\RH\Formations\Listeners\NotificationListener(),
        new \App\Modules\RH\Formations\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Formations\Events\CompetencyValidated::class => [
        new \App\Modules\RH\Formations\Listeners\AuditListener(),
        new \App\Modules\RH\Formations\Listeners\NotificationListener(),
        new \App\Modules\RH\Formations\Listeners\StatisticsListener(),
    ],

    // ── Module RH V2 — Documents ─────────────────────────────────────────────

    \App\Modules\RH\Documents\Events\HRDocumentCreated::class => [
        new \App\Modules\RH\Documents\Listeners\AuditListener(),
        new \App\Modules\RH\Documents\Listeners\NotificationListener(),
        new \App\Modules\RH\Documents\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Documents\Events\HRDocumentUpdated::class => [
        new \App\Modules\RH\Documents\Listeners\AuditListener(),
        new \App\Modules\RH\Documents\Listeners\NotificationListener(),
        new \App\Modules\RH\Documents\Listeners\StatisticsListener(),
    ],

    \App\Modules\RH\Documents\Events\HRDocumentExpired::class => [
        new \App\Modules\RH\Documents\Listeners\AuditListener(),
        new \App\Modules\RH\Documents\Listeners\NotificationListener(),
        new \App\Modules\RH\Documents\Listeners\StatisticsListener(),
        new \App\Modules\Communication\Listeners\CrossModuleListener(),
    ],

    \App\Modules\RH\Documents\Events\HRDocumentArchived::class => [
        new \App\Modules\RH\Documents\Listeners\AuditListener(),
        new \App\Modules\RH\Documents\Listeners\NotificationListener(),
        new \App\Modules\RH\Documents\Listeners\StatisticsListener(),
    ],

];
