<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * Ramène une COPIE jetable de la base à l'état « avant le Lot 3 » (avant T051 à T058 et T063), quel que soit l'état de la base réelle.
 *
 * Les suites de migration T051–T058 rejouent ces migrations sur une copie et vérifient l'état de départ, l'effet et le retour arrière :
 * elles supposaient que la base source ne les portait pas encore. Une fois les migrations appliquées à la base réelle, la copie les porte
 * déjà ; on les retire donc d'abord (rollback, du plus récent au plus ancien) sur la COPIE uniquement.
 */
final class Lot3Copy
{
    /** Refuse toute base qui n'est pas une copie jetable. */
    public static function retourAvantLot3(PDO $db, string $root): void
    {
        $nom = (string)$db->query('SELECT DATABASE()')->fetchColumn();
        if (!preg_match('/^ecole_app_copy_[a-f0-9]+$/', $nom)) {
            throw new \RuntimeException("Lot3Copy : refus, « $nom » n'est pas une copie jetable.");
        }
        $appliquees = $db->query("SELECT migration FROM migrations_log WHERE status = 'success'")->fetchAll(PDO::FETCH_COLUMN);
        foreach (['T063', 'T058', 'T057', 'T056', 'T055', 'T054', 'T053', 'T052', 'T051'] as $id) {
            if (!in_array($id, $appliquees, true)) {
                continue;
            }
            $fichiers = glob($root . '/database/migrations/' . $id . '_*.php') ?: [];
            if ($fichiers === []) {
                continue;
            }
            $m = require $fichiers[0];
            if (isset($m['rollback'])) {
                ob_start();
                ($m['rollback'])($db);
                ob_end_clean();
            }
            $db->prepare('DELETE FROM migrations_log WHERE migration = ?')->execute([$id]);
        }
    }
}
