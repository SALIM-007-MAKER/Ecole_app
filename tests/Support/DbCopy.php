<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * Copie de travail dans une base temporaire, pour répéter une migration ou une restauration sans toucher à la base vivante (jamais écrite).
 *
 * SOURCE DES DONNÉES : par défaut le jeu de données de référence figé `tests/Support/fixtures/ecole_app_reference.sql` (démonstration, sans
 * secret) : les tests ne dépendent donc plus du contenu de la base de développement, qu'on peut vider ou modifier librement.
 * `DBCOPY_SOURCE=live` rétablit l'ancien comportement (copie de la base vivante), utile pour contrôler une migration sur des données réelles.
 *
 *   $copy = DbCopy::create();           // null si mysqldump/mysql ou le droit CREATE DATABASE sont indisponibles
 *   $pdo  = $copy->pdo();               // connexion à la copie
 *   ... tester ...
 *   $copy->drop();                      // à appeler dans un finally
 *
 * La copie est un dump complet (structure, données, index, clés étrangères, déclencheurs) importé tel quel.
 * Les empreintes de structure et de données permettent de comparer « avant / après ».
 */
final class DbCopy
{
    public readonly string $name;
    private PDO $admin;
    private PDO $pdo;
    private bool $dropped = false;

    /** @var array<string,string> */
    private array $conn;

    private function __construct(string $name, PDO $admin, PDO $pdo, array $conn)
    {
        $this->name = $name;
        $this->admin = $admin;
        $this->pdo = $pdo;
        $this->conn = $conn;
    }

    /** Lit .env (sans l'exporter) et retourne les paramètres de connexion. */
    public static function connectionFromEnv(string $root): array
    {
        $env = [];
        foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) { continue; }
            [$k, $v] = explode('=', $line, 2);
            $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
        }
        return [
            'host' => $env['DB_HOST'] ?? 'localhost', 'port' => $env['DB_PORT'] ?? '3306',
            'db'   => $env['DB_DATABASE'] ?? 'ecole_app', 'user' => $env['DB_USERNAME'] ?? 'root',
            'pass' => $env['DB_PASSWORD'] ?? '',
        ];
    }

    public static function create(?string $root = null): ?self
    {
        $root ??= dirname(__DIR__, 2);
        $c = self::connectionFromEnv($root);
        $dump = self::findBinary('mysqldump');
        $mysql = self::findBinary('mysql');
        if ($dump === null || $mysql === null) { return null; }

        try {
            $admin = new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4", $c['user'], $c['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $name = 'ecole_app_copy_' . bin2hex(random_bytes(4));
            $admin->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Throwable) {
            return null;
        }

        $fixture = $root . '/tests/Support/fixtures/ecole_app_reference.sql';
        $depuisFixture = getenv('DBCOPY_SOURCE') !== 'live' && is_file($fixture);
        $file = $depuisFixture ? $fixture : sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name . '.sql';
        putenv('MYSQL_PWD=' . $c['pass']);
        try {
            $base = ' -h ' . escapeshellarg($c['host']) . ' -P ' . escapeshellarg($c['port']) . ' -u ' . escapeshellarg($c['user']);
            $r1 = 0;
            if (!$depuisFixture) {
                $cmd1 = escapeshellarg($dump) . $base . ' --single-transaction --skip-comments --default-character-set=utf8mb4 '
                      . escapeshellarg($c['db']) . ' > ' . escapeshellarg($file) . ' 2>NUL';
                exec($cmd1, $o1, $r1);
            }
            $cmd2 = escapeshellarg($mysql) . $base . ' --default-character-set=utf8mb4 ' . escapeshellarg($name)
                  . ' < ' . escapeshellarg($file) . ' 2>NUL';
            exec($cmd2, $o2, $r2);
            if ($r1 !== 0 || $r2 !== 0) { throw new \RuntimeException('dump/import impossible'); }

            $pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname=$name;charset=utf8mb4", $c['user'], $c['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            // Contrôle : la copie contient bien des tables
            if ((int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn() < 10) {
                throw new \RuntimeException('copie incomplète');
            }
        } catch (\Throwable) {
            $admin->exec("DROP DATABASE IF EXISTS `$name`");
            return null;
        } finally {
            putenv('MYSQL_PWD');
            if (!$depuisFixture) { @unlink($file); }   // le jeu de données de référence n'est jamais supprimé
        }
        return new self($name, $admin, $pdo, $c);
    }

    public function pdo(): PDO { return $this->pdo; }

    /** Paramètres de connexion de la COPIE (pour lancer l'outil en ligne de commande dessus). */
    public function connection(): array { return ['db' => $this->name] + $this->conn; }

    public function drop(): void
    {
        if ($this->dropped) { return; }
        $this->dropped = true;
        $this->admin->exec("DROP DATABASE IF EXISTS `{$this->name}`");
    }

    public function __destruct() { $this->drop(); }

    /** Chemin d'un binaire MySQL : MYSQL_BIN_DIR, WAMP, puis PATH. */
    public static function findBinary(string $name): ?string
    {
        $candidates = [];
        if (($dir = getenv('MYSQL_BIN_DIR')) !== false && $dir !== '') {
            $candidates[] = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name . '.exe';
            $candidates[] = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name;
        }
        foreach (glob('C:/wamp64/bin/mysql/*/bin/' . $name . '.exe') ?: [] as $p) { $candidates[] = $p; }
        foreach (glob('C:/wamp64/bin/mariadb/*/bin/' . $name . '.exe') ?: [] as $p) { $candidates[] = $p; }
        foreach ($candidates as $c) { if (is_file($c)) { return $c; } }
        $which = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
        $out = trim((string)@shell_exec($which . ' ' . escapeshellarg($name) . ' 2>NUL'));
        $first = strtok($out, "\r\n");
        return $first !== false && $first !== '' && is_file($first) ? $first : null;
    }

    // ── Empreintes avant / après ──────────────────────────────────────────────────────────

    /** Structure normalisée : CREATE TABLE (sans AUTO_INCREMENT=), index et clés étrangères. */
    public static function schema(PDO $pdo, array $tables): array
    {
        $out = [];
        foreach ($tables as $t) {
            $row = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM);
            $out[$t] = preg_replace('/ AUTO_INCREMENT=\d+/', '', (string)$row[1]);
        }
        return $out;
    }

    /** Empreinte exacte du contenu d'une table (toutes colonnes, ordre de clé primaire). */
    public static function dataHash(PDO $pdo, string $table, array $excludeColumns = []): string
    {
        $cols = array_column($pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(), 'Field');
        $cols = array_values(array_diff($cols, $excludeColumns));
        $list = implode(', ', array_map(fn($c) => "`$c`", $cols));
        $rows = $pdo->query("SELECT $list FROM `$table` ORDER BY 1")->fetchAll(PDO::FETCH_NUM);
        return md5(json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    /** @return array<string,string> table => empreinte, pour toutes les tables de la base courante */
    public static function allDataHashes(PDO $pdo, array $excludeTables = []): array
    {
        $out = [];
        foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as [$t]) {
            if (in_array($t, $excludeTables, true)) { continue; }
            $out[$t] = self::dataHash($pdo, $t);
        }
        return $out;
    }

    public static function countRows(PDO $pdo, string $table): int
    {
        return (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    }
}
