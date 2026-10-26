<?php

declare(strict_types=1);

namespace Core\Tenant;

use PDO;

/**
 * Plan comptable, journaux et règles d'un établissement créé APRÈS la migration T058 : copie du modèle (établissement par défaut,
 * config/tenant.php['default_id']) à la première utilisation de la comptabilité. Idempotent ; sans effet pour l'établissement par défaut,
 * hors contexte ou si la base n'est pas migrée (colonne absente).
 */
final class ComptabiliteAmorcage
{
    private static array $faits = [];

    public static function assurer(PDO $pdo): void
    {
        $t = TenantContext::current();
        if ($t === null || isset(self::$faits[$t]) || !TenantScope::colonneEtablissement('finance_comptes')) { return; }
        self::$faits[$t] = true;
        $modele = (int)((require ROOT_PATH . '/config/tenant.php')['default_id'] ?? 1);
        if ($t === $modele) { return; }

        $n = $pdo->prepare('SELECT COUNT(*) FROM finance_comptes WHERE etablissement_id = ?');
        $n->execute([$t]);
        if ((int)$n->fetchColumn() > 0) { return; }

        $pdo->beginTransaction();
        try {
            // journaux et règles : copie directe (hors id et etablissement_id)
            foreach (['finance_journaux_comptables', 'finance_regles_comptables'] as $table) {
                $cols = array_column($pdo->query("SELECT column_name AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$table' AND column_name NOT IN ('id','etablissement_id') ORDER BY ordinal_position")->fetchAll(PDO::FETCH_ASSOC), 'c');
                $l = '`' . implode('`,`', $cols) . '`';
                $pdo->prepare("INSERT INTO `$table` (etablissement_id, $l) SELECT ?, $l FROM `$table` WHERE etablissement_id = ?")->execute([$t, $modele]);
            }
            // comptes : copie puis reconstitution de la hiérarchie (parent_id) par code
            $cols = array_column($pdo->query("SELECT column_name AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'finance_comptes' AND column_name NOT IN ('id','etablissement_id','parent_id') ORDER BY ordinal_position")->fetchAll(PDO::FETCH_ASSOC), 'c');
            $l = '`' . implode('`,`', $cols) . '`';
            $pdo->prepare("INSERT INTO finance_comptes (etablissement_id, $l) SELECT ?, $l FROM finance_comptes WHERE etablissement_id = ?")->execute([$t, $modele]);
            $pdo->prepare(
                'UPDATE finance_comptes c
                   JOIN finance_comptes m  ON m.etablissement_id = ? AND m.code = c.code
                   JOIN finance_comptes pm ON pm.id = m.parent_id
                   JOIN finance_comptes pc ON pc.etablissement_id = ? AND pc.code = pm.code
                    SET c.parent_id = pc.id
                  WHERE c.etablissement_id = ?'
            )->execute([$modele, $t, $t]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            self::$faits[$t] = false;
            if (class_exists(\Core\Logger::class)) { \Core\Logger::error('Amorçage comptable impossible (établissement ' . $t . ') : ' . $e->getMessage()); }
        }
    }

    public static function oublier(): void
    {
        self::$faits = [];
    }
}
