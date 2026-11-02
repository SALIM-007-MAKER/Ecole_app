<?php
/**
 * Tests — journal d'audit GLOBAL de la plateforme (étape 1 du Super-Admin SaaS).
 *
 * 1. Écriture : acteur, instantané, établissement, ancienne/nouvelle valeur, masquage des secrets
 * 2. Immuabilité : UPDATE et DELETE refusés par la base
 * 3. Panne du journal : l'opération métier aboutit, l'événement est mis de côté, l'alerte est visible, le rejeu fonctionne
 * 4. Branchement sur les services (établissements, plans) avec ancienne et nouvelle valeur
 * 5. Recherche, filtres, pagination
 * 6. Parcours HTTP : niveaux d'accès, détail, export CSV (en-tête, BOM, injection de formules), refus journalisés
 * 7. Panne du journal en conditions réelles (HTTP) : suspension effectuée, avertissement, alerte au tableau de bord
 * 8. Isolation : un compte d'établissement n'accède à rien du portail plateforme
 *
 * Travaille uniquement sur une copie jetable ecole_app_copy_* : la base vivante n'est jamais touchée.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/PlatformAuditTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
spl_autoload_register(function (string $c): void {
    foreach (['Core\\' => '/core/', 'App\\Shared\\' => '/app/Shared/', 'App\\Controllers\\' => '/app/Controllers/'] as $prefix => $dir) {
        if (str_starts_with($c, $prefix)) {
            $f = ROOT_PATH . $dir . str_replace('\\', '/', substr($c, strlen($prefix))) . '.php';
            if (is_file($f)) { require $f; return; }
        }
    }
});
require_once ROOT_PATH . '/tests/Support/DbCopy.php';

use Core\Database;
use Core\Platform\PlatformAuditService;
use Core\Platform\PlatformEtablissementService;
use Core\Platform\PlatformOperatorService;
use Core\Platform\PlatformPlanService;

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }
/** @return array{0:int,1:string} */
function lancer(array $cmd, array $env = []): array
{
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_PATH, array_merge(getenv(), $env));
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p), $out];
}
/** Requête HTTP simulée du portail plateforme. @return array<string,mixed> */
function http(string $nomCopie, string $operateur, string $methode, string $uri, array $post = [], array $env = []): array
{
    global $dossierSecours;
    [$code, $sortie] = lancer([PHP_BINARY, ROOT_PATH . '/tests/Support/platform_request.php', $operateur, $methode, $uri, json_encode($post)],
        $env + ['REQUEST_AS_DB' => $nomCopie, 'PLATFORM_REQ_AUDIT_DIR' => $dossierSecours]);
    return preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? (json_decode($m[1], true) ?: ['status' => 0]) : ['status' => 0, 'brut' => $sortie, 'code' => $code];
}
function operateur(int $id, string $niveau): string { return json_encode(['id' => $id, 'niveau' => $niveau, 'email' => "op$id@exemple.test", 'nom' => "Op $id"]); }

