<?php
/**
 * Tests — Lot 3, étape 3 : isolation inter-établissements de la Vie scolaire (absences, retards, discipline, récompenses,
 * emplois du temps, activités, salles, plages horaires, catalogues) et des fiches V1 (élèves, classes, matières, enseignants).
 *
 * Deux établissements complets (jeu de données symétrique) sur une COPIE jetable avec T048-T055 appliquées. Chaque accès par
 * identifiant est essayé dans les DEUX sens : fiches, formulaires, actions directes (POST), listes, exports, et identifiants
 * étrangers glissés dans les formulaires. Des témoins positifs prouvent que les pages testées s'affichent pour leur propriétaire.
 *
 * La base source n'est jamais écrite (empreinte avant / après).
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/TenantVieScolaireIsolationTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
chdir(ROOT_PATH);
define('BASE_URL', '/ecole_app');
require_once ROOT_PATH . '/tests/Support/DbCopy.php';
require_once ROOT_PATH . '/tests/Support/ConduiteCopy.php';

use Tests\Support\ConduiteCopy;
use Tests\Support\DbCopy;

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }

foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) { continue; }
    [$k, $v] = explode('=', $line, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}

$conn = DbCopy::connectionFromEnv(ROOT_PATH);
$source = new PDO("mysql:host={$conn['host']};port={$conn['port']};dbname={$conn['db']};charset=utf8mb4", $conn['user'], $conn['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$avantSource = DbCopy::allDataHashes($source);
$copy = DbCopy::create(ROOT_PATH);
if ($copy === null) { echo "SKIP : copie réelle impossible\n"; exit(0); }

try {
    $db = $copy->pdo();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('SET NAMES utf8mb4');
    ConduiteCopy::migrer($db, ROOT_PATH);
    ConduiteCopy::accorderDroitsBulletin($db);
    foreach (['T051_periodes_scolaires_etablissement', 'T052_types_evaluations_etablissement', 'T053_periodes_config_etablissement', 'T054_vie_scolaire_catalogues_etablissement', 'T055_vie_scolaire_salles_plages_etablissement'] as $f) {
        $m = require ROOT_PATH . "/database/migrations/$f.php";
        ob_start(); ($m['run'])($db); ob_end_clean();
    }
    $nom = $copy->name;
    $http = fn(string $role, int $uid, string $m, string $u, array $post = []) => ConduiteCopy::http(ROOT_PATH, $nom, $role, $uid, $m, "/ecole_app{$u}", $post);
    $one = fn(string $sql, array $p = []) => (function () use ($db, $sql, $p) { $s = $db->prepare($sql); $s->execute($p); return $s->fetchColumn(); })();
    $ins = function (string $table, array $row) use ($db): int {
        $db->prepare("INSERT INTO `$table` (`" . implode('`,`', array_keys($row)) . '`) VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));
        return (int)$db->lastInsertId();
    };

    // ── Deux établissements symétriques ──
    $E1 = 1; $ADMIN1 = 1;
    $db->exec("INSERT INTO etablissements (slug, nom, nom_court, type) VALUES ('lot3-vs', 'Ecole Deux VS', 'E2V', 'lycee')");
    $E2 = (int)$db->lastInsertId();
    $ADMIN2 = $ins('users', ['etablissement_id' => $E2, 'nom' => 'Admin2', 'prenom' => 'VS', 'email' => 'admin2-vs@example.test', 'password' => 'x', 'role' => 'admin']);
    $db->exec("INSERT INTO user_etablissements (user_id, etablissement_id, is_primary) VALUES ($ADMIN2, $E2, 1)");
    $PROF2 = $ins('users', ['etablissement_id' => $E2, 'nom' => 'PROFDEUXVS', 'prenom' => 'Paul', 'email' => 'prof2-vs@example.test', 'password' => 'x', 'role' => 'enseignant']);
    $db->exec("INSERT INTO user_etablissements (user_id, etablissement_id, is_primary) VALUES ($PROF2, $E2, 1)");

    $C1 = 131; $EL1 = 1;
    $ELNOM1 = (string)$one('SELECT nom FROM eleves WHERE id = 1');   // marqueur de l'établissement 1
    $C2 = $ins('classes', ['etablissement_id' => $E2, 'nom' => 'CLASSE-E2-VS', 'niveau' => '6eme', 'annee_scolaire' => '2025-2026']);
    $EL2 = $ins('eleves', ['etablissement_id' => $E2, 'matricule' => 'E2VS-1', 'nom' => 'ELEVEDEUXVS', 'prenom' => 'Zoe', 'date_naissance' => '2012-01-01', 'sexe' => 'F', 'classe_id' => $C2, 'actif' => 1]);
    $M1 = 4;
    $M2 = $ins('matieres', ['etablissement_id' => $E2, 'nom' => 'MATIERE-E2-VS', 'coefficient' => 2, 'volume_horaire' => 3]);
    $PR2 = $ins('professeurs', ['etablissement_id' => $E2, 'nom' => 'PROFESSEURDEUXVS', 'prenom' => 'Pia', 'actif' => 1]);
    $PROF1 = (int)$one("SELECT id FROM users WHERE role = 'enseignant' AND etablissement_id = 1 ORDER BY id LIMIT 1");
    $PRNOM1 = (string)$one("SELECT nom FROM professeurs WHERE etablissement_id = 1 ORDER BY id LIMIT 1");
    $PR1 = (int)$one("SELECT id FROM professeurs WHERE etablissement_id = 1 ORDER BY id LIMIT 1");

    // référentiels : un jeu par établissement (colonnes etablissement_id posées par T054/T055)
    $ref = fn(string $table, int $etab, array $row) => $ins($table, $row + ['etablissement_id' => $etab]);
    $SAL1 = $ref('vs_edt_salles', $E1, ['nom' => 'SALLE-E1-VS', 'code' => 'S-E1-VS']); $SAL2 = $ref('vs_edt_salles', $E2, ['nom' => 'SALLE-E2-VS', 'code' => 'S-E2-VS']);
    $PLG1 = $ref('vs_edt_plages_horaires', $E1, ['libelle' => 'PLAGE-E1-VS', 'heure_debut' => '07:00:00', 'heure_fin' => '07:50:00', 'ordre' => 90]);
    $PLG2 = $ref('vs_edt_plages_horaires', $E2, ['libelle' => 'PLAGE-E2-VS', 'heure_debut' => '08:00:00', 'heure_fin' => '08:50:00', 'ordre' => 91]);
    $MOT1 = $ref('vs_motifs_absence', $E1, ['code' => 'MOTIF-E1-VS', 'libelle' => 'MOTIF-E1-VS', 'categorie' => 'AUTRE']); $MOT2 = $ref('vs_motifs_absence', $E2, ['code' => 'MOTIF-E2-VS', 'libelle' => 'MOTIF-E2-VS', 'categorie' => 'AUTRE']);
    $DCAT1 = $ref('vs_discipline_categories', $E1, ['code' => 'DC-E1-VS', 'nom' => 'DCAT-E1-VS']); $DCAT2 = $ref('vs_discipline_categories', $E2, ['code' => 'DC-E2-VS', 'nom' => 'DCAT-E2-VS']);
    $RCAT1 = $ref('vs_recompense_categories', $E1, ['code' => 'RC-E1-VS', 'nom' => 'RCAT-E1-VS']); $RCAT2 = $ref('vs_recompense_categories', $E2, ['code' => 'RC-E2-VS', 'nom' => 'RCAT-E2-VS']);
    $ACAT1 = $ref('vs_activite_categories', $E1, ['code' => 'AC-E1-VS', 'nom' => 'ACAT-E1-VS']); $ACAT2 = $ref('vs_activite_categories', $E2, ['code' => 'AC-E2-VS', 'nom' => 'ACAT-E2-VS']);

    // données par établissement
    $fab = function (int $etab, int $classe, int $eleve, int $admin, int $matiere, int $prof, array $r, string $s) use ($ins, $db): array {
        $o = [];
        $o['absence']  = $ins('vs_absences', ['eleve_id' => $eleve, 'classe_id' => $classe, 'annee_scolaire' => '2025-2026', 'date_absence' => '2026-01-12', 'saisie_par' => $admin, 'statut' => 'non_justifiee']);
        $o['retard']   = $ins('vs_retards', ['eleve_id' => $eleve, 'classe_id' => $classe, 'annee_scolaire' => '2025-2026', 'date_retard' => '2026-01-12', 'heure_arrivee' => '08:20:00', 'saisie_par' => $admin]);
        $o['dossier']  = $ins('vs_dossiers_discipline', ['eleve_id' => $eleve, 'classe_id' => $classe, 'annee_scolaire' => '2025-2026', 'cree_par' => $admin]);
        $o['incident'] = $ins('vs_incidents_discipline', ['dossier_id' => $o['dossier'], 'categorie_id' => $r['dcat'], 'gravite' => 'mineure', 'description' => "INCIDENT-$s", 'date_incident' => '2026-01-12', 'signale_par' => $admin]);
        $o['sanction'] = $ins('vs_sanctions_discipline', ['dossier_id' => $o['dossier'], 'incident_id' => $o['incident'], 'type_sanction' => 'avertissement', 'motif' => "SANCTION-$s", 'date_sanction' => '2026-01-13', 'prononce_par' => $admin]);
        $o['recompense'] = $ins('vs_recompenses', ['eleve_id' => $eleve, 'classe_id' => $classe, 'annee_scolaire' => '2025-2026', 'categorie_id' => $r['rcat'], 'motif' => "RECOMPENSE-$s", 'date_attribution' => '2026-01-14', 'attribue_par' => $admin]);
        $o['edt']      = $ins('vs_emplois_du_temps', ['classe_id' => $classe, 'annee_scolaire' => '2025-2026', 'cree_par' => $admin, 'statut' => 'brouillon']);
        $o['creneau']  = $ins('vs_edt_creneaux', ['emploi_du_temps_id' => $o['edt'], 'classe_id' => $classe, 'matiere_id' => $matiere, 'enseignant_id' => $prof, 'salle_id' => $r['salle'], 'jour' => 2, 'plage_id' => $r['plage'], 'annee_scolaire' => '2025-2026']);
        $o['activite'] = $ins('vs_activites', ['categorie_id' => $r['acat'], 'titre' => "ACTIVITE-$s", 'date_activite' => '2026-03-10', 'heure_debut' => '14:00:00', 'heure_fin' => '16:00:00', 'annee_scolaire' => '2025-2026', 'cree_par' => $admin, 'statut' => 'brouillon', 'capacite_max' => 20]);
        return $o;
    };
    $D1 = $fab($E1, $C1, $EL1, $ADMIN1, $M1, $PROF1, ['dcat' => $DCAT1, 'rcat' => $RCAT1, 'acat' => $ACAT1, 'salle' => $SAL1, 'plage' => $PLG1], 'E1VS');
    $D2 = $fab($E2, $C2, $EL2, $ADMIN2, $M2, $PROF2, ['dcat' => $DCAT2, 'rcat' => $RCAT2, 'acat' => $ACAT2, 'salle' => $SAL2, 'plage' => $PLG2], 'E2VS');
    $id = fn(string $t) => (int)$one("SELECT MAX(id) FROM $t");

    $absent = fn(string $b, string ...$ms) => array_reduce($ms, fn($ok, $x) => $ok && !str_contains($b, $x), true);
    $lire = function (string $titre, int $uid, string $uri, array $absents, ?string $present = null) use ($http, $absent): void {
        [$r, $b] = $http('admin', $uid, 'GET', $uri);
        check($absent($b, ...$absents), "$titre : GET $uri — aucune donnée de l'autre établissement", 'statut ' . ($r['status'] ?? '?'));
        if ($present !== null) { check(str_contains($b, $present), "témoin positif : $uri affiche « $present » à son propriétaire", 'statut ' . ($r['status'] ?? '?')); }
    };

    // ═════════════════════════════════════════════════════════════════════
    section('1. Lectures par identifiant : établissement 2 → données de l\'établissement 1 (et inversement)');
    $lectures = [
        // [chemin avec ids de l'établissement 1, marqueur 1, chemin 2, marqueur 2]
        ["/v2/vie-scolaire/absences/{$D1['absence']}", $ELNOM1, "/v2/vie-scolaire/absences/{$D2['absence']}", 'ELEVEDEUXVS'],
        ["/v2/vie-scolaire/absences/{$D1['absence']}/edit", $ELNOM1, "/v2/vie-scolaire/absences/{$D2['absence']}/edit", 'ELEVEDEUXVS'],
        ["/v2/vie-scolaire/absences/{$D1['absence']}/justifier", $ELNOM1, "/v2/vie-scolaire/absences/{$D2['absence']}/justifier", 'ELEVEDEUXVS'],
        ["/v2/vie-scolaire/retards/{$D1['retard']}", $ELNOM1, "/v2/vie-scolaire/retards/{$D2['retard']}", 'ELEVEDEUXVS'],
        ["/v2/vie-scolaire/retards/{$D1['retard']}/edit", $ELNOM1, "/v2/vie-scolaire/retards/{$D2['retard']}/edit", 'ELEVEDEUXVS'],
        ["/v2/vie-scolaire/retards/{$D1['retard']}/justifier", $ELNOM1, "/v2/vie-scolaire/retards/{$D2['retard']}/justifier", 'ELEVEDEUXVS'],
        ["/v2/vie-scolaire/discipline/{$D1['dossier']}", 'INCIDENT-E1VS', "/v2/vie-scolaire/discipline/{$D2['dossier']}", 'INCIDENT-E2VS'],
        ["/v2/vie-scolaire/discipline/{$D1['dossier']}/sanctionner", $ELNOM1, "/v2/vie-scolaire/discipline/{$D2['dossier']}/sanctionner", 'ELEVEDEUXVS'],
        ["/v2/vie-scolaire/discipline/sanctions/{$D1['sanction']}/appel", 'SANCTION-E1VS', "/v2/vie-scolaire/discipline/sanctions/{$D2['sanction']}/appel", 'SANCTION-E2VS'],
        ["/v2/vie-scolaire/recompenses/{$D1['recompense']}", 'RECOMPENSE-E1VS', "/v2/vie-scolaire/recompenses/{$D2['recompense']}", 'RECOMPENSE-E2VS'],
        ["/v2/vie-scolaire/recompenses/{$D1['recompense']}/edit", 'RECOMPENSE-E1VS', "/v2/vie-scolaire/recompenses/{$D2['recompense']}/edit", 'RECOMPENSE-E2VS'],
        ["/v2/vie-scolaire/emplois-du-temps/{$D1['edt']}", '2nde C1', "/v2/vie-scolaire/emplois-du-temps/{$D2['edt']}", 'CLASSE-E2-VS'],
        ["/v2/vie-scolaire/emplois-du-temps/{$D1['edt']}/creneaux/ajouter", '2nde C1', "/v2/vie-scolaire/emplois-du-temps/{$D2['edt']}/creneaux/ajouter", 'CLASSE-E2-VS'],
        ["/v2/vie-scolaire/activites/{$D1['activite']}", 'ACTIVITE-E1VS', "/v2/vie-scolaire/activites/{$D2['activite']}", 'ACTIVITE-E2VS'],
        ["/v2/vie-scolaire/activites/{$D1['activite']}/edit", 'ACTIVITE-E1VS', "/v2/vie-scolaire/activites/{$D2['activite']}/edit", 'ACTIVITE-E2VS'],
        ["/v2/vie-scolaire/activites/{$D1['activite']}/inscrire", 'ACTIVITE-E1VS', "/v2/vie-scolaire/activites/{$D2['activite']}/inscrire", 'ACTIVITE-E2VS'],
        ["/eleves/$EL1", $ELNOM1, "/eleves/$EL2", 'ELEVEDEUXVS'],
        ["/classes/$C1", '2nde C1', "/classes/$C2", 'CLASSE-E2-VS'],
        ["/matieres/$M1", 'Fran', "/matieres/$M2", 'MATIERE-E2-VS'],
    ];
    foreach ($lectures as [$u1, $m1, $u2, $m2]) {
        $lire('admin 2', $ADMIN2, $u1, [$m1]);
        $lire('admin 1', $ADMIN1, $u2, [$m2]);
    }
    // témoins positifs : la page se rend bien pour son propriétaire
    foreach ([[$ADMIN1, "/v2/vie-scolaire/absences/{$D1['absence']}", $ELNOM1], [$ADMIN2, "/v2/vie-scolaire/absences/{$D2['absence']}", 'ELEVEDEUXVS'],
              [$ADMIN1, "/v2/vie-scolaire/discipline/{$D1['dossier']}", 'INCIDENT-E1VS'], [$ADMIN2, "/v2/vie-scolaire/discipline/{$D2['dossier']}", 'INCIDENT-E2VS'],
              [$ADMIN1, "/v2/vie-scolaire/recompenses/{$D1['recompense']}", 'RECOMPENSE-E1VS'], [$ADMIN2, "/v2/vie-scolaire/recompenses/{$D2['recompense']}", 'RECOMPENSE-E2VS'],
              [$ADMIN1, "/v2/vie-scolaire/activites/{$D1['activite']}", 'ACTIVITE-E1VS'], [$ADMIN2, "/v2/vie-scolaire/activites/{$D2['activite']}", 'ACTIVITE-E2VS'],
              [$ADMIN1, "/v2/vie-scolaire/retards/{$D1['retard']}", $ELNOM1], [$ADMIN2, "/v2/vie-scolaire/retards/{$D2['retard']}", 'ELEVEDEUXVS'],
              [$ADMIN1, "/v2/vie-scolaire/emplois-du-temps/{$D1['edt']}", '2nde C1'], [$ADMIN2, "/v2/vie-scolaire/emplois-du-temps/{$D2['edt']}", 'CLASSE-E2-VS']] as [$uid, $uri, $marq]) {
        [$r, $b] = $http('admin', $uid, 'GET', $uri);
        check(str_contains($b, $marq), "témoin positif : $uri affiche « $marq » à son propriétaire", 'statut ' . ($r['status'] ?? '?'));
    }

    // ═════════════════════════════════════════════════════════════════════
    section('2. Listes, statistiques, exports et référentiels');
    $listes = [
        ['/v2/vie-scolaire/absences', $ELNOM1, 'ELEVEDEUXVS'], ['/v2/vie-scolaire/absences/statistiques', null, null], ['/v2/vie-scolaire/retards', $ELNOM1, 'ELEVEDEUXVS'],
        ['/v2/vie-scolaire/retards/export', $ELNOM1, 'ELEVEDEUXVS'], ['/v2/vie-scolaire/discipline', $ELNOM1, 'ELEVEDEUXVS'], ['/v2/vie-scolaire/discipline/export', $ELNOM1, 'ELEVEDEUXVS'],
        ['/v2/vie-scolaire/recompenses', 'RECOMPENSE-E1VS', 'RECOMPENSE-E2VS'], ['/v2/vie-scolaire/recompenses/export', 'RECOMPENSE-E1VS', 'RECOMPENSE-E2VS'],
        ['/v2/vie-scolaire/recompenses/classement?classe_id=' . $C1, $ELNOM1, null], ['/v2/vie-scolaire/recompenses/classement?classe_id=' . $C2, null, 'ELEVEDEUXVS'], ['/v2/vie-scolaire/emplois-du-temps', '2nde C1', 'CLASSE-E2-VS'],
        ['/v2/vie-scolaire/activites', 'ACTIVITE-E1VS', 'ACTIVITE-E2VS'], ['/v2/vie-scolaire/activites/export', 'ACTIVITE-E1VS', 'ACTIVITE-E2VS'],
        ['/v2/vie-scolaire/emplois-du-temps/salles', 'SALLE-E1-VS', 'SALLE-E2-VS'], ['/v2/vie-scolaire/emplois-du-temps/plages', 'PLAGE-E1-VS', 'PLAGE-E2-VS'],
['/v2/vie-scolaire/discipline/create', 'DCAT-E1-VS', 'DCAT-E2-VS'],
        ['/v2/vie-scolaire/recompenses/create', 'RCAT-E1-VS', 'RCAT-E2-VS'], ['/v2/vie-scolaire/activites/create', 'ACAT-E1-VS', 'ACAT-E2-VS'],
        ['/v2/vie-scolaire/retards/create', $ELNOM1, 'ELEVEDEUXVS'], ['/v2/vie-scolaire/emplois-du-temps/create', '2nde C1', 'CLASSE-E2-VS'],
        ['/eleves', $ELNOM1, 'ELEVEDEUXVS'], ['/professeurs', $PRNOM1, 'PROFESSEURDEUXVS'],
    ];
    foreach ($listes as [$uri, $m1, $m2]) {
        if ($m1 === null) { continue; }
        [$r1, $b1] = $http('admin', $ADMIN1, 'GET', $uri);
        [$r2, $b2] = $http('admin', $ADMIN2, 'GET', $uri);
        check(!str_contains($b1, (string)($m2 ?? 'zzz-aucun')) && !str_contains($b2, $m1), "$uri : chaque établissement ne voit que ses propres données", 's1=' . ($r1['status'] ?? '?') . ' s2=' . ($r2['status'] ?? '?'));
        if ($m2 !== null) { check(str_contains($b2, $m2), "témoin positif : $uri affiche « $m2 » à l'établissement 2", 'statut ' . ($r2['status'] ?? '?')); }
    }
    foreach ([[$ADMIN1, $D1['absence'], 'MOTIF-E1-VS', 'MOTIF-E2-VS'], [$ADMIN2, $D2['absence'], 'MOTIF-E2-VS', 'MOTIF-E1-VS']] as [$uid, $abs, $propre, $autre]) {
        [$r, $b] = $http('admin', $uid, 'GET', "/v2/vie-scolaire/absences/$abs/justifier");
        check(str_contains($b, $propre) && !str_contains($b, $autre), "formulaire de justification d'absence : motifs de son établissement uniquement (« $propre » présent, « $autre » absent)", 'statut ' . ($r['status'] ?? '?'));
    }
    [$r, $b] = $http('admin', $ADMIN1, 'GET', '/v2/vie-scolaire/absences/pointage?classe_id=' . $C2);
    check(!str_contains($b, 'ELEVEDEUXVS'), 'pointage d\'une classe d\'un autre établissement : aucune donnée');
    [$r, $b] = $http('admin', $ADMIN2, 'GET', '/v2/vie-scolaire/absences/pointage?classe_id=' . $C1);
    check(!str_contains($b, $ELNOM1), 'pointage d\'une classe de l\'établissement 1 par l\'établissement 2 : aucune donnée');

    // ═════════════════════════════════════════════════════════════════════
    section('3. Actions directes (POST) : l\'établissement 2 ne modifie rien de l\'établissement 1');
    $instantane = fn() => md5(json_encode(array_map(fn($t) => $db->query("SELECT * FROM $t ORDER BY id")->fetchAll(), [
        'vs_absences', 'vs_justifications_absences', 'vs_retards', 'vs_justifications_retards', 'vs_dossiers_discipline', 'vs_incidents_discipline', 'vs_sanctions_discipline', 'vs_appels_discipline',
        'vs_recompenses', 'vs_emplois_du_temps', 'vs_edt_creneaux', 'vs_edt_remplacements', 'vs_activites', 'vs_activite_inscriptions', 'vs_edt_salles', 'vs_edt_plages_horaires', 'matieres', 'eleves', 'classes',
    ])));
    $cibles1 = [
        ['absences : valider', "/v2/vie-scolaire/absences/{$D1['absence']}/valider", []], ['absences : refuser', "/v2/vie-scolaire/absences/{$D1['absence']}/refuser", ['motif_refus' => 'x']],
        ['absences : justifier', "/v2/vie-scolaire/absences/{$D1['absence']}/justifier", ['motif_id' => $MOT2, 'commentaire' => 'PIRATE']], ['absences : supprimer', "/v2/vie-scolaire/absences/{$D1['absence']}/delete", []],
        ['retards : modifier', "/v2/vie-scolaire/retards/{$D1['retard']}/update", ['heure_arrivee' => '10:00', 'commentaire' => 'PIRATE']], ['retards : valider', "/v2/vie-scolaire/retards/{$D1['retard']}/valider", []],
        ['retards : supprimer', "/v2/vie-scolaire/retards/{$D1['retard']}/delete", []], ['retards : justifier', "/v2/vie-scolaire/retards/{$D1['retard']}/justifier", ['commentaire' => 'PIRATE']],
        ['discipline : traiter incident', "/v2/vie-scolaire/discipline/incidents/{$D1['incident']}/traiter", []], ['discipline : valider sanction', "/v2/vie-scolaire/discipline/sanctions/{$D1['sanction']}/valider", []],
        ['discipline : lever sanction', "/v2/vie-scolaire/discipline/sanctions/{$D1['sanction']}/lever", []], ['discipline : déposer un appel', "/v2/vie-scolaire/discipline/sanctions/{$D1['sanction']}/appel", ['description' => 'PIRATE']],
        ['discipline : sanctionner', "/v2/vie-scolaire/discipline/{$D1['dossier']}/sanctionner", ['type_sanction' => 'exclusion', 'motif' => 'PIRATE', 'date_sanction' => '2026-02-01']], ['discipline : clôturer', "/v2/vie-scolaire/discipline/{$D1['dossier']}/cloturer", []],
        ['récompenses : modifier', "/v2/vie-scolaire/recompenses/{$D1['recompense']}/update", ['motif' => 'PIRATE']], ['récompenses : valider', "/v2/vie-scolaire/recompenses/{$D1['recompense']}/valider", []], ['récompenses : révoquer', "/v2/vie-scolaire/recompenses/{$D1['recompense']}/revoquer", ['motif' => 'x']],
        ['EDT : publier', "/v2/vie-scolaire/emplois-du-temps/{$D1['edt']}/publier", []], ['EDT : ajouter un créneau', "/v2/vie-scolaire/emplois-du-temps/{$D1['edt']}/creneaux", ['matiere_id' => $M2, 'enseignant_id' => $PROF2, 'jour' => 4, 'plage_id' => $PLG2, 'salle_id' => $SAL2, 'type_cours' => 'cours']],
        ['EDT : modifier un créneau', "/v2/vie-scolaire/emplois-du-temps/creneaux/{$D1['creneau']}/modifier", ['matiere_id' => $M1, 'enseignant_id' => $PROF1, 'jour' => 5, 'plage_id' => $PLG1, 'salle_id' => $SAL1, 'type_cours' => 'cours']],
        ['EDT : supprimer un créneau', "/v2/vie-scolaire/emplois-du-temps/creneaux/{$D1['creneau']}/supprimer", []],
        ['EDT : remplacement', '/v2/vie-scolaire/emplois-du-temps/remplacements', ['creneau_id' => $D1['creneau'], 'date_remplacement' => '2026-02-02', 'enseignant_absent_id' => $PROF1, 'remplacant_id' => $PROF2, 'motif_absence' => 'PIRATE']],
        ['salles : modifier', "/v2/vie-scolaire/emplois-du-temps/salles/$SAL1", ['nom' => 'PIRATE-SALLE', 'code' => 'PIR', 'capacite' => 10, 'type' => 'classe']], ['salles : basculer', "/v2/vie-scolaire/emplois-du-temps/salles/$SAL1/toggle", []],
        ['plages : modifier', "/v2/vie-scolaire/emplois-du-temps/plages/$PLG1", ['libelle' => 'PIRATE-PLAGE', 'heure_debut' => '06:00', 'heure_fin' => '06:50', 'ordre' => 1]], ['plages : basculer', "/v2/vie-scolaire/emplois-du-temps/plages/$PLG1/toggle", []],
        ['activités : modifier', "/v2/vie-scolaire/activites/{$D1['activite']}/update", ['titre' => 'PIRATE-ACT', 'categorie_id' => $ACAT2, 'date_activite' => '2026-03-11', 'heure_debut' => '14:00', 'heure_fin' => '15:00']],
        ['activités : publier', "/v2/vie-scolaire/activites/{$D1['activite']}/publier", []], ['activités : annuler', "/v2/vie-scolaire/activites/{$D1['activite']}/annuler", ['motif_annulation' => 'PIRATE']],
        ['activités : inscrire un élève', "/v2/vie-scolaire/activites/{$D1['activite']}/inscrire", ['eleve_id' => $EL2]], ['activités : présences', "/v2/vie-scolaire/activites/{$D1['activite']}/presences", ['presences' => [$EL1 => 'present']]],
    ];
    foreach ($cibles1 as [$lib, $uri, $post]) {
        $avant = $instantane();
        $http('admin', $ADMIN2, 'POST', $uri, $post);
        check($instantane() === $avant, "admin 2 : $lib (ressource de l'établissement 1) sans effet");
    }

    // ═════════════════════════════════════════════════════════════════════
    section('4. Actions directes (POST) : l\'établissement 1 ne modifie rien de l\'établissement 2');
    $cibles2 = [
        ['absences : valider', "/v2/vie-scolaire/absences/{$D2['absence']}/valider", []], ['absences : supprimer', "/v2/vie-scolaire/absences/{$D2['absence']}/delete", []],
        ['retards : supprimer', "/v2/vie-scolaire/retards/{$D2['retard']}/delete", []], ['discipline : clôturer', "/v2/vie-scolaire/discipline/{$D2['dossier']}/cloturer", []],
        ['discipline : valider sanction', "/v2/vie-scolaire/discipline/sanctions/{$D2['sanction']}/valider", []], ['récompenses : valider', "/v2/vie-scolaire/recompenses/{$D2['recompense']}/valider", []],
        ['EDT : publier', "/v2/vie-scolaire/emplois-du-temps/{$D2['edt']}/publier", []], ['EDT : supprimer un créneau', "/v2/vie-scolaire/emplois-du-temps/creneaux/{$D2['creneau']}/supprimer", []],
        ['salles : basculer', "/v2/vie-scolaire/emplois-du-temps/salles/$SAL2/toggle", []], ['plages : basculer', "/v2/vie-scolaire/emplois-du-temps/plages/$PLG2/toggle", []],
        ['activités : publier', "/v2/vie-scolaire/activites/{$D2['activite']}/publier", []], ['activités : annuler', "/v2/vie-scolaire/activites/{$D2['activite']}/annuler", ['motif_annulation' => 'PIRATE']],
    ];
    foreach ($cibles2 as [$lib, $uri, $post]) {
        $avant = $instantane();
        $http('admin', $ADMIN1, 'POST', $uri, $post);
        check($instantane() === $avant, "admin 1 : $lib (ressource de l'établissement 2) sans effet");
    }

    // ═════════════════════════════════════════════════════════════════════
    section('4b. Enseignants (V1) : fiches, suppression, affectations croisées');
    $lire('admin 2', $ADMIN2, "/professeurs/$PR1", [$PRNOM1]);
    $lire('admin 1', $ADMIN1, "/professeurs/$PR2", ['PROFESSEURDEUXVS']);
    [$r, $b] = $http('admin', $ADMIN2, 'GET', "/professeurs/$PR2");
    check(str_contains($b, 'PROFESSEURDEUXVS'), 'témoin positif : admin 2 lit la fiche de son propre enseignant', 'statut ' . ($r['status'] ?? '?'));
    $http('admin', $ADMIN1, 'POST', "/professeurs/$PR2/delete");
    check((int)$one('SELECT COUNT(*) FROM professeurs WHERE id = ?', [$PR2]) === 1, 'admin 1 : suppression d\'un enseignant de l\'établissement 2 sans effet');
    $http('admin', $ADMIN2, 'POST', "/professeurs/$PR1/delete");
    check((int)$one('SELECT COUNT(*) FROM professeurs WHERE id = ?', [$PR1]) === 1, 'admin 2 : suppression d\'un enseignant de l\'établissement 1 sans effet');
    $nbEns = (int)$one('SELECT COUNT(*) FROM enseignements');
    $http('admin', $ADMIN2, 'POST', "/professeurs/$PR1/affecter-enseignement", ['matiere_id' => $M2, 'classe_id' => $C2, 'annee_scolaire' => '2099-2100']);
    $http('admin', $ADMIN2, 'POST', "/professeurs/$PR2/affecter-enseignement", ['matiere_id' => $M1, 'classe_id' => $C2, 'annee_scolaire' => '2099-2100']);
    $http('admin', $ADMIN1, 'POST', "/classes/$C2/affecter-enseignant", ['professeur_id' => $PR2, 'matiere_id' => $M2, 'annee_scolaire' => '2099-2100']);
    check((int)$one('SELECT COUNT(*) FROM enseignements') === $nbEns, 'affectations d\'enseignement croisées entre établissements : aucune créée');
    $http('admin', $ADMIN2, 'POST', "/professeurs/$PR2/affecter-enseignement", ['matiere_id' => $M2, 'classe_id' => $C2, 'annee_scolaire' => '2099-2100']);
    check((int)$one('SELECT COUNT(*) FROM enseignements WHERE annee_scolaire = \'2099-2100\'') === 1, 'témoin positif : l\'affectation cohérente de l\'établissement 2 est créée');

    // ═════════════════════════════════════════════════════════════════════
    section('5. Identifiants étrangers dans les formulaires de création');
    $compte = fn(string $t) => (int)$one("SELECT COUNT(*) FROM $t");
    $creer = function (string $lib, int $uid, string $uri, array $post, string $table) use ($http, $compte): void {
        $n = $compte($table);
        $http('admin', $uid, 'POST', $uri, $post);
        check($compte($table) === $n, "admin $uid : $lib — refusé, rien créé");
    };
    $creer('absence pour un élève/une classe d\'un autre établissement', $ADMIN2, '/v2/vie-scolaire/absences', ['eleve_id' => $EL1, 'classe_id' => $C1, 'date_absence' => '2026-02-03', 'annee_scolaire' => '2025-2026'], 'vs_absences');
    $creer('absence avec élève étranger dans la classe propre', $ADMIN2, '/v2/vie-scolaire/absences', ['eleve_id' => $EL1, 'classe_id' => $C2, 'date_absence' => '2026-02-03', 'annee_scolaire' => '2025-2026'], 'vs_absences');
    $creer('retard pour un élève d\'un autre établissement', $ADMIN2, '/v2/vie-scolaire/retards', ['eleve_id' => $EL1, 'classe_id' => $C1, 'date_retard' => '2026-02-03', 'heure_arrivee' => '08:30', 'annee_scolaire' => '2025-2026'], 'vs_retards');
    $creer('dossier de discipline pour un élève d\'un autre établissement', $ADMIN2, '/v2/vie-scolaire/discipline', ['eleve_id' => $EL1, 'classe_id' => $C1, 'categorie_id' => $DCAT2, 'gravite' => 'mineure', 'description' => 'PIRATE', 'date_incident' => '2026-02-03', 'annee_scolaire' => '2025-2026'], 'vs_dossiers_discipline');
    $creer('dossier de discipline avec une catégorie d\'un autre établissement', $ADMIN2, '/v2/vie-scolaire/discipline', ['eleve_id' => $EL2, 'classe_id' => $C2, 'categorie_id' => $DCAT1, 'gravite' => 'mineure', 'description' => 'PIRATE', 'date_incident' => '2026-02-03', 'annee_scolaire' => '2025-2026'], 'vs_incidents_discipline');
    $creer('récompense pour un élève d\'un autre établissement', $ADMIN2, '/v2/vie-scolaire/recompenses', ['eleve_id' => $EL1, 'classe_id' => $C1, 'categorie_id' => $RCAT2, 'motif' => 'PIRATE', 'date_attribution' => '2026-02-03', 'annee_scolaire' => '2025-2026'], 'vs_recompenses');
    $creer('récompense avec une catégorie d\'un autre établissement', $ADMIN2, '/v2/vie-scolaire/recompenses', ['eleve_id' => $EL2, 'classe_id' => $C2, 'categorie_id' => $RCAT1, 'motif' => 'PIRATE', 'date_attribution' => '2026-02-03', 'annee_scolaire' => '2025-2026'], 'vs_recompenses');
    $creer('emploi du temps pour une classe d\'un autre établissement', $ADMIN2, '/v2/vie-scolaire/emplois-du-temps', ['classe_id' => $C1, 'annee_scolaire' => '2025-2026', 'periode_id' => 90, 'semaine_type' => 'standard'], 'vs_emplois_du_temps');
    $creer('créneau avec une salle / plage / matière d\'un autre établissement', $ADMIN2, "/v2/vie-scolaire/emplois-du-temps/{$D2['edt']}/creneaux", ['matiere_id' => $M1, 'enseignant_id' => $PROF2, 'jour' => 3, 'plage_id' => $PLG1, 'salle_id' => $SAL1, 'type_cours' => 'cours'], 'vs_edt_creneaux');
    $creer('activité avec une catégorie d\'un autre établissement', $ADMIN2, '/v2/vie-scolaire/activites', ['titre' => 'PIRATE-ACT', 'categorie_id' => $ACAT1, 'date_activite' => '2026-04-01', 'heure_debut' => '14:00', 'heure_fin' => '15:00', 'annee_scolaire' => '2025-2026'], 'vs_activites');
    $http('admin', $ADMIN2, 'POST', '/v2/vie-scolaire/emplois-du-temps/salles', ['nom' => 'SALLE-E2-NEW', 'code' => 'S-E2-NEW', 'capacite' => 20, 'type' => 'classe']);
    check((int)$one("SELECT etablissement_id FROM vs_edt_salles WHERE nom = 'SALLE-E2-NEW'") === $E2, 'une salle créée par l\'établissement 2 lui appartient');
} catch (Throwable $e) {
    $failed++; echo "\033[31m  ✗ exception : " . $e->getMessage() . "\033[0m\n";
} finally {
    $copy->drop();
}

section('6. Base réelle');
check(DbCopy::allDataHashes($source) === $avantSource, 'base réelle inchangée (empreinte de toutes les tables)');
check(count(array_filter($source->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN), fn($d) => str_starts_with($d, 'ecole_app_copy_'))) === 0, 'aucune base temporaire restante');

echo "\n" . ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : $passed/" . ($passed + $failed) . " assertions passées\033[0m\n";
exit($failed === 0 ? 0 : 1);
