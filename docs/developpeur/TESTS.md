# Tests — SCOLARIS V2

## 1. Pas de framework de test

Ce projet **n'utilise pas PHPUnit** (ni aucun autre framework — cohérent avec
l'absence totale de dépendances Composer, voir
`docs/developpeur/STANDARDS_CODE.md` §1). Chaque fichier de test est un script PHP
autonome exécutable directement en CLI, avec ses propres fonctions `assert_eq()`/
`assert_true()`/`assert_false()` et son propre compteur pass/fail.

> **Exception notable** : `tests/Api/FilterTest.php` a été écrit pour PHPUnit
> (`use PHPUnit\Framework\TestCase`) et échoue immédiatement
> (`Class "PHPUnit\Framework\TestCase" not found"`) puisque PHPUnit n'est pas
> installé. Ses assertions sont dupliquées et fonctionnelles dans
> `tests/Api/PaginationTest.php`. Ne pas suivre ce fichier comme modèle — voir §2
> pour le format correct.

## 2. Format standard d'un fichier de test

```php
<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));

// Charger .env manuellement
foreach (file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$key, $val] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
}

// Autoloader privé minimal (attention : peut masquer un gap du VRAI autoloader
// de production, voir §4)
spl_autoload_register(function (string $class): void { /* ... */ });

$passed = 0; $failed = 0;
function assert_eq(mixed $expected, mixed $actual, string $label): void { /* ... */ }
function assert_true(bool $v, string $label): void { assert_eq(true, $v, $label); }
function section(string $t): void { echo "\n── $t\n"; }

$pdo = Core\Database::getInstance()->getConnection();
$pdo->beginTransaction();
try {
    // ... créer des données de test, exercer le code, assert_*() ...
} finally {
    $pdo->rollBack();   // AUCUNE donnée de test ne doit persister
}

if ($failed > 0) exit(1);
```

**Toute mutation de données de test doit être enveloppée dans une transaction PDO
annulée en fin de script** (`finally { $pdo->rollBack(); }`) — c'est la garantie
de non-pollution utilisée systématiquement dans ce projet.

## 3. Exécuter les tests

```bash
# Un seul fichier
php tests/Unit/TenantResolverTest.php

# Toute une catégorie (PowerShell/bash — pas de runner agrégé fourni)
for f in tests/Unit/*.php; do php "$f"; done
```

Aucun script agrégant l'ensemble de la suite n'existe à ce jour (à créer si le
besoin s'en fait sentir — voir `ROADMAP_V2_1.md`).

## 4. La limite de cette approche — leçon de Phase 15.1

Un fichier de test qui définit son **propre autoloader privé** (voir §2) peut
faire passer un test alors que le code équivalent échouerait en production, si cet
autoloader privé couvre un namespace que le vrai bootstrap
(`public/index.php`) ne couvre pas. C'est exactement ce qui s'est produit pour
`App\Shared\Api\*` : testé vert pendant plusieurs phases via un autoloader de test
qui l'incluait, cassé en production car absent du vrai autoloader — non détecté
jusqu'à un appel HTTP réel en Phase 15.1 (voir
`RELEASE_CANDIDATE_RC1_REPORT.md` §2.2).

**Règle à suivre désormais** : pour toute nouvelle route/contrôleur, complétez les
tests unitaires par **au moins un appel HTTP réel** (`curl` contre l'application
qui tourne réellement, via `public/index.php`) avant de considérer la
fonctionnalité validée.

## 5. Inventaire des suites de tests

| Fichier | Domaine | Assertions |
|---|---|---|
| `tests/security_tests.php` | Sécurité transverse | 48 |
| `tests/functional_tests.php` | Fonctionnel transverse | 109 |
| `tests/Unit/TenantResolverTest.php` | Résolution tenant (sous-domaine/domaine/chemin) | 29 |
| `tests/Unit/TenantIsolationTest.php` | Isolation données par tenant (modèles V1) | 24 |
| `tests/Unit/RbacTenantTest.php` | RBAC multi-tenant | 18 |
| `tests/Unit/BrandingTenantTest.php` | Branding par établissement | 18 |
| `tests/Unit/MultiTenantUserTest.php` | Utilisateurs multi-établissements | 15 |
| `tests/Unit/DomainTenantTest.php` | Domaines personnalisés | 40 |
| `tests/Unit/StorageQuotaTest.php` | Stockage & quotas | 30 |
| `tests/Unit/CacheQueueTest.php` | Cache & files d'attente | 40 |
| `tests/Unit/PlatformAdminTest.php` | Portail Super-Admin | 56 |
| `tests/Unit/BackupDrTest.php` | Sauvegardes & reprise après incident | 43 |
| `tests/Unit/AcademicAnalyticsServiceTest.php`, `AcademicCalculationServiceTest.php`, `BulletinGeneratorTest.php`, `RankingEngineTest.php` | Académique | (non ré-inventoriées ici, non modifiées récemment) |
| `tests/Api/ApiKeyTest.php` | Clés API | 20 |
| `tests/Api/AuthApiTest.php` | Authentification JWT | 18 |
| `tests/Api/PaginationTest.php` (+ Filter + Sort) | Pagination/filtrage/tri API | 38 |
| `tests/Api/RateLimitTest.php` | Limitation de débit | 29 |
| `tests/Api/WebhookTest.php` (+ ApiKey) | Webhooks | 15 |
| `tests/Api/FilterTest.php` | ⚠️ Cassé (PHPUnit non installé) — voir §1 | — |

**Total suite active (hors fichier cassé)** : 530 assertions, toutes vertes à la
date de la Phase 15.1 (`RELEASE_CANDIDATE_RC1_REPORT.md` §4).

## 6. Documents associés

`RELEASE_CANDIDATE_RC1_REPORT.md`, `MULTI_TENANT_SYSTEM_INTEGRATION_REVIEW.md`.
