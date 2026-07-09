# Phase 14.8 — Stockage & Quotas — Rapport d'implémentation

## 1. Contexte & périmètre

Blueprint §25.1, ligne Phase 14.8 : *"Stockage S3 + Quotas — Storage multi-tenant
isolé — Livrable : StorageService S3-adapter, QuotaChecker, migration fichiers —
Validation : Upload dans tenant A → invisible depuis tenant B."*

Comme pour la Phase 14.7, cet environnement local (WAMP, sans credentials S3, sans
bucket réel) ne permet pas de tester un adaptateur S3 fonctionnel contre une
infrastructure réelle. Décision de périmètre (même discipline qu'en 14.7) : livrer
une architecture **`StorageInterface`** conforme au blueprint §12.1, un adaptateur
**local** pleinement fonctionnel et testé, un adaptateur **S3 stub** qui respecte le
contrat (prêt à être remplacé par une implémentation réelle sans toucher aux
appelants), et un **`TenantQuotaService`** complet (§12.4) — testé rigoureusement au
niveau applicatif.

Les modules qui seraient les plus gros consommateurs de stockage (Documents,
Bibliothèque, Inventaire) sont **désactivés** dans `config/modules.php`
(`documents=>false`, `bibliotheque=>false`, `inventaire=>false`) et leurs tables ne
sont pas appliquées dans cette base — fait déjà documenté en Phase 14.4. Le seul
appelant réel et actif de stockage de fichiers tenant-scopé aujourd'hui est le
branding (Phase 14.5, `TenantStorageService`). L'implémentation adapte donc ce seul
appelant réel plutôt que de fabriquer des intégrations non vérifiables pour des
modules absents.

## 2. Composants créés

| Fichier | Rôle |
|---|---|
| `core/Storage/StorageInterface.php` | Contrat `put/get/url/delete/exists/size` (§12.1) |
| `core/Storage/LocalStorageAdapter.php` | Implémentation disque local hors webroot (`storage/tenants/...`), anti path-traversal, URL signée HMAC-SHA256 avec expiration |
| `core/Storage/S3StorageAdapter.php` | Stub conforme à l'interface — lève une exception explicite plutôt que de simuler un comportement non testable (voir §7) |
| `core/Storage/StorageManager.php` | Fabrique (driver `local`/`s3` piloté par `STORAGE_DRIVER`), `pathFor()` = convention de chemin tenant (§12.2) |
| `config/storage.php` | Configuration du driver actif, base_dir local, credentials S3 (env), clé de signature |
| `core/Tenant/StorageQuotaExceededException.php` | Exception dédiée dépassement de quota |
| `core/Tenant/TenantQuotaService.php` | `getLimits/getUsage/checkUploadAllowed/recordUpload/recordDeletion/recalculate/history/alerts` |
| `database/migrations/T013_storage_quota_infrastructure.php` | Table `api_uploads` (ledger d'usage) + colonnes `etablissements.max_documents/max_attachments` |
| `database/migrations/T014_quota_permissions.php` | Permission `quota.view` → `etab_permissions`/`etab_role_permissions` (admin, directeur) |
| `app/Controllers/QuotaController.php` | `index()` (vue), `api()` (JSON), `recalculate()` — tous scopés à l'établissement de l'utilisateur connecté |
| `app/Views/settings/quotas.php` | Jauges stockage/utilisateurs/élèves/documents, alertes, historique |
| `tests/Unit/StorageQuotaTest.php` | 30 assertions (voir §6) |

## 3. Composants modifiés

| Fichier | Modification |
|---|---|
| `core/Tenant/TenantStorageService.php` | Injection de `TenantQuotaService` ; `put()` appelle désormais `checkUploadAllowed()` avant écriture et `recordUpload()` après ; `delete()` appelle `recordDeletion()` |
| `config/permissions.php` | Ajout `quota.view` aux rôles `admin` et `directeur` |
| `config/routes.php` | 3 routes `/parametres/quotas*` |

Aucun fichier V1 déplacé, aucun contrôleur V1 supprimé, aucune route existante
modifiée, aucune logique métier des modules existants (Élèves, Notes, Absences...)
touchée.

## 4. Gestion des quotas

Dimensions et source de vérité :

| Dimension | Limite (table `etablissements`) | Usage mesuré |
|---|---|---|
| Stockage (Mo) | `storage_quota_mb` (existant depuis 14.2) | `SUM(size_bytes)` du ledger `api_uploads` WHERE `deleted_at IS NULL` |
| Utilisateurs | `max_users` (existant depuis 14.2) | `COUNT(*)` de `users` WHERE `etablissement_id` |
| Élèves | `max_eleves` (existant depuis 14.2) | `COUNT(*)` de `eleves` WHERE `etablissement_id` |
| Documents | `max_documents` (**nouveau**, T013) | `COUNT(*)` de `doc_documents` **si la table existe**, sinon `null` (dimension non applicable, distincte de 0) |
| Pièces jointes | `max_attachments` (**nouveau**, T013) | idem sur `doc_attachments` |

`checkUploadAllowed(etabId, sizeBytes)` compare l'usage projeté au quota et lève
`StorageQuotaExceededException` en cas de dépassement ; un seuil d'avertissement à
80 % déclenche une entrée `Logger::security('QUOTA_STORAGE_WARNING', ...)` sans
bloquer l'upload. `alerts()` expose ce même calcul (plus utilisateurs/élèves) sous
forme de liste `{type, level, message}` consommée par la vue et l'API.

Architecture extensible pour les futurs plans (§12.4 du blueprint, table
`platform_plans` déjà existante depuis la Phase 14.2) : les limites vivent sur
`etablissements` (valeur effective par tenant), `platform_plans` définit les valeurs
par défaut d'un plan — l'attribution plan→tenant (écriture de `plan_id` et copie des
limites) est du ressort du Super Admin SaaS, prévu Phase 14.10, non construit ici.

## 5. Adaptations du stockage

Convention de chemin tenant (§12.2) implémentée dans `StorageManager::pathFor()` :
`{etablissement_id}/{module}/{context}/{filename}`. `LocalStorageAdapter` stocke sous
`ROOT_PATH/storage/tenants/...`, **hors du webroot** (conforme §12.3 — accès
uniquement via `url()`, qui génère un lien signé HMAC-SHA256 avec expiration).
Isolation stricte : `resolve()` rejette tout chemin contenant `..` ou commençant par
`/` — aucune traversée possible hors de `storage/tenants/`.

`TenantStorageService` (branding, seul appelant réel) a été adapté pour passer par
`TenantQuotaService` à chaque écriture/suppression — les logos, favicons et images de
connexion comptent désormais dans l'usage de stockage du tenant, au même titre que
tout futur module qui utiliserait `Core\Storage` directement.

## 6. Tests réalisés

`tests/Unit/StorageQuotaTest.php` — 30/30 assertions, transaction PDO annulée en fin
de script + nettoyage explicite des fichiers écrits sur disque (hors DB) :

1. **`LocalStorageAdapter`** (13 assertions) : cycle complet put/get/exists/size/delete,
   **isolation** (même nom de fichier dans 2 tenants différents → contenus jamais
   confondus), rejet de traversée de chemin (`..`), génération/vérification de
   signature HMAC (valide, expirée, falsifiée, mauvaise clé).
2. **`checkUploadAllowed()`** (3 assertions) : upload dans le quota accepté, upload
   dépassant le quota refusé (`StorageQuotaExceededException`), même test avec un
   quota différent pour un second tenant.
3. **Ledger `api_uploads`** (6 assertions) : usage = somme des tailles enregistrées,
   suppression (soft) exclut le fichier de l'usage sans perdre l'historique,
   `recalculate()` cohérent avec `getUsage()`, `history()` retourne les entrées
   actives et supprimées, quota vérifié dynamiquement après usage réel (200 Ko
   déjà utilisés → 500 Ko accepté, 900 Ko refusé).
4. **Isolation stricte inter-tenant** (4 assertions) : un upload massif dans
   l'établissement B n'affecte pas l'usage de l'établissement A, quotas
   indépendants (1 Mo vs 10 Mo) correctement lus par tenant.
5. **`TenantStorageService` — intégration réelle** (2 assertions) : `put()` refuse
   effectivement l'upload quand le quota est dépassé (avant tout accès disque —
   `checkUploadAllowed()` est invoqué avant `move_uploaded_file()`), le mécanisme de
   journalisation qu'il utilise enregistre bien dans le ledger.
6. **`alerts()`** (2 assertions) : alerte critique déclenchée quand le quota est
   dépassé, aucune fausse alerte quand l'usage est confortablement sous le quota.

**Non-régression** : `tests/Unit/BrandingTenantTest.php` ré-exécuté après l'ajout du
contrôle de quota dans `TenantStorageService` — toujours **18/18**. Suite historique
`security_tests.php` (48/48) et `functional_tests.php` (109/109) ré-exécutées — aucune
régression.

**Vérification HTTP** : les routes `/parametres/quotas`, `/parametres/quotas/api`,
`/parametres/quotas/recalculer` répondent (302 vers `/login` sans session — confirme
que `requireAuth()` protège correctement la page, aucune ne renvoie 404/500). La
vérification complète en session authentifiée n'a pas pu être effectuée par `curl`
faute de connaître les identifiants de test actuels ; la couverture fonctionnelle
réelle repose donc sur les 30 assertions CLI ci-dessus plutôt que sur une capture
HTTP live, à la différence des phases précédentes où les identifiants de test étaient
connus.

Total cumulé des tests Multi-Tenant (Phases 14.2 → 14.8) :
TenantResolverTest(29) + TenantIsolationTest(24) + RbacTenantTest(18) +
BrandingTenantTest(18) + MultiTenantUserTest(15) + DomainTenantTest(40) +
StorageQuotaTest(30) = **174**, plus `security_tests.php`(48) +
`functional_tests.php`(109) = **331/331 tests, tous verts**.

## 7. Anomalie détectée / correction appliquée

**Bug réel trouvé pendant les tests** : `TenantQuotaService::alerts()` utilisait la
garde `if ($quotaBytes > 0)` avant de calculer le ratio d'usage — un établissement
avec `storage_quota_mb = 0` (quota nul, cas limite légitime : établissement suspendu
ou plan sans allocation de stockage) ne déclenchait donc **aucune alerte**, y compris
en présence d'un usage réel positif, alors que ce cas est le plus critique de tous
(tout octet stocké dépasse déjà le quota). Corrigé en traitant explicitement le cas
`$quotaBytes <= 0` : alerte critique dès que l'usage est `> 0`. Détecté par
`tests/Unit/StorageQuotaTest.php` section 6, corrigé, re-testé (30/30).

## 8. API

`GET /parametres/quotas/api` — JSON `{limits, usage, alerts, history}`, protégé par
`requirePermission('quota.view')`, toujours scopé à l'établissement de l'utilisateur
connecté (jamais un identifiant fourni par la requête). Volontairement une route
authentifiée par session (comme le reste de l'admin `/parametres/*`), et non un
endpoint de l'API Platform (Phase 13.x, JWT/API Keys) — l'API Platform expose des
données métier consommées par des clients externes/mobiles, alors que ceci est une
vue d'administration interne ; l'exposer aussi via l'API Platform (avec sa propre
authentification, ses scopes, sa pagination) est une extension naturelle mais hors du
périmètre littéral "QuotaChecker" de cette phase — non construite pour éviter le
même type de code non appelé/non testable documenté en §9.

## 9. Portails

Le module Portails (Phase 12.x) est **désactivé** (`config/modules.php` :
`portals=>false`). Construire un widget affichant la consommation dans un portail
inactif produirait du code non exécutable et invérifiable. `TenantQuotaService` est
prêt à être consommé par un futur widget portail dès son activation (même service
que celui utilisé par `QuotaController`, aucune dépendance à l'authentification par
session) — non câblé ici, cohérent avec la décision similaire prise en Phase 14.7
pour l'API/Portails.

## 10. Hors périmètre (infrastructure / modules absents)

- **Adaptateur S3 fonctionnel** : aucune credential/bucket réel dans cet
  environnement — `S3StorageAdapter` est un stub conforme à l'interface qui lève une
  exception explicite plutôt que de simuler un comportement non vérifié.
- **Compteurs "documents"/"attachments"** : dimension de quota présente (colonnes
  `etablissements.max_documents/max_attachments`, logique `getUsage()` prête) mais
  `null` dans cette base tant que le module Documents n'est pas activé et ses tables
  appliquées.
- **Widget Portails** et **exposition via l'API Platform** : voir §8/§9.
- **Migration de fichiers existants** ("migration fichiers" du blueprint) : il
  n'existe aujourd'hui aucun fichier historique à migrer vers `storage/tenants/` — le
  seul stockage de fichiers réel (branding) reste volontairement sur son chemin
  `public/uploads/branding/...` existant (nécessaire pour rester servi publiquement
  par URL directe, contrairement à `storage/` qui est hors webroot) ; il est
  désormais quota-conscient et journalisé sans changer de convention de chemin — un
  déplacement physique n'apporterait aucun bénéfice testable dans cet état du projet.

## 11. Décision finale

**GO WITH FIXES** *(fixes = les 4 points hors périmètre du §10, qui ne bloquent pas
l'usage applicatif du sous-système mais devront être adressés avant un déploiement
public réel avec de vrais volumes de documents)*.

`StorageInterface`/`LocalStorageAdapter`, `TenantQuotaService` (quota, ledger,
alertes, historique), et l'intégration réelle sur le seul appelant de stockage actif
(branding) sont implémentés et testés (331/331 tests verts, zéro régression, 1
anomalie trouvée et corrigée pendant les tests). Ne pas commencer la phase suivante
tant que cette phase n'est pas validée.
