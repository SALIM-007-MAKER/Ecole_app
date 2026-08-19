<?php

namespace App\Modules\Academique\Services;

use App\Modules\Academique\Repositories\BulletinRepository;
use App\Modules\Academique\Repositories\RankingRepository;
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
 */
class BulletinEngineFactory
{
    public static function make(int $etablissementId): BulletinGenerator
    {
        $settings = SettingsService::make();

        $notePassage     = (float)$settings->get($etablissementId, 'academique', 'note_passage', '10');
        $seuilRattrapage = (float)$settings->get($etablissementId, 'academique', 'seuil_rattrapage', '8');
        $bulletinSecret   = self::getOrCreateSecret($settings, $etablissementId);

        $calculator    = new AcademicCalculationService(['note_passage' => $notePassage]);
        $rankingEngine = new RankingEngine($calculator, new RankingRepository(), ['note_passage' => $notePassage]);
        $bulletinRepo  = new BulletinRepository();

        return new BulletinGenerator(
            calculator: $calculator,
            rankingEngine: $rankingEngine,
            repo: $bulletinRepo,
            seuilRattrapage: $seuilRattrapage,
            notePassage: $notePassage,
            appKey: $bulletinSecret,
        );
    }

    /**
     * Secret propre à l'établissement utilisé comme sel du jeton de
     * vérification QR (remplace l'ancien littéral codé en dur 'ecole_app_v2',
     * devinable puisque partagé par tous les établissements et visible dans
     * le code source). Généré à la volée au premier bulletin, puis persisté —
     * même mécanisme que les settings existants (etab_settings), aucune
     * nouvelle table nécessaire.
     */
    private static function getOrCreateSecret(SettingsService $settings, int $etablissementId): string
    {
        $secret = $settings->get($etablissementId, 'academique', 'bulletin_secret', null);
        if (is_string($secret) && $secret !== '') {
            return $secret;
        }

        $secret = bin2hex(random_bytes(32));
        $settings->set($etablissementId, 'academique', 'bulletin_secret', $secret, 'string');
        return $secret;
    }
}
