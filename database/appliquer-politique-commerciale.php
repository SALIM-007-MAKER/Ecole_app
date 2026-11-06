<?php

/**
 * Politique commerciale EduNova — grille tarifaire des trois plans en FCFA (XOF), quotas, essai et grâce, réglages globaux.
 *
 * Décisions de l'exploitant (novembre 2026) appliquées ici :
 *   - FCFA (XOF) exclusivement pour les tarifs et les opérations commerciales ;
 *   - Starter 7 500 / 75 000, Pro 15 000 / 150 000, Enterprise 30 000 / 300 000 (mensuel / annuel) ;
 *   - règle annuelle : 10 mensualités payées pour 12 mois d'accès (prix annuel = 10 × prix mensuel) ;
 *   - quotas : Starter 500 élèves / 30 utilisateurs / 2 Go ; Pro 1 500 / 100 / 10 Go ; Enterprise 10 000 / 500 / 50 Go ;
 *   - 15 jours d'essai gratuit et 5 jours de délai de grâce (sur chaque plan ET comme valeurs par défaut des réglages globaux).
 *
 * Usage :
 *   Simulation (défaut, AUCUNE écriture) : php database/appliquer-politique-commerciale.php
 *   Application : php database/appliquer-politique-commerciale.php --appliquer --sauvegarde=database/backups/auto_ecole_app_AAAAMMJJ_HHMMSS.sql
 *                                                                  [--confirmer-plan-utilise]
 *
 * Garde-fous :
 *   - passe par PlatformPlanService::update() et PlatformSettings::enregistrer() : validation (montants entiers en XOF), transaction, journal d'audit
 *     STRICT (acteur « système »), et confirmation explicite pour un plan déjà utilisé (--confirmer-plan-utilise) ; jamais de SQL direct ;
 *   - toutes les valeurs sont validées AVANT la première écriture (une saisie invalide n'écrit rien) ;
 *   - une sauvegarde récente et complète (database/backups/auto_ecole_app_*.sql, moins de 60 minutes) est exigée pour appliquer ;
 *   - NE MODIFIE JAMAIS les abonnements, les établissements ni les paiements : un changement de plan n'est pas rétroactif. Une empreinte de ces tables
 *     avant et après est comparée ; toute différence est signalée ;
 *   - idempotent : relancé, il ne fait rien (aucune écriture, aucun audit) ;
 *   - ne touche ni aux modules inclus, ni aux noms, descriptions, ordre, visibilité et appels d'API des plans.
 *
 * Retour arrière : les valeurs « avant » figurent dans le journal d'audit global (plan.updated, avant/après) ; la sauvegarde permet une restauration complète.
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $classe): void {
    if (str_starts_with($classe, 'Core\\')) {
        $fichier = ROOT_PATH . '/core/' . str_replace('\\', '/', substr($classe, 5)) . '.php';
        if (is_file($fichier)) { require $fichier; }
    }
});

use Core\Platform\PlanValidationException;
use Core\Platform\PlatformPlanService;
use Core\Platform\PlatformSettings;

// ─── La grille (seule source des valeurs) ───────────────────────────────────────────────────────────────────────────────

const DEVISE = 'XOF';
const ESSAI_JOURS = 15;
const GRACE_JOURS = 5;
const MENSUALITES_PAR_AN = 10;

/** code du plan => [prix mensuel XOF, élèves max, utilisateurs max, stockage en Mo] */
const GRILLE = [
    'starter'    => ['prix_mensuel' => 7500,  'max_eleves' => 500,   'max_users' => 30,  'storage_quota_mb' => 2048],
    'pro'        => ['prix_mensuel' => 15000, 'max_eleves' => 1500,  'max_users' => 100, 'storage_quota_mb' => 10240],
    'enterprise' => ['prix_mensuel' => 30000, 'max_eleves' => 10000, 'max_users' => 500, 'storage_quota_mb' => 51200],
];
const REGLAGES_GLOBAUX = [
    PlatformSettings::DEVISE => DEVISE,
    PlatformSettings::ESSAI  => '15',     // = ESSAI_JOURS
    PlatformSettings::GRACE  => '5',      // = GRACE_JOURS
];

// ─── Environnement ──────────────────────────────────────────────────────────────────────────────────────────────────────

