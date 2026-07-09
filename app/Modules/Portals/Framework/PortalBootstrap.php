<?php
declare(strict_types=1);

namespace App\Modules\Portals\Framework;

use App\Modules\Portals\Shared\Widgets\NotificationWidget;
use App\Modules\Portals\Shared\Widgets\MessageWidget;
use App\Modules\Portals\Shared\Widgets\ShortcutsWidget;
use App\Modules\Portals\Shared\Widgets\AnnouncementsWidget;
use App\Modules\Portals\Shared\Search\EleveSearchHandler;
use App\Modules\Portals\Shared\Search\DocumentSearchHandler;

// Admin
use App\Modules\Portals\Admin\Widgets\StatsGlobalesWidget;
use App\Modules\Portals\Admin\Widgets\ModulesStatusWidget;
use App\Modules\Portals\Admin\Widgets\ActiviteRecenteWidget;
use App\Modules\Portals\Admin\Widgets\UsersActifsWidget;
use App\Modules\Portals\Admin\Widgets\AlertesSystèmeWidget;
use App\Modules\Portals\Admin\Widgets\RepartitionRolesWidget;
use App\Modules\Portals\Admin\Widgets\CapaciteWidget;
use App\Modules\Portals\Admin\Widgets\SessionsActivesWidget;

// Direction
use App\Modules\Portals\Direction\Widgets\KpiScolariteWidget;
use App\Modules\Portals\Direction\Widgets\KpiAcademiqueWidget;
use App\Modules\Portals\Direction\Widgets\KpiFinanceWidget;
use App\Modules\Portals\Direction\Widgets\KpiRhWidget;
use App\Modules\Portals\Direction\Widgets\AbsencesSemaineWidget;
use App\Modules\Portals\Direction\Widgets\PaiementsRecentWidget;
use App\Modules\Portals\Direction\Widgets\BulletinStatsWidget;
use App\Modules\Portals\Direction\Widgets\AlertesDirectionWidget;
use App\Modules\Portals\Direction\Widgets\CalendrierWidget;

// Enseignant
use App\Modules\Portals\Enseignant\Widgets\MonEmploiDuTempsWidget;
use App\Modules\Portals\Enseignant\Widgets\MesClassesWidget;
use App\Modules\Portals\Enseignant\Widgets\AbsencesClasseWidget;
use App\Modules\Portals\Enseignant\Widgets\EvaluationsANoterWidget;
use App\Modules\Portals\Enseignant\Widgets\StatsNotesWidget;
use App\Modules\Portals\Enseignant\Widgets\ProchainsCoursWidget;
use App\Modules\Portals\Enseignant\Widgets\MessagesRecentsWidget;

// Eleve
use App\Modules\Portals\Eleve\Widgets\MonEmploiDuTempsEleveWidget;
use App\Modules\Portals\Eleve\Widgets\NotesRecentesWidget;
use App\Modules\Portals\Eleve\Widgets\AbsencesEleveWidget;
use App\Modules\Portals\Eleve\Widgets\EmpruntsWidget;
use App\Modules\Portals\Eleve\Widgets\BulletinDisponibleWidget;
use App\Modules\Portals\Eleve\Widgets\ActivitesEleveWidget;
use App\Modules\Portals\Eleve\Widgets\ProchinesEcheancesWidget;
use App\Modules\Portals\Eleve\Widgets\MessagesEleveWidget;

// Parent
use App\Modules\Portals\Parent\Widgets\MesEnfantsWidget;
use App\Modules\Portals\Parent\Widgets\AbsencesRecentesWidget;
use App\Modules\Portals\Parent\Widgets\PaiementsEnAttenteWidget;
use App\Modules\Portals\Parent\Widgets\NotesRecentesParentWidget;
use App\Modules\Portals\Parent\Widgets\BulletinsParentWidget;
use App\Modules\Portals\Parent\Widgets\MessagesParentWidget;
use App\Modules\Portals\Parent\Widgets\ProchainsEvenementsWidget;

// Comptabilite
use App\Modules\Portals\Comptabilite\Widgets\CaisseDuJourWidget;
use App\Modules\Portals\Comptabilite\Widgets\FacturesEnAttenteWidget;
use App\Modules\Portals\Comptabilite\Widgets\RecettesMoisWidget;
use App\Modules\Portals\Comptabilite\Widgets\ImpayesWidget;
use App\Modules\Portals\Comptabilite\Widgets\TauxRecouvrementWidget;
use App\Modules\Portals\Comptabilite\Widgets\DerniersPaiementsWidget;
use App\Modules\Portals\Comptabilite\Widgets\ComparatifMensuelWidget;
use App\Modules\Portals\Comptabilite\Widgets\DepensesMoisWidget;

// RH
use App\Modules\Portals\RH\Widgets\PresencesAujourdhuiWidget;
use App\Modules\Portals\RH\Widgets\CongesEnAttenteWidget;
use App\Modules\Portals\RH\Widgets\ContratsExpirationWidget;
use App\Modules\Portals\RH\Widgets\EffectifsWidget;
use App\Modules\Portals\RH\Widgets\FormationsEnCoursWidget;
use App\Modules\Portals\RH\Widgets\EvaluationsPlanifieesWidget;
use App\Modules\Portals\RH\Widgets\AlertesRhWidget;
use App\Modules\Portals\RH\Widgets\AbsencesRhWidget;

