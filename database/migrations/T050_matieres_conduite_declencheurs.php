<?php

/**
 * T050 — Déclencheurs de protection de la matière de Conduite (BEFORE INSERT / UPDATE / DELETE sur `matieres`)
 *
 * Empêche, même en SQL direct, de supprimer la Conduite, de retirer son marqueur, de changer son coefficient (≠ 1),
 * son volume horaire (≠ 0) ou son établissement, et de marquer une matière non conforme. Le libellé reste modifiable.
 * Les trois déclencheurs sont définis dans App\Modules\Academique\Services\ConduiteProtectionStatus.
 *
 * Tout ou rien : si un déclencheur ne peut pas être créé, ceux déjà créés sont supprimés et la migration ÉCHOUE avec
 * le message « T050 ÉCHEC — la protection SQL de la Conduite N'EST PAS opérationnelle » (migrate.php journalise `failed`
 * et retourne 1 ; la migration est retentée au passage suivant). Elle ne réussit que si la vérification finale
 * (présence, moment, événement, corps) est valide. Idempotente.
 *
 * Limites : TRUNCATE, DROP, ALTER et les comptes ayant TRIGGER ou SUPER contournent la protection ; un export mysqldump
 * écrit un DEFINER à retirer pour restaurer sur un autre serveur. Voir docs/deploiement/CONDUITE_DECLENCHEUR.md.
 *
 * Retour arrière : T050, puis T049, puis T048.
 */

require_once dirname(__DIR__, 2) . '/app/Modules/Academique/Services/ConduiteProtectionStatus.php';

use App\Modules\Academique\Services\ConduiteProtectionStatus;

return [
    'id'         => 'T050',
    'name'       => 'Matières — déclencheurs de protection de la Conduite',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        // Hook d'injection de panne, RÉSERVÉ aux copies jetables de test (jamais actif sur une autre base).
        $hook = null;
        $panne = getenv('CONDUITE_T050_FAIL_AT');
        $base  = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($panne !== false && ctype_digit($panne) && preg_match('/^ecole_app_copy_[a-f0-9]+$/', $base)) {
            $hook = static function (int $rang) use ($panne): void {
                if ($rang === (int)$panne) { throw new RuntimeException('panne injectée (test) au déclencheur ' . $rang); }
            };
        }
        ConduiteProtectionStatus::installer($pdo, $hook);
    },

    'rollback' => function (PDO $pdo): void {
        ConduiteProtectionStatus::supprimer($pdo);
    },
];
