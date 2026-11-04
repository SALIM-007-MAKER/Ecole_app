<?php
// Écrivain concurrent : tente UNE écriture dans sa propre transaction, puis ANNULE (rien ne persiste dans la copie).
// usage: worker.php <copie> <action> <timeout_s> [paramJson]
require __DIR__ . '/common.php';
[, $db, $action, $timeout] = $argv;
$p = json_decode($argv[4] ?? '{}', true) ?: [];
$pdo = copie_pdo($db, (int)$timeout);
$cond = (int)$pdo->query('SELECT id FROM matieres WHERE est_conduite = 1 AND etablissement_id = 1')->fetchColumn();
try {
    $pdo->beginTransaction();
    switch ($action) {
        case 'insert_note':
            $pdo->exec("INSERT INTO notes_v2 (evaluation_id, eleve_id, valeur, statut, created_by) VALUES ({$p['eval']}, {$p['eleve']}, 12, 'saisie', 1)"); break;
        case 'update_note':
            $pdo->exec("UPDATE notes_v2 SET valeur = valeur + 1 WHERE evaluation_id = {$p['eval']} AND eleve_id = {$p['eleve']}"); break;
        case 'deactivate_student':
            $pdo->exec("UPDATE eleves SET actif = 0 WHERE id = {$p['eleve']}"); break;
        case 'move_student_in':
            $pdo->exec("UPDATE eleves SET classe_id = {$p['classe']} WHERE id = {$p['eleve']}"); break;
        case 'insert_student':
            $pdo->exec("INSERT INTO eleves (etablissement_id, matricule, nom, prenom, date_naissance, sexe, classe_id, actif) VALUES (1, 'CC-" . uniqid() . "', 'Test', 'Concurrent', '2012-01-01', 'M', {$p['classe']}, 1)"); break;
        case 'archive_eval':
            $pdo->exec("UPDATE evaluations SET statut = 'archivee' WHERE id = {$p['eval']}"); break;
        case 'insert_eval':
            $pdo->exec("INSERT INTO evaluations (periode_scolaire_id, type_evaluation_id, matiere_id, classe_id, libelle, coefficient, note_max, statut, date_evaluation) VALUES ({$p['periode']}, {$p['type']}, $cond, {$p['classe']}, 'CC', 1, 20, 'publiee', '2026-06-21')"); break;
        case 'update_other_note':      // témoin : note d'une autre matière, hors périmètre des verrous
            $pdo->exec("UPDATE notes_v2 SET valeur = valeur + 1 WHERE id = {$p['note']}"); break;
        default:
            throw new RuntimeException("action inconnue $action");
    }
    $res = 'AUTORISEE';
} catch (Throwable $e) {
    $errno = copie_errno($e);
    $res = $errno === 1205 ? 'BLOQUEE' : ($errno === 1213 ? 'INTERBLOCAGE' : "ERREUR $errno " . substr($e->getMessage(), 0, 80));
}
if ($pdo->inTransaction()) { $pdo->rollBack(); }
echo json_encode(['action' => $action, 'resultat' => $res], JSON_UNESCAPED_UNICODE) . "\n";
