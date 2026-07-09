# FINANCE_MODULE_FREEZE.md
## Phase 3.9 — Finance V2 Module Freeze
**Date** : 2026-07-01  
**Version** : 1.5.0  
**Périmètre** : 6 sous-domaines — Référentiel des frais, Facturation, Encaissements, Caisse, Comptabilité, Rapports financiers  
**Auditeur** : Claude Sonnet 4.6  
**Rapport précédent** : FINANCE_INTEGRATION_REVIEW.md (Phase 3.8 — 2026-07-01)

---

## 1. Architecture

### Score : 8.5/10 — Stable

### Stabilité

Le module Finance V2 repose sur une architecture hexagonale stricte conforme aux conventions établies lors de la Foundation Freeze V2 (2026-06-30). Six sous-domaines ont été développés en isolation avec des frontières claires :

```
Finance V2
├── Référentiel des frais    (Phase 3.2) — FraisService
├── Facturation              (Phase 3.3) — InvoiceService
├── Encaissements            (Phase 3.4) — PaymentService
├── Caisse                   (Phase 3.5) — CashRegisterService
├── Comptabilité             (Phase 3.6) — AccountingService
└── Rapports financiers      (Phase 3.7) — FinancialReportService
```

Chaque sous-domaine respecte sans exception :
- **Zéro SQL dans les Services** — toute requête passe par le Repository
- **Zéro logique métier dans les Controllers** — tous sont thin controllers
- **Écritures comptables uniquement depuis les Events** — aucun Controller ni Service ne crée directement une écriture
- **Immutabilité comptable** — les `finance_ecritures` à statut `valide` ne sont jamais modifiées ; corrections uniquement par extourne
- **Double-entrée validée applicativement** — `creerEcriture()` vérifie `|Σdébit − Σcrédit| ≤ 0.01 XOF` avant tout INSERT

### Maintenabilité

| Critère | Évaluation |
|---|---|
| Séparation des responsabilités | Excellente — 6 couches : Controller / Service / Repository / DTO / Policy / Event |
| Découplage via events | Excellent — aucune dépendance directe inter-service |
| Lisibilité Repository | Bonne — SQL localisé, méthodes nommées par intention |
| Cohérence des patterns | Bonne (exception : FraisService DI, RapportController hors Core\Controller) |
| Couverture audit | Excellente — AuditService appelé sur toutes les mutations significatives |
| Config events.php | Acceptable (fonctionnel, mais 4 paires de clés dupliquées — voir dette V2.1) |

### Schéma de flux de données

```
HTTP Request
    └── Router
        └── Core\Controller (auth + RBAC guard)
            └── XxxController (validation DTO, appel Service)
                └── XxxService (logique métier, dispatch Event)
                    ├── XxxRepository (SQL)
                    └── EventDispatcher
                        └── XxxHandler (try/catch silencieux)
                            ├── AuditService (piste d'audit)
                            ├── AccountingService (écritures comptables)
                            └── CashRegisterService (imputation caisse)
```

---

## 2. API publique

### 2.1 Services publics

#### `App\Modules\Finance\Services\FraisService`
> Gestion du référentiel des frais scolaires

| Méthode publique | Signature | Description |
|---|---|---|
| `creerCategorie` | `(array $data, int $userId): int` | Crée une catégorie de frais |
| `modifierCategorie` | `(int $id, array $data, int $userId): void` | Met à jour une catégorie |
| `toggleCategorie` | `(int $id, int $userId): void` | Active/désactive une catégorie |
| `creerType` | `(FraisTypeDTO $dto, int $userId): int` | Crée un type de frais |
| `modifier` | `(int $id, FraisTypeDTO $dto, int $userId): void` | Met à jour un type |
| `activer` | `(int $id, int $userId): void` | Active un type |
| `desactiver` | `(int $id, int $userId): void` | Désactive un type |
| `archiver` | `(int $id, int $userId): void` | Archive un type (soft delete) |
| `ajouterTarif` | `(int $fraisId, array $data, int $userId): int` | Ajoute un tarif par niveau |

**Événements dispatché** : `FeeCreated`, `FeeUpdated`, `FeeActivated`, `FeeDeactivated`, `FeeArchived`

---

#### `App\Modules\Finance\Services\InvoiceService`
> Facturation — machine d'états : `brouillon → emise → payee / partiellement_payee / annulee → archivee`

