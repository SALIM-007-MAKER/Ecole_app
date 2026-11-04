<?php

/**
 * T041 — Suppression définitive des tables V1 (absences, planning), dernière
 * étape du cycle "V1 → V2 → retrait progressif" pour ces deux domaines.
 *
 * Préalables déjà vérifiés avant cette migration :
 *   - Toutes les données réelles migrées vers V2 et vérifiées en parité
 *     (T037 absences, T038 référentiel EDT, T039 retards, T040 correction
 *     d'une justification à partir de la donnée réelle V1).
 *   - Plus aucun contrôleur/modèle/vue V1 dans le dépôt (supprimés avant
 *     cette migration) ; plus aucune route ne les sert.
 *   - Sauvegarde complète (structure + données) prise avant exécution :
 *     database/backups/v1_tables_backup_before_drop_20260821.sql
 *     (non versionnée — contient des données personnelles).
 *
 * Tables supprimées : `justifications` (dépend de `absences`), `absences`
 * (dépend de `creneaux`), `emplois_du_temps` (dépend de `salles`/`creneaux`),
 * `salles`, `creneaux`. FK désactivées le temps de l'opération pour ne pas
 * dépendre de l'ordre exact des contraintes croisées.
 *
 * Irréversible — 'reversible' => false à dessein : la restauration passe par
 * le fichier de sauvegarde ci-dessus, pas par un rollback automatique.
 */

return [
    'id'         => 'T041',
    'name'       => 'Suppression des tables V1 : absences, justifications, emplois_du_temps, salles, creneaux',
    'reversible' => false,

    'run' => function (PDO $pdo): void {
        $tables = ['justifications', 'absences', 'emplois_du_temps', 'salles', 'creneaux'];

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($tables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
                error_log("[T041] Table `{$table}` supprimée.");
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    },
];