$env = [];
if (is_file(ROOT_PATH . '/.env')) {
    foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
        [$k, $v] = explode('=', $l, 2);
        $env[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
}
foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $cle) {
    $v = getenv($cle);
    if ($v !== false && $v !== '') { $env[$cle] = $v; }
}
// Cible alternative réservée aux tests : une copie jetable dont le nom l'indique, jamais une autre base.
$cibleTest = getenv('POLITIQUE_DB');
if ($cibleTest !== false && $cibleTest !== '') {
    if (!preg_match('/^ecole_app_copy_[a-f0-9]+$/', $cibleTest)) { fwrite(STDERR, "REFUS : POLITIQUE_DB doit être une copie jetable ecole_app_copy_*\n"); exit(2); }
    $env['DB_DATABASE'] = $cibleTest;
}
$_ENV = array_merge($_ENV, $env);

function arreter(string $message, int $code = 1): never
{
    fwrite(STDERR, "ECHEC : {$message}\n");
    exit($code);
}

$options = getopt('', ['appliquer', 'sauvegarde:', 'confirmer-plan-utilise', 'sans-sauvegarde']);
$appliquer = array_key_exists('appliquer', $options);
$testeur = ($cibleTest !== false && $cibleTest !== '');

try {
    $pdo = \Core\Database::getInstance()->getConnection();
} catch (\Throwable $e) {
    arreter('connexion à la base impossible : ' . $e->getMessage());
}
$base = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
$plans = new PlatformPlanService($pdo);
$reglages = new PlatformSettings($pdo);

// ─── État actuel ────────────────────────────────────────────────────────────────────────────────────────────────────────

/** @return array<string,array<string,mixed>> plans indexés par code */
function planParCode(PlatformPlanService $plans): array
{
    $o = [];
    foreach ($plans->all(true) as $p) { $o[(string)$p['code']] = $p; }
    return $o;
}

/** Empreinte des tables que cette opération ne doit JAMAIS modifier. @return array<string,string> */
function empreintes(PDO $pdo): array
{
    $o = [];
    foreach (['platform_subscriptions', 'etablissements', 'platform_payments'] as $t) {
        $o[$t] = (string)$pdo->query("CHECKSUM TABLE `{$t}`")->fetch(PDO::FETCH_ASSOC)['Checksum'];
    }
    return $o;
}

/** Valeurs cibles d'un plan (chaînes, montants entiers : le XOF n'a pas de décimales). @return array<string,string> */
function cible(string $code): array
{
    $g = GRILLE[$code];
    return [
        'devise'            => DEVISE,
        'prix_mensuel'      => (string)$g['prix_mensuel'],
        'prix_annuel'       => (string)($g['prix_mensuel'] * MENSUALITES_PAR_AN),   // 10 mensualités pour 12 mois d'accès
        'max_eleves'        => (string)$g['max_eleves'],
        'max_users'         => (string)$g['max_users'],
        'storage_quota_mb'  => (string)$g['storage_quota_mb'],
        'duree_essai_jours' => (string)ESSAI_JOURS,
        'jours_grace'       => (string)GRACE_JOURS,
    ];
}

/** Valeur d'un champ de plan comparable à la cible (montants : nombre, sans zéros de fin). */
function normaliserChamp(string $champ, mixed $valeur): string
{
    return in_array($champ, ['prix_mensuel', 'prix_annuel'], true) ? rtrim(rtrim(number_format((float)$valeur, 2, '.', ''), '0'), '.') : (string)$valeur;
}

$existants = planParCode($plans);
foreach (array_keys(GRILLE) as $code) {
    if (!isset($existants[$code])) { arreter("le plan « {$code} » n'existe pas : rien n'est fait."); }
}

// ─── Simulation : avant / après ─────────────────────────────────────────────────────────────────────────────────────────