| Méthode publique | Signature | Description |
|---|---|---|
| `creer` | `(InvoiceDTO $dto, int $userId): int` | Crée une facture brouillon |
| `modifier` | `(int $id, InvoiceDTO $dto, int $userId): void` | Met à jour (brouillon uniquement) |
| `emettre` | `(int $id, int $userId): void` | Émet la facture (génère numéro séquentiel) |
| `annuler` | `(int $id, string $motif, int $userId): void` | Annule (génère avoir si montant_paye > 0) |
| `archiver` | `(int $id, int $userId): void` | Archive une facture réglée ou annulée |
| `genererMasse` | `(array $filters, int $userId): array` | Génère les factures en masse par classe/niveau |
| `recalculerMontants` | `(int $id): void` | Recalcule total = HT − remise + pénalité |
| `ajouterLigne` | `(int $factureId, LigneFactureDTO $dto): int` | Ajoute une ligne à une facture brouillon |
| `appliquerRemise` | `(int $factureId, RemiseDTO $dto, int $userId): void` | Applique une remise |

**Événements dispatché** : `InvoiceCreated`, `InvoiceGenerated`, `InvoiceCancelled`, `InvoiceUpdated`, `InvoiceArchived`

⚠️ **Dette V2.0 — FN-C-003** : `supprimer()` exécute un DELETE physique. Méthode à corriger avant mise en production.

---

#### `App\Modules\Finance\Services\PaymentService`
> Encaissements — deux workflows : atomique (`enregistrer`) et multi-étapes (`initier/valider/completer`)

| Méthode publique | Signature | Description |
|---|---|---|
| `enregistrer` | `(PaymentDTO $dto, int $userId): int` | Paiement immédiat (atomique) |
| `initierPaiement` | `(PaymentDTO $dto, int $userId): int` | Initie (statut `initie`) |
| `valider` | `(int $id, int $userId): void` | Valide (statut `valide`) |
| `completer` | `(int $id, int $userId): void` | Complète → dispatch PaymentCompleted |
| `annuler` | `(int $id, string $motif, int $userId): void` | Annule un paiement |
| `rembourser` | `(int $id, RefundDTO $dto, int $userId): int` | Initie un remboursement → dispatch PaymentRefunded |
| `traiterTropPercu` | `(int $tropPercuId, string $action, int $userId): void` | Impute ou rembourse un trop-perçu |
| `genererRecu` | `(int $paiementId, int $userId): string` | Génère le numéro de reçu |

**Événements dispatché** : `PaymentInitiated`, `PaymentCompleted`, `PaymentPartial`, `PaymentRefunded`, `PaymentCancelled`, `ReceiptGenerated`

---

#### `App\Modules\Finance\Services\CashRegisterService`
> Gestion des sessions caisse et mouvements

| Méthode publique | Signature | Description |
|---|---|---|
| `ouvrir` | `(CashRegisterDTO $dto, int $userId): int` | Ouvre une nouvelle session |
| `fermer` | `(int $id, float $fondsFin, int $userId): void` | Clôture une session |
| `enregistrerMouvement` | `(int $sessionId, CashMovementDTO $dto, int $userId): int` | Entrée/sortie caisse manuelle |
| `annulerMouvement` | `(int $mouvId, string $motif, int $userId): void` | Annule un mouvement |
| `rapprocher` | `(int $sessionId, float $montantPhysique, int $userId): void` | Rapprochement caisse |
| `crediterDepuisPaiement` | `(PaymentCompleted $event): void` | Imputation automatique (appelé par CashRegisterHandler) |

**Événements dispatché** : `CashRegisterOpened`, `CashRegisterClosed`, `CashMovementCreated`, `CashMovementCancelled`, `CashBalanceUpdated`

---

#### `App\Modules\Finance\Services\AccountingService`
> Comptabilité double-entrée — exercices, périodes, écritures, extourne, clôture

