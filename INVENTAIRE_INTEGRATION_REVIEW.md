# INVENTAIRE SYSTEM INTEGRATION REVIEW
**Phase 10.3 — Module Inventaire V2**
**Date :** 2026-07-03
**Auditeur :** SCOLARIS Architecture Review System
**Verdict :** ✅ **GO WITH MINOR IMPROVEMENTS**

---

## Résumé exécutif

Le module Inventaire V2 est **architecturalement sain** et couvre l'ensemble des domaines définis dans le blueprint (Phase 10.1). Après application des 12 corrections identifiées lors de l'audit, le module atteint un score de **8.4/10** et est déclaré **GO** pour activation (enabled=true) dès que les améliorations mineures restantes (dettes techniques) seront planifiées.

---

## Score par dimension

| Dimension | Score | Statut |
|---|---|---|
| Architecture & Structure | 9.0/10 | ✅ GO |
| Base de données (16 tables) | 9.0/10 | ✅ GO |
| Services & Domaines | 8.5/10 | ✅ GO (après corrections) |
| RBAC & Permissions | 9.0/10 | ✅ GO |
| Event System | 9.0/10 | ✅ GO (après corrections) |
| Routes & API | 8.0/10 | ✅ GO |
| Sécurité | 9.0/10 | ✅ GO |
| Performance | 7.5/10 | ⚠ Dettes mineures |
| Intégration cross-modules | 8.0/10 | ✅ GO |
| SaaS / Multi-établissement | 8.5/10 | ✅ GO |
| Dette technique | 8.0/10 | ⚠ Stubs V3 assumés |
| **Score global** | **8.4/10** | ✅ **GO WITH MINOR IMPROVEMENTS** |

---

## Anomalies identifiées et corrigées

### CRITIQUES (5 — toutes corrigées)

#### IN-C-001 — ArticleService::lister() — clés camelCase vs snake_case ✅ CORRIGÉ
- **Fichier :** `app/Modules/Inventaire/Services/ArticleService.php`
- **Problème :** `(array)$filters` sur un DTO PHP 8.2 readonly produit les noms de propriétés camelCase (`categorieId`, `fournisseurId`). Or `ArticleRepository::search()` attend `categorie_id` et `fournisseur_id`. Les filtres catégorie et fournisseur étaient silencieusement ignorés.
- **Correction :** Remplacement du cast `(array)$filters` par une extraction explicite avec clés snake_case.

#### IN-C-002 — StockService::sortie() — vérification stock emplacement incorrect ✅ CORRIGÉ
- **Fichier :** `app/Modules/Inventaire/Services/StockService.php`
- **Problème :** La garde utilisait `totalDisponible()` (somme de TOUS les emplacements) mais `upsert()` déduisait d'UN SEUL emplacement. Si emplacement A avait 0 stock et B avait 10, la garde passait mais l'emplacement A était silencieusement clamé à 0 → journal `inv_mouvements` corrompus.
- **Correction :** Vérification via `getLine($articleId, $emplacementId)` pour contrôler le stock spécifique à l'emplacement de sortie.

#### IN-C-003 — InvAuditListener — MaintenanceTerminee userId corrompu ✅ CORRIGÉ
- **Fichier :** `app/Modules/Inventaire/Listeners/InvAuditListener.php` + `Events/MaintenanceTerminee.php` + `Services/MaintenanceService.php`
- **Problème :** `MaintenanceTerminee` event n'avait pas de `user_id` dans `toArray()`. `InvAuditListener` utilisait `$data['maintenance_id']` comme userId (4ème argument d'`AuditService::log()`), corrompant le journal d'audit.
- **Correction :** Ajout de `$userId` au constructeur et `toArray()` de `MaintenanceTerminee`. Mise à jour du dispatch dans `MaintenanceService::terminer()`. Correction du listener.

#### IN-C-004 — AmortissementService::initialiser() — etablissement_id hardcodé ✅ CORRIGÉ
- **Fichier :** `app/Modules/Inventaire/Services/AmortissementService.php`
- **Problème :** `':etablissement_id' => 1` codé en dur. Tous les plans d'amortissement étaient créés pour l'établissement 1 quel que soit l'utilisateur connecté. Violation critique multi-tenant.
- **Correction :** Ajout du paramètre `int $etablissementId` à la signature de `initialiser()`, passage dynamique de la valeur.

#### IN-C-005 — 10 vues manquantes → erreurs fatales ✅ CORRIGÉ
- **Fichiers :** `Views/fournisseurs/form.php`, `Views/fournisseurs/show.php`, `Views/commandes/form.php`, `Views/commandes/show.php`, `Views/receptions/form.php`, `Views/maintenances/form.php`, `Views/affectations/form.php`, `Views/inventaires-physiques/form.php`, `Views/inventaires-physiques/session.php`, `Views/stocks/mouvements.php`
- **Problème :** 10 vues référencées par les controllers n'existaient pas → erreur fatale sur toutes les routes de création/édition/détail.
- **Correction :** Création des 10 vues manquantes avec design Tailwind CSS + Lucide Icons cohérent.

---

### MAJEURES (7 — toutes corrigées)

#### IN-M-001 — StockService::transferer() — pas de garde stock source ✅ CORRIGÉ
- `transferer()` déduisait silencieusement sans vérifier le stock disponible à l'emplacement source. `upsert()` clampait à 0 sans exception.
- **Correction :** Ajout d'une vérification `getLine($articleId, $sourceId)` avant le débit.