$libelles = [
    'devise' => 'Devise', 'prix_mensuel' => 'Prix mensuel', 'prix_annuel' => 'Prix annuel', 'max_eleves' => 'Élèves max', 'max_users' => 'Utilisateurs max',
    'storage_quota_mb' => 'Stockage (Mo)', 'duree_essai_jours' => 'Essai (jours)', 'jours_grace' => 'Grâce (jours)',
];
$aFaire = [];          // code => [champ => [avant, apres]]
$utilises = [];        // code => nombre d'établissements
echo "=== Base cible : {$base}" . ($appliquer ? '  (APPLICATION)' : '  (simulation : aucune écriture)') . "\n\n";
foreach (GRILLE as $code => $_) {
    $p = $existants[$code];
    $c = cible($code);
    $utilises[$code] = $plans->usageCount((int)$p['id']);
    echo "Plan « {$code} » (id {$p['id']}) — utilisé par {$utilises[$code]} établissement(s)\n";
    foreach ($libelles as $champ => $libelle) {
        $avant = normaliserChamp($champ, $p[$champ]);
        $apres = $c[$champ];
        if ($avant !== $apres) { $aFaire[$code][$champ] = [$avant, $apres]; }
        echo sprintf("  %-18s %12s  →  %-12s %s\n", $libelle, $avant, $apres, $avant === $apres ? '(inchangé)' : '*');
    }
    echo "\n";
}
echo "Non modifiés par cette opération : modules inclus, noms, descriptions, ordre, visibilité, appels d'API des plans.\n\n";

$etatReglages = $reglages->tous();
$reglagesAFaire = [];
echo "Réglages globaux de la plateforme\n";
foreach (REGLAGES_GLOBAUX as $cle => $voulu) {
    $r = $etatReglages[$cle] ?? [];
    $actuel = (string)($r['effective'] ?? '');   // valeur EFFECTIVE (surcharge > environnement > défaut)
    $source = (string)($r['source'] ?? '?');
    if ($actuel !== $voulu || $source !== 'base') { $reglagesAFaire[$cle] = $voulu; }
    echo sprintf("  %-24s %-8s (source : %-13s)  →  %-8s %s\n", $cle, $actuel, $source, $voulu, ($actuel !== $voulu || $source !== 'base') ? '*' : '(inchangé)');
}
echo "  (fuseau actuel : " . (string)($etatReglages[PlatformSettings::FUSEAU]['effective'] ?? '?') . " — non modifié)\n\n";

$abonnements = $pdo->query(
    "SELECT s.etablissement_id, p.code AS plan, s.statut, s.cycle, s.devise FROM platform_subscriptions s JOIN platform_plans p ON p.id = s.plan_id WHERE s.est_courant = 1"
)->fetchAll(PDO::FETCH_ASSOC);
echo "Abonnements courants (jamais modifiés par cette opération : un changement de plan n'est pas rétroactif)\n";
foreach ($abonnements as $a) {
    echo "  établissement {$a['etablissement_id']} : plan {$a['plan']}, {$a['statut']}, cycle {$a['cycle']}, devise {$a['devise']}\n";
}
if ($abonnements === []) { echo "  (aucun)\n"; }
echo "\n";

// ─── Validation complète AVANT toute écriture ──────────────────────────────────────────────────────────────────────────

$aucunChangement = $aFaire === [] && $reglagesAFaire === [];
$erreurs = [];
foreach (GRILLE as $code => $_) {
    if (!isset($aFaire[$code])) { continue; }
    try {
        $plans->normaliser(cible($code), $existants[$code]);
    } catch (PlanValidationException $e) {
        foreach ($e->getErrors() as $m) { $erreurs[] = "{$code} : {$m}"; }
    }
}
if ($erreurs !== []) {
    arreter("valeurs refusées par la validation, RIEN n'est écrit :\n  - " . implode("\n  - ", $erreurs));
}

if ($aucunChangement) {
    echo "Rien à faire : les trois plans et les réglages globaux sont déjà conformes à la politique commerciale.\n";
    exit(0);
}
if (!$appliquer) {
    $nb = array_sum(array_map('count', $aFaire));
    echo "SIMULATION TERMINÉE : {$nb} valeur(s) de plan et " . count($reglagesAFaire) . " réglage(s) global(aux) seraient modifiés.\n";
    echo "Pour appliquer : php database/appliquer-politique-commerciale.php --appliquer --sauvegarde=<fichier récent de database/backups>"
        . (in_array(true, array_map(fn($c) => ($utilises[$c] ?? 0) > 0 && isset($aFaire[$c]), array_keys(GRILLE)), true) ? ' --confirmer-plan-utilise' : '') . "\n";
    exit(0);
}

// ─── Garde-fous d'application ───────────────────────────────────────────────────────────────────────────────────────────