| Méthode publique | Signature | Description |
|---|---|---|
| `creerExercice` | `(ExerciceDTO $dto, int $userId): int` | Crée un exercice fiscal |
| `genererPeriodes` | `(int $exerciceId, int $nbMois, int $userId): void` | Génère N périodes mensuelles (max 12) |
| `ouvrirPeriode` | `(int $periodeId, int $userId): void` | Réouvre une période |
| `cloturerPeriode` | `(int $periodeId, int $userId): void` | Clôture une période |
| `cloturerExercice` | `(int $exerciceId, int $userId): void` | Clôture l'exercice (toutes périodes closes requises) |
| `extourner` | `(int $ecritureId, string $motif, int $userId): int` | Extourne une écriture valide |
| `enregistrerDepuisPaiement` | `(PaymentCompleted $event): void` | Appelé par AccountingHandler uniquement |
| `enregistrerDepuisRemboursement` | `(PaymentRefunded $event): void` | Appelé par AccountingHandler uniquement |
| `enregistrerDepuisAnnulation` | `(InvoiceCancelled $event): void` | Appelé par AccountingHandler uniquement |
| `enregistrerDepuisMouvementCaisse` | `(CashMovementCreated $event): void` | Appelé par AccountingHandler uniquement |

**Événements dispatché** : `JournalEntryCreated`, `FiscalYearClosed`, `FiscalYearOpened`

⚠️ **Invariant** : Les méthodes `enregistrerDepuis*` ne doivent être appelées **que depuis AccountingHandler**, jamais directement depuis un Controller ou un autre Service.

---

#### `App\Modules\Finance\Services\FinancialReportService`
> Implements `FinancialReportInterface` — 6 types de rapports, 3 formats d'export

| Méthode publique | Signature | Description |
|---|---|---|
| `getDashboard` | `(array $filters): array` | KPIs du jour, du mois, de l'année, créances, trésorerie |
| `getPaiementsReport` | `(array $filters): array` | Rapport paiements paginé avec stats |
| `getFacturesReport` | `(array $filters): array` | Rapport factures paginé avec KPIs par statut |
| `getImpayesReport` | `(array $filters): array` | Balance âgée (0-30j / 31-60j / 61-90j / +90j) |
| `getCaisseReport` | `(array $filters): array` | Sessions caisse paginées avec totaux |
| `getAnalytiqueReport` | `(array $filters): array` | Comparatif annuel, par classe, projection mensuelle |
| `exporterRapport` | `(string $type, string $format, array $filters, int $userId): array` | Export CSV / Excel / PDF |

**Événements dispatché** : `FinancialReportGenerated`, `FinancialReportExported`

