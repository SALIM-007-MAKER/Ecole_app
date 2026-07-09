# MILESTONE 3 — ARCHITECTURE REVIEW GLOBAL
## SCOLARIS V2 — Avant Module RH

**Date :** 2026-07-02  
**Auditeur :** Claude Code (claude-sonnet-4-6)  
**Scope :** Architecture globale SCOLARIS V2 — Core + 4 modules V2 (Scolarité, Académique, Finance, Vie Scolaire)  
**Objectif :** Audit pré-RH — vérifier que le socle est sain avant d'ajouter un 5e module  
**Contrainte :** Lecture seule — aucune modification de code

---

## TABLE DES MATIÈRES

1. [Résumé Exécutif](#1-résumé-exécutif)
2. [État du Système au Milestone 3](#2-état-du-système-au-milestone-3)
3. [Dimension 1 — Architecture Globale](#3-dimension-1--architecture-globale)
4. [Dimension 2 — Base de Données](#4-dimension-2--base-de-données)
5. [Dimension 3 — Event System](#5-dimension-3--event-system)
6. [Dimension 4 — Shared Services](#6-dimension-4--shared-services)
7. [Dimension 5 — RBAC & Permissions](#7-dimension-5--rbac--permissions)
8. [Dimension 6 — Sécurité](#8-dimension-6--sécurité)
9. [Dimension 7 — Performance](#9-dimension-7--performance)
10. [Dimension 8 — Compatibilité V1](#10-dimension-8--compatibilité-v1)
11. [Dimension 9 — Dette Technique](#11-dimension-9--dette-technique)
12. [Dimension 10 — Préparation Module RH](#12-dimension-10--préparation-module-rh)
13. [Matrice des Problèmes](#13-matrice-des-problèmes)
14. [Score Global & Verdict](#14-score-global--verdict)

---

## 1. Résumé Exécutif

Ce rapport présente l'audit d'architecture global du système SCOLARIS V2 avant le démarrage du module RH (Ressources Humaines). L'audit couvre l'ensemble de la pile : Core framework, 4 modules V2, shared services, RBAC, sécurité, performance et préparation au prochain module.

**Score global : 7.2 / 10**

**Verdict : GO RH WITH FIXES**

Le socle architectural est sain et cohérent. Le framework custom PHP 8.2 a prouvé sa robustesse à travers 4 modules V2 distincts. Les patterns établis (Repository/Service/DTO/Policy/Event) sont uniformément appliqués. Trois problèmes critiques doivent être traités avant ou en parallèle du démarrage du module RH — aucun n'est bloquant pour commencer la conception, mais ils doivent être résolus avant la première mise en production partielle.

---

## 2. État du Système au Milestone 3

### 2.1 Modules V2 — Inventaire

| Module | Version | Enabled | Phase finale | Migrations SQL | Verdict freeze |
|--------|---------|---------|-------------|----------------|----------------|
| **Scolarité** | v2.5.0 | **false** | 1.6 | 3 PENDING (fichiers non créés) | NO-GO conditionnel (DT-M1) |
| **Académique** | v2.1.0 | **false** | 2.10 | 1 PENDING (fichier non créé) | NO-GO conditionnel (3 AN-C) |
| **Finance** | v1.5.0 | **true** | 3.7 | 5 done + 1 PENDING (décaissements) | NO-GO conditionnel (3 FN-C) |
| **Vie Scolaire** | v2.6.0 | **true** | 5.9 | 7 done | GO with minor improvements |

> **Observation critique** : Le module.json d'Académique (lu directement) affiche `"phase": "2.1"` et liste seulement `PeriodeController` — il n'a pas été mis à jour après les phases 2.2 à 2.10. L'implémentation réelle est complète (338 tests confirmés en Phase 2.10), mais la déclaration de module est obsolète.

### 2.2 Core Framework — Inventaire

| Composant | Fichier | État |
|-----------|---------|------|
| Bootstrap | `core/Application.php` | Stable |
| Router | `core/Router.php` | Stable |
| Event Dispatcher | `core/EventDispatcher.php` | Stable |
| Database | `core/Database.php` | Stable |
| Controller base | `core/Controller.php` | Stable |
| View engine | `core/View.php` | Stable |

### 2.3 Shared Services — Inventaire

| Service | État | Notes |
|---------|------|-------|
| `AuditService` | Complet | sanitize(), diff(), logLogin(), pagination |
| `NotificationService` | Partiel | Triggers V1 uniquement (4 triggers) |
| `UploadService` | Complet | MIME check, GD resize, path traversal |
| `EmailService` | Thin wrapper | php mail() |
| `SmsService` | Thin wrapper | Provider externe |

### 2.4 Chiffres Globaux V2

| Dimension | Chiffre |
|-----------|---------|
| Modules V2 | 4 (2 enabled, 2 disabled) |
| Tables SQL V2 | ~63 (28 Finance + 29 VS + 6 Scolarité pending + Académique pending) |
| Migrations exécutées | 12 (5 Finance + 7 VS) |
| Migrations PENDING | 5 (3 Scolarité + 1 Académique + 1 Finance décaissements) |
| Événements total | ~90 (26 Scolarité + 26 Académique + 27 Finance + 28 VS) |
| Permissions total | ~120 (20 Scolarité + 3 Académique + 31 Finance + 36 VS) |
| Fichiers PHP (modules) | ~350+ estimés |

---

## 3. Dimension 1 — Architecture Globale

**Score : 8 / 10**

### Points forts

**Pattern MVC uniforme :** Les 4 modules V2 appliquent rigoureusement le pattern `Controller → Service → Repository`, avec séparation claire des responsabilités. Les contrôleurs sont thin (validation + dispatch + render), les services contiennent la logique métier, les repositories ont le monopole SQL.

**Module system fonctionnel :** `config/modules.php` orchestre le chargement conditionnel des routes. `Core\Application` charge les routes module uniquement si `enabled: true`. Les événements sont chargés inconditionnellement depuis `config/events.php`. Cette asymétrie est intentionnelle (routes disabled ≠ events disabled).

**Autoloader PSR-4 :** `App\Modules\` namespace correctement configuré. `View::Module::path` notation pour les vues de module. La résolution `str_contains($handler, '\\')` dans Router distingue V1 (`App\Controllers\X`) de V2 (`App\Modules\X`).

**Isolation des modules :** Chaque module est autonome (module.json, routes.php, controllers, services, repos, dtos, policies, events, listeners, views). Aucun module V2 n'importe directement un autre module V2.

### Problèmes identifiés

**AR-M-001 — Scolarité et Académique désactivés (Majeur)**  
`enabled: false` dans modules.php signifie que leurs routes ne sont pas chargées. Les utilisateurs ne peuvent pas accéder aux fonctionnalités V2 Scolarité et Académique malgré des mois de développement. Ce sont 2 modules complets inaccessibles en production.

**AR-M-002 — module.json Académique obsolète (Majeur)**  
Le fichier déclare `"phase": "2.1"` et liste uniquement `PeriodeController` alors que les phases 2.1 à 2.10 sont implémentées (TypesÉvaluations, Notes, Moyennes, Bulletins, Ranking, Analytics, etc.). Ce décalage documentation/code crée une ambiguïté sur l'état réel du module.

**AR-M-003 — Couplage Application/EventDispatcher au boot (Mineur)**  
`events.php` est chargé à chaque requête et instancie de nouveaux objets Listener à chaque fois. Sur un système avec ~90 événements et ~4-5 listeners chacun, c'est ~450 objets créés à chaque requête pour ne potentiellement n'en utiliser qu'un.

---

## 4. Dimension 2 — Base de Données

**Score : 7 / 10**

### Points forts

**Sécurité des migrations :** Toutes les migrations V2 utilisent `CREATE TABLE IF NOT EXISTS`. Aucun `DROP TABLE` sur tables avec données. Soft delete (`deleted_at`) systématique dans tous les modules. Pattern de migration sécurisé.

**Isolation des namespaces SQL :** `finance_` pour Finance, `vs_` pour Vie Scolaire. Zéro collision avec les tables V1 existantes. Scolarité V2 réutilise les tables V1 existantes (`eleves`, `classes`, `matieres`) avec des ALTER TABLE additifs.

**PDO Singleton propre :** `core/Database.php` expose un singleton PDO avec `getConnection()`. Paramétrage PDO conforme dans `config/database.php`. Toutes les requêtes V2 utilisent des paramètres liés (zero SQL injection).

**Charset et collation :** DSN inclut `charset=utf8mb4` (implicite dans les migrations). Support Unicode complet.

### Problèmes identifiés

**AR-C-001 — Migrations SQL de Scolarité et Académique non créées (Critique)**  
Le module.json de Scolarité liste 3 migrations `PENDING` mais ces fichiers SQL n'existent pas dans `database/migrations/`. Même constat pour Académique (1 migration pending). Les modules ne peuvent pas être activés sans que ces migrations soient créées ET exécutées sur la base de données.

```
PENDING (fichiers inexistants) :
- database/migrations/migration_inscriptions.sql   (Scolarité Phase 1.4)
- database/migrations/migration_familles.sql        (Scolarité Phase 1.5)
- database/migrations/migration_matieres.sql        (Scolarité Phase 1.6)
- database/migrations/migration_periodes_scolaires.sql (Académique Phase 2.1)
- database/migrations/finance_006_decaissements.sql (Finance Phase décaissements)
```

**AR-M-004 — Pas de migration tracker (Majeur)**  
Aucun système ne trace quelles migrations ont été exécutées sur quelle base de données. Les migrations sont des scripts manuels exécutés hors application. Risque de double-exécution ou d'oubli lors des déploiements.

**AR-M-005 — N+1 dans plusieurs repositories (Majeur)**  
Constaté dans Phase 5.8 (ActivityRepository.findAll, TimetableRepository) : des sous-requêtes corrélées dans les SELECT pour des compteurs (`nb_inscrits`, `nb_attente`, `nb_creneaux`) génèrent un N+1 SQL invisible. À mesure que les tables grandissent, ce pattern deviendra un goulot d'étranglement.

**AR-mn-001 — incrementVersion() non-atomique (Mineur)**  
Dans TimetableRepository : UPDATE + SELECT séquentiel sans transaction. En cas de requêtes concurrentes, deux requêtes peuvent lire le même numéro de version.

---

## 5. Dimension 3 — Event System

**Score : 6.5 / 10**

### Points forts

**Isolation par try/catch :** `EventDispatcher::dispatch()` wrappe chaque listener dans un try/catch individuel. Un listener qui échoue ne bloque pas les autres. Comportement défensif correct.

**`forget()` pour les tests :** Méthode de cleanup propre pour les tests unitaires. Pattern testé et validé (338 tests Académique, 103 Analytics).

**Sémantique past-tense uniforme :** Tous les événements utilisent le past-tense (`EleveInscrit`, `PaymentCompleted`, `ActivityPublished`). Cohérence totale sur les ~90 événements.

**Cross-domain via string matching :** La technique `str_ends_with($class, 'EventName')` dans les DisciplineIntegrationHandler et équivalents évite les imports directs inter-modules. Couplage faible maintenu.

### Problèmes identifiés

**AR-C-002 — Doublons de clés PHP dans events.php — Finance (Critique)**  
Identifié en Phase 5.8 (VS-C-002). Quatre événements Finance ont deux entrées comme clé PHP dans le tableau de configuration :
- `PaymentCompleted::class` → entrée 1 (PaymentHandler) écrasée par entrée 2 (PaymentHandler + AccountingHandler)
- `PaymentRefunded::class` → même pattern
- `CashMovementCreated::class` → même pattern  
- `InvoiceCancelled::class` → même pattern

PHP ne lève pas d'erreur sur les clés dupliquées — la dernière entrée gagne silencieusement. Actuellement les handlers manquants ne sont pas perdus (la 2e entrée contient tous les handlers de la 1re + AccountingHandler). Mais ce pattern est **extrêmement fragile** : un futur développeur ajoutant un listener en entrée 1 verra son listener ignoré sans avertissement.

**AR-M-006 — Événements Scolarité/Académique wired mais routes disabled (Majeur)**  
Les listeners pour Scolarité et Académique sont enregistrés à chaque requête (events.php chargé inconditionnellement), mais les routes de ces modules ne sont pas accessibles. Les événements ne seront jamais dispatched. Overhead de boot sans bénéfice.

**AR-M-007 — Pas de queue/async pour les listeners lents (Majeur)**  
Le système est entièrement synchrone. Un NotificationListener qui ferait un appel SMTP ou SMS bloquerait la réponse HTTP. Actuellement les NotificationListeners sont des stubs (MS2-M-004) — mais lorsqu'ils seront implémentés, ce sera un problème de performance bloquant.

**AR-mn-002 — Listeners instanciés à chaque requête (Mineur)**  
Application.php charge events.php à chaque requête. Chaque `listen()` appelle `new ConcreteListener()`. ~90 événements × ~3 listeners = ~270 instanciations par requête, dont 0 sont utilisées si la requête ne dispatch aucun événement.

---

## 6. Dimension 4 — Shared Services

**Score : 7.5 / 10**

### Points forts

**AuditService — Excellente implémentation :**  
- `sanitize()` masque les clés sensibles (password, token, etc.)  
- `diff()` calcule les modifications champ par champ (before/after)  
- `logLogin()` pour traçabilité des authentifications  
- `getIp()` respecte X-Forwarded-For (compatible reverse proxy)  
- `insert()` silencieux (try/catch) — l'audit ne bloque jamais la requête métier  
- `search()` paginée avec filtres

**UploadService — Implémentation sécurisée :**  
- Vérification MIME réelle (`mime_content_type()` sur le fichier tmp, pas le type déclaré)  
- Protection path traversal dans `delete()` (must start with `storage/`)  
- Nommage `bin2hex(random_bytes(8))` (entropie suffisante)  
- Resize GD conditionnel (graceful si extension absente)  
- URL servie via route sécurisée `/uploads/serve/{type}/{filename}`

**NotificationService — Architecture multichannel correcte :**  
- 3 canaux : interne (DB), email (SMTP), SMS (provider externe)  
- Préférences par utilisateur et par trigger
- Log systématique (succès et échec) via NotificationLogModel  
- `notifyBulk()` avec `array_unique()` pour déduplication

### Problèmes identifiés

**AR-M-008 — NotificationService : triggers V1 uniquement (Majeur)**  
Les 4 triggers déclarés (`note`, `absence`, `paiement`, `annonce`) correspondent aux fonctionnalités V1. Les modules V2 (Discipline, Récompenses, EmploisDuTemps, Activités, Finance) ont chacun leurs NotificationListeners en stub. Lorsque ces stubs seront implémentés, ils devront soit utiliser les 4 triggers existants (inadapté sémantiquement), soit ajouter de nouveaux triggers à NotificationService (et `TRIGGERS` constant + préférences).

**AR-M-009 — Tous les NotificationListeners V2 sont des stubs (Majeur)**  
Confirmé Phase 4.0 (MS2-M-004) : aucun module V2 n'envoie réellement de notifications aux utilisateurs via le canal NotificationService. Les events sont dispatchés, les listeners les reçoivent, mais n'appellent pas `NotificationService::notify()`. Le système de notification est donc non-fonctionnel pour toutes les nouvelles fonctionnalités V2.

**AR-mn-003 — EmailService utilise PHP mail() (Mineur)**  
`EmailService` wrapper de `mail()` natif. Pas de queue, pas de retry, pas de deliverability tracking. Acceptable pour un MVP scolaire, mais limitant pour un volume de notifications important (ex: annonce à toute l'école).

---

## 7. Dimension 5 — RBAC & Permissions

**Score : 7.5 / 10**

### Points forts

**Double vérification systématique :** Les controllers V2 appellent `requirePermission()` (session-based) ET délèguent à la Policy (`canX($user)`). Défense en profondeur.

**Logger::security intégré :** `requirePermission()` et `verifyCsrf()` loguent les tentatives non-autorisées via `Logger::security`. Traçabilité des intrusions.

**Coverage 7 rôles × 120 permissions :** Chaque rôle a une liste de permissions explicite dans `config/permissions.php`. Pas de permissions implicites ou héritées. Politique de moindre privilège respectée.

**Policies par module :** Chaque domaine a sa propre Policy (`ElevePolicy`, `InvoicePolicy`, `ActivityPolicy`, etc.) avec une logique métier affinée (ex: `FamillePolicy::canViewEleve` vérifie la relation parent-enfant).

### Problèmes identifiés

**AR-M-010 — RBAC V2 tables non utilisées pour l'autorisation (Majeur)**  
Les tables `roles`, `permissions`, `role_permissions`, `user_roles` existent (Phase Foundation V2) mais aucun contrôleur ne les interroge pour l'autorisation. La source de vérité reste `$_SESSION['permissions']` chargée depuis `config/permissions.php` au login. RBAC V2 = infrastructure morte.

**AR-mn-004 — Gap: secrétaire manque attendance.session.validate (Mineur)**  
Identifié Phase 5.8 : le rôle `secrétaire` a `attendance.view` mais pas `attendance.session.validate`. Un secrétaire ne peut pas valider les sessions de présence. Potentiellement intentionnel, mais non documenté.

**AR-mn-005 — Modules disabled = permissions inutiles (Mineur)**  
Les permissions `eleves.*`, `classes.*`, `matieres.*`, `inscriptions.*`, `familles.*` (Scolarité V2) et `academique.periodes.*` sont dans `config/permissions.php` et chargées en session — mais les routes correspondantes n'existent pas (`enabled: false`). Overhead de session mineur.

---

## 8. Dimension 6 — Sécurité

**Score : 7 / 10**

### Points forts

**CSRF systématique :** `verifyCsrf()` appliqué sur toutes les routes POST/PUT/DELETE dans les modules V2. Token généré par session. Rotation implicite à chaque nouvelle session.

**PDO paramétré sans exception :** Aucune concaténation SQL directe dans les repositories V2. `buildWhere()` construits avec des bindings. Zéro injection SQL identifiée.

**Security headers :** `sendSecurityHeaders()` appliqué dans `Core\Controller` : CSP, X-Frame-Options (SAMEORIGIN), X-Content-Type-Options (nosniff), Referrer-Policy (strict-origin-when-cross-origin).

**Soft delete universel :** `deleted_at` présent sur toutes les entités principales. Aucun DELETE physique sur données métier. Auditabilité complète.

**UploadService MIME-safe :** Vérification du MIME sur le fichier temporaire réel, pas sur le header HTTP déclaré par le client. Protection contre les uploads malveillants déguisés.

### Problèmes identifiés

**AR-C-003 — XSS dans 3 vues Vie Scolaire (Critique)**  
Identifié Phase 5.8 : les vues `activites/statistiques.php`, `activites/inscrire.php`, `activites/edit.php` affichent les messages flash avec `<?= $_SESSION['flash_error'] ?>` sans `htmlspecialchars()`. Un attaquant contrôlant un message flash (via redirection forgée) peut injecter du HTML/JavaScript.

```php
// Vulnérable (3 vues concernées)
<?= $_SESSION['flash_error'] ?>

// Correct
<?= htmlspecialchars($_SESSION['flash_error'] ?? '', ENT_QUOTES, 'UTF-8') ?>
```

**AR-M-011 — CSP avec 'unsafe-inline' (Majeur)**  
`Content-Security-Policy: script-src 'self' 'unsafe-inline' cdn.tailwindcss.com cdn.jsdelivr.net`  
`unsafe-inline` annule la protection XSS du CSP. Adopté pour le CDN Tailwind/Lucide. Acceptable temporairement mais doit évoluer vers des nonces ou SRI hash en production.

**AR-M-012 — HTTP_REFERER non validé dans back() et verifyCsrf() (Majeur)**  
`Core\Controller::back()` redirige vers `$_SERVER['HTTP_REFERER']` sans validation. `verifyCsrf()` redirige vers REFERER en cas d'échec. Open redirect potentiel si REFERER est forgé par un attaquant.

**AR-M-013 — APP_KEY null par défaut (Majeur)**  
`config/app.php` : `'key' => $_ENV['APP_KEY'] ?? null`. Si `.env` ne définit pas APP_KEY, la clé est null. Selon l'utilisation de cette clé (génération de tokens, HMAC), une clé nulle affaiblit significativement la sécurité cryptographique.

**AR-mn-006 — Pas de rate limiting (Mineur)**  
Aucun rate limiting sur les endpoints d'authentification ou les routes sensibles. Un attaquant peut effectuer des milliers de tentatives de login. Acceptable sur un réseau scolaire fermé, problématique si exposé sur Internet.

---

## 9. Dimension 7 — Performance

**Score : 6.5 / 10**

### Points forts

**PDO Singleton :** Une seule connexion PDO par requête. Pas d'ouverture/fermeture répétée.

**Pagination systématique :** Tous les index des modules V2 utilisent `LIMIT ? OFFSET ?`. Pas de `SELECT *` illimité sur les listes.

**Soft delete indexé :** `deleted_at IS NULL` dans tous les WHERE. Les tables sont petites en contexte scolaire (< 10 000 élèves typiquement) — performance acceptable même sans index partiel.

### Problèmes identifiés

**AR-M-014 — N+1 correlated subqueries dans findAll() (Majeur)**  
Plusieurs repositories utilisent des sous-requêtes corrélées dans les SELECT principaux :
- `ActivityRepository::findAll()` — `(SELECT COUNT(*) FROM vs_activite_inscriptions WHERE activite_id = a.id ...)` par ligne
- `TimetableRepository::findAllEdts()` — `(SELECT COUNT(*) FROM vs_edt_creneaux WHERE edt_id = e.id ...)` par ligne
- Pattern similaire probable dans Finance et Scolarité

Pour 100 activités : 100 sous-requêtes supplémentaires exécutées. À optimiser avec JOIN + GROUP BY ou champ dénormalisé.

**AR-M-015 — Listeners instanciés à chaque requête HTTP (Majeur)**  
`Application.php` charge `config/events.php` à chaque requête. Ce fichier fait `EventDispatcher::listen(EventClass::class, new ConcreteListener())` pour chaque mapping. Avec ~90 événements et ~3 listeners chacun : ~270 instanciations à chaque requête, sans cache d'opcode suffisant pour les objets.

**AR-mn-007 — Pas de cache applicatif (Mineur)**  
Aucun APCu, Redis, Memcached, ou cache fichier. Chaque requête recharge : `config/permissions.php` (via session), `config/modules.php`, `config/events.php`. Pour un usage scolaire (< 100 utilisateurs simultanés), c'est acceptable. Pour un déploiement multi-établissement, ce sera limitant.

**AR-mn-008 — Pas d'index composite sur les colonnes de filtrage (Mineur)**  
Les migrations V2 définissent les clés primaires et étrangères, mais peu d'index composites sur les colonnes de filtrage fréquents. Exemple : `(annee_scolaire, statut, classe_id)` sur `vs_absences` pour les requêtes de statistiques.

---

## 10. Dimension 8 — Compatibilité V1

**Score : 9 / 10**

### Points forts

**Zéro modification de routes V1 :** Confirmé à travers toutes les phases. Aucune route V1 touchée. `Core\Router` applique first-match-wins avec les routes V1 chargées avant les modules V2.

**Préfixes SQL non-conflictuels :** `finance_` et `vs_` sans collision avec les tables V1 (`eleves`, `classes`, `professeurs`, `notes`, `paiements`, etc.).

**Coexistence des contrôleurs :** V1 = `App\Controllers\X`, V2 = `App\Modules\X`. Détection automatique par présence de backslash dans le handler name. Aucune ambiguïté.

**Session partagée :** Les modules V2 lisent `$_SESSION['user']` exactement comme V1. Authentification compatible.

### Problème identifié

**AR-mn-009 — Académique V2 dépend de Scolarité V2 (disabled) (Mineur)**  
`module.json` Académique : `"depends": ["scolarite"]`. Or Scolarité est `enabled: false`. Si Académique était activé, ses routes fonctionneraient, mais ses Services qui appellent des données Scolarité V2 (InscriptionService, EleveService) accéderaient à des tables non-migrées. Dépendance de module non enforced au runtime.

---

## 11. Dimension 9 — Dette Technique

**Score : 6 / 10**

### Dette héritée des Milestones précédents

| ID | Source | Description | Sévérité |
|----|--------|-------------|----------|
| DT-FN-C001 | Phase 3.9 | double-entrée comptabilité non testée en production | Critique |
| DT-FN-C002 | Phase 3.9 | Décaissements non implémentés (finance_006_decaissements.sql manquant) | Critique |
| DT-FN-C003 | Phase 3.9 | Réconciliation caisse vs comptabilité non automatique | Critique |
| DT-AN-C001 | Phase 2.10 | Calcul moyennes : règle coefficients non implémentée uniformément | Critique |
| DT-AN-C002 | Phase 2.10 | Bulletins PDF : template non validé graphiquement | Critique |
| DT-AN-C003 | Phase 2.10 | RankingEngine : ex-aequo sur moyennes strictement égales | Critique |
| DT-SC-M1 | Phase 1.7 | MatiereAssigned/Removed jamais dispatched | Majeur |
| DT-M004 | Phase 4.0 | NotificationListeners tous stubs dans 4 modules | Majeur |
| VS-C-001 | Phase 5.8 | Race condition inscriptions activités (UNIQUE NULL) | Critique |
| VS-C-002 | Phase 5.8 | events.php : 4 clés Finance dupliquées | Critique |

### Dette identifiée dans ce Milestone

| ID | Description | Sévérité |
|----|-------------|----------|
| AR-C-001 | 5 migrations SQL PENDING non créées comme fichiers | Critique |
| AR-C-002 | = VS-C-002 (confirmé dans Dim. 3) | Critique |
| AR-C-003 | = XSS flash messages 3 vues VS (Dim. 6) | Critique |
| AR-M-002 | module.json Académique obsolète (phase 2.1 au lieu de 2.10) | Majeur |
| AR-M-010 | RBAC V2 tables non utilisées pour l'autorisation | Majeur |
| AR-M-013 | APP_KEY null par défaut | Majeur |

### Vélocité de la dette

Chaque milestone a accumulé une dette non-résolue avant d'avancer. Le pattern `NO-GO conditionnel → avancer quand même` a permis de progresser rapidement mais crée un arriéré de ~10 problèmes critiques. Avant la mise en production du système complet, un sprint de remédiation sera nécessaire.

---

## 12. Dimension 10 — Préparation Module RH

**Score : 7.5 / 10**

### Analyse des prérequis

#### Ce qui est PRÊT pour le module RH

**Framework :** Le pattern Module complet est éprouvé (4 modules, ~350 fichiers, patterns uniformes). La création d'un nouveau module suit la procédure établie en Phase 1.1 :
1. `mkdir app/Modules/RH/`
2. Ajouter entrée dans `config/modules.php`
3. Créer `module.json`, `routes.php`, `controllers/`, `services/`, `repositories/`, `dto/`, `policies/`, `events/`, `listeners/`, `views/`

**Shared Services disponibles :**  
- `AuditService` — prêt à l'emploi, aucune modification requise
- `UploadService` — prêt (photo employé = type `photo_professeur` ou nouveau type `photo_employe`)
- `NotificationService` — utilisable avec trigger `annonce` en attendant les triggers RH dédiés

**Tables V1 réutilisables :**  
- `professeurs` (V1) contient enseignants avec email, téléphone, spécialité
- `users` (V1) contient comptes utilisateurs
- Le module RH peut enrichir ces entités via des tables RH additionnelles (contrats, congés, fiches de paie) sans ALTER TABLE destructif

**RBAC prêt :** Nouveau préfixe `rh.*` à ajouter dans `config/permissions.php` pour les permissions RH.

#### Ce qui MANQUE ou DOIT ÊTRE DÉCIDÉ

**Périmètre RH — Domaines à définir :**
Les domaines typiques d'un module RH scolaire :
1. **Employés** — fiche employé, données administratives, documents
2. **Contrats** — type (CDI/CDD/vacation), dates, rémunération
3. **Postes & Organigramme** — directions, départements, hiérarchie
4. **Congés & Absences RH** — demandes, soldes, validation workflow
5. **Paie** — fiches de paie, éléments variables, virements
6. **Évaluation** — entretiens annuels, objectifs (optionnel)

**Table de référence manquante : `rh_employes`**  
Le module RH a besoin d'une entité centrale `employe` distincte de `user` et de `professeur`. La relation tri-directionnelle (un employé peut être un `user` ET un `professeur` V1) doit être conçue avec soin pour ne pas casser V1.

**Dépendance sur Scolarité V2 (disabled) :**  
Si les enseignants gérés par RH doivent être liés aux `ClasseModel` et `MatiereModel` de Scolarité V2, ce module devra être activé (avec ses migrations). Décision architecturale à prendre avant Phase 6.1.

**Dépendance Finance pour la paie :**  
Les fiches de paie génèrent des décaissements → `finance_006_decaissements.sql` doit exister. Or cette migration est PENDING. Le sous-module Paie du RH dépend d'une dette Finance non résolue.

#### Recommandations pour Phase 6.x

1. **Commencer par les domaines indépendants** : Employés + Contrats + Congés n'ont pas de dépendance sur Scolarité V2 activée ni sur Finance décaissements.
2. **Différer la Paie** jusqu'à résolution de `finance_006_decaissements.sql`.
3. **Ne pas activer Scolarité V2** pour RH Phase 6.x — utiliser les tables V1 `professeurs` + jointure.
4. **Préfixe SQL proposé** : `rh_` pour toutes les nouvelles tables RH.

---

## 13. Matrice des Problèmes

### Critiques (bloquants prod, pas nécessairement bloquants dev RH)

| ID | Dimension | Description | Action requise |
|----|-----------|-------------|----------------|
| **AR-C-001** | Base de données | 5 migrations SQL PENDING non créées comme fichiers | Créer les fichiers SQL avant activation des modules |
| **AR-C-002** | Event System | 4 entrées Finance dupliquées dans events.php | Fusionner les 4 doublons |
| **AR-C-003** | Sécurité | XSS flash messages dans 3 vues Vie Scolaire | Ajouter htmlspecialchars() |

### Majeures (à traiter en V2.1)

| ID | Dimension | Description |
|----|-----------|-------------|
| AR-M-001 | Architecture | Scolarité et Académique désactivés en production |
| AR-M-002 | Architecture | module.json Académique obsolète (phase 2.1 affiché vs 2.10 réel) |
| AR-M-007 | Event System | Pas de queue/async pour listeners lents (SMS/email) |
| AR-M-008 | Shared Services | NotificationService triggers V1 uniquement |
| AR-M-009 | Shared Services | Tous les NotificationListeners V2 sont des stubs |
| AR-M-010 | RBAC | RBAC V2 tables (roles/permissions) non utilisées pour l'autorisation |
| AR-M-011 | Sécurité | CSP avec unsafe-inline (annule protection XSS niveau CSP) |
| AR-M-012 | Sécurité | HTTP_REFERER non validé dans back() et verifyCsrf() |
| AR-M-013 | Sécurité | APP_KEY null par défaut dans config/app.php |
| AR-M-014 | Performance | N+1 correlated subqueries dans findAll() multiple repositories |
| AR-M-015 | Performance | ~270 instanciations Listener à chaque requête HTTP |

### Mineures (backlog V2.2)

| ID | Dimension | Description |
|----|-----------|-------------|
| AR-mn-001 | Base de données | incrementVersion() non-atomique (TimetableRepository) |
| AR-mn-002 | Event System | Listeners instanciés même si non utilisés |
| AR-mn-003 | Shared Services | EmailService utilise php mail() sans queue ni retry |
| AR-mn-004 | RBAC | Secrétaire manque attendance.session.validate |
| AR-mn-005 | RBAC | Permissions modules disabled chargées en session (overhead inutile) |
| AR-mn-006 | Sécurité | Pas de rate limiting sur l'authentification |
| AR-mn-007 | Performance | Pas de cache applicatif (APCu/Redis) |
| AR-mn-008 | Performance | Index composites manquants sur colonnes de filtrage |
| AR-mn-009 | V1 Compat | Académique depends scolarite (disabled) — non enforced runtime |

---

## 14. Score Global & Verdict

### Scores par Dimension

| # | Dimension | Score | Poids |
|---|-----------|-------|-------|
| 1 | Architecture Globale | 8.0 | ×2 |
| 2 | Base de Données | 7.0 | ×1.5 |
| 3 | Event System | 6.5 | ×1.5 |
| 4 | Shared Services | 7.5 | ×1 |
| 5 | RBAC & Permissions | 7.5 | ×1 |
| 6 | Sécurité | 7.0 | ×1.5 |
| 7 | Performance | 6.5 | ×1 |
| 8 | Compatibilité V1 | 9.0 | ×1.5 |
| 9 | Dette Technique | 6.0 | ×1 |
| 10 | Préparation Module RH | 7.5 | ×1 |

**Somme pondérée :** (8.0×2 + 7.0×1.5 + 6.5×1.5 + 7.5×1 + 7.5×1 + 7.0×1.5 + 6.5×1 + 9.0×1.5 + 6.0×1 + 7.5×1) / (2+1.5+1.5+1+1+1.5+1+1.5+1+1)

**= (16+10.5+9.75+7.5+7.5+10.5+6.5+13.5+6+7.5) / 13 = 95.75 / 13 ≈ 7.37**

### Score Global : **7.4 / 10**

---

## ═══════════════════════════════════════════════════════
## VERDICT FINAL : ✅ GO RH WITH FIXES
## ═══════════════════════════════════════════════════════

**Le démarrage du Module RH est autorisé sous conditions.**

### Pourquoi GO (et non NO-GO)

1. **Le framework est mature.** 4 modules V2 complets, patterns uniformes, ~350 fichiers PHP cohérents. La courbe d'apprentissage du framework est nulle pour une équipe déjà rodée.

2. **Aucun critique ne bloque la conception RH.** AR-C-001 (migrations manquantes) concerne Scolarité/Académique, pas RH. AR-C-002 (events.php doublons) est une correction de 10 lignes. AR-C-003 (XSS vues VS) ne touche pas l'infrastructure.

3. **Les shared services sont prêts.** AuditService, UploadService, et NotificationService (même partiel) sont disponibles sans modification pour le module RH.

4. **La V1 est intacte.** 9/10 en compatibilité — aucun risque de régression pour les utilisateurs V1 existants.

### Conditions (hotfixes à exécuter en parallèle du développement RH)

| Priorité | Action | Délai |
|----------|--------|-------|
| **P1** | Corriger AR-C-002 : fusionner les 4 doublons Finance dans events.php | Avant prochain déploiement |
| **P1** | Corriger AR-C-003 : ajouter htmlspecialchars() sur les 3 vues VS | Avant prochain déploiement |
| **P2** | Créer AR-C-001 : écrire les 5 fichiers SQL de migration manquants | Avant activation Scolarité/Académique |
| **P2** | Corriger AR-M-013 : documenter/enforcer APP_KEY non-null en production | Avant déploiement prod |

### Ce que le Module RH PEUT commencer immédiatement

- Phase 6.1 : Blueprint RH — définir périmètre (6 domaines proposés), tables SQL, services, événements, permissions
- Phase 6.2 : Domaine Employés — sans dépendance sur Scolarité V2 ni Finance décaissements
- Phase 6.3 : Domaine Contrats — sans dépendance bloquante
- Phase 6.4 : Domaine Congés — avec NotificationService (trigger `annonce` en attendant `conge`)

### Ce que le Module RH DOIT DIFFÉRER

- Sous-module Paie : attend `finance_006_decaissements.sql` (FN-C-002 résolu)
- Liaison Enseignant→Matière V2 : attend activation Scolarité V2 (AR-C-001 résolu)

---

**Document produit le :** 2026-07-02  
**Prochaine étape recommandée :** Phase 6.1 — Blueprint Module RH  
**Responsable :** SALIM-007-MAKER / SCOLARIS V2
