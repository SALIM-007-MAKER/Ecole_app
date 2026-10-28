<?php
/**
 * Tests — isolation inter-établissements de la Finance, des Rapports et des Annonces (audit d'isolation).
 *
 * Deux établissements sur une COPIE jetable (T048-T058 appliquées) ; l'établissement 2 porte des données marquées « ZZB » (facture,
 * paiement, reçu, frais, décaissement, exercice, écriture, annonce…). Vérifie, depuis l'établissement 1 puis depuis l'établissement 2 :
 * aucune lecture croisée (listes, fiches par identifiant, rapports), aucune écriture croisée, numérotation séparée, migrations T057 / T058
 * (rattachement, copies, idempotence, retour arrière) et amorçage du plan comptable d'un établissement récent.
 *
 * La base source n'est jamais écrite (empreinte avant / après).
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/TenantFinanceIsolationTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
chdir(ROOT_PATH);
define('BASE_URL', '/ecole_app');
require_once ROOT_PATH . '/tests/Support/DbCopy.php';
require_once ROOT_PATH . '/tests/Support/ConduiteCopy.php';
require_once ROOT_PATH . '/tests/Support/Lot3Copy.php';

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

/** Insère une ligne en complétant les colonnes NOT NULL sans défaut ; etablissement_id si la colonne existe. */
function ins(PDO $db, string $t, array $v, ?int $id = null, ?int $etab = null): int
{
    static $cache = [];
    $cache[$t] ??= $db->query("SELECT column_name n, data_type d, is_nullable nl, column_default df, extra ex, column_type ct FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$t' ORDER BY ordinal_position")->fetchAll(PDO::FETCH_ASSOC);
    $row = [];
    foreach ($cache[$t] as $c) {
        $n = $c['n'];
        if (array_key_exists($n, $v)) { $row[$n] = $v[$n]; continue; }
        if ($n === 'id') { if ($id !== null) { $row['id'] = $id; } continue; }
        if ($n === 'etablissement_id' && $etab !== null) { $row[$n] = $etab; continue; }
        if ($c['nl'] === 'NO' && $c['df'] === null && !str_contains($c['ex'], 'auto')) {
            $row[$n] = match (true) {
                in_array($c['d'], ['int', 'bigint', 'smallint', 'tinyint', 'mediumint', 'decimal', 'float', 'double']) => 0,
                $c['d'] === 'date' => '2026-01-15', in_array($c['d'], ['datetime', 'timestamp']) => '2026-01-15 10:00:00', $c['d'] === 'time' => '08:00:00',
                $c['d'] === 'json' => '{}', $c['d'] === 'enum' => (preg_match("/enum\('([^']+)'/", $c['ct'], $m) ? $m[1] : ''),
                default => 'ZZB-x',
            };
        }
    }
    $db->prepare('INSERT INTO `' . $t . '` (`' . implode('`,`', array_keys($row)) . '`) VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));
    return (int)($row['id'] ?? $db->lastInsertId());
}

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
    \Tests\Support\Lot3Copy::retourAvantLot3($db, ROOT_PATH);   // la base réelle porte désormais T051–T058 : la copie est ramenée à l'état « avant »
    $nom = $copy->name;
    $one = fn(string $sql, array $p = []) => (function () use ($db, $sql, $p) { $s = $db->prepare($sql); $s->execute($p); return $s->fetchColumn(); })();
    $colonne = fn(string $t) => (int)$one("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$t' AND column_name = 'etablissement_id'");

    section('1. Migrations T051-T058 sur la copie');
    foreach (['T051', 'T052', 'T053', 'T054', 'T055', 'T056'] as $t) { foreach (glob(ROOT_PATH . "/database/migrations/{$t}_*.php") as $f) { $m = require $f; ob_start(); ($m['run'])($db); ob_end_clean(); } }
    $m57 = require ROOT_PATH . '/database/migrations/T057_finance_catalogues_etablissement.php';
    $m58 = require ROOT_PATH . '/database/migrations/T058_finance_comptabilite_etablissement.php';
    $avant57 = $colonne('finance_modes_paiement') + $colonne('finance_exercices');
    ob_start(); ($m57['run'])($db); ($m58['run'])($db); $sortie = ob_get_clean();
    $tables = ['finance_frais_types', 'finance_categories_frais', 'finance_modes_paiement', 'finance_categories_depenses', 'finance_fournisseurs', 'finance_exercices', 'finance_comptes', 'finance_journaux_comptables', 'finance_regles_comptables'];
    check($avant57 === 0 && array_sum(array_map($colonne, $tables)) === 9, 'T057 + T058 : colonne etablissement_id sur les neuf tables');
    check((int)$one('SELECT COUNT(*) FROM finance_exercices WHERE etablissement_id IS NULL') === 0, 'exercices : tous rattachés (établissement unique)');
    check((int)$one('SELECT COUNT(*) FROM finance_comptes WHERE etablissement_id IS NULL') === 0 && (int)$one('SELECT COUNT(*) FROM finance_comptes') >= 27, 'plan comptable : toutes les lignes rattachées à l\'établissement propriétaire');
    check((int)$one('SELECT COUNT(*) FROM finance_modes_paiement WHERE etablissement_id = 1') >= 8, 'modes de paiement : une copie pour l\'établissement 1', trim($sortie));
    $h1 = DbCopy::allDataHashes($db);
    ob_start(); ($m57['run'])($db); ($m58['run'])($db); ob_end_clean();
    check(DbCopy::allDataHashes($db) === $h1, 'rejouer T057 et T058 ne change rien (idempotentes)');

    section('2. Deux établissements, données marquées ZZB pour l\'établissement 2');
    $db->exec('SET FOREIGN_KEY_CHECKS=0');
    $E2 = 2; $k = 0; $id = function () use (&$k) { return 5000 + 100 * (++$k); };
    $db->exec("INSERT INTO etablissements (id, slug, nom, nom_court, type, statut) VALUES ($E2, 'zzb', 'ZZB-ECOLE', 'ZZB', 'lycee', 'active')");
    $U = [];
    foreach (['admin', 'comptable', 'secretaire'] as $r) {
        $U[$r] = ins($db, 'users', ['nom' => "ZZB-U-$r", 'prenom' => 'Deux', 'email' => "zzb-$r@e2.test", 'password' => 'x', 'role' => $r, 'actif' => 1, 'etablissement_id' => $E2], $id());
        ins($db, 'user_etablissements', ['user_id' => $U[$r], 'etablissement_id' => $E2, 'is_primary' => 1]);
    }
    $CL = ins($db, 'classes', ['nom' => 'ZZB-CLASSE', 'niveau' => 'Seconde', 'annee_scolaire' => '2026-2027'], $id(), $E2);
    $EL = ins($db, 'eleves', ['matricule' => 'ZZB-M1', 'nom' => 'ZZB-ELEVE', 'prenom' => 'Zoe', 'date_naissance' => '2010-01-01', 'sexe' => 'F', 'classe_id' => $CL, 'actif' => 1], $id(), $E2);
    $MP = ins($db, 'finance_modes_paiement', ['code' => 'zzbcash', 'nom' => 'ZZB-ESPECES', 'actif' => 1], $id(), $E2);
    $FT = ins($db, 'finance_frais_types', ['code' => 'ZZBF', 'nom' => 'ZZB-FRAIS', 'montant_defaut' => 1000], $id(), $E2);
    $FA = ins($db, 'finance_factures', ['numero' => 'ZZB-F-0001', 'eleve_id' => $EL, 'annee_scolaire' => '2026-2027', 'montant_total' => 1000, 'montant_paye' => 0, 'statut' => 'emise'], $id());
    ins($db, 'finance_lignes_facture', ['facture_id' => $FA, 'libelle' => 'ZZB-LIGNE', 'montant_unitaire' => 1000, 'montant_total' => 1000]);
    $PA = ins($db, 'finance_paiements', ['numero' => 'ZZB-P-0001', 'facture_id' => $FA, 'mode_paiement_id' => $MP, 'montant' => 500, 'montant_applique' => 500, 'date_paiement' => '2026-11-02 09:00:00', 'statut' => 'complete'], $id());
    $RE = ins($db, 'finance_recus', ['numero' => 'ZZB-R-0001', 'paiement_id' => $PA, 'facture_id' => $FA, 'eleve_id' => $EL, 'montant' => 500], $id());
    $DE = ins($db, 'finance_decaissements', ['numero' => 'ZZB-D-1', 'libelle' => 'ZZB-DEPENSE', 'montant' => 77, 'date_depense' => '2026-11-04', 'statut' => 'paye', 'saisi_par' => $U['comptable']], $id());
    $EX = ins($db, 'finance_exercices', ['libelle' => 'ZZB-EXERCICE', 'date_debut' => '2026-01-01', 'date_fin' => '2026-12-31', 'statut' => 'ouvert'], $id(), $E2);
    $PE = ins($db, 'finance_periodes_comptables', ['exercice_id' => $EX, 'numero' => 1, 'libelle' => 'ZZB-PERIODE-C', 'date_debut' => '2026-01-01', 'date_fin' => '2026-01-31', 'statut' => 'ouverte'], $id());
    $JO = ins($db, 'finance_journaux_comptables', ['code' => 'ZZBJ', 'libelle' => 'ZZB-JOURNAL', 'type' => 'caisse', 'actif' => 1], $id(), $E2);
    $CP = ins($db, 'finance_comptes', ['code' => 'ZZB1', 'libelle' => 'ZZB-COMPTE', 'type' => 'charge', 'classe' => 6, 'actif' => 1], $id(), $E2);
    $EC = ins($db, 'finance_ecritures', ['numero' => 'ZZB-ECR-1', 'date_ecriture' => '2026-01-10', 'journal_id' => $JO, 'exercice_id' => $EX, 'periode_id' => $PE, 'libelle' => 'ZZB-ECRITURE', 'source' => 'manuel', 'statut' => 'valide', 'created_by' => $U['comptable']], $id());
    ins($db, 'finance_lignes_ecriture', ['ecriture_id' => $EC, 'compte_id' => $CP, 'libelle' => 'ZZB-LIGNE-ECR', 'debit' => 123, 'credit' => 0, 'created_by' => $U['comptable']]);
    $AN = ins($db, 'annonces', ['titre' => 'ZZB-ANNONCE', 'contenu' => 'ZZB-CONTENU', 'publie_par' => $U['admin'], 'actif' => 1], $id());
    $db->exec('SET FOREIGN_KEY_CHECKS=1');
    $http = fn(string $role, int $uid, string $m, string $u, array $post = [], array $env = []) => ConduiteCopy::http(ROOT_PATH, $nom, $role, $uid, $m, "/ecole_app{$u}", $post, $env);
    $E1ADMIN = 1; $E1COMPTABLE = 4;

    section('3. Établissement 1 : aucune lecture de l\'établissement 2');
    $pages = ['/v2/finance/factures', '/v2/finance/paiements', '/v2/finance/frais', '/v2/finance/rapports/dashboard', '/v2/finance/rapports/factures', '/v2/finance/rapports/paiements',
        '/v2/finance/rapports/impayes', '/v2/finance/rapports/analytique', '/v2/finance/decaissements', '/v2/finance/comptabilite/plan-comptable', '/v2/finance/comptabilite/ecritures',
        '/v2/finance/comptabilite/exercices', '/v2/finance/comptabilite/balance', '/v2/finance/comptabilite/grand-livre', '/annonces', '/rapports', '/rapports/presences', '/rapports/scolaire'];
    foreach ($pages as $u) {
        [$r, $b] = $http('admin', $E1ADMIN, 'GET', $u);
        check(!str_contains($b, 'ZZB') && ($r['status'] ?? 0) !== 500, "E1 $u : aucune donnée ZZB", 'HTTP ' . ($r['status'] ?? '?'));
    }
    foreach (["/v2/finance/factures/$FA", "/v2/finance/factures/$FA/print", "/v2/finance/paiements/$PA", "/v2/finance/paiements/$PA/recu", "/v2/finance/frais/$FT",
        "/v2/finance/decaissements/$DE", "/v2/finance/comptabilite/ecritures/$EC", "/v2/finance/comptabilite/exercices/$EX", "/annonces/$AN/edit"] as $u) {
        [$r, $b] = $http('admin', $E1ADMIN, 'GET', $u);
        check(!str_contains($b, 'ZZB') && in_array($r['status'] ?? 0, [302, 403, 404], true), "E1 $u : refusé", 'HTTP ' . ($r['status'] ?? '?'));
    }

    section('4. Établissement 1 : aucune écriture sur l\'établissement 2');
    $avantE2 = DbCopy::allDataHashes($db);
    foreach ([["/v2/finance/factures/$FA/annuler", ['motif' => 'HACK']], ["/v2/finance/paiements/$PA/annuler", ['motif' => 'HACK']], ["/v2/finance/frais/$FT/delete", []],
        ["/v2/finance/decaissements/$DE/annuler", ['motif' => 'HACK']], ["/v2/finance/comptabilite/ecritures/$EC/extourner", ['motif' => 'HACK']], ["/annonces/$AN/delete", []],
        ["/v2/finance/comptabilite/exercices/$EX/cloturer", []]] as [$u, $post]) {
        $http('admin', $E1ADMIN, 'POST', $u, $post);
    }
    check(DbCopy::allDataHashes($db) === $avantE2, 'sept actions de modification/suppression ciblant l\'établissement 2 : aucune ligne modifiée');
    check((int)$one('SELECT COUNT(*) FROM finance_factures WHERE id = ? AND statut = \'emise\'', [$FA]) === 1 && (int)$one('SELECT COUNT(*) FROM annonces WHERE id = ?', [$AN]) === 1, 'facture et annonce de l\'établissement 2 intactes');

    section('5. Établissement 2 : voit ses données, jamais celles de l\'établissement 1');
    foreach (['/v2/finance/factures' => 'ZZB-F-0001', '/v2/finance/paiements' => 'ZZB-P-0001', '/v2/finance/frais' => 'ZZB-FRAIS', '/annonces' => 'ZZB-ANNONCE'] as $u => $att) {
        [$r, $b] = $http('admin', $U['admin'], 'GET', $u);
        check(str_contains($b, $att), "E2 $u : ses propres données sont visibles", 'HTTP ' . ($r['status'] ?? '?'));
    }
    $e1 = ['Benali', 'Meziane', 'Frais d\'inscription', 'Mensualité scolaire'];
    foreach (['/v2/finance/factures', '/v2/finance/paiements', '/v2/finance/frais', '/v2/finance/rapports/dashboard', '/rapports/presences', '/eleves'] as $u) {
        [$r, $b] = $http('admin', $U['admin'], 'GET', $u);
        $vus = array_values(array_filter($e1, fn($x) => str_contains(html_entity_decode($b, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $x)));
        check($vus === [], "E2 $u : aucune donnée de l'établissement 1", implode(',', $vus));
    }
    $fa1 = (int)$one('SELECT MIN(id) FROM finance_factures WHERE id < 5000');
    [$r, $b] = $http('admin', $U['admin'], 'GET', "/v2/finance/factures/$fa1");
    check(in_array($r['status'] ?? 0, [302, 403, 404], true), 'E2 : la facture n°' . $fa1 . ' de l\'établissement 1 est refusée', 'HTTP ' . ($r['status'] ?? '?'));

    section('6. Numérotation et amorçage comptable (dans le processus, base = la copie)');
    $_ENV['DB_DATABASE'] = $nom;   // Core\Database vise la copie (jamais la base réelle)
    spl_autoload_register(function (string $c): void {
        foreach (["Core\\" => '/core/', "App\\" => '/app/'] as $pre => $dir) {
            if (str_starts_with($c, $pre)) { $f = ROOT_PATH . $dir . str_replace("\\", '/', substr($c, strlen($pre))) . '.php'; if (is_file($f)) { require_once $f; } return; }
        }
    });
    \Core\Tenant\TenantScope::oublierSchema();
    check(\Core\Tenant\TenantScope::cleSequence('FAC') === 'FAC', 'sans contexte : clé de séquence historique « FAC »');
    \Core\Tenant\TenantContext::set(1);
    check(\Core\Tenant\TenantScope::cleSequence('FAC') === 'FAC', 'établissement 1 (par défaut) : « FAC » (numéros historiques conservés)');
    \Core\Tenant\TenantContext::set(2);
    check(\Core\Tenant\TenantScope::cleSequence('FAC') === 'FAC-E2' && strlen(\Core\Tenant\TenantScope::cleSequence('PAI-LONGUE-XXXXXXXX')) <= 20, 'établissement 2 : « FAC-E2 » (compteur et numéros séparés, ≤ 20 caractères)');
    $repoInv = new \App\Modules\Finance\Repositories\InvoiceRepository();
    $n1 = $repoInv->genererNumero('FAC', 2026); $n2 = $repoInv->genererNumero('FAC', 2026);
    check($n1 === 'FAC-E2-2026-0001' && $n2 === 'FAC-E2-2026-0002', 'numérotation des factures de l\'établissement 2 : FAC-E2-2026-0001, -0002', "$n1 / $n2");
    \Core\Tenant\TenantContext::set(1);
    $n3 = $repoInv->genererNumero('FAC', 2026);
    check(preg_match('/^FAC-2026-\d{4}$/', $n3) === 1, 'établissement 1 : numéro historique inchangé (FAC-2026-NNNN)', $n3);

    \Core\Tenant\TenantContext::set(2);
    $nbCpt = fn() => (int)$one('SELECT COUNT(*) FROM finance_comptes WHERE etablissement_id = 2');
    $db->exec('DELETE FROM finance_lignes_ecriture WHERE ecriture_id = ' . $EC); $db->exec('DELETE FROM finance_ecritures WHERE id = ' . $EC); $db->exec('DELETE FROM finance_comptes WHERE etablissement_id = 2');
    \Core\Tenant\ComptabiliteAmorcage::oublier();
    new \App\Modules\Finance\Repositories\AccountingRepository();   // première utilisation de la comptabilité par l'établissement 2
    check($nbCpt() === (int)$one('SELECT COUNT(*) FROM finance_comptes WHERE etablissement_id = 1'), 'amorçage : le plan comptable de l\'établissement 1 est copié pour l\'établissement 2 (même nombre de comptes)');
    check((int)$one('SELECT COUNT(*) FROM finance_journaux_comptables WHERE etablissement_id = 2') === (int)$one('SELECT COUNT(*) FROM finance_journaux_comptables WHERE etablissement_id = 1') + 1, 'amorçage : journaux du modèle copiés (+ le journal de test de l\'établissement 2)');
    check((int)$one('SELECT COUNT(*) FROM finance_regles_comptables WHERE etablissement_id = 2') === (int)$one('SELECT COUNT(*) FROM finance_regles_comptables WHERE etablissement_id = 1'), 'amorçage : règles comptables copiées');
    check((int)$one('SELECT COUNT(*) FROM finance_comptes c JOIN finance_comptes p ON p.id = c.parent_id WHERE c.etablissement_id = 2 AND p.etablissement_id <> 2') === 0, 'amorçage : la hiérarchie des comptes pointe vers des comptes de l\'établissement 2 (aucun lien vers l\'établissement 1)');
    check((int)$one('SELECT COUNT(*) FROM finance_comptes c WHERE c.etablissement_id = 2 AND c.parent_id IS NOT NULL') === (int)$one('SELECT COUNT(*) FROM finance_comptes c WHERE c.etablissement_id = 1 AND c.parent_id IS NOT NULL'), 'amorçage : autant de comptes enfants que dans le modèle');
    $h2 = DbCopy::allDataHashes($db);
    \Core\Tenant\ComptabiliteAmorcage::oublier(); new \App\Modules\Finance\Repositories\AccountingRepository();
    check(DbCopy::allDataHashes($db) === $h2, 'amorçage idempotent (second appel : aucune modification)');
    $repoAcc = new \App\Modules\Finance\Repositories\AccountingRepository();
    check(count($repoAcc->listComptes()) === $nbCpt() && $repoAcc->findExercice($EX)?->libelle === 'ZZB-EXERCICE', 'établissement 2 : lit ses comptes et son exercice');
    \Core\Tenant\TenantContext::set(1);
    check($repoAcc->findExercice($EX) === null && $repoAcc->findEcriture($EC) === null, 'établissement 1 : l\'exercice de l\'établissement 2 est introuvable');
    check(count(array_filter($repoAcc->listComptes(), fn($c) => (int)$c->etablissement_id !== 1)) === 0, 'établissement 1 : aucun compte d\'un autre établissement');

    section('7. Retour arrière de T058 / T057');
    $db->exec('DELETE FROM finance_comptes WHERE etablissement_id = 2'); $db->exec('DELETE FROM finance_journaux_comptables WHERE etablissement_id = 2'); $db->exec('DELETE FROM finance_regles_comptables WHERE etablissement_id = 2');
    $db->exec('SET FOREIGN_KEY_CHECKS=0'); $db->exec('DELETE FROM finance_periodes_comptables WHERE exercice_id = ' . $EX); $db->exec('DELETE FROM finance_exercices WHERE id = ' . $EX); $db->exec('SET FOREIGN_KEY_CHECKS=1');
    ob_start(); ($m58['rollback'])($db); ob_end_clean();
    check(array_sum(array_map($colonne, ['finance_exercices', 'finance_comptes', 'finance_journaux_comptables', 'finance_regles_comptables'])) === 0, 'T058 : retour arrière (colonnes supprimées, unicité d\'origine rétablie)');
    ob_start(); ($m58['run'])($db); ob_end_clean();
    check(array_sum(array_map($colonne, ['finance_exercices', 'finance_comptes', 'finance_journaux_comptables', 'finance_regles_comptables'])) === 4, 'T058 : réapplication après retour arrière');
    section('8. Base réelle');
    check(DbCopy::allDataHashes($source) === $avantSource, 'base réelle inchangée (empreinte de toutes les tables)');
} finally {
    $copy->drop();
}
check(count(array_filter($source->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN), fn($d) => str_starts_with($d, 'ecole_app_copy_'))) === 0, 'aucune base temporaire restante');

echo "\n" . ($failed === 0 ? "\033[1;32m" : "\033[1;31m") . "Résultat : $passed/" . ($passed + $failed) . " assertions passées\033[0m\n";
exit($failed === 0 ? 0 : 1);
