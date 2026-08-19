<?php

/**
 * T023 — Classification comptable des modes de paiement (caisse/banque)
 *
 * `App\Modules\Finance\Services\AccountingService` classait les modes de
 * paiement en "espèces" vs "bancaire" via deux tableaux PHP hardcodés
 * (CASH_MODES/BANK_MODES) — un nouveau mode ajouté en base (ex: PAYPAL)
 * n'aurait été reconnu ni comme caisse ni comme banque, faussant le
 * routage des écritures comptables. Cette migration ajoute la colonne
 * `categorie` à `finance_modes_paiement`, seedée pour reproduire EXACTEMENT
 * la classification hardcodée existante (zéro changement de comportement),
 * afin que la classification devienne une donnée éditable plutôt qu'une
 * constante PHP.
 *
 * Idempotente : ADD COLUMN IF NOT EXISTS-style (vérifie avant d'altérer),
 * UPDATE ... WHERE categorie IS NULL (ne réécrit jamais une valeur déjà
 * personnalisée par un établissement).
 */

return [
    'id'         => 'T023',
    'name'       => 'finance_modes_paiement — colonne categorie (caisse/banque)',
    'reversible' => true,

    'run' => function (PDO $pdo): void {
        $col = $pdo->query("SHOW COLUMNS FROM `finance_modes_paiement` LIKE 'categorie'")->fetch(PDO::FETCH_ASSOC);
        if (!$col) {
            $pdo->exec(
                "ALTER TABLE `finance_modes_paiement`
                 ADD COLUMN `categorie` ENUM('caisse','banque') NOT NULL DEFAULT 'caisse'
                 AFTER `code`"
            );
        }

        // Seed reproduisant exactement CASH_MODES/BANK_MODES (AccountingService, avant ce correctif)
        $banque = ['CHQ', 'VIR', 'CB'];
        $stmt = $pdo->prepare(
            "UPDATE `finance_modes_paiement` SET categorie = 'banque' WHERE code = ?"
        );
        foreach ($banque as $code) {
            $stmt->execute([$code]);
        }
        $pdo->exec(
            "UPDATE `finance_modes_paiement` SET categorie = 'caisse'
             WHERE code IN ('ESP','OM','WAVE','MOOV','AVOIR')"
        );
    },

    'rollback' => function (PDO $pdo): void {
        $col = $pdo->query("SHOW COLUMNS FROM `finance_modes_paiement` LIKE 'categorie'")->fetch(PDO::FETCH_ASSOC);
        if ($col) {
            $pdo->exec("ALTER TABLE `finance_modes_paiement` DROP COLUMN `categorie`");
        }
    },
];
