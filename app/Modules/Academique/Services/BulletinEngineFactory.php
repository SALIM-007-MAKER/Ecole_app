<?php

namespace App\Modules\Academique\Services;

use App\Modules\Academique\Repositories\BulletinRepository;
use App\Modules\Academique\Repositories\RankingRepository;
use Core\Logger;
use Core\Tenant\SettingsService;

/**
 * Point de construction unique du moteur de bulletin, configuré par
 * établissement (Paramètres > Notation) — évite que
 * BulletinController/ParentController/EspaceEleveController dupliquent le
 * même câblage manuel avec des seuils figés dans le code.
 *
 * Sans cette factory, `note_passage`/`seuil_rattrapage` modifiables dans
 * l'écran de paramétrage n'avaient aucun effet sur les bulletins réellement
 * générés (toujours 10.0/8.0 codés en dur) — voir ACADEMIQUE_MODULE_FREEZE.md
 * et l'audit métier du bulletin.
 *
 * Jetons de vérification : le secret vient UNIQUEMENT de BULLETIN_TOKEN_SECRET (environnement). Il n'est plus
 * généré ni stocké en base, et il n'existe aucune valeur par défaut. S'il manque ou s'il est invalide, le moteur
 * est quand même construit (les aperçus parents/élèves n'ont pas besoin de jeton) mais toute génération
 * persistée est refusée avec un message explicite (BulletinTokenException).
 */
class BulletinEngineFactory
{
    private static bool $secretAbsentSignale = false;

    public static function make(int $etablissementId): BulletinGenerator
    {
        $settings = SettingsService::make();

        $notePassage     = (float)$settings->get($etablissementId, 'academique', 'note_passage', '10');
        $seuilRattrapage = (float)$settings->get($etablissementId, 'academique', 'seuil_rattrapage', '8');

        $calculator    = new AcademicCalculationService(['note_passage' => $notePassage]);
        $rankingEngine = new RankingEngine($calculator, new RankingRepository(), ['note_passage' => $notePassage]);
        $bulletinRepo  = new BulletinRepository();

        return new BulletinGenerator(
            calculator: $calculator,
            rankingEngine: $rankingEngine,
            repo: $bulletinRepo,
            seuilRattrapage: $seuilRattrapage,
            notePassage: $notePassage,
            tokens: self::tokenService(),
        );
    }

    /** Service de jetons, ou null si BULLETIN_TOKEN_SECRET est absent/invalide (signalé une fois par processus). */
    private static function tokenService(): ?BulletinTokenService
    {
        try {
            return BulletinTokenService::fromEnvironment();
        } catch (BulletinTokenException $e) {
            if (!self::$secretAbsentSignale) {
                self::$secretAbsentSignale = true;
                Logger::warning('Jetons de bulletins indisponibles : ' . $e->getMessage());   // jamais la valeur du secret
            }
            return null;
        }
    }
}
