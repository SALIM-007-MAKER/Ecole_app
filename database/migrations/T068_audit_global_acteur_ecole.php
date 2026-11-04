<?php

/**
 * T068 — Journal d'audit global : acteur « school_user » (utilisateur d'un établissement)
 *
 * L'authentification globale (`/login` commun) écrit des événements `auth.*` dans `platform_audit_logs`. Leur acteur est un utilisateur
 * d'établissement, ni un opérateur de la plateforme ni un visiteur anonyme : la colonne `actor_type` reçoit une quatrième valeur.
 *
 *  - `ALTER TABLE platform_audit_logs MODIFY actor_type ENUM('operator','system','anonymous','school_user') NOT NULL DEFAULT 'operator'`.
 *    Extension PURE de l'ENUM : aucune ligne n'est modifiée, valeurs existantes et défaut inchangés. Le journal reste en ajout seul : les
 *    déclencheurs `trg_platform_audit_no_update` / `trg_platform_audit_no_delete` ne sont ni modifiés ni contournés (ils sont vérifiés
 *    après l'opération).
 *  - L'identifiant de l'utilisateur n'est PAS écrit dans `operator_id` (qui désigne un opérateur) mais dans `entity_id` avec
 *    `entity_type = 'user'`.
 *
 * Idempotente. Avant son application, le code reste fonctionnel : les événements sont alors écrits avec l'acteur « anonymous ».
 *
 * Retour arrière : restaure l'ENUM à trois valeurs UNIQUEMENT si aucune ligne n'utilise « school_user » (le journal est en ajout seul : on
 * ne réécrit pas l'historique) ; sinon l'opération est refusée avec un message explicite et rien n'est modifié.
 */

return [
    'id'         => 'T068',
    'name'       => 'Journal d\'audit global — acteur school_user (utilisateurs d\'établissement), extension pure de l\'ENUM',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $type = static fn(): string => (string)$pdo->query(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_audit_logs' AND COLUMN_NAME = 'actor_type'"
        )->fetchColumn();

        if ($type() === '') {
            throw new RuntimeException('platform_audit_logs est absente : appliquer T064 d\'abord.');
        }
        if (!str_contains($type(), "'school_user'")) {
            $pdo->exec("ALTER TABLE `platform_audit_logs` MODIFY COLUMN `actor_type` ENUM('operator','system','anonymous','school_user') NOT NULL DEFAULT 'operator'");
        }

        // Le journal doit rester en ajout seul : les deux déclencheurs doivent toujours exister.
        $n = (int)$pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = 'platform_audit_logs'
               AND TRIGGER_NAME IN ('trg_platform_audit_no_update', 'trg_platform_audit_no_delete')"
        )->fetchColumn();
        if ($n !== 2) {
            throw new RuntimeException('Les déclencheurs d\'ajout seul de platform_audit_logs sont absents : migration refusée.');
        }
    },

    'rollback' => function (PDO $pdo): void {
        $utilises = (int)$pdo->query("SELECT COUNT(*) FROM `platform_audit_logs` WHERE `actor_type` = 'school_user'")->fetchColumn();
        if ($utilises > 0) {
            throw new RuntimeException("Retour arrière refusé : {$utilises} ligne(s) du journal d'audit (en ajout seul) utilisent l'acteur « school_user ». Aucune modification effectuée.");
        }
        $pdo->exec("ALTER TABLE `platform_audit_logs` MODIFY COLUMN `actor_type` ENUM('operator','system','anonymous') NOT NULL DEFAULT 'operator'");
    },
];
