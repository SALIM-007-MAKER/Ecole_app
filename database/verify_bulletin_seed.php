<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_URL', 'http://localhost/ecole_app');

spl_autoload_register(static function (string $class): void {
    $map = ['Core\\' => ROOT_PATH . '/core/', 'App\\' => ROOT_PATH . '/app/'];
    foreach ($map as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) require_once $file;
        }
    }
});

$pdo = \Core\Database::getInstance()->getConnection();
$classe = $pdo->prepare('SELECT id FROM classes WHERE description = ? LIMIT 1');
$classe->execute(['TEST-BUL-2026-2027']);
$classeId = (int)$classe->fetchColumn();
$eleves = $pdo->prepare('SELECT id, matricule FROM eleves WHERE classe_id = ? ORDER BY matricule');
$eleves->execute([$classeId]);
$eleves = $eleves->fetchAll(\PDO::FETCH_ASSOC);
$periodes = $pdo->query("SELECT id, numero FROM periodes_scolaires WHERE annee_scolaire = '2026-2027' AND type_periode = 'semestre' ORDER BY numero")->fetchAll(\PDO::FETCH_ASSOC);
$adminId = (int)$pdo->query("SELECT id FROM users WHERE etablissement_id = 1 AND role IN ('admin', 'directeur') AND actif = 1 ORDER BY id LIMIT 1")->fetchColumn();

$engine = \App\Modules\Academique\Services\BulletinEngineFactory::make(1);
foreach ($periodes as $periode) {
    foreach ($eleves as $eleve) {
        $engine->genererBulletin((int)$eleve['id'], (int)$periode['id'], $adminId);
    }
}

$stats = $pdo->prepare(
    'SELECT b.periode_id, COUNT(*) AS bulletins, MIN(b.moyenne) AS minimum, MAX(b.moyenne) AS maximum,
            MIN(b.rang) AS meilleur_rang, MAX(b.rang) AS dernier_rang,
            MIN(JSON_LENGTH(JSON_EXTRACT(b.data_json, "$.lignes_matieres"))) AS min_matieres,
            MAX(JSON_LENGTH(JSON_EXTRACT(b.data_json, "$.lignes_matieres"))) AS max_matieres
     FROM bulletins_v2 b WHERE b.classe_id = ? GROUP BY b.periode_id ORDER BY b.periode_id'
);
$stats->execute([$classeId]);
foreach ($stats->fetchAll(\PDO::FETCH_ASSOC) as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

$appreciations = $pdo->prepare('SELECT COUNT(*) FROM appreciations_matiere WHERE classe_id = ?');
$appreciations->execute([$classeId]);
echo 'Appreciations matière: ' . $appreciations->fetchColumn() . PHP_EOL;
