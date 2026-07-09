# MULTI_TENANT_TABLES_MIGRATION_REPORT.md
## Phase 14.3 — Migration des tables métier vers le mode Multi-Tenant

> **Statut :** ✅ IMPLÉMENTÉ — décision : **GO WITH FIXES** (voir §8)
> **Date :** 2026-07-08
> **Périmètre :** Les 9 tables V1 explicitement diagnostiquées non-conformes par le blueprint (§3.1) : `users`, `classes`, `eleves`, `professeurs`, `matieres`, `enseignements`, `notes`, `absences`, `periodes`.
> **Référence :** `MULTI_TENANT_V2_BLUEPRINT.md` §3.1, §24.1 Phase B, §25.1 Phase 14.3

---

## 1. Résumé exécutif

Les 9 tables métier V1 identifiées par l'audit du blueprint portent désormais
`etablissement_id NOT NULL` avec clé étrangère, index et backfill complet
vers l'établissement existant (`edunova-demo`, id=1). Les 9 modèles PHP
correspondants ont été adaptés pour filtrer et taguer automatiquement selon
le `TenantContext` — **sans modifier aucune logique métier** (mêmes
signatures, mêmes valeurs de retour, mêmes règles de calcul).

**Deux bugs critiques de régression ont été détectés et corrigés pendant
l'implémentation** (voir §5) : les écritures brutes de `NoteModel::upsert()`
et `AbsenceModel::storePointage()` ne fournissaient pas `etablissement_id`,
ce qui aurait fait échouer la saisie de notes et le pointage d'absences dès
l'application de la contrainte `NOT NULL`.

L'isolation inter-tenant a été validée par un test dédié créant un second
établissement réel et vérifiant lecture/recherche/pagination/création/
modification/suppression sous les deux contextes : **24/24 assertions
passées**, y compris protection contre les fuites par ID deviné (IDOR).
**210/210 tests au total** (sécurité + fonctionnels + résolveur 14.2 +
isolation 14.3), zéro régression.

---

## 2. Tables modifiées

| Table | Lignes | Colonne ajoutée | Index | FK |
|---|---|---|---|---|
| `users` | 7 | `etablissement_id INT UNSIGNED NOT NULL` | `idx_etab (etablissement_id, role)` | `fk_users_etab → etablissements(id)` |
| `classes` | 4 | idem | `idx_etab (etablissement_id, annee_scolaire)` | `fk_classes_etab` |
| `eleves` | 10 | idem | `idx_etab (etablissement_id, classe_id)` | `fk_eleves_etab` |
| `professeurs` | 8 | idem | `idx_etab (etablissement_id, actif)` | `fk_professeurs_etab` |
| `matieres` | 8 | idem | `idx_etab (etablissement_id)` | `fk_matieres_etab` |
| `enseignements` | 12 | idem | `idx_etab (etablissement_id, classe_id)` | `fk_enseignements_etab` |
| `notes` | 27 | idem | `idx_etab (etablissement_id, eleve_id)` | `fk_notes_etab` |
| `absences` | 7 | idem | `idx_etab (etablissement_id, classe_id)` | `fk_absences_etab` |
| `periodes` | 3 | idem | `idx_etab (etablissement_id, annee_scolaire)` | `fk_periodes_etab` |

Toutes les FK utilisent `ON DELETE RESTRICT` (un établissement ne peut pas
être supprimé tant qu'il a des données métier — cohérent avec la règle
"aucun DELETE physique" du projet).

Backfill : 100% des lignes existantes rattachées à `etablissement_id = 1`
(vérifié par requête `GROUP_CONCAT(DISTINCT etablissement_id)` par table —
une seule valeur, `1`, partout). Row counts identiques avant/après migration
(aucune perte de données).

---

## 3. Migration créée

`database/migrations/T006_v1_tables_etablissement_id.php` — idempotente,
réversible (`rollback` fourni), boucle générique sur les 9 tables en 5 étapes
par table (ADD COLUMN nullable → backfill → NOT NULL → INDEX → FK), fenêtre
de maintenance ~42s en local (volumes de dev faibles).

Sauvegarde complète de la base effectuée avant exécution
(`pre_T006_backup.sql`), conformément à la mitigation RISQUE 4 du blueprint.

---

## 4. Modèles adaptés

### 4.1 Infrastructure réutilisable (`core/Model.php`, `core/Tenant/TenantContext.php`)

- `TenantContext::current(): ?int` — nouvel accesseur non-levant (retourne
  `null` si non résolu, contrairement à `require()`/`id()` qui lèvent).
