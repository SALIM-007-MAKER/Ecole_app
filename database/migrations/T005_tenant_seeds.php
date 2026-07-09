<?php

/**
 * T005 — Phase 14.2 (Infrastructure Tenant / Fondation) — Étapes A5 à A8
 *
 * Peuple les tables de fondation créées par T001-T004 :
 *   A5. 1 établissement de démo (deviendra etablissement_id=1, utilisé par
 *       la Phase 14.3 pour rattacher les données V1 existantes)
 *   A6. Plans SaaS Starter/Pro/Enterprise
 *   A7. Permissions importées depuis config/permissions.php → etab_permissions
 *   A8. Rôles système → etab_roles (etablissement_id = NULL)
 *
 * Idempotent (INSERT IGNORE / ON DUPLICATE KEY) — rejouable sans effet de bord.
 * Purement additif — aucune table existante n'est modifiée.
 */

return [
    'id'         => 'T005',
    'name'       => 'Seeds fondation tenant (établissement démo, plans, permissions, rôles)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {

        // ── A6. Plans SaaS ────────────────────────────────────────────────────
        $plans = [
            // code,       nom,          max_users, max_eleves, storage_mb, api/jour, modules_json,                                           mensuel,  annuel,   devise
            ['starter',    'Starter',    20,  200,  512,  2000,  '["scolarite","academique","finance"]',                                        2500.00,  25000.00, 'DZD'],
            ['pro',        'Pro',        80,  1500, 5120, 20000, '["scolarite","academique","finance","vie_scolaire","rh"]',                     6500.00,  65000.00, 'DZD'],
            ['enterprise', 'Enterprise', 500, 10000,51200,100000,'["scolarite","academique","finance","vie_scolaire","rh","documents","bibliotheque","inventaire","rapports","portals","api"]', 15000.00, 150000.00, 'DZD'],
        ];
        $stmtPlan = $pdo->prepare(
            "INSERT INTO platform_plans (code, nom, max_users, max_eleves, storage_quota_mb, api_calls_per_day, modules_inclus, prix_mensuel, prix_annuel, devise)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE nom = VALUES(nom)"
        );
        foreach ($plans as $p) {
            $stmtPlan->execute($p);
        }

        $proPlanId = (int)$pdo->query("SELECT id FROM platform_plans WHERE code = 'pro'")->fetchColumn();

        // ── A5. Établissement de démo ─────────────────────────────────────────
        // Devient l'établissement d'id=1 (première ligne d'une table vide) —
        // c'est l'établissement auquel la Phase 14.3 rattachera les données
        // V1 existantes (users, classes, eleves, ...).
        $exists = $pdo->query("SELECT id FROM etablissements WHERE slug = 'edunova-demo'")->fetchColumn();
        if (!$exists) {
            $stmt = $pdo->prepare(
                "INSERT INTO etablissements
                    (slug, nom, nom_court, type, pays, plan_id, statut, activated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                'edunova-demo',
                'EduNova — Établissement Démo',
                'EduNova',
                'lycee',
                'DZ',
                $proPlanId ?: null,
                'active',
            ]);
        }

        // ── A7. Permissions depuis config/permissions.php ────────────────────
        $permissionsByRole = require ROOT_PATH . '/config/permissions.php';
        $allCodes = [];
        foreach ($permissionsByRole as $codes) {
            foreach ($codes as $code) {
                $allCodes[$code] = true;
            }
        }

        $stmtPerm = $pdo->prepare(
            "INSERT INTO etab_permissions (code, libelle, module, action)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE libelle = VALUES(libelle)"
        );
        foreach (array_keys($allCodes) as $code) {
            $segments = explode('.', $code);
            $action   = end($segments);
            $module   = $segments[0];
            $libelle  = ucfirst(str_replace(['.', '_'], ' ', $code));
            $stmtPerm->execute([$code, $libelle, $module, $action]);
        }

        // ── A8. Rôles système (etablissement_id = NULL) ──────────────────────
        $systemRoles = [
            ['admin',      'Administrateur', 'Accès total à la plateforme'],
            ['directeur',  'Directeur',      'Direction de l\'établissement'],
            ['secretaire', 'Secrétaire',     'Administration scolaire'],
            ['comptable',  'Comptable',      'Gestion financière'],
            ['enseignant', 'Enseignant',     'Corps pédagogique'],
            ['parent',     'Parent',         'Représentant légal de l\'élève'],
            ['eleve',      'Élève',          'Apprenant'],
        ];
        $stmtRole = $pdo->prepare(
            "INSERT INTO etab_roles (etablissement_id, code, nom, description, is_system)
             VALUES (NULL, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE nom = VALUES(nom)"
        );
        foreach ($systemRoles as [$code, $nom, $desc]) {
            $stmtRole->execute([$code, $nom, $desc]);
        }
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec("DELETE FROM etab_roles WHERE is_system = 1 AND etablissement_id IS NULL");
        $pdo->exec("DELETE FROM etab_permissions");
        $pdo->exec("DELETE FROM etablissements WHERE slug = 'edunova-demo'");
        $pdo->exec("DELETE FROM platform_plans WHERE code IN ('starter','pro','enterprise')");
    },
];