$sha = null;
if (!($testeur && array_key_exists('sans-sauvegarde', $options))) {
    $fichier = (string)($options['sauvegarde'] ?? '');
    if ($fichier === '') { arreter("--sauvegarde=<fichier> est obligatoire : une sauvegarde récente et complète doit exister avant d'appliquer."); }
    $reel = realpath($fichier) ?: realpath(ROOT_PATH . '/' . $fichier);
    $dossier = realpath(ROOT_PATH . '/database/backups');
    if ($reel === false || $dossier === false || !str_starts_with(str_replace('\\', '/', $reel), str_replace('\\', '/', $dossier) . '/')) {
        arreter("la sauvegarde doit être un fichier existant de database/backups.");
    }
    if (!preg_match('/^auto_ecole_app_\d{8}_\d{6}\.sql$/', basename($reel))) { arreter('nom de sauvegarde inattendu (auto_ecole_app_AAAAMMJJ_HHMMSS.sql attendu).'); }
    if (time() - (int)filemtime($reel) > 3600) { arreter('la sauvegarde date de plus de 60 minutes : refaites-la (php database/backup.php --verify-restore).'); }
    if (filesize($reel) < 50000) { arreter('la sauvegarde est anormalement petite : refusée.'); }
    $fin = (string)file_get_contents($reel, false, null, max(0, (int)filesize($reel) - 400));
    if (!str_contains($fin, 'Dump completed')) { arreter('la sauvegarde est incomplète (pas de marque de fin de dump) : refusée.'); }
    $sha = hash_file('sha256', $reel);
    echo 'Sauvegarde contrôlée : ' . basename($reel) . ' (' . round(filesize($reel) / 1024) . " Ko, sha256 {$sha})\n\n";
}
foreach (GRILLE as $code => $_) {
    if (isset($aFaire[$code]) && ($utilises[$code] ?? 0) > 0 && !array_key_exists('confirmer-plan-utilise', $options)) {
        arreter("le plan « {$code} » est utilisé par {$utilises[$code]} établissement(s) : ajoutez --confirmer-plan-utilise pour confirmer explicitement le changement de prix et de devise.");
    }
}

// ─── Application ────────────────────────────────────────────────────────────────────────────────────────────────────────

$avant = empreintes($pdo);
$faits = [];
try {
    foreach (GRILLE as $code => $_) {
        if (!isset($aFaire[$code])) { continue; }
        $donnees = cible($code);
        if (($utilises[$code] ?? 0) > 0) { $donnees['confirmer_conditions'] = '1'; }
        $ok = $plans->update((int)$existants[$code]['id'], $donnees, 0);   // 0 = acteur « système » dans le journal d'audit
        $faits[] = "plan {$code} " . ($ok ? 'mis à jour' : 'déjà conforme');
        echo "  ✓ plan {$code} : " . ($ok ? 'mis à jour (audit plan.updated)' : 'déjà conforme') . "\n";
    }
    if ($reglagesAFaire !== []) {
        $cles = $reglages->enregistrer($reglagesAFaire, 0);
        echo '  ✓ réglages globaux : ' . ($cles === [] ? 'déjà conformes' : implode(', ', $cles)) . "\n";
    }
} catch (\Throwable $e) {
    fwrite(STDERR, "\nECHEC en cours d'application : " . $e->getMessage() . "\n");
    fwrite(STDERR, 'Déjà appliqué : ' . ($faits === [] ? 'rien' : implode(' ; ', $faits)) . ". Chaque plan est une opération atomique ; relancer le script reprend là où il s'est arrêté (idempotent).\n");
    exit(1);
}

// ─── Vérification finale ────────────────────────────────────────────────────────────────────────────────────────────────

$apres = empreintes($pdo);
$apresPlans = planParCode($plans);
$ecarts = [];
foreach (GRILLE as $code => $_) {
    foreach (cible($code) as $champ => $voulu) {
        if (normaliserChamp($champ, $apresPlans[$code][$champ]) !== $voulu) { $ecarts[] = "{$code}.{$champ}"; }
    }
}
$intact = $avant === $apres;
echo "\nVérification : plans " . ($ecarts === [] ? 'conformes' : 'NON conformes (' . implode(', ', $ecarts) . ')')
    . ' ; abonnements, établissements et paiements ' . ($intact ? 'strictement inchangés' : 'MODIFIÉS (anormal : ' . implode(', ', array_keys(array_diff_assoc($apres, $avant))) . ')') . ".\n";
exit($ecarts === [] && $intact ? 0 : 3);
