<?php

/**
 * T024 — Corrige T023 : ajoute la catégorie 'avoir' (troisième branche)
 *
 * `AccountingService::resoudreReglePaiement()` (avant refactor) utilisait
 * en réalité TROIS branches, pas deux : CASH_MODES → 'payment_esp',
 * BANK_MODES → 'payment_bnq', et tout le reste (donc 'AVOIR', qui n'était
 * dans AUCUNE des deux listes) → 'payment_avoir'. T023 avait classé
 * AVOIR comme 'caisse' par erreur, ce qui aurait changé son comportement
 * comptable (routage vers la règle 'payment_esp' au lieu de
 * 'payment_avoir'). Corrigé ici avant toute utilisation applicative de la
 * colonne `categorie` — voir NIGER_APP_CONFIGURATION_AUDIT.md.
 *
 * Idempotente.
 */

return [
    'id'         => 'T024',
    'name'       => "finance_modes_paiement — categorie ENUM + 'avoir' (corrige T023)",
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $col = $pdo->query("SHOW COLUMNS FROM `finance_modes_paiement` LIKE 'categorie'")->fetch(PDO::FETCH_ASSOC);
        if ($col && !str_contains($col['Type'], "'avoir'")) {
            $pdo->exec(
                "ALTER TABLE `finance_modes_paiement`
                 MODIFY COLUMN `categorie` ENUM('caisse','banque','avoir') NOT NULL DEFAULT 'caisse'"
            );
        }
        $pdo->exec("UPDATE `finance_modes_paiement` SET categorie = 'avoir' WHERE code = 'AVOIR'");
    },

    'rollback' => function (PDO $pdo): void {
        $pdo->exec("UPDATE `finance_modes_paiement` SET categorie = 'caisse' WHERE code = 'AVOIR'");
        $col = $pdo->query("SHOW COLUMNS FROM `finance_modes_paiement` LIKE 'categorie'")->fetch(PDO::FETCH_ASSOC);
        if ($col && str_contains($col['Type'], "'avoir'")) {
            $pdo->exec(
                "ALTER TABLE `finance_modes_paiement`
                 MODIFY COLUMN `categorie` ENUM('caisse','banque') NOT NULL DEFAULT 'caisse'"
            );
        }
    },
];
