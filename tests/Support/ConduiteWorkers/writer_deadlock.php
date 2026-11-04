<?php
// Écrivain « lourd » qui croise l'ordre de verrouillage du publicateur : modifie les commentaires des notes d'une évaluation
// (verrous X sur les notes), signale qu'il les tient, attend, puis touche l'évaluation (verrou X) -> interblocage avec le publicateur.
// usage: writer_deadlock.php <copie> <evalId> <fichier_notes_verrouillees> <pause_secondes> [eleveId]
// Sans eleveId : écrivain LOURD (toutes les notes) ; avec eleveId : écrivain LÉGER (une seule note) — InnoDB choisit comme victime
// la transaction la plus légère, donc l'un ou l'autre acteur selon le poids.
require __DIR__ . '/common.php';
[, $db, $eval, $flag, $pause] = $argv;
$eleve = isset($argv[5]) ? (int)$argv[5] : null;
$pdo = copie_pdo($db, 8);
try {
    $pdo->beginTransaction();
    $pdo->exec("UPDATE notes_v2 SET commentaire = CONCAT(COALESCE(commentaire, ''), '.') WHERE evaluation_id = " . (int)$eval . ($eleve !== null ? " AND eleve_id = $eleve" : ''));
    touch($flag);
    usleep((int)((float)$pause * 1000000));
    $pdo->exec("UPDATE evaluations SET libelle = libelle WHERE id = " . (int)$eval);
    $pdo->commit();
    echo json_encode(['acteur' => 'ecrivain', 'issue' => 'VALIDE']) . "\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    echo json_encode(['acteur' => 'ecrivain', 'issue' => 'ECHEC', 'errno' => copie_errno($e)]) . "\n";
}
