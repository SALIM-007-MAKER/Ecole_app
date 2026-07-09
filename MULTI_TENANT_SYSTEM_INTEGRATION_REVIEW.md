# Phase 14.12 — Revue d'intégration système Multi-Tenant — SCOLARIS V2 / EduNova

**Score global : 8.4/10 — Décision finale : GO**

Aucune nouvelle fonctionnalité n'a été ajoutée durant cette phase. Le travail a
consisté en un audit complet du code et de la base existants (lecture, requêtes de
diagnostic, exécution de la suite de tests complète), la correction des anomalies
réelles trouvées, puis une nouvelle exécution de validation. Toutes les corrections
listées au §5 ont été appliquées et re-testées avant la rédaction de ce rapport.

---

## 1. Méthodologie

- Exécution intégrale de la suite de tests Multi-Tenant (9 fichiers `tests/Unit/*Test.php`
  couvrant les Phases 14.2→14.11) + suites historiques (`security_tests.php`,
  `functional_tests.php`).
- Requêtes de diagnostic directes sur la base de données : couverture d'index,
  intégrité référentielle, présence réelle des tables par module.
- Relecture ciblée du code aux points d'intégration les plus à risque : autoloader,
  `TenantMiddleware`, résolution tenant dans l'API Platform et les Portails,
  chaîne cache/queue/backup.
- Un agent d'exploration dédié a vérifié, par lecture de code uniquement, si l'API
  Platform et le framework Portails référencent `Core\Tenant\TenantContext`.
- Mesure de performance réelle (chronométrage) sur l'opération la plus coûteuse du
  système (restauration globale de vérification, Phase 14.11).

## 2. Points validés

### 2.1 Infrastructure Tenant
- `TenantContext` (singleton), `TenantResolver` (5 stratégies : session → sous-domaine
  → domaine personnalisé → chemin → header), `TenantMiddleware` (construit, lint
  propre, jamais activé — voir §4) tous présents et cohérents avec le blueprint.
- Propagation du contexte pour le travail différé : `Core\Queue\QueueWorker`
  positionne et restaure explicitement `TenantContext` autour de chaque tâche
  (testé avec 2 tenants entrelacés sur 5 tâches consécutives, zéro fuite).
- Propagation pour les événements synchrones : triviale par construction
  (`EventDispatcher::dispatch()` reste dans le même process PHP/la même requête que
  le listener — vérifié par lecture de `core/EventDispatcher.php`).

### 2.2 Base de données
- **19/19 tables** possédant une colonne `etablissement_id` ont un index dessus
  (vérifié via `SHOW INDEX`) — aucune table orpheline sans index de filtrage tenant.
- **Zéro ligne orpheline** détectée (`etablissement_id` pointant vers un
  établissement inexistant) sur l'ensemble des tables tenant-scopées.
- 9 modèles V1 correctement marqués `tenantScoped = true` (Absence, Classe, Eleve,
  Enseignement, Matiere, Note, Periode, Professeur, User) — liste inchangée depuis
  la Phase 14.3, aucune dérive détectée.

### 2.3 RBAC
- Résolution à 3 paliers (tenant → V2 legacy → V1 config) toujours cohérente
  (RbacTenantTest 18/18) — équivalence stricte confirmée entre chaque rôle système
  et ses permissions, quel que soit le palier de résolution.
