<?php

namespace App\Controllers;

use Core\Platform\PlanAuditException;
use Core\Platform\PlanValidationException;
use Core\Platform\PlatformController;
use Core\Platform\PlatformSettings;
use Core\Session;
use Core\Database;

/**
 * Paramètres globaux de la plateforme. super_admin uniquement (lecture comme écriture : ce sont des réglages commerciaux).
 * Une valeur laissée vide supprime la surcharge (retour au .env, puis au défaut du code). Les changements sont audités (avant / après).
 */
class PlatformSettingsController extends PlatformController
{
    public function index(): void
    {
        $this->requirePlatformLevel('super_admin');
        $pdo = Database::getInstance()->getConnection();
        $actif = PlatformSettings::disponible($pdo);

        $this->render('platform/parametres/index', [
            'title'    => 'Paramètres',
            'actif'    => $actif,
            'reglages' => (new PlatformSettings($pdo))->tous(),
            'old'      => Session::getFlash('old', []),
        ], 'platform');
    }

    public function update(): void
    {
        $this->requirePlatformLevel('super_admin');
        $this->verifyCsrf();

        $donnees = [];
        foreach (array_keys(PlatformSettings::DEFINITIONS) as $cle) {
            if ($this->request->post($cle) !== null) {
                $donnees[$cle] = (string)$this->request->post($cle, '');
            }
        }
        try {
            $modifies = PlatformSettings::make()->enregistrer($donnees, $this->operatorId());
            Session::flash('success', $modifies === [] ? 'Aucun changement.' : count($modifies) . ' paramètre(s) enregistré(s).');
        } catch (PlanValidationException $e) {
            Session::flash('errors', $e->getErrors());
            Session::flash('old', $donnees);
        } catch (PlanAuditException $e) {
            Session::flash('errors', [$e->getMessage()]);
            Session::flash('old', $donnees);
        }
        $this->redirect(BASE_URL . '/platform/parametres');
    }
}
