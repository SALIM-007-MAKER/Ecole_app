<?php

/**
 * T008 — Phase 14.5 (Branding Multi-Tenant) — Seed
 *
 * Peuple `etablissement_branding` (créée vide en Phase 14.2, T004) pour
 * l'établissement existant (edunova-demo, id=1) avec EXACTEMENT les
 * valeurs actuellement codées en dur dans les layouts (app_name='EduNova',
 * logo edunova-logo.png, couleur violette #7c3aed) — condition de
 * non-régression visuelle absolue quand BrandingService remplacera ces
 * valeurs statiques.
 *
 * Peuple aussi les champs de branding étendus (police, thème, image de
 * connexion, contact, pied de page, affichage) dans `etab_settings`
 * (section='branding'), architecture extensible : toute future colonne de
 * personnalisation s'ajoute par une simple ligne clé/valeur, sans migration
 * de schéma.
 *
 * Idempotent, purement additif.
 *
 * Voir MULTI_TENANT_V2_BLUEPRINT.md §9.3, §10.
 */

return [
    'id'         => 'T008',
    'name'       => 'Seed branding établissement démo (etablissement_branding + etab_settings)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {

        $etabId = (int)$pdo->query(
            "SELECT id FROM etablissements WHERE slug = 'edunova-demo' LIMIT 1"
        )->fetchColumn();

        if (!$etabId) {
            throw new \RuntimeException("T008 : établissement 'edunova-demo' introuvable — exécuter T005 d'abord.");
        }

        // ── etablissement_branding (cache dénormalisé — cœur du branding) ────
        $stmt = $pdo->prepare(
            "INSERT INTO etablissement_branding
                (etablissement_id, logo_url, logo_dark_url, favicon_url, primary_color, secondary_color, app_name, welcome_message)
             VALUES (?, ?, NULL, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                logo_url = VALUES(logo_url), favicon_url = VALUES(favicon_url),
                primary_color = VALUES(primary_color), secondary_color = VALUES(secondary_color),
                app_name = VALUES(app_name), welcome_message = VALUES(welcome_message)"
        );
        $stmt->execute([
            $etabId,
            '/assets/img/edunova-logo.png',
            '/assets/img/edunova-logo.png',
            '#7c3aed',
            '#0ea5e9',
            'EduNova',
            'Bienvenue sur votre espace de gestion scolaire.',
        ]);

        // ── etab_settings (section='branding') — champs étendus ──────────────
        $settings = [
            ['font_family',       'Inter',    'string'],
            ['theme_mode',        'light',    'string'],   // light | dark | auto
            ['login_image_url',   '',         'string'],
            ['contact_phone',     '',         'string'],
            ['contact_email',     '',         'string'],
            ['contact_address',   '',         'string'],
            ['footer_text',       '© ' . date('Y') . ' EduNova — Tous droits réservés', 'string'],
            ['show_breadcrumbs',  '1',        'boolean'],
        ];

        $stmtSet = $pdo->prepare(
            "INSERT INTO etab_settings (etablissement_id, section, cle, valeur, type)
             VALUES (?, 'branding', ?, ?, ?)
             ON DUPLICATE KEY UPDATE valeur = valeur" // ne pas écraser une valeur déjà personnalisée
        );
        foreach ($settings as [$cle, $valeur, $type]) {
            $stmtSet->execute([$etabId, $cle, $valeur, $type]);
        }
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec("DELETE FROM etab_settings WHERE section = 'branding'");
        $pdo->exec("DELETE FROM etablissement_branding");
    },
];