/**
 * Bootstrap idempotent du Portal Framework.
 * Appelé depuis PortalBaseController::__construct() — s'exécute une seule fois par requête.
 */
class PortalBootstrap
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        // Widgets partagés (tous portails)
        WidgetRegistry::register(new NotificationWidget());
        WidgetRegistry::register(new MessageWidget());
        WidgetRegistry::register(new ShortcutsWidget());
        WidgetRegistry::register(new AnnouncementsWidget());

        // Handlers de recherche globale
        GlobalSearchEngine::registerHandler(new EleveSearchHandler());
        GlobalSearchEngine::registerHandler(new DocumentSearchHandler());

        // ── Admin (8) ─────────────────────────────────────────
        WidgetRegistry::register(new StatsGlobalesWidget());
        WidgetRegistry::register(new ModulesStatusWidget());
        WidgetRegistry::register(new ActiviteRecenteWidget());
        WidgetRegistry::register(new UsersActifsWidget());
        WidgetRegistry::register(new AlertesSystèmeWidget());
        WidgetRegistry::register(new RepartitionRolesWidget());
        WidgetRegistry::register(new CapaciteWidget());
        WidgetRegistry::register(new SessionsActivesWidget());

        // ── Direction (9) ─────────────────────────────────────
        WidgetRegistry::register(new KpiScolariteWidget());
        WidgetRegistry::register(new KpiAcademiqueWidget());
        WidgetRegistry::register(new KpiFinanceWidget());
        WidgetRegistry::register(new KpiRhWidget());
        WidgetRegistry::register(new AbsencesSemaineWidget());
        WidgetRegistry::register(new PaiementsRecentWidget());
        WidgetRegistry::register(new BulletinStatsWidget());
        WidgetRegistry::register(new AlertesDirectionWidget());
        WidgetRegistry::register(new CalendrierWidget());

        // ── Enseignant (7) ────────────────────────────────────
        WidgetRegistry::register(new MonEmploiDuTempsWidget());
        WidgetRegistry::register(new MesClassesWidget());
        WidgetRegistry::register(new AbsencesClasseWidget());
        WidgetRegistry::register(new EvaluationsANoterWidget());
        WidgetRegistry::register(new StatsNotesWidget());
        WidgetRegistry::register(new ProchainsCoursWidget());
        WidgetRegistry::register(new MessagesRecentsWidget());

        // ── Élève (8) ─────────────────────────────────────────
        WidgetRegistry::register(new MonEmploiDuTempsEleveWidget());
        WidgetRegistry::register(new NotesRecentesWidget());
        WidgetRegistry::register(new AbsencesEleveWidget());
        WidgetRegistry::register(new EmpruntsWidget());
        WidgetRegistry::register(new BulletinDisponibleWidget());
        WidgetRegistry::register(new ActivitesEleveWidget());
        WidgetRegistry::register(new ProchinesEcheancesWidget());
        WidgetRegistry::register(new MessagesEleveWidget());

        // ── Parent (7) ────────────────────────────────────────
        WidgetRegistry::register(new MesEnfantsWidget());
        WidgetRegistry::register(new AbsencesRecentesWidget());
        WidgetRegistry::register(new PaiementsEnAttenteWidget());
        WidgetRegistry::register(new NotesRecentesParentWidget());
        WidgetRegistry::register(new BulletinsParentWidget());
        WidgetRegistry::register(new MessagesParentWidget());
        WidgetRegistry::register(new ProchainsEvenementsWidget());

        // ── Comptabilité (8) ──────────────────────────────────
        WidgetRegistry::register(new CaisseDuJourWidget());
        WidgetRegistry::register(new FacturesEnAttenteWidget());
        WidgetRegistry::register(new RecettesMoisWidget());
        WidgetRegistry::register(new ImpayesWidget());
        WidgetRegistry::register(new TauxRecouvrementWidget());
        WidgetRegistry::register(new DerniersPaiementsWidget());
        WidgetRegistry::register(new ComparatifMensuelWidget());
        WidgetRegistry::register(new DepensesMoisWidget());

        // ── RH (8) ────────────────────────────────────────────
        WidgetRegistry::register(new PresencesAujourdhuiWidget());
        WidgetRegistry::register(new CongesEnAttenteWidget());
        WidgetRegistry::register(new ContratsExpirationWidget());
        WidgetRegistry::register(new EffectifsWidget());
        WidgetRegistry::register(new FormationsEnCoursWidget());
        WidgetRegistry::register(new EvaluationsPlanifieesWidget());
        WidgetRegistry::register(new AlertesRhWidget());
        WidgetRegistry::register(new AbsencesRhWidget());
    }

    /** Point d'extension pour les widgets tiers */
    public static function registerWidget(\App\Modules\Portals\Contracts\WidgetInterface $widget): void
    {
        WidgetRegistry::register($widget);
    }

    /** Point d'extension pour les handlers de recherche tiers */
    public static function registerSearchHandler(\App\Modules\Portals\Contracts\SearchHandlerInterface $handler): void
    {
        GlobalSearchEngine::registerHandler($handler);
    }

    public static function reset(): void
    {
        self::$booted = false;
        WidgetRegistry::reset();
        GlobalSearchEngine::reset();
    }
}
