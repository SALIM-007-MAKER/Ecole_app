# FINANCE_INTEGRATION_REVIEW.md
## Phase 3.8 — Finance V2 System Integration Review
**Date** : 2026-07-01  
**Auditeur** : Claude Sonnet 4.6 (automatisé)  
**Version module** : 1.5.0  
**Périmètre** : 6 sous-domaines — Référentiel, Facturation, Encaissements, Caisse, Comptabilité, Rapports

---

## 1. Scores

| Dimension          | Score |
|--------------------|-------|
| Architecture       | 8.5/10 |
| Sécurité           | 7.0/10 |
| Système d'événements | 6.5/10 |
| Comptabilité       | 8.0/10 |
| Cohérence métier   | 7.5/10 |
| Performances       | 7.0/10 |
| Maintenabilité     | 7.5/10 |
| **Score global**   | **7.4/10** |

---

## 2. Problèmes critiques (bloquants)

### FN-C-001 — Namespace AuditService incorrect dans Phase 3.7
**Sévérité** : CRITIQUE  
**Fichiers** : `Listeners/ReportHandler.php`, `Services/FinancialReportService.php`  
**Symptôme** : `use Core\AuditService` — cette classe n'existe pas. Elle est à `App\Services\AuditService`.  
**Impact** : Fatal PHP error à l'instanciation de ReportHandler ou FinancialReportService. Toutes les routes `/v2/finance/rapports/*` plantent. L'AccountingHandler (Phase 3.6) qui importe correctement `App\Services\AuditService` confirme la bonne namespace.  
**Correction** : Remplacer `use Core\AuditService` par `use App\Services\AuditService` dans les deux fichiers.

### FN-C-002 — Colonne `mode_paiement` inexistante dans `finance_paiements`
**Sévérité** : CRITIQUE  
**Fichiers** : `Repositories/AccountingRepository.php:585-593`  
**Symptôme** : `findPaiementMode()` exécute `SELECT mode_paiement FROM finance_paiements` mais la table ne contient que `mode_paiement_id` (INT, FK). La colonne `mode_paiement` (string) n'existe pas.  
**Impact** : La query retourne toujours NULL. `AccountingService::enregistrerDepuisRemboursement()` ligne 68 fait `$mode = $this->repo->findPaiementMode($e->paiementId) ?? 'ESP'`. Conséquence : **tout remboursement par chèque, virement, mobile money est comptabilisé en espèces (journal CAI, compte 53xx) au lieu du journal bancaire (BNQ, compte 51xx)**. Erreur comptable silencieuse — les écritures sont créées mais affectent le mauvais journal et les mauvais comptes.  
**Correction** : La query doit faire un JOIN : `SELECT mp.code FROM finance_paiements p JOIN finance_modes_paiement mp ON mp.id = p.mode_paiement_id WHERE p.id = ?`.

### FN-C-003 — DELETE physique dans InvoiceService (violation règle fondamentale)
**Sévérité** : CRITIQUE  
**Fichier** : `Services/InvoiceService.php:392`  
**Symptôme** : `$pdo->exec("DELETE FROM finance_factures WHERE id = {$factureId}")` — suppression physique d'une facture brouillon.  
**Impact** : Viole la règle explicite « Aucun DELETE physique — Soft Delete ou archivage uniquement ». Les lignes en CASCADE (finance_lignes_facture, finance_remises) sont aussi supprimées physiquement. Impossibilité de reconstruction de l'audit en cas de litige. De plus, la construction de la requête par concaténation d'entier est acceptable en l'état mais constitue un anti-pattern SQL injection si le type change.  
**Correction** : Remplacer par `$this->repo->update($factureId, ['statut' => 'annulee'])` uniquement (la facture reste dans la DB avec statut brouillon/annulée, archivable).

---

## 3. Problèmes majeurs

