<?php
/**
 * Anonymise une base de DÉMONSTRATION (copie jetable) avant de l'enregistrer comme jeu de données de référence des tests.
 *
 * Les noms ne sont écrits nulle part ici : ils sont lus dans la base elle-même (élèves, enseignants, employés, comptes, familles), puis remplacés
 * partout (colonnes texte et JSON : bulletins, notifications, journaux d'audit…) par des noms fictifs stables Nom01 / Prenom01. Téléphones,
 * adresses, dates de naissance et nom de l'établissement sont aussi remplacés. Idempotent : les noms déjà fictifs sont ignorés.
 *
 * Usage : Anonymiseur::run($pdoDeLaCopie) — n'accepte qu'une base nommée ecole_app_copy_*.
 */
declare(strict_types=1);

final class Anonymiseur
{
    private const FICTIF = '/^(Nom|Prenom)\d+$/u';
    /** Mots qui ne sont pas des noms de personnes même s'ils figurent dans une colonne nom/prénom. */
    private const IGNORES = ['administrateur', 'admin', 'test', 'demo', 'démo'];

    /** @return array<string,int> compteurs */
    public static function run(PDO $p): array
    {
        $db = (string)$p->query('SELECT DATABASE()')->fetchColumn();
        if (!preg_match('/^ecole_app_copy_[a-f0-9]+$/', $db)) { throw new RuntimeException("Refus : $db n'est pas une copie jetable"); }
        $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $p->exec('SET NAMES utf8mb4');
        $stats = ['noms' => 0, 'prenoms' => 0, 'valeurs' => 0, 'colonnes' => 0];

        // 1) correspondance réel -> fictif (insensible à la casse), noms puis prénoms
        $map = [];
        $noms = []; $prenoms = [];
        foreach (['eleves', 'professeurs', 'rh_employes', 'users'] as $t) {
            foreach ($p->query("SELECT DISTINCT nom FROM `$t` WHERE nom IS NOT NULL AND nom <> ''")->fetchAll(PDO::FETCH_COLUMN) as $v) { $noms[] = $v; }
            foreach ($p->query("SELECT DISTINCT prenom FROM `$t` WHERE prenom IS NOT NULL AND prenom <> ''")->fetchAll(PDO::FETCH_COLUMN) as $v) { $prenoms[] = $v; }
        }
        foreach ($p->query("SELECT DISTINCT nom FROM familles WHERE nom IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $v) { $noms[] = trim((string)preg_replace('/^Famille\s+/iu', '', $v)); }
        $mots = fn(string $v): array => array_values(array_filter(preg_split('/[\s\-\']+/u', $v) ?: [], fn($m) => mb_strlen($m) >= 3));   // « Aïcha Maïga » -> mots
        sort($noms, SORT_NATURAL | SORT_FLAG_CASE); sort($prenoms, SORT_NATURAL | SORT_FLAG_CASE);
        foreach (['noms' => [$noms, 'Nom'], 'prenoms' => [$prenoms, 'Prenom']] as $cle => [$liste, $pref]) {
            $compteur = 0;
            foreach ($liste as $v) {
                foreach ($mots((string)$v) as $m) {
                    $k = mb_strtolower($m);
                    if (isset($map[$k]) || preg_match(self::FICTIF, $m) || in_array($k, self::IGNORES, true)) { continue; }
                    $map[$k] = sprintf('%s%02d', $pref, ++$compteur); $stats[$cle]++;
                }
            }
        }
        // nom de l'établissement réel -> nom neutre
        $extra = ['persévérance' => 'Démo', 'perseverance' => 'Demo'];
        foreach ($extra as $k => $v) { $map[$k] = $v; }

        // 2) expressions de recherche, y compris la forme échappée en JSON (ï)
        $motifs = []; $likes = [];
        foreach ($map as $k => $fictif) {
            $motifs['/(?<![\p{L}\p{N}])' . preg_quote($k, '/') . '(?![\p{L}\p{N}])/iu'] = $fictif;
            $likes[] = $k;
            $esc = strtolower(trim((string)json_encode($k, JSON_UNESCAPED_SLASHES), '"'));
            if ($esc !== $k) { $motifs['/(?<![\p{L}\p{N}])' . preg_quote($esc, '/') . '(?![\p{L}\p{N}])/iu'] = $fictif; $likes[] = $esc; }
        }
        $remplacer = fn(string $s): string => preg_replace(array_keys($motifs), array_values($motifs), $s) ?? $s;

        // 3) déclencheurs mis de côté (ils bloquent certaines modifications), puis rétablis à l'identique
        $trg = [];
        foreach ($p->query('SELECT trigger_name FROM information_schema.triggers WHERE trigger_schema = DATABASE()')->fetchAll(PDO::FETCH_COLUMN) as $n) {
            $trg[$n] = (string)$p->query("SHOW CREATE TRIGGER `$n`")->fetch(PDO::FETCH_ASSOC)['SQL Original Statement'];
        }
        foreach (array_keys($trg) as $n) { $p->exec("DROP TRIGGER `$n`"); }
        $p->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            // 4) tous les textes de la base
            $cols = $p->query("SELECT table_name t, column_name c, data_type d FROM information_schema.columns c WHERE table_schema = DATABASE()
                AND data_type IN ('varchar','char','tinytext','text','mediumtext','longtext','json') AND table_name <> 'migrations_log'
                AND table_name IN (SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE')")->fetchAll(PDO::FETCH_ASSOC);
            $ou = fn(string $c): string => '(' . implode(' OR ', array_map(fn($l) => "LOWER(CONVERT(`$c` USING utf8mb4)) LIKE " . $p->quote('%' . $l . '%'), $likes)) . ')';
            foreach ($cols as $x) {
                $t = $x['t']; $c = $x['c'];
                $vals = $p->query("SELECT DISTINCT `$c` FROM `$t` WHERE `$c` IS NOT NULL AND " . $ou($c))->fetchAll(PDO::FETCH_COLUMN);
                if (!$vals) { continue; }
                $stats['colonnes']++;
                $egal = $x['d'] === 'json' ? "CAST(`$c` AS CHAR) = ?" : "`$c` = ?";   // une colonne JSON ne se compare pas à une chaîne telle quelle
                $up = $p->prepare("UPDATE `$t` SET `$c` = ? WHERE $egal");
                foreach ($vals as $old) { $new = $remplacer((string)$old); if ($new !== $old) { $up->execute([$new, $old]); $stats['valeurs']++; } }
            }
            // 5) coordonnées et dates de naissance
            foreach (['eleves', 'professeurs', 'rh_employes', 'users', 'familles'] as $t) {
                if ($p->query("SHOW COLUMNS FROM `$t` LIKE 'telephone'")->fetch()) { $p->exec("UPDATE `$t` SET telephone = CONCAT('0600', LPAD(id, 6, '0')) WHERE telephone IS NOT NULL AND telephone <> ''"); }
                if ($p->query("SHOW COLUMNS FROM `$t` LIKE 'adresse'")->fetch() && $t !== 'familles') { $p->exec("UPDATE `$t` SET adresse = CONCAT(id, ' rue de Test') WHERE adresse IS NOT NULL AND adresse <> ''"); }
            }
            foreach ($p->query("SELECT table_name t, column_name c FROM information_schema.columns WHERE table_schema = DATABASE() AND column_name LIKE '%naissance%' AND data_type = 'date'")->fetchAll(PDO::FETCH_ASSOC) as $x) {
                $p->exec("UPDATE `{$x['t']}` SET `{$x['c']}` = DATE_ADD(`{$x['c']}`, INTERVAL (CAST(id AS SIGNED) * 37 % 300) - 150 DAY) WHERE `{$x['c']}` IS NOT NULL");   // âge conservé à ±5 mois près
            }
            // 6) adresses IP réelles éventuelles dans les journaux -> 127.0.0.1
            foreach ($p->query("SELECT table_name t, column_name c FROM information_schema.columns WHERE table_schema = DATABASE() AND column_name IN ('ip','ip_address','adresse_ip')")->fetchAll(PDO::FETCH_ASSOC) as $x) {
                $p->exec("UPDATE `{$x['t']}` SET `{$x['c']}` = '127.0.0.1' WHERE `{$x['c']}` IS NOT NULL AND `{$x['c']}` NOT IN ('127.0.0.1','::1')");
            }
        } finally {
            $p->exec('SET FOREIGN_KEY_CHECKS = 1');
            foreach ($trg as $sql) { $p->exec($sql); }
        }
        return $stats;
    }
}
