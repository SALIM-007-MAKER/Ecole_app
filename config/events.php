<?php

use App\Events\EleveCreated;
use App\Events\AbsenceCreee;
use App\Events\DocumentGenere;
use App\Events\ImportCsvCompleted;
use App\Listeners\AuditHandler;
use App\Listeners\NotificationHandler;
use App\Listeners\StatsCacheHandler;
use App\Modules\Scolarite\Events\EleveUpdated;
use App\Modules\Scolarite\Events\EleveArchived;
use App\Modules\Scolarite\Events\ClasseCreated;
use App\Modules\Scolarite\Events\ClasseUpdated;
use App\Modules\Scolarite\Events\ClasseDeleted;
use App\Modules\Scolarite\Events\EleveAssignedToClasse;
use App\Modules\Scolarite\Events\EleveRemovedFromClasse;
use App\Modules\Scolarite\Events\InscriptionCreated;
use App\Modules\Scolarite\Events\InscriptionUpdated;
use App\Modules\Scolarite\Events\InscriptionCancelled;
use App\Modules\Scolarite\Events\ReinscriptionCreated;
use App\Modules\Scolarite\Events\ClasseChanged;
use App\Modules\Scolarite\Events\SchoolYearChanged;
use App\Modules\Scolarite\Listeners\EleveHandler;
use App\Modules\Scolarite\Listeners\ClasseHandler;
use App\Modules\Scolarite\Listeners\InscriptionHandler;
use App\Modules\Scolarite\Events\ParentCreated;
use App\Modules\Scolarite\Events\ParentUpdated;
use App\Modules\Scolarite\Events\ParentLinkedToStudent;
use App\Modules\Scolarite\Events\ParentUnlinkedFromStudent;
use App\Modules\Scolarite\Events\EmergencyContactUpdated;
use App\Modules\Scolarite\Listeners\FamilleHandler;
use App\Modules\Scolarite\Events\MatiereCreated;
use App\Modules\Scolarite\Events\MatiereUpdated;
use App\Modules\Scolarite\Events\MatiereArchived;
use App\Modules\Scolarite\Events\MatiereAssignedToClasse;
use App\Modules\Scolarite\Events\MatiereRemovedFromClasse;
use App\Modules\Scolarite\Listeners\MatiereHandler;
use App\Modules\Academique\Events\PeriodeCreated;
use App\Modules\Academique\Events\PeriodeUpdated;
use App\Modules\Academique\Events\PeriodeActivated;
use App\Modules\Academique\Events\PeriodeLocked;
use App\Modules\Academique\Events\PeriodeUnlocked;
use App\Modules\Academique\Events\PeriodeArchived;
use App\Modules\Academique\Listeners\PeriodeHandler;
use App\Modules\Academique\Events\EvaluationTypeCreated;
use App\Modules\Academique\Events\EvaluationTypeUpdated;
use App\Modules\Academique\Events\EvaluationTypeActivated;
use App\Modules\Academique\Events\EvaluationTypeDeactivated;
use App\Modules\Academique\Events\EvaluationTypeArchived;
use App\Modules\Academique\Listeners\TypeEvaluationHandler;
use App\Modules\Academique\Events\AverageCalculated;
use App\Modules\Academique\Events\ClassAverageUpdated;
use App\Modules\Academique\Listeners\AverageHandler;
use App\Modules\Academique\Events\RankingGenerated;
use App\Modules\Academique\Events\RankingUpdated;
use App\Modules\Academique\Listeners\RankingHandler;
use App\Modules\Academique\Events\BulletinGenerated;
use App\Modules\Academique\Events\BulletinPublished;
use App\Modules\Academique\Events\BulletinArchived;
use App\Modules\Academique\Events\BulletinAppreciationUpdated;
use App\Modules\Academique\Listeners\BulletinHandler;
use App\Modules\Academique\Events\AnalyticsGenerated;
use App\Modules\Academique\Events\StatisticsUpdated;
use App\Modules\Academique\Listeners\AnalyticsHandler;
use App\Modules\Academique\Events\NoteCreated;
use App\Modules\VieScolaire\Absences\Events\StudentAbsent;
use App\Modules\VieScolaire\Absences\Events\AbsenceJustified;
use App\Modules\VieScolaire\Absences\Events\AbsenceRejected;
use App\Modules\VieScolaire\Absences\Listeners\AttendanceHandler;
use App\Modules\VieScolaire\Presences\Events\AttendanceStarted;
use App\Modules\VieScolaire\Presences\Events\AttendanceValidated;
use App\Modules\VieScolaire\Presences\Events\AttendanceCompleted;
use App\Modules\VieScolaire\Presences\Events\StudentPresent;
use App\Modules\VieScolaire\Presences\Events\StudentAbsent as PresenceStudentAbsent;
use App\Modules\VieScolaire\Presences\Events\StudentLate;
use App\Modules\VieScolaire\Presences\Listeners\AuditListener;
use App\Modules\VieScolaire\Presences\Listeners\NotificationListener;
use App\Modules\VieScolaire\Presences\Listeners\StatisticsListener;
use App\Modules\VieScolaire\Retards\Events\StudentLate as RetardStudentLate;
use App\Modules\VieScolaire\Retards\Events\LateJustified;
use App\Modules\VieScolaire\Retards\Events\LateRejected;
use App\Modules\VieScolaire\Retards\Events\LateThresholdReached;
use App\Modules\VieScolaire\Retards\Listeners\AuditListener as RetardAuditListener;
use App\Modules\VieScolaire\Retards\Listeners\NotificationListener as RetardNotificationListener;
use App\Modules\VieScolaire\Retards\Listeners\StatisticsListener as RetardStatisticsListener;
use App\Modules\VieScolaire\Discipline\Events\DisciplineCaseCreated;
use App\Modules\VieScolaire\Discipline\Events\DisciplinaryActionAssigned;
use App\Modules\VieScolaire\Discipline\Events\DisciplineCaseClosed;
use App\Modules\VieScolaire\Discipline\Events\DisciplineAppealSubmitted;
use App\Modules\VieScolaire\Discipline\Listeners\AuditListener as DisciplineAuditListener;
use App\Modules\VieScolaire\Discipline\Listeners\NotificationListener as DisciplineNotificationListener;
use App\Modules\VieScolaire\Discipline\Listeners\StatisticsListener as DisciplineStatisticsListener;
use App\Modules\VieScolaire\Discipline\Listeners\DisciplineIntegrationHandler;
use App\Modules\VieScolaire\Recompenses\Events\RewardGranted;
use App\Modules\VieScolaire\Recompenses\Events\RewardUpdated;
use App\Modules\VieScolaire\Recompenses\Events\RewardRevoked;
use App\Modules\VieScolaire\Recompenses\Listeners\AuditListener as RewardAuditListener;
use App\Modules\VieScolaire\Recompenses\Listeners\NotificationListener as RewardNotificationListener;
use App\Modules\VieScolaire\Recompenses\Listeners\StatisticsListener as RewardStatisticsListener;
use App\Modules\VieScolaire\EmploisDuTemps\Events\TimetableCreated;
use App\Modules\VieScolaire\EmploisDuTemps\Events\TimetableUpdated;
use App\Modules\VieScolaire\EmploisDuTemps\Events\TimetablePublished;
use App\Modules\VieScolaire\EmploisDuTemps\Events\TimetableConflictDetected;
use App\Modules\VieScolaire\EmploisDuTemps\Events\TeacherReplacementAssigned;
use App\Modules\VieScolaire\EmploisDuTemps\Listeners\AuditListener as EdtAuditListener;
use App\Modules\VieScolaire\EmploisDuTemps\Listeners\NotificationListener as EdtNotificationListener;
use App\Modules\VieScolaire\EmploisDuTemps\Listeners\StatisticsListener as EdtStatisticsListener;
use App\Modules\VieScolaire\Activites\Events\ActivityCreated;
use App\Modules\VieScolaire\Activites\Events\ActivityUpdated;
use App\Modules\VieScolaire\Activites\Events\ActivityPublished;
use App\Modules\VieScolaire\Activites\Events\ActivityCancelled;
use App\Modules\VieScolaire\Activites\Events\StudentRegisteredToActivity;
use App\Modules\VieScolaire\Activites\Listeners\AuditListener as ActivityAuditListener;
use App\Modules\VieScolaire\Activites\Listeners\NotificationListener as ActivityNotificationListener;
use App\Modules\VieScolaire\Activites\Listeners\StatisticsListener as ActivityStatisticsListener;
use App\Modules\Finance\Events\FeeCreated;
use App\Modules\Finance\Events\FeeUpdated;
use App\Modules\Finance\Events\FeeActivated;
use App\Modules\Finance\Events\FeeDeactivated;
use App\Modules\Finance\Events\FeeArchived;
use App\Modules\Finance\Listeners\FraisAuditHandler;
use App\Modules\Academique\Events\NoteUpdated;
use App\Modules\Academique\Events\NotePublished;
use App\Modules\Academique\Events\NoteLocked;
use App\Modules\Academique\Events\NoteImported;
use App\Modules\Academique\Listeners\NoteHandler;
use App\Modules\Academique\Listeners\NoteNotificationHandler;
use App\Modules\Academique\Events\AppreciationMatiereSaisie;
use App\Modules\Academique\Listeners\AppreciationHandler;
use App\Modules\Academique\Events\EvaluationCreated;
use App\Modules\Academique\Events\EvaluationUpdated;
use App\Modules\Academique\Events\EvaluationPublished;
use App\Modules\Academique\Events\EvaluationLocked;
use App\Modules\Academique\Events\EvaluationArchived;
use App\Modules\Academique\Listeners\EvaluationHandler;
// ── Module Communication V2 ──────────────────────────────────────────────────
use App\Modules\Communication\Events\NotificationCreated;
use App\Modules\Communication\Events\NotificationRead;
use App\Modules\Communication\Events\NotificationsBulkRead;
use App\Modules\Communication\Events\MessageQueued;
use App\Modules\Communication\Events\MessageSent;
use App\Modules\Communication\Events\MessageFailed;
use App\Modules\Communication\Events\ThreadCreated;
use App\Modules\Communication\Events\ThreadMessageSent;
use App\Modules\Communication\Events\DiffusionEnvoyee;
use App\Modules\Communication\Events\CampagneLancee;
use App\Modules\Communication\Events\CampagneTerminee;
use App\Modules\Communication\Events\PreferencesUpdated;
use App\Modules\Communication\Listeners\CrossModuleListener;
use App\Modules\Communication\Listeners\AuditListener as CommunicationAuditListener;
use App\Modules\Communication\Listeners\RealTimeListener;

