<?php
/**
 * Tests — normalisation des numéros de téléphone du canal SMS (SmsService::normalizePhone).
 *
 * Défaut corrigé : les zéros de tête étaient retirés AVANT de tester le préfixe « 00 », donc « 00227… » devenait « 227… » puis
 * recevait l'indicatif une seconde fois (« 227227… ») ; un numéro déjà complet sans « + » (« 227… ») était lui aussi doublé.
 *
 * 1. Configuration par défaut : Niger (indicatif 227, numéros locaux à 8 chiffres)
 * 2. Numéros locaux : indicatif ajouté, séparateurs et zéro de tête retirés
 * 3. Numéros internationaux (« + » / « 00 ») : jamais modifiés, jamais préfixés deux fois
 * 4. Numéros déjà complets sans « + » : pas de double indicatif
 * 5. Numéros inutilisables : refusés (chaîne vide)
 * 6. Autre pays (indicatif 221, local 9 chiffres) : la logique n'est pas figée sur le Niger
 *
 * Aucun accès base de données.
 *
 * Lance : C:\wamp64\bin\php\php8.2.29\php.exe tests/Unit/SmsNormalisationTest.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
    if (str_starts_with(trim($l), '#') || !str_contains($l, '=')) { continue; }
    [$k, $v] = explode('=', $l, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}
require_once ROOT_PATH . '/app/Services/SmsService.php';

$passed = 0; $failed = 0;
function check(bool $ok, string $label, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "\033[32m  ✓ $label\033[0m\n"; }
    else     { $failed++; echo "\033[31m  ✗ $label" . ($detail !== '' ? " — $detail" : '') . "\033[0m\n"; }
}
function section(string $t): void { echo "\n\033[33m── $t\033[0m\n"; }

/** Normalise $numero avec la configuration donnée, sans passer par le constructeur (pas de lecture de .env / passerelle). */
function normaliser(string $numero, array $config): string
{
    $svc = (new ReflectionClass(\App\Services\SmsService::class))->newInstanceWithoutConstructor();
    $p = new ReflectionProperty($svc, 'config'); $p->setAccessible(true); $p->setValue($svc, $config);
    $m = new ReflectionMethod($svc, 'normalizePhone'); $m->setAccessible(true);
    return $m->invoke($svc, $numero);
}

// ═══════════════════════════════════════════════════════════
section('1. Configuration par défaut');
$reelle = require ROOT_PATH . '/config/sms.php';
$defaut = ['country_code' => '227', 'local_length' => 8];
check($reelle['country_code'] === '227' || isset($_ENV['SMS_COUNTRY_CODE']), 'indicatif par défaut : 227 (Niger)', (string)$reelle['country_code']);
check($reelle['local_length'] === 8 || isset($_ENV['SMS_LOCAL_LENGTH']), 'longueur locale par défaut : 8 chiffres', (string)$reelle['local_length']);
check(normaliser('90123456', $reelle) !== '' || isset($_ENV['SMS_COUNTRY_CODE']), 'la vraie configuration produit un numéro');

// ═══════════════════════════════════════════════════════════
section('2. Numéros locaux');
foreach ([
    ['90123456',       '22790123456'],
    ['90 12 34 56',    '22790123456'],
    ['90-12-34-56',    '22790123456'],
    ['(90) 12.34.56',  '22790123456'],
    ['090123456',      '22790123456'],
    [' 90123456 ',     '22790123456'],
    ['20 73 45 67',    '22720734567'],
    ['87654321',       '22787654321'],
] as [$entree, $attendu]) {
    check(normaliser($entree, $defaut) === $attendu, json_encode($entree) . " → $attendu", normaliser($entree, $defaut));
}

// ═══════════════════════════════════════════════════════════
section('3. Numéros internationaux : inchangés, jamais doublés');
foreach ([
    ['+22790123456',        '+22790123456'],
    ['+227 90 12 34 56',    '+22790123456'],
    ['+(227) 90-12-34-56',  '+22790123456'],
    ['+221771234567',       '+221771234567'],
    ['+33612345678',        '+33612345678'],
    ['0022790123456',       '0022790123456'],
    ['00227 90 12 34 56',   '0022790123456'],
    ['00221771234567',      '00221771234567'],
    ['0090123456',          '0090123456'],   // « 00 » = préfixe international : jamais interprété comme un zéro de tête local
] as [$entree, $attendu]) {
    check(normaliser($entree, $defaut) === $attendu, json_encode($entree) . " → $attendu", normaliser($entree, $defaut));
}
check(!str_contains(normaliser('0022790123456', $defaut), '227227'), 'le cas du défaut : « 00227… » n\'est plus préfixé une seconde fois');

// ═══════════════════════════════════════════════════════════
section('4. Numéros déjà complets, sans « + »');
foreach ([
    ['22790123456',   '22790123456'],
    ['227 90 12 34 56', '22790123456'],
    ['022790123456',  '22790123456'],
] as [$entree, $attendu]) {
    check(normaliser($entree, $defaut) === $attendu, json_encode($entree) . " → $attendu (pas de double indicatif)", normaliser($entree, $defaut));
}
// Un numéro local de 8 chiffres qui COMMENCE par 227 n'est pas confondu avec un numéro complet (longueurs différentes).
check(normaliser('22712345', $defaut) === '22722712345', 'numéro local de 8 chiffres commençant par 227 : indicatif bien ajouté (longueur différente)');

// ═══════════════════════════════════════════════════════════
section('5. Numéros inutilisables');
foreach (['', '   ', 'abc', '123', '+123', '001234', '000000', '+', '00', '12-34'] as $mauvais) {
    check(normaliser($mauvais, $defaut) === '', json_encode($mauvais) . ' refusé (chaîne vide)', normaliser($mauvais, $defaut));
}

// ═══════════════════════════════════════════════════════════
section('6. Autre pays : Sénégal (221, local à 9 chiffres)');
$sn = ['country_code' => '221', 'local_length' => 9];
check(normaliser('77 123 45 67', $sn) === '221771234567', 'local sénégalais : indicatif 221 ajouté');
check(normaliser('221771234567', $sn) === '221771234567', 'numéro sénégalais déjà complet : inchangé');
check(normaliser('+221771234567', $sn) === '+221771234567', 'international explicite : inchangé');
check(normaliser('00221771234567', $sn) === '00221771234567', 'préfixe 00 : inchangé');
check(normaliser('90123456', $sn) === '22190123456', 'un numéro de 8 chiffres reçoit l\'indicatif du pays configuré');
$sansConfig = normaliser('90123456', []);
check($sansConfig === '22790123456', 'configuration vide : repli sur le Niger (227, 8 chiffres)', $sansConfig);

echo "\n$passed OK, $failed KO\n";
exit($failed ? 1 : 0);