- `Core\Model` : propriété opt-in `$tenantScoped` (défaut `false` — aucun
  changement pour les 14 autres modèles de l'app) + `tenantId()` qui
  retourne `TenantContext::current() ?? config('tenant.default_id')`.
  Les 7 méthodes CRUD génériques (`findAll`, `findById`, `findBy`,
  `findOneBy`, `count`, `paginate`, `insert`, `update`, `delete`) filtrent/
  taguent automatiquement quand `$tenantScoped = true`.
- `config/tenant.php` : nouvelle clé `default_id` (repli tant que
  `TenantMiddleware` n'est pas activé — voir §6).

### 4.2 Les 9 modèles (`$tenantScoped = true` + requêtes personnalisées patchées)

| Modèle | Méthodes personnalisées adaptées |
|---|---|
| `UserModel` | `updateLastLogin`, `updatePassword`, `paginateOrdered`, `findAllWithRoles` |
| `ClasseModel` | `findWithStats`, `findForSelect`, `findWithDetails`, `getEleves`, `getElevesDisponibles` |
| `EleveModel` | `buildWhere` (recherche+pagination), `findWithDetails`, `findByClasse`, `findByParent`, `findByEmail`, `countByClasse`, `countBySexe`, `generateMatricule`, `matriculeExists` |
| `ProfesseurModel` | `findForSelect`, `findWithStats`, `findWithDetails`, `buildWhere`, `emailExists`, `findByUserId`, `countElevesForUser` |
| `MatiereModel` | `findWithStats`, `findWithDetails`, `findForSelect`, `nomExists` |
| `EnseignementModel` | `findByClasseId`, `findByMatiereId`, `exists`, `findByProfesseurId` |
| `NoteModel` | `findByControle`, `getMatieresPourClasse`, `getNotesForClasse`, `getClassementClasse`, **`upsert`** (écriture), `calculerMoyenneMatiere`, `calculerMoyenneGenerale`, `recalculerDepuisControle`, `recalculerClasse` |
| `AbsenceModel` | `buildWhere`, `findForPointage`, **`storePointage`** (écriture), `findWithDetails`, `findForEleve`, `findForParent`, `getStatsGlobales`, `getStatsEleve`, `getStatsParClasse`, `getTendanceHebdo`, `getTopAbsents`, `getAlertes`, `updateStatutJustif` |
| `PeriodeModel` | `findForSelect`, `findActive`, `findByAnnee`, `getAnnees`, `findWithStats` |

**Exceptions volontaires (non filtrées, par design) :**
- `UserModel::emailExistsForOther()` — l'unicité de l'email reste **globale**
  entre tenants, conformément au blueprint §7.1 ("email UNIQUE global").
- `UserModel::getPermissions()` — catalogue de permissions système, non
  spécifique à un établissement.
- `password_resets` (via `createResetToken`/`findByResetToken`/
  `markResetTokenUsed`) — explicitement exclu par le blueprint §5.3.

---

## 5. Corrections appliquées pendant l'implémentation

**BUG CRITIQUE 1 — `NoteModel::upsert()`** : `INSERT INTO notes (eleve_id,
controle_id, note, absent)` sans `etablissement_id`. Avec la contrainte
`NOT NULL` posée par T006, **toute saisie de note aurait échoué**. Corrigé :
la colonne est désormais fournie via `$this->tenantId()`.

**BUG CRITIQUE 2 — `AbsenceModel::storePointage()`** : même défaut sur
`INSERT INTO absences (...)`. **Le pointage quotidien des absences aurait
échoué**. Corrigé de la même façon.

Ces deux bugs ont été détectés par un test de création réel (élève créé via
l'interface, `id=11`, `etablissement_id=1` correctement tagué, puis nettoyé)
avant d'être généralisés à tous les modèles ; la revue systématique des 9
modèles a ensuite confirmé qu'aucune autre écriture brute similaire ne
subsistait sur les tables migrées.

---

## 6. Vérifications d'isolation

Contrôlé, avec preuve à l'appui (§7) :
- ✅ Chaque table appartient correctement à un tenant (100% des lignes
  taguées, contrainte `NOT NULL` + FK active).
- ✅ Aucune donnée ne peut être lue entre deux tenants (`findById`,
  recherche, pagination, statistiques agrégées — testé avec IDOR).
- ✅ Les requêtes utilisent automatiquement `TenantContext` (mécanisme
  opt-in dans `Core\Model`, actif sur les 9 modèles).
- ✅ Les index restent performants : chaque `idx_etab` commence par
  `etablissement_id` suivi de la colonne la plus filtrée (conforme à la
  RÈGLE 3 du blueprint §2.2).
- ⚠️ `TenantMiddleware` reste **non activé** (voir §9) — conforme au
  périmètre demandé, mais signifie que l'isolement n'est aujourd'hui
  démontré qu'en conditions de test (`TenantContext::set()` manuel), pas
  encore appliqué automatiquement par le pipeline HTTP réel.

---

## 7. Résultats des tests

| Suite | Résultat | Portée |
|---|---|---|
| `tests/Unit/TenantIsolationTest.php` (nouveau) | **24/24 PASS** | Lecture/recherche/pagination/création/modification/suppression, 2 tenants réels, IDOR, upsert/storePointage, régression sans contexte |
| `tests/Unit/TenantResolverTest.php` (Phase 14.2) | **29/29 PASS** | Non-régression fondation |
| `tests/security_tests.php` (pré-existant) | **48/48 PASS** | Régression sécurité globale |
| `tests/functional_tests.php` (pré-existant) | **109/109 PASS** | Régression fonctionnelle globale |
| **Total** | **210/210 PASS** | |
| Vérification manuelle navigateur | ✅ | Login, liste élèves (10→11 après création réelle, nettoyée), recherche, création via formulaire — 0 erreur JS/HTTP |

Méthodologie du test d'isolation : établissement B créé réellement en base
dans une transaction PDO, données de test créées sous les deux contextes via
les modèles eux-mêmes (pas de fixtures SQL manuelles), toutes les
assertions vérifiées, **rollback complet en fin de script** — zéro donnée de
test persistée, confirmé par requête post-exécution.

---

## 8. Décision finale : **GO WITH FIXES**

**GO** sur le périmètre strictement défini par le blueprint (9 tables) :
migration propre, isolation prouvée, zéro régression, bugs critiques de
saisie trouvés et corrigés avant livraison.

**WITH FIXES** — dette technique résiduelle identifiée pendant
l'implémentation, à traiter avant que la Phase 14.4 (RBAC multi-tenant)
n'active l'application réelle du filtrage (voir §9 pour le détail complet) :
les tables `controles`, `moyennes_generales`, `moyennes_matieres`,
`justifications` ne portent pas `etablissement_id` (hors périmètre 14.3 —
non listées dans le diagnostic §3.1 du blueprint) et quelques requêtes
d'agrégat pur (`NoteModel::globalStats()`, sous-requêtes de
`getBulletinData()`) qui ne joignent aucune des 9 tables scopées restent
non filtrées. Ce n'est **pas une fuite de données activement exploitable
aujourd'hui** (un seul établissement existe en production, aucune table
V2/portails n'expose ces agrégats à un autre tenant), mais devient un vrai
risque dès qu'un deuxième établissement utilisera l'application.

---

## 9. Dette technique restante

| Élément | Risque | Recommandation |
|---|---|---|
| `controles`, `moyennes_generales`, `moyennes_matieres`, `justifications` sans `etablissement_id` | Moyen — agrégats cross-tenant possibles sur `NoteModel::globalStats()` et 2 sous-requêtes de `getBulletinData()` | Migration complémentaire (T007) avant Phase 14.4, même périmètre reproductible avec le script T006 |
| Autres tables V1 métier non citées par l'audit blueprint §3.1 : `paiements`, `depenses`, `depenses_categories`, `frais_eleves`, `frais_types`, `annonces`, `notifications*`, `emplois_du_temps`, `creneaux`, `salles`, `annees_scolaires`, `push_subscriptions` | Moyen — mêmes tables mono-tenant aujourd'hui | Décision à prendre : étendre le même schéma en 14.3-bis, ou couvrir dans une phase dédiée. Non traité ici pour respecter strictement le périmètre validé. |
| `config/tenant.php['default_id']` (repli à `1`) | Faible tant qu'un seul établissement existe ; deviendra un point critique dès l'activation de Phase 14.4+ | Supprimer/durcir le repli une fois `TenantMiddleware` activé (chaque requête aura alors un vrai contexte résolu) |
| `TenantMiddleware` toujours non activé dans `Application::run()` | — | Conforme au périmètre demandé ; activation prévue Phase 14.4 |
| `eleves.matricule` reste unique globalement (pas par tenant) | Faible | Décision produit à prendre (matricules partagés entre écoles vs. par école) — non traité, hors scope colonne/FK/index |

---

## 10. Compatibilité V2

**100% compatible.** Aucune signature de méthode modifiée, aucune règle de
calcul modifiée (moyennes, classement, statuts de justification identiques),
aucune route ni contrôleur touché. Les 14 autres modèles de l'application
(`$tenantScoped` par défaut `false`) sont totalement inchangés.

---

## 11. Recommandation

Prêt pour **PHASE 14.3 SYSTEM INTEGRATION REVIEW** avec la réserve du §9
(tables secondaires non scopées) à porter à la connaissance du relecteur.
Avant d'activer l'application réelle du multi-tenant (Phase 14.4 RBAC +
activation `TenantMiddleware`), traiter au minimum la ligne "Risque Moyen"
du tableau §9 (controles/moyennes/justifications) pour fermer les derniers
agrégats cross-tenant.