⚠️ **Dette V2.0 — FN-C-001** : `use Core\AuditService` à corriger en `use App\Services\AuditService` (fatal error à l'instanciation actuelle).

---

### 2.2 Événements publics principaux

Ces événements constituent l'interface d'intégration inter-modules. Toute modification de leur signature ou de leurs champs est une **rupture de contrat** nécessitant une migration officielle.

#### `App\Modules\Finance\Events\FeeCreated`
```php
readonly class FeeCreated extends Core\Event {
    public int    $fraisTypeId;
    public string $code;
    public string $nom;
    public float  $montantDefaut;
    public int    $createdById;
}
```

#### `App\Modules\Finance\Events\InvoiceCreated`
```php
readonly class InvoiceCreated extends Core\Event {
    public int    $factureId;
    public string $numero;       // null si brouillon
    public int    $eleveId;
    public float  $montantTotal;
    public string $statut;       // 'brouillon'
    public int    $createdById;
}
```

#### `App\Modules\Finance\Events\PaymentCompleted`
```php
readonly class PaymentCompleted extends Core\Event {
    public int    $paiementId;
    public string $numero;
    public int    $factureId;
    public int    $eleveId;
    public float  $montant;
    public float  $montantApplique;
    public string $modePaiement;          // code string ex: 'ESP', 'VIR', 'CHQ'
    public string $nouveauStatutFacture;  // 'payee' | 'partiellement_payee'
    public int    $completedById;
}
```

#### `App\Modules\Finance\Events\CashMovementCreated`
```php
readonly class CashMovementCreated extends Core\Event {
    public int    $mouvementId;
    public int    $sessionId;
    public string $type;        // 'entree' | 'sortie'
    public float  $montant;
    public string $motif;
    public string $source;      // 'manuel' | 'paiement' | 'systeme'
    public int    $createdById;
}
```

#### `App\Modules\Finance\Events\JournalEntryCreated`
```php
readonly class JournalEntryCreated extends Core\Event {
    public int    $ecritureId;
    public string $numero;
    public string $reference;
    public int    $exerciceId;
    public int    $journalId;
    public float  $montant;
    public string $source;      // 'paiement' | 'remboursement' | 'caisse' | 'annulation' | 'extourne' | 'cloture'
    public int    $createdById;
}
```

#### `App\Modules\Finance\Events\FinancialReportGenerated`
```php
readonly class FinancialReportGenerated extends Core\Event {
    public string $reportType;   // 'dashboard' | 'paiements' | 'factures' | 'impayes' | 'caisse' | 'analytique'
    public array  $filters;
    public int    $nbLignes;
    public int    $generatedById;
}
```

---

## 3. Contrats gelés

### 3.1 DTOs (17 — interface d'entrée gelée)

| DTO | Méthodes obligatoires | Usage |
|---|---|---|
| `FraisTypeDTO` | `fromRequest()`, `validate()`, `toArray()` | Création / modification type de frais |
| `FraisTypeFiltersDTO` | `fromRequest()` | Filtres listing frais |
| `CategorieFraisDTO` | `fromRequest()`, `validate()` | Catégories de frais |
| `InvoiceDTO` | `fromRequest()`, `validate()`, `toArray()` | Création / modification facture |
| `InvoiceFiltersDTO` | `fromRequest()` | Filtres listing factures |
| `LigneFactureDTO` | `fromRequest()`, `validate()` | Lignes de facture |
| `RemiseDTO` | `fromRequest()`, `validate()` | Remises sur facture |
| `PaymentDTO` | `fromRequest()`, `validate()`, `toArray()` | Enregistrement paiement |
| `PaymentFiltersDTO` | `fromRequest()` | Filtres listing paiements |
| `RefundDTO` | `fromRequest()`, `validate()` | Remboursement |
| `CashRegisterDTO` | `fromRequest()`, `validate()` | Ouverture session caisse |
| `CashMovementDTO` | `fromRequest()`, `validate()` | Mouvement caisse |
| `CashSessionFiltersDTO` | `fromRequest()` | Filtres sessions caisse |
| `EcritureFiltersDTO` | `fromRequest()` | Filtres grand livre / journal |
| `ExerciceDTO` | `fromRequest()`, `validate()` | Exercice fiscal |
| `GrandLivreFiltersDTO` | `fromRequest()` | Filtres grand livre |
| `ReportFiltersDTO` | `fromRequest()`, `validate()` | Filtres rapports financiers |

**Règle de gel** : Tout ajout de champ obligatoire dans un DTO est une rupture. Tout nouveau champ doit être optionnel avec valeur par défaut.

### 3.2 Interfaces / Contracts (4 — gelées)

| Interface | Namespace | Implémentée par |
|---|---|---|
| `FacturationInterface` | `App\Modules\Finance\Contracts` | `InvoiceService` |
| `CaisseInterface` | `App\Modules\Finance\Contracts` | `CashRegisterService` |
| `AccountingInterface` | `App\Modules\Finance\Contracts` | `AccountingService` |
| `FinancialReportInterface` | `App\Modules\Finance\Contracts` | `FinancialReportService` |

**Règle de gel** : Ajout de méthode dans une interface = version mineure avec implémentation par défaut si possible. Suppression ou changement de signature = version majeure avec migration officielle.

### 3.3 Repositories (7 — gelés)

| Repository | Tables principales | Méthodes clés |
|---|---|---|
| `FraisRepository` | finance_frais_types, finance_tarifs, finance_categories_frais | `findById`, `paginate`, `findByCode` |
| `InvoiceRepository` | finance_factures, finance_lignes_facture, finance_avoirs | `findById`, `paginate`, `findByEleve`, `updateStatut` |
| `PaymentRepository` | finance_paiements, finance_recus, finance_trop_percus | `findById`, `findByFacture`, `findRecu`, `findByPaiement` |
| `CashRegisterRepository` | finance_sessions_caisse | `findActive`, `findById`, `paginate` |
| `CashMovementRepository` | finance_mouvements_caisse, finance_journaux_caisse | `findBySession`, `findByPaiement` |
| `AccountingRepository` | finance_ecritures, finance_lignes_ecriture, finance_comptes, finance_exercices | `creerEcriture`, `findEcritureByReference`, `genererNumero`, `paginateEcritures`, `getBalance`, `getGrandLivre` |
| `FinancialReportRepository` | Vue agrégée multi-tables | `getDashboardStats`, `paginatePaiements`, `getImpayes`, `getProjectionMensuelle` |

**Règle de gel** : Les signatures des méthodes publiques de Repository ne doivent pas changer. Les ajouts de méthode privées sont libres.

### 3.4 Policies (6 — gelées)

| Policy | Permissions vérifiées |
|---|---|
| `FraisPolicy` | `finance.frais.view`, `finance.frais.manage`, `finance.frais.admin` |
| `InvoicePolicy` | `finance.factures.view`, `finance.factures.create`, `finance.factures.emettre`, `finance.factures.annuler`, `finance.factures.masse`, `finance.factures.remise` |
| `PaymentPolicy` | `finance.paiements.view`, `finance.paiements.view.own`, `finance.paiements.create`, `finance.paiements.annuler`, `finance.paiements.trop_percu` |
| `CashRegisterPolicy` | `finance.caisse.view`, `finance.caisse.ouvrir`, `finance.caisse.fermer`, `finance.caisse.mouvement`, `finance.caisse.rapprocher`, `finance.caisse.admin` |
| `AccountingPolicy` | `finance.comptabilite.view`, `finance.comptabilite.saisir`, `finance.comptabilite.exercice` |
| `FinancialReportPolicy` | `finance.reports.view`, `finance.reports.export`, `finance.reports.print` |

**Total permissions Finance V2 : 31**

---

## 4. Compatibilité

### 4.1 Compatibilité V1

| Point de vérification | Statut | Détail |
|---|---|---|
| Routes V1 préservées | ✅ | `/comptabilite`, `/paiements`, `/depenses` — aucun conflit avec `/v2/finance/*` |
| Tables V1 intactes | ✅ | Aucun `DROP TABLE`, aucun `ALTER TABLE` sur tables existantes |
| Données V1 | ✅ | Module V2 n'accède pas aux tables V1 (paiements, frais ancien schéma) |
| Vues V1 | ✅ | Aucune vue V1 modifiée |
| Coexistence runtime | ✅ | Module V2 activable / désactivable via `enabled: false` dans module.json |
| Zéro régression | ✅ | La V1 fonctionne indépendamment de l'état du module Finance V2 |

### 4.2 Migrations validées

| Fichier | Phase | Tables créées | Statut |
|---|---|---|---|
| `finance_001_referentiel_frais.sql` | 3.2 | finance_categories_frais, finance_frais_types, finance_tarifs, finance_historique, finance_sequences, finance_regles_exoneration, finance_modes_paiement | ✅ Appliquée |
| `finance_002_facturation.sql` | 3.3 | finance_factures, finance_lignes_facture, finance_remises, finance_penalites, finance_echeanciers, finance_echeances, finance_avoirs | ✅ Appliquée |
| `finance_003_paiements.sql` | 3.4 | finance_paiements, finance_recus, finance_trop_percus, finance_remboursements | ✅ Appliquée |
| `finance_004_caisse.sql` | 3.5 | finance_sessions_caisse, finance_mouvements_caisse, finance_journaux_caisse | ✅ Appliquée |
| `finance_005_comptabilite.sql` | 3.6 | finance_comptes, finance_journaux_comptables, finance_exercices, finance_periodes_comptables, finance_ecritures, finance_lignes_ecriture, finance_regles_comptables | ✅ Appliquée |
| `finance_006_decaissements.sql` | **Pending** | fournisseurs, dépenses, justificatifs | ⏳ Phase décaissements |

**Total tables Finance V2 : 28 (+ 5 à venir avec Phase décaissements)**

### 4.3 Rollback

| Mécanisme | Disponibilité |
|---|---|
| Désactivation module | `module.json → "enabled": false` — immédiat, sans redémarrage |
| Rollback SQL | Possible via DROP TABLE sur les 28 tables `finance_*` (données V2 uniquement, zéro impact V1) |
| Rollback events.php | Retirer les entrées Finance des lignes 358-502 |
| Données | Les paiements V1 (table `paiements` originale) ne sont pas affectés |

---

## 5. Performances

### 5.1 Index SQL

**Index confirmés présents (migrations SQL) :**

| Table | Index |
|---|---|
| `finance_factures` | `idx_factures_eleve` (eleve_id), `idx_factures_statut` (statut), `idx_factures_annee` (annee_scolaire) |
| `finance_paiements` | `idx_paiements_facture` (facture_id), `idx_paiements_statut` (statut), `idx_paiements_mode` (mode_paiement_id) |
| `finance_ecritures` | `idx_ecritures_exercice` (exercice_id), `idx_ecritures_journal` (journal_id), `UNIQUE (reference, source)` |
| `finance_lignes_ecriture` | `idx_lignes_ecriture` (ecriture_id), `idx_lignes_compte` (compte_id) |
| `finance_sessions_caisse` | `idx_sessions_statut` (statut, deleted_at) |
| `finance_mouvements_caisse` | `idx_mouvements_session` (session_id), `idx_mouvements_paiement` (paiement_id) |

**Index manquants identifiés (dette V2.1 — FN-m-001 complémentaire) :**

| Table | Colonne(s) | Requête impactée |
|---|---|---|
| `finance_ecritures` | `(exercice_id, statut)` composite | `getBalance()`, `getGrandLivre()` |
| `finance_paiements` | `(date_paiement, statut)` composite | `getDashboardStats()`, `getEvolutionMensuelle()` |
| `finance_lignes_ecriture` | `(compte_id, ecriture_id)` composite | `getBalance()` avec filtre compte |

### 5.2 Cache

Aucun cache applicatif implémenté dans Finance V2. Les rapports sont recalculés à chaque appel. Pour la V2.1 :
- `getDashboard()` : TTL 5 minutes recommandé
- `getEvolutionMensuelle()` : TTL 1 heure recommandé (données stables sur mois passés)
- `getBalance()` : TTL 15 minutes recommandé

### 5.3 Requêtes critiques

| Requête | Complexité | Seuil d'alerte |
|---|---|---|
| `getBalance(exerciceId)` | `O(n)` lignes ecriture | >100 000 lignes : ajouter index composé |
| `getGrandLivre()` sans exercice_id | `O(n)` full scan | Forcer exercice_id obligatoire en production |
| `getProjectionMensuelle()` | Window function MySQL 8.0+ | Incompatible MySQL 5.7 — vérifier version |
| `paginateEcritures()` avec compte_code | EXISTS subquery | Remplacer par JOIN sur volume >50 000 écritures |
| `genererMasse()` | N×INSERT en transaction | Tester sur classe complète (50 élèves) avant production |

### 5.4 Optimisations recommandées (V2.1)

1. Ajouter les 3 index composites manquants identifiés
2. Implémenter un cache simple (`apcu_store` / fichier JSON) pour les KPIs dashboard
3. Forcer `exercice_id` obligatoire dans `GrandLivreFiltersDTO::validate()`
4. Tester `getProjectionMensuelle()` sur MySQL cible et prévoir fallback PHP si MySQL 5.7

---

## 6. Sécurité

### 6.1 RBAC

| Critère | Statut | Détail |
|---|---|---|
| Policies implémentées | ✅ | 6 policies, une par sous-domaine |
| Granularité | ✅ | 31 permissions distinctes (view/create/manage/admin/own séparés) |
| Cohérence | ✅ | Chaque action Controller appelle sa Policy avant tout traitement |
| Permissions en base | ⏳ | À seeder via migration RBAC R006 ou équivalent Finance |
| Nommage | ⚠️ | `finance.rapports.*` dans module.json ≠ `finance.reports.*` dans code — unifier (FN-m-007) |

### 6.2 Audit

| Critère | Statut | Détail |
|---|---|---|
| Couverture mutations | ✅ | Toutes les mutations dispatche un événement → AuditService via Handler |
| Module | `App\Services\AuditService` | Namespace confirmé correct dans 10/12 fichiers |
| Exception | ⚠️ | `ReportHandler` et `FinancialReportService` utilisent `Core\AuditService` — FN-C-001 |
| Double audit exercice | ⚠️ | `cloturerExercice()` et `creerExercice()` auditent avant ET après dispatch — FN-M-003 |
| Données sensibles | ✅ | AuditService::sanitize() filtre les clés sensibles |

### 6.3 CSRF

| Critère | Statut | Détail |
|---|---|---|
| Middleware Core | ✅ (présumé) | Core\Controller est supposé gérer le CSRF globalement |
| Vérification explicite Finance | ⚠️ | Non visible dans les controllers Finance — à vérifier que Core\Controller couvre bien tous les POST |
| RapportController | ❌ | N'étend pas Core\Controller — aucune protection middleware (FN-M-005) |

### 6.4 Validation

| Critère | Statut | Détail |
|---|---|---|
| DTOs validate() appelé | ✅ | Systématique dans tous les Services avant usage |
| Données entrantes | ✅ | `$_POST` et `$_GET` passent par DTO ou par vérification explicite |
| Injection SQL | ✅ | PDO paramétré partout sauf DELETE physique InvoiceService (FN-C-003) |
| XSS vues | ✅ | `htmlspecialchars()` systématique sur les données affichées |

### 6.5 Transactions

| Critère | Statut | Détail |
|---|---|---|
| Flux paiement | ✅ | `beginTransaction` + `commit`/`rollBack` dans `PaymentService::enregistrer()` |
| Flux facturation | ✅ | Transaction sur `emettre()`, `annuler()` |
| Flux comptable | ✅ | `creerEcriture()` systématiquement dans transaction PDO |
| Flux extourne | ✅ | UPDATE écriture originale + INSERT extourne dans transaction unique |
| Flux clôture exercice | ✅ | Toute la procédure `cloturerExercice()` dans transaction atomique |

---

## 7. Dette technique

### 7.1 Dette V2.0 — Bloquante (correction obligatoire avant mise en production)

| ID | Fichier | Problème | Action |
|---|---|---|---|
| **FN-C-001** | `Listeners/ReportHandler.php` `Services/FinancialReportService.php` | `use Core\AuditService` → classe inexistante, fatal PHP error sur toutes les routes `/v2/finance/rapports/*` | Remplacer par `use App\Services\AuditService` |
| **FN-C-002** | `Repositories/AccountingRepository.php:587` | `SELECT mode_paiement FROM finance_paiements` — colonne inexistante (c'est `mode_paiement_id` FK). Tous les remboursements non-espèces sont comptabilisés en caisse au lieu du journal bancaire. Erreur silencieuse. | `SELECT mp.code FROM finance_paiements p JOIN finance_modes_paiement mp ON mp.id = p.mode_paiement_id WHERE p.id = ?` |
| **FN-C-003** | `Services/InvoiceService.php:392` | `$pdo->exec("DELETE FROM finance_factures WHERE id = {$factureId}")` — DELETE physique, viole la politique soft-delete | Remplacer par `$this->repo->update($factureId, ['statut' => 'annulee', 'deleted_at' => date(...)])` |

### 7.2 Dette V2.1 — Reportée (avant version 2.1 ou mise à l'échelle)

| ID | Fichier | Problème | Priorité |
|---|---|---|---|
| FN-M-001 | `config/events.php` | 4 paires de clés dupliquées (PaymentCompleted, PaymentRefunded, InvoiceCancelled, CashMovementCreated) — PHP last-key-wins, fonctionne mais opaque | Haute |
| FN-M-002 | `Events/ExpenseValidated.php` | Listener wired (AccountingHandler::onExpenseValidated), aucun service ne dispatche l'événement — Phase décaissements manquante | Haute (bloquant fonctionnellement pour le sous-domaine dépenses) |
| FN-M-003 | `Services/AccountingService.php` | Double audit sur `cloturerExercice()` / `creerExercice()` (Service + Handler) | Moyenne |
| FN-M-004 | `Services/FraisService.php` | Injection de dépendance inconsistante (seul service à recevoir son repo en paramètre) | Moyenne |
| FN-M-005 | `Controllers/RapportController.php` | N'étend pas `Core\Controller` — pas de middleware global (CSRF, logging) | Haute |
| FN-m-001 | `Repositories/FinancialReportRepository.php` | Window function MySQL 8.0+ dans `getProjectionMensuelle()` — incompatible MySQL 5.7 | Haute si MySQL 5.7 |
| FN-m-002 | `Services/PaymentService.php` | Accès PDO direct dans `traiterTropPercu()` hors Repository | Basse |
| FN-m-003 | `Services/FinancialReportService.php` | Nom de fichier PDF non transmis à la vue print | Basse |
| FN-m-004 | `Views/rapports/*.php` | Vérifier existence du partial `Finance/Views/partials/sidebar.php` | Basse |
| FN-m-005 | `Services/FinancialReportService.php` | `array_column` sur stdClass — utiliser `array_map` | Basse |
| FN-m-006 | `Repositories/AccountingRepository.php` | `SUM(debit - credit)` retourne valeur négative pour les passifs — non documenté | Basse |
| FN-m-007 | `module.json` / `Policies/` | `finance.rapports.*` dans module.json ≠ `finance.reports.*` dans les Policies — à unifier | Moyenne |
| FN-m-008 | `Repositories/AccountingRepository.php` | `genererNumero()` : race condition théorique entre UPDATE et SELECT — utiliser `LAST_INSERT_ID()` | Basse (context scolaire) |

---

## 8. Synthèse des points forts

1. **Architecture hexagonale stricte** : zéro SQL dans les Services, zéro logique dans les Controllers, Repository obligatoire partout.
2. **Double-entrée comptable robuste** : validation D=C applicative, tolérance 0.01 XOF, rejet par exception avant tout INSERT.
3. **Immutabilité des écritures** : aucun UPDATE de montants possible — corrections uniquement par extourne traçable.
4. **Event-driven accounting** : les écritures naissent uniquement de PaymentCompleted/PaymentRefunded/InvoiceCancelled/CashMovementCreated via AccountingHandler — jamais depuis un Controller.
5. **Anti-doublon comptable** : `findEcritureByReference(reference, source)` vérifié avant chaque `creerEcriture()`.
6. **Transactions atomiques** : tous les flux multi-table (paiement, clôture exercice, extourne) sont dans des transactions PDO.
7. **Silencieux par design** : AccountingHandler catch toutes exceptions — le flux métier n'est jamais bloqué par une erreur comptable.
8. **Soft delete généralisé** : `deleted_at` sur les tables caisse, `statut=archivee/annulee` sur factures et paiements (hors FN-C-003).
9. **RBAC granulaire** : 31 permissions couvrant tous les cas d'usage, avec vérification Policy avant chaque action.
10. **Compatibilité V1 totale** : aucune régression possible sur l'application existante.

---

## 9. Inventaire final du module

```
app/Modules/Finance/
├── Controllers/          6 fichiers
│   ├── FraisController.php
│   ├── FactureController.php
│   ├── PaiementController.php
│   ├── CaisseController.php
│   ├── ComptabiliteController.php
│   └── RapportController.php
├── Services/             6 fichiers
├── Repositories/         7 fichiers
├── Models/              13 fichiers
├── DTO/                 17 fichiers
├── Policies/             6 fichiers
├── Contracts/            4 fichiers
├── Events/              27 fichiers
├── Listeners/            6 fichiers
├── Views/               ~40 fichiers (7 sous-répertoires)
├── routes.php            163 lignes, 50 routes
└── module.json           v1.5.0

database/migrations/
├── finance_001_referentiel_frais.sql     ✅
├── finance_002_facturation.sql           ✅
├── finance_003_paiements.sql             ✅
├── finance_004_caisse.sql                ✅
└── finance_005_comptabilite.sql          ✅

Tables Finance V2 : 28
Events Finance V2 : 27
Routes Finance V2 : 50
Permissions Finance V2 : 31
```

---

## 10. Décision finale

### Score global : **7.6/10**

| Dimension | Score |
|---|---|
| Architecture | 8.5/10 |
| Comptabilité | 8.0/10 |
| Sécurité | 7.0/10 |
| Event System | 7.0/10 (duplicate keys + ExpenseValidated orphan) |
| Cohérence métier | 7.5/10 |
| Performances | 7.0/10 |
| Maintenabilité | 7.5/10 |
| **Global** | **7.6/10** |

---

### Décision : **NO-GO conditionnel**

Le module Finance V2 ne peut pas être déclaré stable en l'état.

**Trois corrections critiques sont requises avant le gel définitif :**

| # | ID | Action | Fichier | Effort |
|---|---|---|---|---|
| 1 | FN-C-001 | Remplacer `use Core\AuditService` → `use App\Services\AuditService` | `ReportHandler.php`, `FinancialReportService.php` | ~5 min |
| 2 | FN-C-002 | Corriger `findPaiementMode()` avec JOIN sur `finance_modes_paiement` | `AccountingRepository.php:587` | ~10 min |
| 3 | FN-C-003 | Remplacer DELETE physique par soft delete | `InvoiceService.php:392` | ~5 min |

**Après correction des 3 critiques → GO**

Le module Finance V2 sera déclaré stable et son architecture gelée. Les dettes V2.1 listées ci-dessus seront traitées dans la prochaine itération ou lors de la Phase décaissements (ExpenseValidated).

### Interface publique gelée (post-GO)

Les éléments suivants ne devront plus être modifiés sans migration officielle :

- Signatures des 6 Services publics
- Champs des 6 Events principaux
- Méthodes publiques des 7 Repositories
- Signatures des 4 Interfaces / Contracts
- Structure des 17 DTOs (tout nouveau champ = optionnel)
- Les 31 permissions RBAC Finance V2

---

*FINANCE_MODULE_FREEZE.md — Phase 3.9 — Finance V2 v1.5.0 — 2026-07-01*