### FN-M-001 — Entrées dupliquées dans config/events.php (opacité de maintenance)
**Sévérité** : MAJEURE  
**Fichier** : `config/events.php`  
**Symptôme** : Quatre événements ont deux définitions dans le tableau PHP :  
- `PaymentCompleted::class` → ligne 409 (sans AccountingHandler) puis ligne 457 (avec AccountingHandler)
- `PaymentRefunded::class` → ligne 418 puis ligne 463  
- `InvoiceCancelled::class` → ligne 390 puis ligne 468  
- `CashMovementCreated::class` → ligne 440 puis ligne 473  

PHP applique le comportement "last-key-wins" — les définitions de la ligne 409-450 sont **silencieusement écrasées**. Le résultat runtime est correct (AccountingHandler est bien appelé) mais la lisibilité est très mauvaise.  
**Risque** : Un développeur modifiant uniquement la première occurrence n'obtient aucun effet observable.  
**Correction** : Fusionner chaque paire en une seule définition avec tous les handlers. Supprimer les entrées redondantes des lignes 408-450.

### FN-M-002 — ExpenseValidated : listener wired, dispatcher absent
**Sévérité** : MAJEURE  
**Fichiers** : `Listeners/AccountingHandler.php:74-80`, `Events/ExpenseValidated.php`  
**Symptôme** : `AccountingHandler::onExpenseValidated()` et `config/events.php` mappent correctement `ExpenseValidated → AccountingHandler`. Mais aucun service existant ne dispatche jamais cet événement (la Phase décaissements n'est pas implémentée).  
**Impact** : La règle comptable `expense_validated` est seedée mais jamais activée. Les dépenses **ne génèrent aucune écriture comptable**. Le bilan est faux sur les charges.  
**Note** : C'est une dette documentée (`migrations_pending`), mais elle crée un risque fonctionnel si le module est mis en production avant la Phase décaissements.

### FN-M-003 — Double audit pour FiscalYearClosed et FiscalYearOpened
**Sévérité** : MAJEURE  
**Fichier** : `Services/AccountingService.php:263,386` + `Listeners/AccountingHandler.php:107,121`  
**Symptôme** : `AccountingService::cloturerExercice()` appelle `$this->audit->log()` directement **avant** de dispatcher `FiscalYearClosed`. `AccountingHandler::onFiscalYearClosed()` appelle aussi `$this->audit->log()`. Même pattern pour `creerExercice()`/`FiscalYearOpened`.  
**Impact** : Deux entrées d'audit pour chaque ouverture/clôture d'exercice. La piste d'audit est polluée et peut induire en erreur.  
**Correction** : Choisir une seule stratégie — soit l'audit dans le Service (avant dispatch), soit uniquement dans le Handler. Recommandation : retirer l'audit du Service, le Handler est la stratégie canonique du module.

### FN-M-004 — FraisService : injection de dépendance inconsistante
**Sévérité** : MAJEURE  
**Fichier** : `Services/FraisService.php:25`  
**Symptôme** : `FraisService` reçoit `FraisRepository` en paramètre constructeur. Tous les autres services (InvoiceService, PaymentService, CashRegisterService, AccountingService, FinancialReportService) instancient leur(s) repository(ies) en interne avec `new XxxRepository()`.  
**Impact** : `FraisController` doit connaître le Repository pour l'injecter, brisant l'encapsulation. Si un nouveau développeur suit le pattern des autres services, il oubliera le paramètre.  
**Correction** : Soit aligner FraisService sur le pattern `new FraisRepository()` en interne, soit documenter que FraisService est un cas volontaire de DI manuelle.

### FN-M-005 — RapportController : pattern d'authentification divergent
**Sévérité** : MAJEURE  
**Fichier** : `Controllers/RapportController.php`  
**Symptôme** : RapportController implémente `private function auth()` qui lit `$_SESSION['user']` et redirige vers `/login` manuellement. Les 5 autres contrôleurs Finance héritent de `Core\Controller` et utilisent `$this->currentUser()` + `$this->requirePermission()`.  
**Impact** : RapportController **n'étend pas** `Core\Controller`. Si Core\Controller gère du middleware global (CSRF, logging, headers de sécurité), ces protections ne s'appliquent pas aux routes rapports.  
**Correction** : Faire hériter RapportController de `Core\Controller` et utiliser `$this->currentUser()` + `$this->requirePermission()`.

---

## 4. Problèmes mineurs

### FN-m-001 — Window functions SQL : compatibilité MySQL 5.7
**Sévérité** : MINEURE  
**Fichier** : `Repositories/FinancialReportRepository.php:596`  
**Symptôme** : `AVG(...) OVER (ORDER BY ... ROWS BETWEEN 2 PRECEDING AND CURRENT ROW)` — fonction fenêtre SQL uniquement disponible sous MySQL 8.0+.  
**Impact** : Fatal DB error sur `GET /v2/finance/rapports/analytique` si l'environnement utilise MySQL 5.7 (courant sur WampServer ancien).  
**Correction** : Vérifier la version MySQL cible. Si MySQL 5.7 doit être supporté, remplacer par une sous-requête corrélée ou une calcul applicatif PHP.

### FN-m-002 — traiterTropPercu : accès PDO direct dans PaymentService
**Sévérité** : MINEURE  
**Fichier** : `Services/PaymentService.php:421-430`  
**Symptôme** : `$this->repo->getPdo()->prepare('SELECT * FROM finance_trop_percus WHERE id = ?')` — accès SQL direct depuis le Service, contournant le Repository.  
**Correction** : Déplacer dans PaymentRepository : `findTropPercu(int $id): ?object`.

### FN-m-003 — FinancialReportService::exportPdf() retourne data brut
**Sévérité** : MINEURE  
**Fichier** : `Services/FinancialReportService.php:148-155`  
**Symptôme** : Le PDF "export" retourne simplement les données avec `_export_pdf = true`. Le `_filename` est ignoré par RapportController dans la branche `format === 'pdf'`. La vue print.php ne reçoit pas le nom de fichier.  
**Impact** : L'utilisateur peut imprimer, mais le nom de fichier PDF suggéré au navigateur est celui de la page HTML (URL), pas le nom formaté.

### FN-m-004 — Vues rapports : absence de composant `partials/sidebar`
**Sévérité** : MINEURE  
**Fichiers** : `Views/rapports/*.php`  
**Symptôme** : `include BASE_PATH . '/app/Modules/Finance/Views/partials/sidebar.php'` — ce fichier n'a pas été créé lors de la Phase 3.7. Si la sidebar Finance V2 n'est pas partagée avec les autres sous-domaines, les vues rapports pourraient lever une erreur `include` silencieuse (selon la configuration PHP `include_errors`).  
**Correction** : Vérifier que le partial sidebar Finance V2 existe, ou adapter le chemin si la sidebar est partagée au niveau global.

### FN-m-005 — Rapport analytique : `array_column` sur stdClass array
**Sévérité** : MINEURE  
**Fichier** : `Services/FinancialReportService.php:131,144`  
**Symptôme** : `array_sum(array_column((array)$impayes, 'montant_restant'))` — `array_column` fonctionne sur des tableaux d'objets en PHP 7+, mais le cast `(array)$impayes` est redondant si `$impayes` est déjà un array. Le vrai problème est que `array_column` avec des objets `stdClass` retourne les propriétés comme clés entières si les objets sont castés en array avec préfixes privés. Le pattern sûr est `array_map(fn($i) => $i->montant_restant, $impayes)`.

### FN-m-006 — Balance : résultat de solde peut être négatif (actifs passifs)
**Sévérité** : MINEURE  
**Fichier** : `Repositories/AccountingRepository.php:522`  
**Symptôme** : `SUM(le.debit - le.credit) AS solde` — pour les comptes passifs et produits (classe 1,2,4,7), le solde normal est créditeur, donc la colonne `solde` sera négative. Les vues balance doivent gérer l'affichage (abs() + signe) ce qui n'est pas documenté.

---

## 5. Analyse du système d'événements

### 5.1 Cartographie complète

| Événement | Dispatché par | Listeners | Statut |
|---|---|---|---|
| FeeCreated/Updated/Activated/Deactivated/Archived | FraisService | FraisAuditHandler | ✅ OK |
| InvoiceCreated | InvoiceService | InvoiceHandler | ✅ OK |
| InvoiceGenerated | InvoiceService | InvoiceHandler | ✅ OK |
| InvoiceCancelled | InvoiceService | InvoiceHandler + AccountingHandler | ✅ OK (last-key-wins) |
| InvoiceUpdated | InvoiceService | InvoiceHandler | ✅ OK |
| InvoiceArchived | InvoiceService | InvoiceHandler | ✅ OK |
| PaymentInitiated | PaymentService | PaymentHandler | ✅ OK |
| PaymentCompleted | PaymentService | PaymentHandler + CashRegisterHandler + AccountingHandler | ✅ OK (last-key-wins) |
| PaymentPartial | PaymentService | PaymentHandler | ✅ OK |
| PaymentRefunded | PaymentService | PaymentHandler + AccountingHandler | ✅ OK (last-key-wins) |
| PaymentCancelled | PaymentService | PaymentHandler | ✅ OK |
| ReceiptGenerated | PaymentService | PaymentHandler | ✅ OK |
| CashRegisterOpened | CashRegisterService | CashRegisterHandler | ✅ OK |
| CashRegisterClosed | CashRegisterService | CashRegisterHandler | ✅ OK |
| CashMovementCreated | CashRegisterService | CashRegisterHandler + AccountingHandler | ✅ OK (last-key-wins) |
| CashMovementCancelled | CashRegisterService | CashRegisterHandler | ✅ OK |
| CashBalanceUpdated | CashRegisterService | CashRegisterHandler | ✅ OK |
| JournalEntryCreated | AccountingService | AccountingHandler (audit) | ✅ OK |
| FiscalYearClosed | AccountingService | AccountingHandler (audit) | ⚠️ Double audit (FN-M-003) |
| FiscalYearOpened | AccountingService | AccountingHandler (audit) | ⚠️ Double audit (FN-M-003) |
| ExpenseValidated | **PERSONNE** | AccountingHandler | ❌ Orphelin côté dispatch (FN-M-002) |
| FinancialReportGenerated | RapportController | ReportHandler | ✅ OK (si FN-C-001 corrigé) |
| FinancialReportExported | FinancialReportService | ReportHandler | ✅ OK (si FN-C-001 corrigé) |

### 5.2 Événements orphelins (dispatch sans listener)
- Aucun événement dispatché sans listener.

### 5.3 Listeners inutilisés
- `AccountingHandler::onExpenseValidated()` — wired mais jamais déclenché.

### 5.4 Dépendances circulaires
- **Risque détecté** : `CashRegisterService::crediterDepuisPaiement()` est appelé par `CashRegisterHandler::onPaymentCompleted()`, qui est lui-même un listener sur `PaymentCompleted`. `crediterDepuisPaiement()` appelle `this->enregistrerMouvement()`, qui dispatche `CashMovementCreated`. `CashMovementCreated` → `CashRegisterHandler::onMovementCreated()` (audit seulement) → OK, pas de boucle.
- **Également** : `AccountingService::creerEcriture()` dispatche `JournalEntryCreated`. `AccountingHandler::onJournalEntryCreated()` ne rappelle pas AccountingService. Pas de boucle.
- **Conclusion** : Pas de dépendance circulaire effective.

---

## 6. Analyse comptable

### 6.1 Intégrité D = C
✅ **Validée** : `AccountingService::creerEcriture()` vérifie `abs($totalDebit - $totalCredit) > 0.01` et lève `DomainException` avant tout INSERT. La tolérance 0.01 XOF est appropriée pour éviter les erreurs de virgule flottante.

### 6.2 Immuabilité des écritures
✅ **Correcte** : Seule `updateEcriture()` est appelée depuis `extourner()` pour passer le statut de `valide` à `extourne`. Aucune modification de montants. Les lignes (`finance_lignes_ecriture`) ne sont jamais mises à jour.

### 6.3 Extourne
✅ **Correctement implémentée** :
- Vérification statut `valide` avant extourne.
- Inversion débit/crédit sur toutes les lignes.
- Écriture originale marquée `extourne`.
- Nouvelle écriture créée avec source `extourne`.
- Audit traçant les deux ids.

### 6.4 Séquencement des numéros ECR
✅ **Thread-safe** : `INSERT ... ON DUPLICATE KEY UPDATE valeur = valeur + 1` puis `SELECT valeur` — atomique sous InnoDB.
⚠️ **Race condition théorique** : Entre le `ON DUPLICATE KEY UPDATE` et le `SELECT valeur` dans `genererNumero()`, une autre transaction pourrait incrémenter à nouveau. Le pattern correct est `LAST_INSERT_ID(valeur + 1)` dans le UPDATE, puis `SELECT LAST_INSERT_ID()`. À faible charge (contexte scolaire), le risque est acceptable.

### 6.5 Clôture exercice
✅ **Logique correcte** :
- Vérifie que toutes les périodes sont clôturées (`nb_periodes_ouvertes === 0`).
- Rouverture temporaire de la dernière période pour l'écriture de clôture.
- Fermeture immédiate après.
- Résultat net → compte 1200.

⚠️ **Cas limite** : Si l'exercice n'a aucune écriture (toutes les classes 6 et 7 à zéro), `$lignes` sera vide et aucune écriture de clôture n'est créée. L'exercice passe quand même en `cloture`. C'est techniquement correct mais ne documente pas une clôture à solde nul.

### 6.6 Anti-doublon comptable
✅ **Correctement implémenté** : `findEcritureByReference(reference, source)` appelé avant chaque `creerEcriture()` dans toutes les méthodes de `AccountingService`. Protège contre les rejeux d'événements.

---

## 7. Analyse du flux métier complet

```
Référentiel des frais
↓ FraisService → FeeCreated (audit)
Facturation
↓ InvoiceService → InvoiceCreated → brouillon → émission → InvoiceUpdated
Paiement
↓ PaymentService → PaymentCompleted (= 3 handlers)
  ├─ PaymentHandler → audit + reçu
  ├─ CashRegisterHandler → crédite caisse session active
  └─ AccountingHandler → écriture CAI/BNQ (VTE 7012 → 53/51)
Caisse
↓ CashRegisterService → journal session → rapprochement
Comptabilité
↓ AccountingRepository → grand livre, balance, extourne, clôture
Rapports
↓ FinancialReportService → dashboard, paiements, factures, impayés, caisse, analytique
```

**Flux testé mentalement — paiement espèces 50 000 XOF :**
1. `PaymentService::enregistrer()` → INSERT finance_paiements, montant_applique=50000, statut='complete'
2. Facture mise à jour : montant_paye += 50000, statut → 'payee'
3. Reçu créé
4. `PaymentCompleted` dispatché : `{modePaiement: 'ESP', montantApplique: 50000}`
5. CashRegisterHandler → crédite session active (+50000)
6. AccountingHandler → `enregistrerDepuisPaiement()`  
   - resoudreReglePaiement('ESP') → règle `payment_esp` (journal=CAI, debit=531, credit=7012)
   - Vérifie anti-doublon OK
   - creerEcriture → D=C → INSERT écriture + 2 lignes → ✅

**Flux remboursement (bug FN-C-002) :**
1. Remboursement par virement (code='VIR')
2. `PaymentRefunded` dispatché
3. AccountingHandler → `enregistrerDepuisRemboursement()`
4. `findPaiementMode(paiementId)` → retourne **NULL** (colonne inexistante)
5. `$mode = NULL ?? 'ESP'` → mode forcé à ESP
6. Règle `refund_esp` utilisée au lieu de `refund_bnq`
7. Écriture créée sur le journal CAI (caisse) au lieu de BNQ — **ERREUR COMPTABLE SILENCIEUSE**

---

## 8. Analyse sécurité

| Point de contrôle | Statut | Note |
|---|---|---|
| Authentification session | ✅ | `currentUser()` dans tous les controllers sauf RapportController |
| Autorisation RBAC | ✅ | Policy cohérente pour chaque sous-domaine |
| Validation DTO | ✅ | Tous les DTOs ont `validate()` appelé avant usage |
| Prepared statements | ✅ | PDO paramétré partout, sauf `genererNumero()` (entiers hardcodés, acceptable) |
| DELETE physique | ❌ | InvoiceService::supprimer() — FN-C-003 |
| CSRF | ⚠️ | Non visible dans Finance module — dépend du Core\Controller middleware |
| Données sensibles en audit | ✅ | AuditService::sanitize() filtre les clés sensibles |
| XSS en vues | ✅ | `htmlspecialchars()` systématiquement appliqué sur les outputs utilisateur |
| Soft delete | ✅ | deleted_at dans finance_sessions_caisse, statuts archivage ailleurs |
| Injection SQL | ✅ | Sauf `genererNumero()` (entiers controllés) et `getBalance()` (HAVING sans param) |

**Permissions définies :** 31 permissions dans module.json — granularité appropriée.
**Incohérence** : module.json liste `finance.rapports.view/.export` mais les policies et le code utilisent `finance.reports.view/.export/.print` (sans 's' → `reports` vs `rapports`). À unifier.

---

## 9. Analyse performances

### 9.1 Requêtes N+1 détectées
- **Aucun N+1 confirmé** dans les Services. Toutes les listings utilisent des JOINs en une seule requête.

### 9.2 Requêtes coûteuses identifiées

| Requête | Fichier | Risque |
|---|---|---|
| `getBalance(exerciceId)` | AccountingRepository | Full scan finance_lignes_ecriture avec GROUP BY + HAVING. Critique avec >100k lignes |
| `getGrandLivre(filters)` | AccountingRepository | Multi-JOIN sur 4 tables sans filtre exercice obligatoire. Sans exercice_id, full scan |
| `getDashboardStats(date)` | FinancialReportRepository | 7 CASE aggregations sur finance_paiements sans partition |
| `getProjectionMensuelle` | FinancialReportRepository | Window function + sous-requête imbriquée — MySQL 8.0 requis |

### 9.3 Index manquants identifiés

| Table | Colonne manquante | Requête impactée |
|---|---|---|
| `finance_ecritures` | `(reference, source)` | findEcritureByReference() — appelé à chaque paiement |
| `finance_ecritures` | `(exercice_id, statut)` | Toutes les requêtes comptables |
| `finance_lignes_ecriture` | `(compte_id, ecriture_id)` | getBalance, getGrandLivre |
| `finance_paiements` | `(date_paiement, statut)` | getDashboardStats, getEvolutionMensuelle |
| `finance_factures` | `(montant_total, montant_paye, statut)` | getImpayes, getStatsFactures |

### 9.4 Optimisations recommandées
- `getBalance()` : ajouter un index composé `(exercice_id, statut)` sur finance_ecritures
- `findEcritureByReference()` : ajouter `UNIQUE KEY (reference, source, statut)` ou au moins un index composé
- Grand Livre sans exercice_id : forcer exercice_id ou limiter à 12 derniers mois

---

## 10. Compatibilité V1

| Point | Statut |
|---|---|
| Routes V1 préservées | ✅ — préfixe /v2/finance/ sans overlap |
| Tables V1 intactes | ✅ — aucun DROP TABLE, aucune modification de schéma V1 |
| Rollback possible | ✅ — désactiver `enabled: false` dans module.json suffit |
| Données historiques | ✅ — les tables V2 sont addatives |
| Coexistence | ✅ — les deux modules accèdent à `eleves`, `classes` sans conflit |

---

## 11. Dette technique

### Critique (blocage production)
| ID | Description |
|---|---|
| FN-C-001 | `Core\AuditService` → `App\Services\AuditService` dans ReportHandler + FinancialReportService |
| FN-C-002 | `findPaiementMode()` : colonne inexistante, remboursements mal journalisés |
| FN-C-003 | DELETE physique dans InvoiceService::supprimer() |

### Majeure (correction avant freeze)
| ID | Description |
|---|---|
| FN-M-001 | Fusionner les entrées dupliquées dans config/events.php |
| FN-M-002 | ExpenseValidated : documenter officiellement comme Phase décaissements requise |
| FN-M-003 | Audit double sur ouverture/clôture exercice |
| FN-M-004 | FraisService : aligner le pattern DI avec les autres services |
| FN-M-005 | RapportController : hériter de Core\Controller |

### Mineure (optimisation)
| ID | Description |
|---|---|
| FN-m-001 | Window functions MySQL 8.0+ : vérifier la version cible |
| FN-m-002 | traiterTropPercu : accès PDO direct à déplacer en Repository |
| FN-m-003 | PDF export : nom de fichier non transmis à la vue |
| FN-m-004 | Vérifier existence de partials/sidebar Finance V2 |
| FN-m-005 | array_column sur stdClass : utiliser array_map pour robustesse |
| FN-m-006 | Balance : documenter convention signe solde pour les comptes passifs |
| FN-m-007 | Permissions : unifier `finance.rapports.*` vs `finance.reports.*` (module.json vs code) |
| FN-m-008 | genererNumero : race condition théorique — utiliser LAST_INSERT_ID() |

---

## 12. Synthèse — Points forts

1. **Architecture hexagonale stricte** : zéro SQL dans les Services, zéro logique métier dans les Controllers. Repository pattern rigoureusement respecté.
2. **Immutabilité comptable** : D=C validé applicativement, extourne comme seul mécanisme de correction, aucun UPDATE de montants.
3. **Event-driven correct** : les écritures comptables ne sont jamais créées depuis les Controllers. Toutes passent par AccountingHandler → AccountingService.
4. **Audit complet** : AuditService appelé sur toutes les mutations significatives dans les 6 sous-domaines.
5. **Anti-doublon comptable** : findEcritureByReference() protège contre les rejeux d'événements.
6. **Transactions PDO** : tous les flux multi-table sont enveloppés dans beginTransaction/commit/rollBack.
7. **Soft delete généralisé** : seul InvoiceService::supprimer() fait exception (FN-C-003).
8. **RBAC granulaire** : 31 permissions couvrent tous les cas d'usage avec Policy cohérente.
9. **DTO validation** : validate() systématiquement appelé avant traitement dans les Services.
10. **Compatibilité V1 totale** : zéro régression sur l'application existante.

---

## 13. Décision GO / NO-GO

### Décision : **NO-GO conditionnel**

Le module Finance V2 ne peut pas être gelé en l'état. Trois problèmes critiques doivent être résolus :

| Priorité | ID | Action requise | Estimation |
|---|---|---|---|
| 1 | FN-C-001 | Corriger namespace AuditService dans 2 fichiers | 5 min |
| 2 | FN-C-002 | Corriger findPaiementMode() — JOIN finance_modes_paiement | 10 min |
| 3 | FN-C-003 | Remplacer DELETE physique par update statut dans InvoiceService | 5 min |

**Après correction des 3 critiques :** GO conditionnel avec traitement des majeures avant mise en production.

### Conditions post-GO
Les problèmes majeurs (FN-M-001 à FN-M-005) doivent être traités avant la mise en production réelle mais ne bloquent pas le gel technique de la V2.

---

## 14. Checklist de validation post-correction

```
[ ] FN-C-001 — use App\Services\AuditService dans ReportHandler et FinancialReportService
[ ] FN-C-002 — findPaiementMode() retourne mp.code via JOIN
[ ] FN-C-003 — supprimer() ne fait plus de DELETE physique
[ ] FN-M-001 — config/events.php : définitions fusionnées (4 événements)
[ ] FN-M-002 — ExpenseValidated marqué [PENDING: Phase décaissements] dans module.json
[ ] FN-M-003 — Double audit retiré de AccountingService (gardé uniquement dans AccountingHandler)
[ ] FN-M-004 — FraisService aligné sur pattern DI interne
[ ] FN-M-005 — RapportController extends Core\Controller
[ ] FN-m-001 — Version MySQL vérifiée ou fallback analytique implémenté
[ ] FN-m-007 — Permissions unifiées finance.reports.* dans module.json
```

---

*Rapport produit le 2026-07-01 — Finance V2 module v1.5.0*  
*Audit automatisé sur 90+ fichiers PHP, 5 migrations SQL, 27 événements*