/**
 * Mapping Event → Listeners.
 *
 * Ajouter un effet de bord = ajouter un handler dans ce fichier.
 * Ne jamais modifier les contrôleurs existants pour ça.
 *
 * Ordre des listeners : AuditHandler toujours en premier (critique).
 */
return [

    EleveCreated::class => [
        new AuditHandler(),
        new StatsCacheHandler(),
    ],

    AbsenceCreee::class => [
        new AuditHandler(),
        new NotificationHandler(),
        new StatsCacheHandler(),
    ],

    DocumentGenere::class => [
        new AuditHandler(),
    ],

    ImportCsvCompleted::class => [
        new AuditHandler(),
        new StatsCacheHandler(),
    ],

    EleveUpdated::class => [
        new EleveHandler(),
    ],

    EleveArchived::class => [
        new EleveHandler(),
    ],

    ClasseCreated::class => [
        new ClasseHandler(),
    ],

    ClasseUpdated::class => [
        new ClasseHandler(),
    ],

    ClasseDeleted::class => [
        new ClasseHandler(),
    ],

    EleveAssignedToClasse::class => [
        new ClasseHandler(),
    ],

    EleveRemovedFromClasse::class => [
        new ClasseHandler(),
    ],

    InscriptionCreated::class => [
        new InscriptionHandler(),
    ],

    InscriptionUpdated::class => [
        new InscriptionHandler(),
    ],

    InscriptionCancelled::class => [
        new InscriptionHandler(),
    ],

    ReinscriptionCreated::class => [
        new InscriptionHandler(),
    ],

    ClasseChanged::class => [
        new InscriptionHandler(),
    ],

    SchoolYearChanged::class => [
        new InscriptionHandler(),
    ],

    ParentCreated::class => [
        new FamilleHandler(),
    ],

    ParentUpdated::class => [
        new FamilleHandler(),
    ],

    ParentLinkedToStudent::class => [
        new FamilleHandler(),
    ],

    ParentUnlinkedFromStudent::class => [
        new FamilleHandler(),
    ],

    EmergencyContactUpdated::class => [
        new FamilleHandler(),
    ],

    MatiereCreated::class => [
        new MatiereHandler(),
    ],

    MatiereUpdated::class => [
        new MatiereHandler(),
    ],

    MatiereArchived::class => [
        new MatiereHandler(),
    ],

    MatiereAssignedToClasse::class => [
        new MatiereHandler(),
    ],

    MatiereRemovedFromClasse::class => [
        new MatiereHandler(),
    ],

    // ── Module Académique V2 ─────────────────────────────────────────────────

    PeriodeCreated::class => [
        new PeriodeHandler(),
    ],

    PeriodeUpdated::class => [
        new PeriodeHandler(),
    ],

    PeriodeActivated::class => [
        new PeriodeHandler(),
    ],

    PeriodeLocked::class => [
        new PeriodeHandler(),
    ],

    PeriodeUnlocked::class => [
        new PeriodeHandler(),
    ],

    PeriodeArchived::class => [
        new PeriodeHandler(),
    ],

    // ── Types d'évaluations V2 ───────────────────────────────────────────────

    EvaluationTypeCreated::class => [
        new TypeEvaluationHandler(),
    ],

    EvaluationTypeUpdated::class => [
        new TypeEvaluationHandler(),
    ],

    EvaluationTypeActivated::class => [
        new TypeEvaluationHandler(),
    ],

    EvaluationTypeDeactivated::class => [
        new TypeEvaluationHandler(),
    ],

    EvaluationTypeArchived::class => [
        new TypeEvaluationHandler(),
    ],

    // ── Évaluations V2 ──────────────────────────────────────────────────────

    EvaluationCreated::class => [
        new EvaluationHandler(),
    ],

    EvaluationUpdated::class => [
        new EvaluationHandler(),
    ],

    EvaluationPublished::class => [
        new EvaluationHandler(),
    ],

    EvaluationLocked::class => [
        new EvaluationHandler(),
    ],

    EvaluationArchived::class => [
        new EvaluationHandler(),
    ],

    // ── Notes V2 ────────────────────────────────────────────────────────────

    NoteCreated::class => [
        new NoteHandler(),
    ],

    NoteUpdated::class => [
        new NoteHandler(),
    ],

    NotePublished::class => [
        new NoteHandler(),
        new NoteNotificationHandler(),
        new CrossModuleListener(),
    ],

    NoteLocked::class => [
        new NoteHandler(),
    ],

    NoteImported::class => [
        new NoteHandler(),
    ],

    AppreciationMatiereSaisie::class => [
        new AppreciationHandler(),
    ],

    // ── Moyennes V2 ─────────────────────────────────────────────────────────

    AverageCalculated::class => [
        new AverageHandler(),
    ],

    ClassAverageUpdated::class => [
        new AverageHandler(),
    ],

    // ── Classement V2 ───────────────────────────────────────────────────────

    RankingGenerated::class => [
        new RankingHandler(),
    ],

    RankingUpdated::class => [
        new RankingHandler(),
    ],

    // ── Bulletins V2 ────────────────────────────────────────────────────────

    BulletinGenerated::class => [
        new BulletinHandler(),
    ],

    BulletinPublished::class => [
        new BulletinHandler(),
        new CrossModuleListener(),
    ],

    BulletinArchived::class => [
        new BulletinHandler(),
    ],

    BulletinAppreciationUpdated::class => [
        new BulletinHandler(),
    ],

    // ── Analytics V2 ────────────────────────────────────────────────────────

    AnalyticsGenerated::class => [
        new AnalyticsHandler(),
    ],

    StatisticsUpdated::class => [
        new AnalyticsHandler(),
    ],

    // ── Module Finance V2 — Référentiel frais ────────────────────────────────

    FeeCreated::class => [
        new FraisAuditHandler(),
    ],

    FeeUpdated::class => [
        new FraisAuditHandler(),
    ],

    FeeActivated::class => [
        new FraisAuditHandler(),
    ],

    FeeDeactivated::class => [
        new FraisAuditHandler(),
    ],

    FeeArchived::class => [
        new FraisAuditHandler(),
    ],

    // ── Module Finance V2 — Facturation ──────────────────────────────────────

    \App\Modules\Finance\Events\InvoiceCreated::class => [
        new \App\Modules\Finance\Listeners\InvoiceHandler(),
        new CrossModuleListener(),
    ],

    \App\Modules\Finance\Events\InvoiceGenerated::class => [
        new \App\Modules\Finance\Listeners\InvoiceHandler(),
    ],

    \App\Modules\Finance\Events\InvoiceUpdated::class => [
        new \App\Modules\Finance\Listeners\InvoiceHandler(),
    ],

    \App\Modules\Finance\Events\InvoiceArchived::class => [
        new \App\Modules\Finance\Listeners\InvoiceHandler(),
    ],

    // ── Module Finance V2 — Encaissements ────────────────────────────────────

    \App\Modules\Finance\Events\PaymentInitiated::class => [
        new \App\Modules\Finance\Listeners\PaymentHandler(),
    ],

    \App\Modules\Finance\Events\PaymentPartial::class => [
        new \App\Modules\Finance\Listeners\PaymentHandler(),
    ],

    \App\Modules\Finance\Events\PaymentCancelled::class => [
        new \App\Modules\Finance\Listeners\PaymentHandler(),
    ],

    \App\Modules\Finance\Events\ReceiptGenerated::class => [
        new \App\Modules\Finance\Listeners\PaymentHandler(),
    ],

    // ── Module Finance V2 — Caisse ───────────────────────────────────────────

    \App\Modules\Finance\Events\CashRegisterOpened::class => [
        new \App\Modules\Finance\Listeners\CashRegisterHandler(),
    ],

    \App\Modules\Finance\Events\CashRegisterClosed::class => [
        new \App\Modules\Finance\Listeners\CashRegisterHandler(),
    ],

    \App\Modules\Finance\Events\CashMovementCancelled::class => [
        new \App\Modules\Finance\Listeners\CashRegisterHandler(),
    ],

    \App\Modules\Finance\Events\CashBalanceUpdated::class => [
        new \App\Modules\Finance\Listeners\CashRegisterHandler(),
    ],

    // ── Module Finance V2 — Comptabilité ─────────────────────────────────────
    // AccountingHandler écoute les événements métier pour créer des écritures
    // automatiques. Échec silencieux — le métier ne doit pas être bloqué.

    // PaymentCompleted → 4 handlers : audit(PaymentHandler), caisse(CashRegisterHandler), compta(AccountingHandler), notification parent
    \App\Modules\Finance\Events\PaymentCompleted::class => [
        new \App\Modules\Finance\Listeners\PaymentHandler(),
        new \App\Modules\Finance\Listeners\CashRegisterHandler(),
        new \App\Modules\Finance\Listeners\AccountingHandler(),
        new \App\Modules\Finance\Listeners\NotificationListener(),
        new CrossModuleListener(),
        new \App\Modules\Bibliotheque\Listeners\FinanceIntegrationListener(),
    ],

    \App\Modules\Finance\Events\PaymentRefunded::class => [
        new \App\Modules\Finance\Listeners\PaymentHandler(),
        new \App\Modules\Finance\Listeners\AccountingHandler(),
    ],

    \App\Modules\Finance\Events\InvoiceCancelled::class => [
        new \App\Modules\Finance\Listeners\InvoiceHandler(),
        new \App\Modules\Finance\Listeners\AccountingHandler(),
    ],

    \App\Modules\Finance\Events\CashMovementCreated::class => [
        new \App\Modules\Finance\Listeners\CashRegisterHandler(),
        new \App\Modules\Finance\Listeners\AccountingHandler(),
    ],

    \App\Modules\Finance\Events\ExpenseValidated::class => [
        new \App\Modules\Finance\Listeners\AccountingHandler(),
    ],

    // ── Module Finance V2 — Dépenses / Décaissements ─────────────────────────

    \App\Modules\Finance\Events\DecaissementCreated::class => [
        new \App\Modules\Finance\Listeners\DecaissementHandler(),
    ],

    \App\Modules\Finance\Events\DecaissementValidated::class => [
        new \App\Modules\Finance\Listeners\DecaissementHandler(),
    ],

    \App\Modules\Finance\Events\DecaissementApproved::class => [
        new \App\Modules\Finance\Listeners\DecaissementHandler(),
    ],

    \App\Modules\Finance\Events\DecaissementRejected::class => [
        new \App\Modules\Finance\Listeners\DecaissementHandler(),
    ],

    \App\Modules\Finance\Events\DecaissementCancelled::class => [
        new \App\Modules\Finance\Listeners\DecaissementHandler(),
    ],

    \App\Modules\Finance\Events\JournalEntryCreated::class => [
        new \App\Modules\Finance\Listeners\AccountingHandler(),
    ],

    \App\Modules\Finance\Events\FiscalYearClosed::class => [
        new \App\Modules\Finance\Listeners\AccountingHandler(),
    ],

    \App\Modules\Finance\Events\FiscalYearOpened::class => [
        new \App\Modules\Finance\Listeners\AccountingHandler(),
    ],

    // Phase 3.7 — Rapports (audit only)
    \App\Modules\Finance\Events\FinancialReportGenerated::class => [
        new \App\Modules\Finance\Listeners\ReportHandler(),
    ],

    \App\Modules\Finance\Events\FinancialReportExported::class => [
        new \App\Modules\Finance\Listeners\ReportHandler(),
    ],

    // ── Module Vie Scolaire V2 — Absences ────────────────────────────────────

    StudentAbsent::class => [
        new AttendanceHandler(),
        new DisciplineIntegrationHandler(),
        new CrossModuleListener(),
    ],

    AbsenceJustified::class => [
        new AttendanceHandler(),
    ],

    AbsenceRejected::class => [
        new AttendanceHandler(),
    ],

    // ── Module Vie Scolaire V2 — Présences / Appel ───────────────────────────

    AttendanceStarted::class => [
        new AuditListener(),
    ],

    AttendanceValidated::class => [
        new AuditListener(),
        new NotificationListener(),
        new StatisticsListener(),
    ],

    AttendanceCompleted::class => [
        new AuditListener(),
        new StatisticsListener(),
    ],

    StudentPresent::class => [
        new AuditListener(),
    ],

    PresenceStudentAbsent::class => [
        new AuditListener(),
        new NotificationListener(),
    ],

    StudentLate::class => [
        new AuditListener(),
        new NotificationListener(),
        new StatisticsListener(),
    ],

    // ── Module Vie Scolaire V2 — Retards ─────────────────────────────────────

    RetardStudentLate::class => [
        new RetardAuditListener(),
        new RetardNotificationListener(),
        new RetardStatisticsListener(),
        new DisciplineIntegrationHandler(),
    ],

    LateJustified::class => [
        new RetardAuditListener(),
        new RetardNotificationListener(),
    ],

    LateRejected::class => [
        new RetardAuditListener(),
        new RetardNotificationListener(),
    ],

    LateThresholdReached::class => [
        new RetardAuditListener(),
        new RetardNotificationListener(),
        new DisciplineIntegrationHandler(),
        new CrossModuleListener(),
    ],

    // ── Module Vie Scolaire V2 — Discipline ──────────────────────────────────

    DisciplineCaseCreated::class => [
        new DisciplineAuditListener(),
        new DisciplineNotificationListener(),
        new DisciplineStatisticsListener(),
        new CrossModuleListener(),
    ],

    DisciplinaryActionAssigned::class => [
        new DisciplineAuditListener(),
        new DisciplineNotificationListener(),
    ],

    DisciplineCaseClosed::class => [
        new DisciplineAuditListener(),
        new DisciplineNotificationListener(),
    ],

    DisciplineAppealSubmitted::class => [
        new DisciplineAuditListener(),
        new DisciplineNotificationListener(),
    ],

    // ── Module Vie Scolaire V2 — Récompenses ─────────────────────────────────

    RewardGranted::class => [
        new RewardAuditListener(),
        new RewardNotificationListener(),
        new RewardStatisticsListener(),
        new CrossModuleListener(),
    ],

    RewardUpdated::class => [
        new RewardAuditListener(),
        new RewardNotificationListener(),
    ],

    RewardRevoked::class => [
        new RewardAuditListener(),
        new RewardNotificationListener(),
    ],

    // ── Module Vie Scolaire V2 — Emplois du temps ────────────────────────────

    TimetableCreated::class => [
        new EdtAuditListener(),
        new EdtNotificationListener(),
    ],

    TimetableUpdated::class => [
        new EdtAuditListener(),
        new EdtStatisticsListener(),
    ],

    TimetablePublished::class => [
        new EdtAuditListener(),
        new EdtNotificationListener(),
        new EdtStatisticsListener(),
        new CrossModuleListener(),
    ],

    TimetableConflictDetected::class => [
        new EdtAuditListener(),
        new EdtStatisticsListener(),
    ],

    TeacherReplacementAssigned::class => [
        new EdtAuditListener(),
        new EdtNotificationListener(),
        new CrossModuleListener(),
    ],

    // ── Module Vie Scolaire V2 — Activités scolaires ─────────────────────────

    ActivityCreated::class => [
        new ActivityAuditListener(),
        new ActivityNotificationListener(),
        new ActivityStatisticsListener(),
    ],

    ActivityUpdated::class => [
        new ActivityAuditListener(),
        new ActivityStatisticsListener(),
    ],

    ActivityPublished::class => [
        new ActivityAuditListener(),
        new ActivityNotificationListener(),
        new ActivityStatisticsListener(),
    ],

    ActivityCancelled::class => [
        new ActivityAuditListener(),
        new ActivityNotificationListener(),
        new ActivityStatisticsListener(),
    ],

    StudentRegisteredToActivity::class => [
        new ActivityAuditListener(),
        new ActivityNotificationListener(),
        new ActivityStatisticsListener(),
        new CrossModuleListener(),
    ],

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
        new CrossModuleListener(),
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
        new CrossModuleListener(),
    ],

    \App\Modules\RH\Conges\Events\LeaveRejected::class => [
        new \App\Modules\RH\Conges\Listeners\AuditListener(),
        new \App\Modules\RH\Conges\Listeners\NotificationListener(),
        new \App\Modules\RH\Conges\Listeners\StatisticsListener(),
        new CrossModuleListener(),
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
        new CrossModuleListener(),
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
        new CrossModuleListener(),
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
        new CrossModuleListener(),
    ],

    \App\Modules\RH\Formations\Events\CertificationGranted::class => [
        new \App\Modules\RH\Formations\Listeners\AuditListener(),
        new \App\Modules\RH\Formations\Listeners\NotificationListener(),
        new \App\Modules\RH\Formations\Listeners\StatisticsListener(),
        new CrossModuleListener(),
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
        new CrossModuleListener(),
    ],

    \App\Modules\RH\Documents\Events\HRDocumentArchived::class => [
        new \App\Modules\RH\Documents\Listeners\AuditListener(),
        new \App\Modules\RH\Documents\Listeners\NotificationListener(),
        new \App\Modules\RH\Documents\Listeners\StatisticsListener(),
    ],

    // ── Module Documents V2 ──────────────────────────────────────────────────

    \App\Modules\Documents\Events\DocumentUploaded::class => [
        new \App\Modules\Documents\Listeners\AuditListener(),
        new \App\Modules\Documents\Listeners\QuotaListener(),
        new \App\Modules\Documents\Listeners\SearchIndexListener(),
    ],

    \App\Modules\Documents\Events\DocumentVersioned::class => [
        new \App\Modules\Documents\Listeners\AuditListener(),
        new \App\Modules\Documents\Listeners\QuotaListener(),
    ],

    \App\Modules\Documents\Events\DocumentArchived::class => [
        new \App\Modules\Documents\Listeners\AuditListener(),
    ],

    \App\Modules\Documents\Events\DocumentRestored::class => [
        new \App\Modules\Documents\Listeners\AuditListener(),
    ],

    \App\Modules\Documents\Events\DocumentTrashed::class => [
        new \App\Modules\Documents\Listeners\AuditListener(),
    ],

    \App\Modules\Documents\Events\DocumentPurged::class => [
        new \App\Modules\Documents\Listeners\AuditListener(),
        new \App\Modules\Documents\Listeners\QuotaListener(),
        new \App\Modules\Documents\Listeners\SearchIndexListener(),
    ],

    \App\Modules\Documents\Events\DocumentShared::class => [
        new \App\Modules\Documents\Listeners\AuditListener(),
        new \App\Modules\Documents\Listeners\NotificationListener(),
        new CrossModuleListener(),
    ],

    \App\Modules\Documents\Events\DocumentShareRevoked::class => [
        new \App\Modules\Documents\Listeners\AuditListener(),
    ],

    \App\Modules\Documents\Events\DocumentTagged::class => [
        new \App\Modules\Documents\Listeners\AuditListener(),
    ],

    \App\Modules\Documents\Events\DocumentMoved::class => [
        new \App\Modules\Documents\Listeners\AuditListener(),
    ],

    \App\Modules\Documents\Events\DocumentExpired::class => [
        new \App\Modules\Documents\Listeners\AuditListener(),
        new \App\Modules\Documents\Listeners\NotificationListener(),
        new CrossModuleListener(),
    ],

    \App\Modules\Documents\Events\DocumentSignatureRequested::class => [
        new \App\Modules\Documents\Listeners\AuditListener(),
        new \App\Modules\Documents\Listeners\NotificationListener(),
    ],

    \App\Modules\Documents\Events\DocumentSigned::class => [
        new \App\Modules\Documents\Listeners\AuditListener(),
        new \App\Modules\Documents\Listeners\NotificationListener(),
    ],

    \App\Modules\Documents\Events\FolderCreated::class => [
        new \App\Modules\Documents\Listeners\AuditListener(),
    ],

    \App\Modules\Documents\Events\FolderDeleted::class => [
        new \App\Modules\Documents\Listeners\AuditListener(),
    ],

    \App\Modules\Documents\Events\QuotaExceeded::class => [
        new \App\Modules\Documents\Listeners\AuditListener(),
        new \App\Modules\Documents\Listeners\NotificationListener(),
    ],

    // ── Module Communication V2 — Événements propres ──────────────────────────

    NotificationCreated::class => [
        new CommunicationAuditListener(),
        new RealTimeListener(),
    ],

    NotificationRead::class => [
        new CommunicationAuditListener(),
    ],

    NotificationsBulkRead::class => [
        new CommunicationAuditListener(),
    ],

    MessageQueued::class => [
        new CommunicationAuditListener(),
    ],

    MessageSent::class => [
        new CommunicationAuditListener(),
        new RealTimeListener(),
    ],

    MessageFailed::class => [
        new CommunicationAuditListener(),
    ],

    ThreadCreated::class => [
        new CommunicationAuditListener(),
        new RealTimeListener(),
    ],

    ThreadMessageSent::class => [
        new CommunicationAuditListener(),
        new RealTimeListener(),
    ],

    DiffusionEnvoyee::class => [
        new CommunicationAuditListener(),
    ],

    CampagneLancee::class => [
        new CommunicationAuditListener(),
    ],

    CampagneTerminee::class => [
        new CommunicationAuditListener(),
    ],

    PreferencesUpdated::class => [
        new CommunicationAuditListener(),
    ],

    // ── Module Bibliothèque V2 ────────────────────────────────────────────────

    \App\Modules\Bibliotheque\Events\OuvrageAjoute::class => [
        new \App\Modules\Bibliotheque\Listeners\AuditListener(),
    ],

    \App\Modules\Bibliotheque\Events\OuvrageModifie::class => [
        new \App\Modules\Bibliotheque\Listeners\AuditListener(),
    ],

    \App\Modules\Bibliotheque\Events\OuvrageArchive::class => [
        new \App\Modules\Bibliotheque\Listeners\AuditListener(),
    ],

    \App\Modules\Bibliotheque\Events\ExemplaireAjoute::class => [
        new \App\Modules\Bibliotheque\Listeners\AuditListener(),
    ],

    \App\Modules\Bibliotheque\Events\ExemplaireStatutChange::class => [
        new \App\Modules\Bibliotheque\Listeners\AuditListener(),
    ],

    \App\Modules\Bibliotheque\Events\EmpruntCree::class => [
        new \App\Modules\Bibliotheque\Listeners\AuditListener(),
    ],

    \App\Modules\Bibliotheque\Events\EmpruntRetourne::class => [
        new \App\Modules\Bibliotheque\Listeners\AuditListener(),
    ],

    \App\Modules\Bibliotheque\Events\EmpruntEnRetard::class => [
        new \App\Modules\Bibliotheque\Listeners\AuditListener(),
        new \App\Modules\Bibliotheque\Listeners\BiblioNotificationHandler(),
        new CrossModuleListener(),
    ],

    \App\Modules\Bibliotheque\Events\EmpruntProlonge::class => [
        new \App\Modules\Bibliotheque\Listeners\AuditListener(),
    ],

    \App\Modules\Bibliotheque\Events\EmpruntPerdu::class => [
        new \App\Modules\Bibliotheque\Listeners\AuditListener(),
    ],

    \App\Modules\Bibliotheque\Events\ReservationCree::class => [
        new \App\Modules\Bibliotheque\Listeners\AuditListener(),
    ],

    \App\Modules\Bibliotheque\Events\ReservationDisponible::class => [
        new \App\Modules\Bibliotheque\Listeners\AuditListener(),
        new \App\Modules\Bibliotheque\Listeners\BiblioNotificationHandler(),
        new CrossModuleListener(),
    ],

    \App\Modules\Bibliotheque\Events\ReservationConfirmee::class => [
        new \App\Modules\Bibliotheque\Listeners\AuditListener(),
    ],

    \App\Modules\Bibliotheque\Events\ReservationAnnulee::class => [
        new \App\Modules\Bibliotheque\Listeners\AuditListener(),
    ],

    \App\Modules\Bibliotheque\Events\ReservationExpiree::class => [
        new \App\Modules\Bibliotheque\Listeners\AuditListener(),
    ],

    \App\Modules\Bibliotheque\Events\PenaliteCreee::class => [
        new \App\Modules\Bibliotheque\Listeners\AuditListener(),
        new \App\Modules\Bibliotheque\Listeners\BiblioNotificationHandler(),
        new CrossModuleListener(),
    ],

    \App\Modules\Bibliotheque\Events\PenalitePayee::class => [
        new \App\Modules\Bibliotheque\Listeners\AuditListener(),
    ],

    \App\Modules\Bibliotheque\Events\InventaireTermine::class => [
        new \App\Modules\Bibliotheque\Listeners\AuditListener(),
    ],

    // ═══════════════════════════════════════════════════════════
    // MODULE INVENTAIRE V2
    // ═══════════════════════════════════════════════════════════
    \App\Modules\Inventaire\Events\ArticleAjoute::class => [
        new \App\Modules\Inventaire\Listeners\InvAuditListener(),
    ],
    \App\Modules\Inventaire\Events\ArticleModifie::class => [
        new \App\Modules\Inventaire\Listeners\InvAuditListener(),
    ],
    \App\Modules\Inventaire\Events\ArticleArchive::class => [
        new \App\Modules\Inventaire\Listeners\InvAuditListener(),
    ],
    \App\Modules\Inventaire\Events\FournisseurAjoute::class => [
        new \App\Modules\Inventaire\Listeners\InvAuditListener(),
    ],
    \App\Modules\Inventaire\Events\FournisseurBloque::class => [
        new \App\Modules\Inventaire\Listeners\InvAuditListener(),
    ],
    \App\Modules\Inventaire\Events\CommandeCreee::class => [
        new \App\Modules\Inventaire\Listeners\InvAuditListener(),
    ],
    \App\Modules\Inventaire\Events\CommandeValidee::class => [
        new \App\Modules\Inventaire\Listeners\InvAuditListener(),
        new \App\Modules\Inventaire\Listeners\InvFinanceIntegrationListener(),
        new \App\Modules\Inventaire\Listeners\InvDocumentsIntegrationListener(),
    ],
    \App\Modules\Inventaire\Events\CommandeRecue::class => [
        new \App\Modules\Inventaire\Listeners\InvAuditListener(),
    ],
    \App\Modules\Inventaire\Events\StockEntree::class => [
        new \App\Modules\Inventaire\Listeners\InvAuditListener(),
    ],
    \App\Modules\Inventaire\Events\StockSortie::class => [
        new \App\Modules\Inventaire\Listeners\InvAuditListener(),
    ],
    \App\Modules\Inventaire\Events\StockTransfert::class => [
        new \App\Modules\Inventaire\Listeners\InvAuditListener(),
    ],
    \App\Modules\Inventaire\Events\StockAjustement::class => [
        new \App\Modules\Inventaire\Listeners\InvAuditListener(),
    ],
    \App\Modules\Inventaire\Events\StockAlerte::class => [
        new \App\Modules\Inventaire\Listeners\InvAuditListener(),
        new \App\Modules\Inventaire\Listeners\InvCommunicationListener(),
    ],
    \App\Modules\Inventaire\Events\AffectationCreee::class => [
        new \App\Modules\Inventaire\Listeners\InvAuditListener(),
        new \App\Modules\Inventaire\Listeners\InvRHIntegrationListener(),
        new \App\Modules\Inventaire\Listeners\InvCommunicationListener(),
    ],
    \App\Modules\Inventaire\Events\AffectationRetournee::class => [
        new \App\Modules\Inventaire\Listeners\InvAuditListener(),
        new \App\Modules\Inventaire\Listeners\InvRHIntegrationListener(),
    ],
    \App\Modules\Inventaire\Events\AffectationPerdue::class => [
        new \App\Modules\Inventaire\Listeners\InvAuditListener(),
        new \App\Modules\Inventaire\Listeners\InvRHIntegrationListener(),
    ],
    \App\Modules\Inventaire\Events\MaintenanceCreee::class => [
        new \App\Modules\Inventaire\Listeners\InvAuditListener(),
        new \App\Modules\Inventaire\Listeners\InvCommunicationListener(),
    ],
    \App\Modules\Inventaire\Events\MaintenanceTerminee::class => [
        new \App\Modules\Inventaire\Listeners\InvAuditListener(),
        new \App\Modules\Inventaire\Listeners\InvDocumentsIntegrationListener(),
    ],
    \App\Modules\Inventaire\Events\InventaireTermine::class => [
        new \App\Modules\Inventaire\Listeners\InvAuditListener(),
        new \App\Modules\Inventaire\Listeners\InvDocumentsIntegrationListener(),
        new \App\Modules\Inventaire\Listeners\InvCommunicationListener(),
    ],
    \App\Modules\Inventaire\Events\AmortissementCalcule::class => [
        new \App\Modules\Inventaire\Listeners\InvAuditListener(),
        new \App\Modules\Inventaire\Listeners\InvFinanceIntegrationListener(),
    ],

    // ── Module Rapports & BI V2 ───────────────────────────────────────────────

    \App\Modules\Rapports\Events\RapportGenere::class => [
        new \App\Modules\Rapports\Listeners\BiAuditListener(),
    ],

    \App\Modules\Rapports\Events\RapportPlanifie::class => [
        new \App\Modules\Rapports\Listeners\BiAuditListener(),
    ],

    \App\Modules\Rapports\Events\RapportExecute::class => [
        new \App\Modules\Rapports\Listeners\BiAuditListener(),
        new \App\Modules\Rapports\Listeners\BiNotificationListener(),
    ],

    \App\Modules\Rapports\Events\RapportExporte::class => [
        new \App\Modules\Rapports\Listeners\BiAuditListener(),
    ],

    \App\Modules\Rapports\Events\KpiSnapshot::class => [
        new \App\Modules\Rapports\Listeners\BiAuditListener(),
        new \App\Modules\Rapports\Listeners\BiSnapshotListener(),
    ],

    \App\Modules\Rapports\Events\DashboardConsulte::class => [
        new \App\Modules\Rapports\Listeners\BiAuditListener(),
    ],

    // ── Module Portails V2 (Phase 12.1) ──────────────────────────────────────
    \App\Modules\Portals\Events\PortalAccessed::class => [
        new \App\Modules\Portals\Listeners\PortalAuditListener(),
    ],

    \App\Modules\Portals\Events\DashboardViewed::class => [
        new \App\Modules\Portals\Listeners\PortalAuditListener(),
        new \App\Modules\Portals\Listeners\PortalAnalyticsListener(),
    ],

    \App\Modules\Portals\Events\WidgetRefreshed::class => [
        new \App\Modules\Portals\Listeners\PortalAnalyticsListener(),
    ],

    \App\Modules\Portals\Events\SearchPerformed::class => [
        new \App\Modules\Portals\Listeners\PortalAuditListener(),
        new \App\Modules\Portals\Listeners\PortalAnalyticsListener(),
    ],

    \App\Modules\Portals\Events\PreferencesSaved::class => [
        new \App\Modules\Portals\Listeners\PortalAuditListener(),
    ],

    \App\Modules\Portals\Events\ShortcutCreated::class => [
        new \App\Modules\Portals\Listeners\PortalAuditListener(),
    ],

    \App\Modules\Portals\Events\ShortcutDeleted::class => [
        new \App\Modules\Portals\Listeners\PortalAuditListener(),
    ],

    \App\Modules\Portals\Events\PortalError::class => [
        new \App\Modules\Portals\Listeners\PortalAuditListener(),
    ],

];
