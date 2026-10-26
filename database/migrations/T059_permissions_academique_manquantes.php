<?php

/**
 * T059 — Permissions académiques déclarées mais jamais attribuées en base (parcours métier critiques, écarts 1 et 6)
 *
 * Constat : config/permissions.php attribue aux rôles admin et directeur `academique.evaluations.admin`, `academique.notes.admin` et
 * `academique.bulletin.admin` (et à l'enseignant `academique.moyennes.view`, `academique.classement.view`), mais le RBAC en base
 * (etab_*) ne les contient pas : administrateur et directeur ne peuvent ni générer, ni publier, ni archiver un bulletin (le code exige
 * `.generate/.publish/.archive` ou `.admin`). `academique.periodes.admin` (réouverture d'une période, modèle par défaut, déverrouillage),
 * exigé par PeriodeController, n'existait nulle part : il est créé ici et attribué à admin et directeur (config/permissions.php aussi).
 *
 * Périmètre : UNIQUEMENT ces huit permissions (dont `academique.types_evaluations.view|manage`, exigées par TypeEvaluationController et absentes partout : page 403 pour tous), rôles « admin » et « directeur » (et « enseignant » pour deux droits de lecture), rôles
 * système ET copies par établissement. Aucune autre permission n'est ajoutée, modifiée ni retirée (fiches élèves / enseignants intactes).
 * Idempotente. Les droits ne changent qu'à la prochaine connexion (ou au prochain rafraîchissement de session).
 * NON APPLIQUÉE À LA BASE RÉELLE tant qu'une autorisation distincte n'a pas été donnée (sauvegarde préalable obligatoire).
 * Retour arrière : retire ces attributions puis ces permissions (sauf celles qui existaient avant T059, jamais supprimées).
 */

return [
    'id'         => 'T059',
    'name'       => 'Permissions académiques manquantes (bulletin.admin, evaluations.admin, notes.admin, periodes.admin, moyennes/classement.view)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $defs = [
            // code => [libellé, action, rôles]
            'academique.bulletin.admin'     => ['Administrer les bulletins (générer, publier, archiver, appréciation du directeur)', 'admin', ['admin', 'directeur']],
            'academique.evaluations.admin'  => ['Administrer les évaluations', 'admin', ['admin', 'directeur']],
            'academique.notes.admin'        => ['Administrer les notes', 'admin', ['admin', 'directeur']],
            'academique.periodes.admin'     => ['Administrer les périodes (réouverture, modèle par défaut, déverrouillage)', 'admin', ['admin', 'directeur']],
            'academique.types_evaluations.view'   => ['Consulter les types d\'évaluation', 'view', ['admin', 'directeur']],
            'academique.types_evaluations.manage' => ['Gérer les types d\'évaluation', 'manage', ['admin', 'directeur']],
            'academique.moyennes.view'      => ['Consulter les moyennes', 'view', ['enseignant']],
            'academique.classement.view'    => ['Consulter le classement', 'view', ['enseignant']],
        ];
        $ins = $pdo->prepare('INSERT IGNORE INTO etab_permissions (code, libelle, module, action) VALUES (?, ?, \'academique\', ?)');
        foreach ($defs as $code => [$libelle, $action, $roles]) {
            $ins->execute([$code, $libelle, $action]);
            $in = "'" . implode("','", $roles) . "'";
            $pdo->prepare(
                "INSERT IGNORE INTO etab_role_permissions (role_id, permission_id)
                 SELECT r.id, p.id FROM etab_roles r JOIN etab_permissions p ON p.code = ?
                  WHERE r.code IN ($in)"
            )->execute([$code]);
        }
    },

    'rollback' => function (PDO $pdo): void {
        // Retire uniquement ce que T059 a ajouté : les attributions de ces six codes aux rôles visés. Les permissions elles-mêmes ne sont
        // supprimées que si plus aucun rôle ne les porte (celles de config/permissions.php déjà présentes avant T059 restent intactes).
        foreach (['academique.bulletin.admin', 'academique.evaluations.admin', 'academique.notes.admin', 'academique.periodes.admin', 'academique.types_evaluations.view', 'academique.types_evaluations.manage', 'academique.moyennes.view', 'academique.classement.view'] as $code) {
            $pdo->prepare('DELETE rp FROM etab_role_permissions rp JOIN etab_permissions p ON p.id = rp.permission_id JOIN etab_roles r ON r.id = rp.role_id WHERE p.code = ? AND r.code IN (\'admin\', \'directeur\', \'enseignant\')')->execute([$code]);
            $pdo->prepare('DELETE FROM etab_permissions WHERE code = ? AND NOT EXISTS (SELECT 1 FROM (SELECT permission_id FROM etab_role_permissions) x JOIN etab_permissions q ON q.id = x.permission_id WHERE q.code = ?)')->execute([$code, $code]);
        }
    },
];