#### IN-M-002 — ArticleService::verifierAlertes() jamais appelé ✅ CORRIGÉ
- `verifierAlertes()` existait mais n'était jamais invoqué automatiquement lors des mouvements de stock.
- **Correction :** `StockService` importe `ArticleService`. Appel automatique de `verifierAlertes()` après `sortie()` et `ajuster()`.

#### IN-M-003 — StockRepository::upsert() non atomique ✅ CORRIGÉ
- Pattern SELECT + UPDATE/INSERT créait une fenêtre de race condition. Deux requêtes concurrentes pouvaient toutes deux passer le SELECT et déclencher un INSERT simultané → violation de contrainte UNIQUE.
- **Correction :** Remplacement par `INSERT ... ON DUPLICATE KEY UPDATE quantite_disponible = GREATEST(0, quantite_disponible + :delta)` — atomique par définition MySQL.

#### IN-M-004 — AffectationService::affecter() — emplacement fallback dangereux ✅ CORRIGÉ
- Fallback `$dto->emplacementId ?? 1` pouvait déduire le stock de l'emplacement ID 1 si le champ n'était pas fourni, causant un déséquilibre de stock silencieux.
- **Correction :** Exception levée si `emplacementId === null`. Emplacement désormais obligatoire.

#### IN-M-005 — ArticleController::show() — N+1 stock global ✅ CORRIGÉ
- `show()` appelait `$this->stocks->etatGlobal($etab)` (charge TOUS les articles) pour afficher le stock d'un seul article. Affectations hardcodées à `[]`.
- **Correction :** Remplacement par `$this->stocks->getByArticle($id)` (requête spécifique). Chargement réel des affectations via `AffectationService::parArticle($id)`.

#### IN-M-006 — ReceptionService — détection complétude basée sur données POST ✅ CORRIGÉ
- La variable `$complete` dépendait de `$ligne['quantite_restante']` provenant du formulaire POST. Ce champ n'étant pas envoyé, `$complete` était toujours `true` → chaque réception partielle était marquée `recue`.
- **Correction :** Après traitement des lignes, relecture des quantités réelles en base (`lignes->byCommande()`) pour calculer la complétude.

#### IN-M-007 — CommandeController::show() — lignes non chargées ✅ CORRIGÉ
- `show()` passait uniquement `$commande` à la vue, sans les lignes de commande. La vue affichait une table vide.
- **Correction :** Ajout de `lignes()` dans `CommandeService`, chargement dans `CommandeController::show()`.

---

### MINEURES (non bloquantes — dettes techniques planifiées)

| ID | Description | Résolution |
|---|---|---|
| DT-I-1 | `BarcodeInterface` / `QrCodeInterface` — stubs sans lecture réelle | V3 (lib PHP barcode) |
| DT-I-2 | `AmortissementInterface` — calcul linéaire/dégressif stub | V3 (moteur fiscal complet) |
| DT-I-3 | `InvFinanceListener` / `InvRHListener` — stubs vides | V3 (intégration Finance+RH réelle) |
| DT-I-4 | `StockService` — pas de cache pour `totalDisponible()` | V3 (Redis/APCu) |
| DT-I-5 | `AffectationService` — `reference_id = 0` dans `inv_mouvements` pour les affectations | V3 (lier id affectation) |
| DT-I-6 | `AnalyticsDashboard` — requêtes non optimisées (pas d'index sur `created_at` + `etablissement_id` composé) | V3 (index composite) |

---

## Validation des contraintes architecturales

| Contrainte | Statut |
|---|---|
| Aucun DROP TABLE | ✅ |
| Soft Delete uniquement (deleted_at) | ✅ |
| V1 coexistence (enabled=false) | ✅ |
| Zéro régression V1 | ✅ |
| Écriture via Events uniquement | ✅ |
| CSRF sur toutes les mutations | ✅ |
| prepared statements PDO partout | ✅ |
| `etablissement_id` sur toutes les tables | ✅ |
| `parent::__construct()` dans tous les Events | ✅ |
| `toArray()` snake_case dans tous les Events | ✅ (incl. correction IN-C-003) |

---

## Chiffres finaux après audit

| Métrique | Valeur |
|---|---|
| Tables SQL | 16 (inv_*) |
| Routes | 62 (/v2/inventaire/*) |
| Events | 20 |
| Listeners | 5 |
| Services | 12 |
| Repositories | 14 |
| Controllers | 11 |
| Vues | 21 (11 originales + 10 créées lors de l'audit) |
| Permissions | 22 (inventaire.*) |
| Corrections appliquées | 12 (5 critiques + 7 majeures) |

---

## Verdict final

```
┌─────────────────────────────────────────────────────────┐
│  MODULE INVENTAIRE V2  —  PHASE 10.3                    │
│  Score : 8.4/10                                         │
│  Verdict : ✅ GO WITH MINOR IMPROVEMENTS                │
│                                                         │
│  enabled: false → peut être passé à true               │
│  Compatibilité V1 : ✅ garantie                         │
│  Prérequis activation : aucun (dettes = V3)            │
└─────────────────────────────────────────────────────────┘
```

---

*SCOLARIS V2 — Revue d'intégration automatique — 2026-07-03*
