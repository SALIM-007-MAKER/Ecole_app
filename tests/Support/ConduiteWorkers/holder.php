<?php
// Détenteur de verrous : exécute la prise de verrous de la publication (vrai ConduiteCompletudeService) puis ATTEND,
// transaction ouverte, qu'on le libère. Variantes : full | full_rc (READ COMMITTED) | weak_pk (lignes existantes seulement) | none (témoin).
// usage: holder.php <copie> <variante> <fichier_pret> <fichier_liberation> <classe> <periode> <bulletinId>
require __DIR__ . '/common.php';
[, $db, $variant, $ready, $release, $classe, $periode, $bul] = $argv;
$classe = (int)$classe; $periode = (int)$periode; $bul = (int)$bul;
$pdo = copie_pdo($db, 5);
if ($variant === 'full_rc') { $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED'); }
else { $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ'); }
$pdo->beginTransaction();
$iso = $pdo->query('SELECT @@transaction_isolation')->fetchColumn();
$cond = (int)$pdo->query('SELECT id FROM matieres WHERE est_conduite = 1 AND etablissement_id = 1')->fetchColumn();
switch ($variant) {
    case 'full':
    case 'full_rc':
        spl_autoload_register(function (string $c): void {
            if (str_starts_with($c, 'Core\\')) { $f = ROOT_PATH . '/core/' . str_replace('\\', '/', substr($c, 5)) . '.php'; if (is_file($f)) require_once $f; }
            elseif (str_starts_with($c, 'App\\')) { $f = ROOT_PATH . '/app/' . str_replace('\\', '/', substr($c, 4)) . '.php'; if (is_file($f)) require_once $f; }
        });
        $etat = (new App\Modules\Academique\Services\ConduiteCompletudeService($pdo))->etatClasse($classe, $periode, true);
        $pdo->query("SELECT id FROM bulletins_v2 WHERE id = $bul FOR UPDATE")->fetchAll();
        $info = ['evaluations' => count($etat['evaluations']), 'manquants' => count($etat['manquants'])];
        break;
    case 'weak_pk':
        $evIds = array_column($pdo->query("SELECT id FROM evaluations WHERE classe_id = $classe AND periode_scolaire_id = $periode AND matiere_id = $cond AND statut IN ('publiee','verrouillee')")->fetchAll(), 'id');
        $evList = implode(',', $evIds ?: [0]);
        $noteIds = array_column($pdo->query("SELECT id FROM notes_v2 WHERE evaluation_id IN ($evList)")->fetchAll(), 'id');
        $elIds = array_column($pdo->query("SELECT id FROM eleves WHERE classe_id = $classe AND actif = 1")->fetchAll(), 'id');
        $info = [
            'evaluations' => count($pdo->query("SELECT id FROM evaluations WHERE id IN ($evList) LOCK IN SHARE MODE")->fetchAll()),
            'eleves' => count($pdo->query('SELECT id FROM eleves WHERE id IN (' . implode(',', $elIds ?: [0]) . ') LOCK IN SHARE MODE')->fetchAll()),
            'notes' => count($pdo->query('SELECT id FROM notes_v2 WHERE id IN (' . implode(',', $noteIds ?: [0]) . ') LOCK IN SHARE MODE')->fetchAll()),
        ];
        break;
    case 'none':
        $info = ['evaluations' => count($pdo->query("SELECT id FROM evaluations WHERE classe_id = $classe AND periode_scolaire_id = $periode AND matiere_id = $cond")->fetchAll())];
        break;
    default:
        fwrite(STDERR, "variante inconnue\n"); exit(2);
}
file_put_contents($ready, json_encode(['iso' => $iso, 'verrous' => $info]));
$t0 = microtime(true);
while (!file_exists($release) && microtime(true) - $t0 < 40) { usleep(100000); }
$pdo->commit();
echo json_encode(['variante' => $variant, 'iso' => $iso, 'verrous' => $info]) . "\n";
