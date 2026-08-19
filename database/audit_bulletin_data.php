<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/core/Database.php';

$pdo = \Core\Database::getInstance()->getConnection();
$wanted = [
    'etablissements', 'annees_scolaires', 'classes', 'eleves', 'inscriptions',
    'matieres', 'classes_matieres', 'enseignements', 'professeurs',
    'periodes_scolaires', 'evaluations', 'types_evaluations', 'notes_v2', 'notes', 'appreciations', 'appreciations_matieres',
    'bulletins_v2', 'bulletins', 'users',
];

foreach ($wanted as $table) {
    $exists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $exists->execute([$table]);
    if ((int)$exists->fetchColumn() !== 1) {
        echo "{$table}: ABSENTE" . PHP_EOL;
        continue;
    }

    $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    $columns = $pdo->query("DESCRIBE `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
    echo "{$table}: {$count} ligne(s)" . PHP_EOL;
    echo '  ' . implode(', ', array_column($columns, 'Field')) . PHP_EOL;
}

echo PHP_EOL . 'Références existantes:' . PHP_EOL;
foreach ([
    "SELECT id, libelle, active FROM annees_scolaires ORDER BY id",
    "SELECT id, nom, niveau, annee_scolaire, etablissement_id FROM classes ORDER BY id",
    "SELECT id, annee_scolaire, type_periode, numero, nom, statut, is_active FROM periodes_scolaires ORDER BY id",
    "SELECT id, code, nom, coefficient_defaut, note_max_defaut FROM types_evaluations ORDER BY ordre, id",
    "SELECT id, nom, coefficient, categorie FROM matieres ORDER BY id",
] as $sql) {
    foreach ($pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
    echo PHP_EOL;
}