$_SESSION = [];
$copie = \Tests\Support\DbCopy::redirigerVersCopie(ROOT_PATH) ?? exit("ECHEC : copie jetable impossible (mysql/mysqldump ou droit CREATE DATABASE)\n");
$nomCopie = $copie->connection()['db'];
$pdo = Database::getInstance()->getConnection();
$dossierSecours = sys_get_temp_dir() . '/pal_audit_' . bin2hex(random_bytes(4));
PlatformAuditService::definirDossierSecours($dossierSecours);
$audit = new PlatformAuditService($pdo);
$ops = new PlatformOperatorService($pdo);
$compter = static fn(string $where = '1=1') => (int)$pdo->query("SELECT COUNT(*) FROM platform_audit_logs WHERE $where")->fetchColumn();
$mdp = 'Zq7!pLm2#vXk9@Rt';
$superId = $ops->creer('super@exemple.test', 'Super Opérateur', 'super_admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$adminId = $ops->creer('admin@exemple.test', 'Admin Opérateur', 'admin', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];
$supportId = $ops->creer('support@exemple.test', 'Support Opérateur', 'support', $mdp, PlatformOperatorService::ACTEUR_CLI)['id'];

// ══════════════════════════════════════════════════════════════════════
section('1. Écriture');
// ══════════════════════════════════════════════════════════════════════
$ok = $audit->recordPar($adminId, 'test.ecriture', [
    'etablissement_id' => 1, 'entity_type' => 'etablissement', 'entity_id' => 1,
    'avant' => ['statut' => 'active', 'password' => 'secret-en-clair'], 'apres' => ['statut' => 'suspended', 'imbrique' => ['token' => 'abc', 'texte' => str_repeat('x', 3000)]],
]);
$l = $pdo->query("SELECT * FROM platform_audit_logs WHERE action = 'test.ecriture'")->fetch(PDO::FETCH_ASSOC);
check($ok === true && $l !== false, 'record() renvoie true et la ligne est en base');
check($l['actor_type'] === 'operator' && (int)$l['operator_id'] === $adminId && $l['actor_email'] === 'admin@exemple.test' && $l['actor_niveau'] === 'admin', 'acteur : opérateur, identifiant, e-mail et niveau (instantanés)');
check((int)$l['etablissement_id'] === 1 && $l['entity_type'] === 'etablissement' && $l['resultat'] === 'success', 'établissement concerné, entité, résultat');
check($l['ip'] === 'cli' && $l['occurred_at'] !== null, 'adresse source et date renseignées');
$avant = json_decode($l['ancienne_valeur'], true); $apres = json_decode($l['nouvelle_valeur'], true);
check($avant['statut'] === 'active' && $apres['statut'] === 'suspended', 'ancienne et nouvelle valeur conservées');
check($avant['password'] === '***' && $apres['imbrique']['token'] === '***' && !str_contains($l['ancienne_valeur'] . $l['nouvelle_valeur'], 'secret-en-clair'), 'secrets masqués (y compris imbriqués)');
check(mb_strlen($apres['imbrique']['texte']) <= 2001, 'valeur démesurée tronquée');
$audit->recordPar(0, 'test.systeme');
check($pdo->query("SELECT actor_type FROM platform_audit_logs WHERE action = 'test.systeme'")->fetchColumn() === 'system', 'opérateur 0 = acteur système');
$audit->record('test.anonyme', ['actor_email' => 'inconnu@exemple.test', 'resultat' => 'failure']);
$a = $pdo->query("SELECT actor_type, actor_email, resultat FROM platform_audit_logs WHERE action = 'test.anonyme'")->fetch(PDO::FETCH_ASSOC);
check($a['actor_type'] === 'anonymous' && $a['actor_email'] === 'inconnu@exemple.test' && $a['resultat'] === 'failure', 'sans session : acteur anonyme (e-mail saisi), résultat échec');
$interdits = array_filter(get_class_methods(PlatformAuditService::class), static fn($m) => preg_match('/update|delete|supprim|modifi|purge|truncate|effac/i', $m));
check($interdits === [], 'le service ne propose aucune méthode de modification ni de suppression', implode(',', $interdits));

// ══════════════════════════════════════════════════════════════════════
section('2. Immuabilité');
// ══════════════════════════════════════════════════════════════════════
$id = (int)$l['id'];
foreach (['UPDATE platform_audit_logs SET action = \'falsifie\' WHERE id = ' . $id => 'UPDATE', 'UPDATE platform_audit_logs SET resultat = \'success\'' => 'UPDATE de masse',
          'DELETE FROM platform_audit_logs WHERE id = ' . $id => 'DELETE', 'DELETE FROM platform_audit_logs' => 'DELETE de masse'] as $sql => $nom) {
    try { $pdo->exec($sql); check(false, "$nom refusé par la base"); }
    catch (PDOException $e) { check(str_contains($e->getMessage(), 'ajout seul'), "$nom refusé par la base", $e->getMessage()); }
}
check($pdo->query("SELECT action FROM platform_audit_logs WHERE id = $id")->fetchColumn() === 'test.ecriture', 'la ligne est intacte après les tentatives');
try { $pdo->exec("REPLACE INTO platform_audit_logs (id, action) VALUES ($id, 'remplace')"); $r = false; } catch (PDOException) { $r = true; }
check($r && $pdo->query("SELECT action FROM platform_audit_logs WHERE id = $id")->fetchColumn() === 'test.ecriture', 'REPLACE INTO (suppression + insertion) refusé');

// ══════════════════════════════════════════════════════════════════════
section('3. Panne du journal : jamais bloquante, jamais silencieuse');
// ══════════════════════════════════════════════════════════════════════
$etabs = new PlatformEtablissementService($pdo);
$plans = new PlatformPlanService($pdo);
PlatformAuditService::reinitialiserCompteur();
$pdo->exec('RENAME TABLE platform_audit_logs TO platform_audit_logs_hs');
$etabPanne = $etabs->create(['slug' => 'panne-' . bin2hex(random_bytes(3)), 'nom' => 'École Panne', 'nom_court' => 'EP', 'type' => 'lycee'], $adminId);
$ok = $audit->record('test.panne', ['entity_type' => 'x']);
$pdo->exec('RENAME TABLE platform_audit_logs_hs TO platform_audit_logs');
check($etabPanne > 0 && $etabs->find($etabPanne) !== null, 'l\'opération métier (création d\'établissement) aboutit malgré la panne du journal');
check($ok === false, 'record() renvoie false : l\'opération ne doit jamais être présentée comme auditée');
check(PlatformAuditService::echecsDeLaRequete() === 2, 'les échecs sont comptés (création + test)', (string)PlatformAuditService::echecsDeLaRequete());
$fichiers = glob($dossierSecours . '/platform_audit_fallback-*.jsonl');
check(count($fichiers) === 1, 'un fichier de secours a été écrit');
$lignes = array_map(static fn($x) => json_decode($x, true), file($fichiers[0], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
check(count($lignes) === 2 && $lignes[0]['action'] === 'tenant.created' && (int)$lignes[0]['operator_id'] === $adminId && $lignes[0]['_cause'] !== '', 'le secours contient l\'événement complet (action, opérateur, cause)');
check($compter("action = 'tenant.created'") === 0, 'rien n\'a été écrit dans le journal pendant la panne');
$resume = PlatformAuditService::resumeSecours();
check($resume['count'] === 2 && $resume['derniere'] === date('Y-m-d'), 'l\'alerte du tableau de bord compte les événements non enregistrés');
$r = $audit->rejouerSecours();
check($r['rejoues'] === 2 && $r['echecs'] === 0 && $compter("action = 'tenant.created'") === 1, 'rejeu : les événements rejoignent le journal');
check(PlatformAuditService::resumeSecours()['count'] === 0 && count(glob($dossierSecours . '/*.rejoue')) === 1, 'rejeu : l\'alerte disparaît, le fichier est conservé (renommé .rejoue)');

// ══════════════════════════════════════════════════════════════════════
section('4. Services : ancienne et nouvelle valeur');
// ══════════════════════════════════════════════════════════════════════
$planId = $plans->create(['code' => 'aud-' . bin2hex(random_bytes(3)), 'nom' => 'Plan Audit', 'max_users' => 10, 'max_eleves' => 100, 'storage_quota_mb' => 256, 'api_calls_per_day' => 1000, 'prix_mensuel' => 5000, 'prix_annuel' => 50000], $adminId);
$pc = $pdo->query("SELECT * FROM platform_audit_logs WHERE action = 'plan.created'")->fetch(PDO::FETCH_ASSOC);
check($pc !== false && (int)$pc['entity_id'] === $planId && json_decode($pc['nouvelle_valeur'], true)['nom'] === 'Plan Audit' && $pc['actor_email'] === 'admin@exemple.test', 'plan.created : valeurs et opérateur');
$plans->update($planId, ['nom' => 'Plan Audit v2', 'max_users' => 20, 'max_eleves' => 100, 'storage_quota_mb' => 256, 'api_calls_per_day' => 1000, 'prix_mensuel' => 6000, 'prix_annuel' => 60000], $adminId);
$pu = $pdo->query("SELECT * FROM platform_audit_logs WHERE action = 'plan.updated'")->fetch(PDO::FETCH_ASSOC);
check(json_decode($pu['ancienne_valeur'], true)['nom'] === 'Plan Audit' && json_decode($pu['nouvelle_valeur'], true)['nom'] === 'Plan Audit v2' && (float)json_decode($pu['nouvelle_valeur'], true)['prix_mensuel'] === 6000.0, 'plan.updated : ancienne et nouvelle valeur (nom, prix)');
$plans->toggleActive($planId, false, $adminId);
$pt = $pdo->query("SELECT * FROM platform_audit_logs WHERE action = 'plan.toggled'")->fetch(PDO::FETCH_ASSOC);
check(json_decode($pt['ancienne_valeur'], true)['actif'] === 1 && json_decode($pt['nouvelle_valeur'], true)['actif'] === 0, 'plan.toggled : actif 1 → 0');
$plans->toggleActive($planId, true, $adminId);

$etab = $etabs->create(['slug' => 'aud-' . bin2hex(random_bytes(3)), 'nom' => 'École Audit', 'nom_court' => 'EA', 'type' => 'college'], $superId);
$etabs->activate($etab, $superId);
$ac = $pdo->query("SELECT * FROM platform_audit_logs WHERE action = 'tenant.activated' AND etablissement_id = $etab")->fetch(PDO::FETCH_ASSOC);
check(json_decode($ac['ancienne_valeur'], true)['statut'] === 'trial' && json_decode($ac['nouvelle_valeur'], true)['statut'] === 'active' && $ac['actor_niveau'] === 'super_admin', 'tenant.activated : trial → active, opérateur super_admin');
$etabs->suspend($etab, $superId, 'Impayé');
$su = $pdo->query("SELECT * FROM platform_audit_logs WHERE action = 'tenant.suspended' AND etablissement_id = $etab")->fetch(PDO::FETCH_ASSOC);
check(json_decode($su['ancienne_valeur'], true)['statut'] === 'active' && json_decode($su['nouvelle_valeur'], true)['raison'] === 'Impayé', 'tenant.suspended : ancien statut et motif');
$etabs->restore($etab, $superId);
$etabs->archive($etab, $superId);
$etabs->assignPlan($etab, $planId, $superId);
$pp = $pdo->query("SELECT * FROM platform_audit_logs WHERE action = 'tenant.plan_assigned' AND etablissement_id = $etab")->fetch(PDO::FETCH_ASSOC);
check(json_decode($pp['nouvelle_valeur'], true)['plan_id'] === $planId && json_decode($pp['nouvelle_valeur'], true)['max_users'] === 20 && array_key_exists('max_users', json_decode($pp['ancienne_valeur'], true)), 'tenant.plan_assigned : plan et limites avant/après');
$etabs->softDelete($etab, $superId);
$sd = $pdo->query("SELECT * FROM platform_audit_logs WHERE action = 'tenant.deleted' AND etablissement_id = $etab")->fetch(PDO::FETCH_ASSOC);
check(json_decode($sd['nouvelle_valeur'], true)['suppression'] === 'logique' && $pdo->query("SELECT COUNT(*) FROM etablissements WHERE id = $etab")->fetchColumn() == 1, 'tenant.deleted : suppression logique tracée, la ligne existe toujours');
foreach (['tenant.created', 'tenant.activated', 'tenant.suspended', 'tenant.restored', 'tenant.archived', 'tenant.plan_assigned', 'tenant.deleted'] as $a) {
    check($compter("action = '$a' AND etablissement_id = $etab") === 1, "une entrée « $a » pour cet établissement");
}
$etabs->suspend(999999, $superId, 'x');
check($compter("action = 'tenant.suspended' AND etablissement_id = 999999") === 0, 'une opération sans effet (établissement inconnu) n\'est pas journalisée comme réussie');

// ══════════════════════════════════════════════════════════════════════
section('5. Recherche, filtres, pagination');
// ══════════════════════════════════════════════════════════════════════
$pdo->exec('SET @ip := 0');
for ($i = 0; $i < 130; $i++) {
    $audit->recordPar($i % 2 ? $supportId : $adminId, $i % 3 === 0 ? 'bulk.alpha' : 'bulk.beta', ['etablissement_id' => $i % 5 === 0 ? 1 : null, 'resultat' => $i % 7 === 0 ? 'denied' : 'success', 'entity_id' => $i + 1000]);
}
$total = $compter("action LIKE 'bulk.%'");
check($total === 130, '130 entrées de test écrites');
$p1 = $audit->search(['action' => 'bulk.alpha'], 1);
$alpha = $compter("action = 'bulk.alpha'");
check($p1['total'] === $alpha && count($p1['rows']) === min(50, $alpha), 'filtre par action', "total={$p1['total']}");
check($audit->search(['q' => 'bulk'], 1)['pages'] === 3 && count($audit->search(['q' => 'bulk'], 3)['rows']) === 30, 'pagination : 130 entrées = 3 pages (50, 50, 30)');
check($audit->search(['q' => 'bulk'], 99)['page'] === 3, 'un numéro de page hors limites est ramené à la dernière page');
check($audit->search(['q' => 'bulk'], -4)['page'] === 1, 'un numéro de page négatif est ramené à 1');
$ids = array_column($audit->search(['q' => 'bulk'], 1)['rows'], 'id');
check($ids === array_values(array_reverse(array_values(array_unique($ids)))) || $ids[0] > $ids[count($ids) - 1], 'tri du plus récent au plus ancien');
check($audit->search(['operator_id' => $supportId, 'action' => 'bulk.beta'])['total'] === $compter("operator_id = $supportId AND action = 'bulk.beta'"), 'filtre par opérateur');
check($audit->search(['etablissement_id' => 1, 'q' => 'bulk'])['total'] === $compter("action LIKE 'bulk.%' AND etablissement_id = 1"), 'filtre par établissement');
check($audit->search(['resultat' => 'denied', 'q' => 'bulk'])['total'] === $compter("action LIKE 'bulk.%' AND resultat = 'denied'"), 'filtre par résultat');
check($audit->search(['date_from' => date('Y-m-d'), 'date_to' => date('Y-m-d'), 'q' => 'bulk'])['total'] === 130, 'filtre par période (aujourd\'hui)');
check($audit->search(['date_from' => date('Y-m-d', strtotime('+1 day')), 'q' => 'bulk'])['total'] === 0, 'filtre par période (demain) : aucun résultat');
check($audit->search(['date_from' => '2026-13-45', 'date_to' => 'nimporte', 'q' => 'bulk'])['total'] === 130, 'dates invalides ignorées (pas d\'erreur SQL)');
check($audit->search(['q' => 'bulk%'])['total'] === 0 && $audit->search(['q' => '%'])['total'] === 0, 'les caractères % et _ de la recherche sont littéraux (pas de joker)');
check($audit->search(['q' => "' OR 1=1 --"])['total'] === 0, 'tentative d\'injection SQL dans la recherche : aucun effet');
check($audit->search(['q' => '1003'])['total'] === 1, 'recherche par identifiant d\'entité');
check(in_array('bulk.alpha', $audit->actions(), true) && count($audit->operateurs()) >= 3, 'listes de filtres (actions, opérateurs)');
$n = 0; foreach ($audit->iterer(['q' => 'bulk'], 40) as $row) { $n++; }
check($n === 40, 'iterer() respecte le plafond demandé');

// ══════════════════════════════════════════════════════════════════════
section('6. Parcours HTTP : niveaux d\'accès, détail, export');
// ══════════════════════════════════════════════════════════════════════
$r = http($nomCopie, 'guest', 'GET', '/ecole_app/platform/audit');
check($r['status'] === 302 && str_contains((string)$r['location'], '/platform/login'), 'sans session : redirigé vers la connexion');
$avantRefus = $compter("action = 'access.denied'");
$r = http($nomCopie, operateur($supportId, 'support'), 'GET', '/ecole_app/platform/audit');
check($r['status'] === 403, 'niveau support : journal refusé (403)');
check($compter("action = 'access.denied' AND resultat = 'denied' AND operator_id = $supportId") === $avantRefus + 1, 'le refus d\'accès est lui-même inscrit au journal');
$r = http($nomCopie, operateur($adminId, 'admin'), 'GET', '/ecole_app/platform/audit?q=bulk');
check($r['status'] === 200 && str_contains($r['text'], '130 entrée(s)') && str_contains($r['text'], 'bulk.alpha'), 'niveau admin : journal consultable, filtres appliqués', mb_substr((string)($r['text'] ?? ''), 0, 120));
$r = http($nomCopie, operateur($adminId, 'admin'), 'GET', '/ecole_app/platform/audit?q=bulk&page=2');
check($r['status'] === 200 && str_contains($r['text'], 'Page 2 sur 3'), 'pagination HTTP');
$r = http($nomCopie, operateur($adminId, 'admin'), 'GET', '/ecole_app/platform/audit?q=%27%20OR%201%3D1%20--');
check($r['status'] === 200 && str_contains($r['text'], '0 entrée(s)'), 'injection SQL via l\'URL : aucun résultat, aucune erreur');
$r = http($nomCopie, operateur($adminId, 'admin'), 'GET', '/ecole_app/platform/audit?q=%3Cscript%3Ealert(1)%3C%2Fscript%3E');
check($r['status'] === 200, 'recherche contenant du HTML : page rendue sans erreur');
$premier = (int)$pdo->query("SELECT MIN(id) FROM platform_audit_logs WHERE action = 'test.ecriture'")->fetchColumn();
$r = http($nomCopie, operateur($adminId, 'admin'), 'GET', "/ecole_app/platform/audit/$premier");
check($r['status'] === 200 && str_contains($r['text'], 'Ancienne valeur') && str_contains($r['text'], 'suspended') && !str_contains($r['text'], 'secret-en-clair'), 'détail : ancienne/nouvelle valeur affichées, secret absent');
$r = http($nomCopie, operateur($adminId, 'admin'), 'GET', '/ecole_app/platform/audit/99999999');
check($r['status'] === 404, 'entrée inconnue : 404');
$r = http($nomCopie, operateur($adminId, 'admin'), 'GET', '/ecole_app/platform/audit/export');
check($r['status'] === 403, 'export : refusé au niveau admin (réservé au super_admin)');
// Cellule piégée pour tableur : doit être neutralisée dans le CSV.
$audit->recordPar($superId, '=HYPERLINK("http://x")', ['entity_type' => '+cmd', 'resultat' => 'failure']);
$avantExport = $compter("action = 'audit.exported'");
$corps = sys_get_temp_dir() . '/pal_export_' . bin2hex(random_bytes(3)) . '.csv';
$r = http($nomCopie, operateur($superId, 'super_admin'), 'GET', '/ecole_app/platform/audit/export?action=bulk.alpha', [], ['REQUEST_AS_BODY_OUT' => $corps]);
$csv = (string)@file_get_contents($corps);
check($r['status'] === 200 && str_starts_with($csv, "\xEF\xBB\xBFid;date;type_acteur"), 'export super_admin : CSV UTF-8 avec BOM et en-têtes');
check(substr_count($csv, "\n") - 1 === $alpha, 'export : une ligne par entrée filtrée (filtre « action » respecté)', 'lignes=' . (substr_count($csv, "\n") - 1) . " attendu=$alpha");
check($compter("action = 'audit.exported'") === $avantExport + 1 && str_contains((string)$pdo->query("SELECT nouvelle_valeur FROM platform_audit_logs WHERE action = 'audit.exported' ORDER BY id DESC LIMIT 1")->fetchColumn(), 'bulk.alpha'), 'l\'export est inscrit au journal avec ses filtres');
$corps2 = sys_get_temp_dir() . '/pal_export_' . bin2hex(random_bytes(3)) . '.csv';
http($nomCopie, operateur($superId, 'super_admin'), 'GET', '/ecole_app/platform/audit/export?q=HYPERLINK', [], ['REQUEST_AS_BODY_OUT' => $corps2]);
$csv2 = (string)@file_get_contents($corps2);
check(str_contains($csv2, "'=HYPERLINK") && !preg_match('/;=HYPERLINK/', $csv2) && str_contains($csv2, ";'+cmd;"), 'CSV : cellules commençant par = ou + neutralisées (injection de formules)');
check(\App\Controllers\PlatformAuditController::neutraliserFormule('@SUM(1)') === "'@SUM(1)" && \App\Controllers\PlatformAuditController::neutraliserFormule('-2+3') === "'-2+3" && \App\Controllers\PlatformAuditController::neutraliserFormule('normal') === 'normal' && \App\Controllers\PlatformAuditController::neutraliserFormule(null) === '', 'neutraliserFormule : @ - neutralisés, texte normal et null inchangés');
@unlink($corps); @unlink($corps2);

// ══════════════════════════════════════════════════════════════════════
section('7. Panne du journal en conditions réelles (HTTP)');
// ══════════════════════════════════════════════════════════════════════
$etabHttp = $etabs->create(['slug' => 'http-' . bin2hex(random_bytes(3)), 'nom' => 'École Http', 'nom_court' => 'EH', 'type' => 'lycee'], $superId);
$etabs->activate($etabHttp, $superId);
foreach (glob($dossierSecours . '/*') as $f) { @unlink($f); }
$r = http($nomCopie, operateur($adminId, 'admin'), 'POST', "/ecole_app/platform/etablissements/$etabHttp/suspendre", ['raison' => 'Test panne'], ['PLATFORM_REQ_BREAK_AUDIT' => '1']);
check($r['status'] === 302 && $pdo->query("SELECT statut FROM etablissements WHERE id = $etabHttp")->fetchColumn() === 'suspended', 'la suspension est bien effectuée malgré la panne du journal');
check($compter("action = 'tenant.suspended' AND etablissement_id = $etabHttp") === 0, 'aucune ligne d\'audit pendant la panne');
check(isset($r['flash']['audit_warning']) && str_contains($r['flash']['audit_warning'], 'PAS pu être enregistrée'), 'l\'opérateur reçoit un avertissement explicite (jamais présenté comme audité)', json_encode($r['flash'] ?? null));
check(PlatformAuditService::resumeSecours()['count'] >= 1, 'l\'événement est dans le fichier de secours');
$r = http($nomCopie, operateur($adminId, 'admin'), 'GET', '/ecole_app/platform/dashboard');
check($r['status'] === 200 && str_contains($r['text'], 'traçabilité incomplète') && str_contains($r['text'], 'audit-rejouer.php'), 'le tableau de bord affiche l\'alerte persistante et la procédure de rejeu');
[$c, $o] = lancer([PHP_BINARY, ROOT_PATH . '/database/audit-rejouer.php'], ['DB_DATABASE' => $nomCopie, 'PLATFORM_AUDIT_FALLBACK_DIR' => $dossierSecours]);
check($c === 0 && $compter("action = 'tenant.suspended' AND etablissement_id = $etabHttp") === 1, 'database/audit-rejouer.php réinscrit l\'événement dans le journal', trim($o));
$r = http($nomCopie, operateur($adminId, 'admin'), 'GET', '/ecole_app/platform/dashboard');
check($r['status'] === 200 && !str_contains($r['text'], 'traçabilité incomplète'), 'après rejeu, l\'alerte disparaît');

// ══════════════════════════════════════════════════════════════════════
section('8. Isolation : un compte d\'établissement n\'accède à rien du portail plateforme');
// ══════════════════════════════════════════════════════════════════════
$ecole = (int)$pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
$requeteEcole = static function (string $methode, string $uri) use ($nomCopie, $ecole): array {
    [, $sortie] = lancer([PHP_BINARY, ROOT_PATH . '/tests/Support/request_as.php', 'admin', (string)$ecole, $methode, $uri, '{}'], ['REQUEST_AS_DB' => $nomCopie]);
    return preg_match('/^RESULT:(.*)$/m', $sortie, $m) ? json_decode($m[1], true) : ['status' => 0];
};
foreach (['/ecole_app/platform/dashboard', '/ecole_app/platform/audit', '/ecole_app/platform/etablissements', '/ecole_app/platform/plans', '/ecole_app/platform/audit/export'] as $uri) {
    $rr = $requeteEcole('GET', $uri);
    check(in_array($rr['status'], [302, 403], true) && !str_contains((string)($rr['text'] ?? ''), 'Journal d\'audit'), "administrateur d'école sur $uri : refusé");
}
$statutAvant = $pdo->query("SELECT statut FROM etablissements WHERE id = $etabHttp")->fetchColumn();
$requeteEcole('POST', "/ecole_app/platform/etablissements/$etabHttp/archiver");
check($pdo->query("SELECT statut FROM etablissements WHERE id = $etabHttp")->fetchColumn() === $statutAvant, 'un administrateur d\'école ne peut pas archiver un établissement (aucun changement)');

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