- Isolation testée dans les deux sens (un rôle personnalisé d'un établissement n'est
  jamais visible d'un autre).

### 2.4 API & Portails
- L'API Platform (JWT `etab` claim + API Keys, Phase 13.x) et le framework Portails
  (Phase 12.x) fonctionnent tous deux correctement pour l'identification du tenant,
  mais via **leurs propres mécanismes indépendants** (`AuthContext` pour l'API,
  `Session::getUser()['etablissement_id']` pour les Portails) — **aucun des deux ne
  référence `Core\Tenant\TenantContext`**, confirmé par lecture de code exhaustive
  (0 occurrence hors `core/Tenant/`). Voir §6 (dette technique) pour l'implication.

### 2.5 Branding, Domaines, Stockage, Cache/Queue, Super-Admin, Backups
Chacun de ces sous-systèmes (Phases 14.5, 14.7, 14.8, 14.9, 14.10, 14.11) dispose de
sa propre suite de tests dédiée, toutes vertes, avec isolation stricte testée
explicitement entre au moins 2 tenants simultanés dans chaque cas. Non ré-détaillé
ici (voir les rapports de phase individuels) sauf pour les anomalies trouvées
pendant CETTE revue (§5).

## 3. Résultats de tests (après corrections, §5)

| Suite | Résultat |
|---|---|
| TenantResolverTest | 29/29 |
| TenantIsolationTest | 24/24 |
| RbacTenantTest | 18/18 |
| BrandingTenantTest | 18/18 |
| MultiTenantUserTest | 15/15 |
| DomainTenantTest | 40/40 |
| StorageQuotaTest | 30/30 |
| CacheQueueTest | 40/40 |
| PlatformAdminTest | 56/56 |
| BackupDrTest | 43/43 |
| `security_tests.php` (historique) | 48/48 |
| `functional_tests.php` (historique) | 109/109 |
| **TOTAL** | **470/470** |

Tests obligatoires de cette phase couverts : multi-écoles (2+ tenants simultanés
dans chaque suite), changement de tenant (MultiTenantUserTest), authentification
(RbacTenantTest + PlatformAdminTest §1), permissions (RbacTenantTest), API/Portails
(audit de code §2.4, aucune régression testable localement au-delà de ce qui existe
déjà), sauvegardes/restauration (BackupDrTest, incluant restauration réelle vers une
base MySQL neuve), performances (§3 ci-dessous), sécurité (isolation testée dans les
deux sens sur chaque sous-système), montée en charge (voir §3, limites documentées).

## 4. Anomalies critiques

**Aucune anomalie critique restante.** Deux anomalies auraient pu être qualifiées de
critiques si elles n'avaient pas été trouvées et corrigées **pendant** cette revue
(voir §5, corrections 1 et 2) — le processus d'audit a fonctionné comme prévu.

Point structurel à ne PAS confondre avec une anomalie : **`TenantMiddleware` n'est
toujours pas activé** dans `core/Application.php`. C'est un choix delibéré et
documenté de manière identique et cohérente depuis la Phase 14.2 (11 phases
consécutives), pas un oubli — chaque sous-système a été conçu pour fonctionner
correctement avec ou sans lui (repli sur `config/tenant.php['default_id']`). Ce
n'est donc pas classé "anomalie", mais c'est LE point de préparation le plus
important avant un déploiement SaaS réel multi-établissements — voir §7 et §8.

## 5. Anomalies mineures trouvées pendant cette revue — corrigées

1. **[Corrigé] `TenantIsolationTest.php` — fragilité de fuseau horaire.**
   Le test comparait une date écrite via `date('Y-m-d')` (PHP, fuseau UTC) à une
   date lue via `CURDATE()` (MySQL, fuseau système = UTC+1 dans cet environnement).
   Pendant l'heure qui suit minuit UTC, les deux horloges désignent des jours
   calendaires différents, provoquant un échec de test intermittent totalement
   indépendant de la logique métier — reproduit et confirmé pendant cette revue
   (`AbsenceModel::storePointage()` fonctionne correctement avec une date fixe,
   testé isolément). **Corrigé** en fixant la date du scénario de test
   (`2026-01-15`) au lieu de dépendre de l'horloge courante. Re-testé : 24/24.
   *Cause racine plus large (hors correction de cette phase, voir §6)* : PHP tourne
   en UTC alors que MySQL tourne dans le fuseau horaire système du serveur — tout
   code métier (pas seulement les tests) comparant une date calculée côté PHP à une
   date calculée côté MySQL est théoriquement exposé au même risque pendant cette
   fenêtre d'une heure. Aucune occurrence de ce patron trouvée dans le code
   Multi-Tenant lui-même lors de cette revue, mais à garder en tête pour un audit
   applicatif plus large (hors périmètre de cette phase).

2. **[Corrigé] Performance de `DatabaseRestorer::insertRows()`.**
   Une requête `INSERT` séparée était exécutée par ligne restaurée. Mesuré sur un
   dump global réaliste (1782 lignes, base de développement) : ~36,6s uniquement
   pour la phase d'insertion. **Corrigé** en regroupant les lignes par lots de 200
   dans des requêtes `INSERT ... VALUES (...), (...), ...` (avec `ON DUPLICATE KEY
   UPDATE` préservé à l'identique pour le mode upsert). `BackupDrTest.php` (43/43)
   re-validé après correction ; durée totale de `restoreGlobalVerify()` réduite de
   ~2m03s à ~1m22s sur cette même machine (le reliquat, ~1m20s, est dominé par le
   `CREATE`/`DROP TABLE` ×51 et le `DROP DATABASE` — voir §3 "performances" et
   §14 pour l'hypothèse retenue sur cette part restante).

Aucune autre anomalie de comportement (fonctionnelle ou de sécurité) trouvée pendant
cette revue. Les anomalies trouvées et corrigées **pendant** les Phases 14.7 à 14.11
elles-mêmes (recherche SQL ambiguë en 14.10, transaction imbriquée + autoloader
manquant en 14.9, auto-corruption d'une sauvegarde tenant en 14.11, seuil de quota
nul en 14.9) restent résolues — re-confirmées vertes par la suite de tests complète
ci-dessus, non ré-décrites ici (voir les rapports de phase individuels).

## 6. Dette technique

| # | Item | Sévérité | Origine |
|---|---|---|---|
| DT1 | API Platform et Portails résolvent le tenant via leurs propres mécanismes (JWT claim / session), jamais via `TenantContext` — activer `TenantMiddleware` ne les rendrait pas automatiquement "conscients" du nouveau composant sans câblage explicite supplémentaire | Mineure | Découverte Phase 14.12 |
| DT2 | Finance/RH/VieScolaire/Api marqués `enabled=true` dans `config/modules.php` mais **zéro table** `finance_*`/`vs_*`/`rh_*` appliquée dans cette base — configuration en décalage avec la réalité, pré-existant (découvert Phase 14.4) | Majeure (hors périmètre Multi-Tenant, voir §12) | Pré-existante, confirmée Phase 14.12 |
| DT3 | Documents/Bibliothèque/Inventaire/Rapports/Communication/Portails : `enabled=false`, aucune table appliquée — cohérent (config reflète la réalité), mais signifie que la compatibilité Multi-Tenant de 6 des 13 modules listés au périmètre de cette revue est non vérifiable dans cet environnement | Mineure (config honnête) | Pré-existante |
| DT4 | Sauvegarde différentielle approximée par `created_at >=` (ne capture ni UPDATE ni DELETE) — une vraie différentielle nécessiterait le binlog MySQL | Mineure, documentée depuis 14.11 | Phase 14.11 |
| DT5 | Adaptateurs S3 (14.8) et Redis (14.9) sont des stubs non fonctionnels (environnement sans credentials/extension) | Mineure, documentée | Phases 14.8/14.9 |
| DT6 | Aucun démon Supervisord/cron — planification (sauvegardes quotidiennes, traitement continu de la file) nécessite une invocation manuelle/planifiée externe | Mineure, documentée | Phases 14.9/14.11 |
| DT7 | Assistant de création d'établissement (onboarding complet avec premier compte admin) non construit — `create()` du portail Super-Admin ne crée que la ligne `etablissements` | Mineure, documentée | Phase 14.10 |
| DT8 | Clé de chiffrement des sauvegardes unique pour toute la plateforme (dérivée de `APP_KEY`), pas une clé par plan comme suggéré au blueprint §19.1 | Mineure, documentée | Phase 14.11 |

**Duplications identifiées** : aucune duplication de logique métier trouvée entre
les sous-systèmes Multi-Tenant eux-mêmes (Storage/Cache/Queue/Backup partagent tous
correctement leurs primitives respectives — ex. `BackupService` réutilise
`Core\Storage`, `PlatformStatsService` réutilise `TenantQuotaService::alerts()` et
`JobQueue::stats()` sans réimplémentation). La seule duplication structurelle est
DT1 (deux mécanismes de résolution tenant coexistant sans unification).

## 7. Risques

| Risque | Impact si non traité | Probabilité dans cet état |
|---|---|---|
| `TenantMiddleware` jamais activé | La résolution automatique par sous-domaine/domaine personnalisé (Phase 14.7) ne s'applique à AUCUNE requête réelle tant que ce n'est pas fait — le système reste, en production actuelle, un mono-tenant "prêt à basculer" plutôt qu'un multi-tenant actif | Élevée si le passage en SaaS réel est planifié à court terme |
| Modules Finance/RH/VieScolaire sans tables appliquées | Toute tentative d'usage réel de ces modules échouera immédiatement (erreur SQL "table introuvable"), indépendamment du Multi-Tenant | Élevée — condition déjà vraie aujourd'hui, hors du travail de cette phase |
| Décalage horaire PHP(UTC)/MySQL(système) | Bugs intermittents sur toute comparaison de date PHP↔MySQL proche de minuit UTC, potentiellement au-delà du seul Multi-Tenant | Faible fréquence (fenêtre d'1h/jour) mais impact potentiellement large en surface |
| Chiffrement des sauvegardes à clé unique plateforme | Compromission de `APP_KEY` = compromission de toutes les sauvegardes de tous les tenants | Faible (nécessite déjà une compromission sérieuse du serveur) |

## 8. Recommandations

1. **Avant tout déploiement SaaS réel** : activer `TenantMiddleware` dans
   `core/Application.php` derrière un test de non-régression complet en conditions
   HTTP réelles (pas seulement les tests unitaires actuels) — c'est un changement
   de comportement du pipeline de requêtes entier, à traiter comme sa propre
   sous-phase de bascule, pas comme un simple flag.
2. Câbler `TenantContext` dans `ApiBaseController`/`PortalBaseController` (DT1)
   une fois le middleware activé, pour que la résolution par domaine personnalisé
   bénéficie aussi à l'API et aux Portails, pas seulement aux vues web classiques.
3. Traiter DT2 (Finance/RH/VieScolaire) comme un prérequis séparé et documenté
   avant toute promesse commerciale sur ces modules — soit appliquer leurs
   migrations, soit corriger `config/modules.php` pour refléter honnêtement
   `enabled=false` tant que ce n'est pas fait.
4. Aligner le fuseau horaire de MySQL sur celui de PHP (ou vice versa) au niveau
   infrastructure — élimine la classe de bugs identifiée en §5.1 pour l'ensemble de
   l'application, pas seulement les tests Multi-Tenant.
5. Avant mise à l'échelle réelle : remplacer les stubs S3/Redis par de vraies
   implémentations testées contre une infrastructure réelle (DT5), et mettre en
   place un vrai scheduler (Supervisord/cron — DT6) pour les sauvegardes
   automatiques et le traitement continu de la file.

## 9-13. Compatibilité modules, préparation SaaS

Voir §2.4, §6 (DT2/DT3) et §7 pour le détail. Synthèse : le socle Multi-Tenant
lui-même (Scolarité-core : élèves/classes/notes/absences/professeurs/matières/
utilisateurs, + toute l'infrastructure SaaS : branding/domaines/stockage/quotas/
cache/queue/super-admin/sauvegardes) est complet, isolé et testé. Les modules
Finance/RH/Vie Scolaire/Documents/Bibliothèque/Inventaire/Rapports/Communication/
Portails/API existent comme code mais leur **compatibilité Multi-Tenant réelle en
production reste non démontrée** dans cet environnement, soit par absence de tables
appliquées (Finance/RH/VieScolaire), soit parce qu'ils sont désactivés (les 6
autres), soit parce qu'ils résolvent le tenant par un mécanisme parallèle non
unifié (API/Portails, DT1). Ceci n'est pas un défaut du travail Multi-Tenant
lui-même — c'est l'état pré-existant de l'intégration plateforme, confirmé et
documenté avec précision par cette revue plutôt que supposé.

Préparation montée en charge/haute disponibilité/cloud : l'architecture
"Shared Database, Shared Schema" avec `TenantCache`/`JobQueue`/`Core\Storage` tous
conçus avec une interface d'adaptateur remplaçable (fichier local → Redis/S3 réels)
est un bon point de départ, mais reste non éprouvée à un volume réel — voir DT5/DT6
et la mesure de performance §3/§5.2 (une opération de maintenance lourde comme la
restauration globale prend encore ~1m20s sur un jeu de données de développement
modeste ; le comportement à l'échelle de production reste à mesurer sur une
infrastructure cible réelle, pas cet environnement WAMP local).

## 14. Décision finale

**GO**

Justification : l'ensemble des 11 sous-phases (14.2→14.11) forme un système
cohérent, testé de bout en bout avec 470/470 tests verts (aucune régression), dont
313 tests spécifiquement Multi-Tenant couvrant l'isolation stricte entre 2+
établissements simultanés sur chaque sous-système. Deux anomalies réelles ont été
trouvées et corrigées pendant cette revue (fragilité de test liée au fuseau
horaire, performance d'insertion en masse) — le processus d'audit a rempli son
rôle. Aucune anomalie critique non résolue ne subsiste dans le périmètre
littéralement construit par les Phases 14.2-14.11.

Ce GO porte sur le **module Multi-Tenant tel que scopé et construit** (infrastructure
tenant, RBAC, branding, domaines, stockage/quotas, cache/queue, super-admin,
sauvegardes/DR) — pas sur une déclaration que "toute la plateforme SCOLARIS V2 est
prête pour un déploiement SaaS commercial multi-établissements en production
aujourd'hui". Cette nuance est documentée explicitement en §7/§8 : l'activation de
`TenantMiddleware` et la résolution de DT1/DT2 restent des prérequis avant une
bascule commerciale réelle, mais ne remettent pas en cause la qualité ni
l'exhaustivité de ce qui a été livré dans le périmètre demandé phase après phase.

Le module Multi-Tenant (Phases 14.2 à 14.11) est déclaré **terminé**.
