# MILESTONE_2_REVIEW.md
## Phase 4.0 — Milestone 2 Architecture Review
**Date** : 2026-07-01  
**Auditeur** : Claude Sonnet 4.6  
**Périmètre** : Core, Shared Services, Event System, RBAC, Scolarité V2, Académique V2, Finance V2  
**Rapports précédents** : SCOLARITE_MODULE_FREEZE.md · ACADEMIQUE_MODULE_FREEZE.md · FINANCE_MODULE_FREEZE.md

---

## Scores globaux

| Dimension | Score |
|---|---|
| Architecture générale | 7.5/10 |
| Intégration inter-modules | 8.0/10 |
| Système d'événements | 7.0/10 |
| Shared Services | 6.5/10 |
| Performances | 6.5/10 |
| Sécurité | 7.5/10 |
| Base de données | 7.0/10 |
| Compatibilité V1 | 9.0/10 |
| Préparation Phase 5 | 7.0/10 |
| **Score global Milestone 2** | **7.4/10** |

---

## 1. Architecture générale

### 1.1 Découpage des couches

```
SCOLARIS V2 — Stack applicatif
├── core/              (14 fichiers) — Framework maison PHP 8.2
│   ├── Application    Singleton bootstrap, chargement routes+events
│   ├── Router         Regex-based, matching séquentiel, no middleware
│   ├── Controller     Base class : auth, CSRF, RBAC, headers, render
│   ├── Database       Singleton PDO (connexion partagée)
│   ├── Session        CSRF tokens, session regeneration 5 min
│   ├── View           Module::path notation, layout system
│   ├── EventDispatcher Static, synchrone, per-listener try/catch
│   ├── Model          Active Record générique
│   ├── Request        Wrapper HTTP
│   ├── Logger         Fichiers journaliers + security log séparé
│   ├── Event          Classe de base événements
│   └── Listener       Interface listeners
│
├── app/Controllers/   V1 controllers (coexistants)
├── app/Models/        V1 models (partiellement utilisés par V2 services)
├── app/Services/      Shared Services (AuditService, NotificationService, UploadService)
│
└── app/Modules/       V2 modules
    ├── Scolarite/     (enabled: false) — 6 sous-domaines
    ├── Academique/    (enabled: false) — 6 sous-domaines
    └── Finance/       (enabled: true)  — 6 sous-domaines
```

### 1.2 Conformité aux responsabilités déclarées

| Couche | Responsabilité attendue | Respect | Écarts |
|---|---|---|---|
| Controller | Validation entrée, appel Service, rendu vue | ✅ | RapportController (Finance) n'étend pas Core\Controller |
| Service | Logique métier, dispatch Events | ✅ | InvoiceService::supprimer() accède PDO directement (FN-C-003) |
| Repository | Toutes les requêtes SQL | ✅ | PaymentService::traiterTropPercu() accède PDO directement (FN-m-002) |
| DTO | Validation et transfert des données | ✅ | — |
| Policy | Vérification permissions | ✅ | — |
| Event | Transfert de données event-driven | ✅ | — |
| Listener | Effets de bord (audit, compta, cache) | ✅ | Double audit sur FiscalYear (FN-M-003) |

### 1.3 Dépendances inter-couches

```
HTTP → Router → Controller
                └── Service → Repository (SQL)
                    └── EventDispatcher → Handler
                                         ├── AuditService
                                         ├── AccountingService → AccountingRepository
                                         └── CashRegisterService → CashRegisterRepository
```

**Dépendances inter-modules (lecture seule, non circulaires) :**

| Module source | Dépend de | Type | Risque |
|---|---|---|---|
| Finance | `eleves` (JOIN) | Lecture seule | Faible |
| Finance | `classes` (JOIN) | Lecture seule | Faible |
| Finance | `users` (SESSION) | Implicite | Nul |
| Académique | `eleves`, `classes`, `matieres` | Lecture seule | Faible |
| Scolarité | `eleves`, `classes`, `matieres` (ALTER) | DDL + DML | Maîtrisé |

**Aucune dépendance circulaire détectée.**

### 1.4 Couplages et violations SOLID détectés

#### MS2-C-001 — Pas de pipeline middleware dans le Router *(CRITIQUE)*

`core/Router.php` appelle directement le controller sans passer par un middleware stack. L'authentification, la vérification CSRF et les guards RBAC sont **opt-in** : ils ne s'appliquent qu'aux controllers qui appellent explicitement `requireAuth()`, `verifyCsrf()`, `requirePermission()`.  
**Risque** : un nouveau controller oubliant d'appeler `requireAuth()` est publicly accessible sans authentification.  
**Impact Phase 5** : avec 8 nouveaux modules, le risque de routes non protégées augmente proportionnellement.  
**Recommandation** : implémenter un middleware obligatoire au niveau du Router (route groups avec garde `auth`).

#### MS2-C-002 — Dual RBAC system : ambiguïté critique *(CRITIQUE)*

Deux systèmes de permissions coexistent :
1. `config/permissions.php` — tableaux plats par rôle, chargés en session à la connexion
2. Tables RBAC V2 (`roles`, `permissions`, `role_permissions`, `user_roles`) — créées par migrations R001-R006

`Core\Controller::can()` lit `$user['permissions']` depuis la session — chargée depuis `config/permissions.php` **ou** depuis les tables RBAC V2 selon l'implémentation du AuthController.

**Incohérence détectée dans `config/permissions.php`** :
- Ligne 33 : `'finance.rapports.view', 'finance.rapports.export'` (préfixe `rapports`)
- Code Finance et module.json : `'finance.reports.view', 'finance.reports.export', 'finance.reports.print'` (préfixe `reports`)

Si `config/permissions.php` est encore authoritative, les permissions Finance rapports sont **systématiquement refusées** pour tous les rôles (mismatch de nommage).

**Recommandation** : décider d'une source unique. Si les tables RBAC V2 sont la cible, retirer `config/permissions.php` progressivement et s'assurer que le AuthController charge depuis les tables.

#### MS2-C-003 — Modules Scolarité et Académique désactivés en config *(MAJEURE)*

`config/modules.php` : `scolarite.enabled = false`, `academique.enabled = false`.  
Leurs routes ne sont pas chargées. Leurs event listeners sont enregistrés dans `config/events.php` mais leurs controllers sont inaccessibles.  
**Implication** : les 338 tests Académique et la suite Scolarité opèrent en isolation mais ne peuvent pas être testés en intégration HTTP.  
**Recommandation** : activer les 3 modules en `enabled: true` simultanément et valider l'absence de conflit de routes avant Phase 5.

#### MS2-C-004 — Logger synchrone en file_put_contents *(MINEURE)*

`core/Logger.php` utilise `file_put_contents($file, $entry, FILE_APPEND | LOCK_EX)`. Sous charge, la contention du verrou sur le fichier journalier peut créer des latences. Pour un contexte scolaire avec <100 utilisateurs simultanés, c'est acceptable. Pour Phase 5 (portails parents + élèves), prévoir un buffer ou un log async.

#### MS2-C-005 — Model::delete() permet les suppressions physiques *(MINEURE)*

`core/Model.php` expose `delete(int $id): bool` qui exécute un DELETE physique. La politique V2 est « soft delete uniquement ». Les Repositories V2 ne l'utilisent pas, mais tout Controller V1 ou tout nouveau développeur peut appeler `$model->delete()` sans violation immédiatement visible.  
**Recommandation** : marquer la méthode `@deprecated` ou la supprimer du Model de base.

### 1.5 Duplications détectées

| Pattern dupliqué | Occurrence | Recommandation |
|---|---|---|
| Instanciation `new Repository()` dans chaque Service | 6 Services Finance, n Académique, n Scolarité | Acceptable (pattern délibéré) |
| `$this->audit->log()` dans Service + Handler | AccountingService (FN-M-003) | Retirer du Service |
| Validation DTO appelée dans Service + parfois re-validée dans Controller | Quelques controllers V1 | Tolérable, non bloquant |
| `Session::getUser()` / `$_SESSION['user']` | RapportController vs autres | Uniformiser via `currentUser()` |

---

## 2. Intégration inter-modules

### 2.1 Flux de données Scolarité → Académique → Finance

```
Scolarité V2
├── EleveService       → crée/inscrit l'élève
├── ClasseService      → affecte l'élève à une classe
└── InscriptionService → génère InscriptionCreated

            ↓ (données partagées via tables eleves/classes)

Académique V2
├── PeriodeScolaireService → définit les périodes de notation
├── EvaluationService      → crée les évaluations par classe+matière
├── NoteService            → saisit les notes par évaluation
├── MoyenneService         → calcule automatiquement les moyennes
├── RankingEngine          → génère le classement
└── BulletinGenerator      → produit le bulletin PDF

            ↓ (données partagées via tables eleves/classes)

Finance V2
├── FraisService     → définit les frais par niveau
├── InvoiceService   → génère les factures par élève
├── PaymentService   → encaisse les paiements
└── AccountingService→ journalise les écritures comptables
```

### 2.2 Points d'intégration vérifiés

| Intégration | Mécanisme | Statut |
|---|---|---|
| Scolarité → Académique | Tables partagées `eleves`, `classes`, `matieres` (lecture) | ✅ OK |
| Scolarité → Finance | Tables partagées `eleves`, `classes` (lecture) | ✅ OK |
| Académique → Finance | Aucune dépendance directe | ✅ OK |
| Finance → NotificationService | Non connecté | ❌ Gap — PaymentCompleted ne notifie pas les parents |
| Scolarité → Finance (inscription → facturation) | Aucune liaison automatique | ⚠️ Manuel — l'inscription ne déclenche pas la génération de facture |
| Académique → Notification | `NoteAjoutee` V1 → NotificationHandler | ✅ V1 uniquement |

### 2.3 Gap d'intégration majeur — Inscription → Facture

Quand un élève est inscrit (`InscriptionCreated` dispatché), aucun listener ne déclenche la génération automatique de facture dans Finance V2. La création de facture reste manuelle via `InvoiceService::genererMasse()`.

**Impact** : pour Phase 5, si une Vie Scolaire déclenche une inscription, les finances doivent être alertées automatiquement.  
**Recommandation** : brancher un listener Finance sur `InscriptionCreated` pour proposer la génération de facture.

### 2.4 Gap d'intégration majeur — PaymentCompleted → NotificationService

`PaymentCompleted` est traité par PaymentHandler (audit), CashRegisterHandler (caisse), AccountingHandler (comptabilité). Personne ne notifie le parent que le paiement a été reçu.  
**Recommandation** : ajouter `NotificationHandler` sur `PaymentCompleted` avec `notifSvc->onPaiement(eleveId, montant)`.

---

## 3. Système d'événements

### 3.1 Cartographie complète (67 événements enregistrés)

| Catégorie | Nb événements | Nb listeners | Statut |
|---|---|---|---|
| V1 legacy (EleveCreated, PaiementValide…) | 7 | 2-3 / event | ✅ Fonctionnel |
| Scolarité V2 (Eleve, Classe, Inscription, Famille, Matiere) | 20 | 1-2 / event | ✅ Fonctionnel |
| Académique V2 (Periode, Evaluation, Note, Moyenne, Ranking, Bulletin, Analytics) | 23 | 1 / event | ✅ Fonctionnel |
| Finance V2 (Frais, Facture, Paiement, Caisse, Comptabilité, Rapport) | 27 | 1-3 / event | ✅ Fonctionnel (4 clés dupliquées) |
| **Total** | **~67** | — | **7 issues mineures** |

### 3.2 Événements orphelins (dispatch sans listener)
Aucun événement dispatché sans listener. ✅

### 3.3 Listeners sans dispatcher
- `AccountingHandler::onExpenseValidated()` — connecté, jamais déclenché (Phase décaissements pending) ⚠️

### 3.4 Dépendances et boucles
Analyse des chaînes de dispatch :

```
PaymentCompleted → [PaymentHandler, CashRegisterHandler, AccountingHandler]
  CashRegisterHandler → crediterDepuisPaiement() → enregistrerMouvement()
    → CashMovementCreated → [CashRegisterHandler (audit), AccountingHandler]
      AccountingHandler → enregistrerDepuisMouvementCaisse()
        source IN ('paiement','systeme') → SKIP
```
**Pas de boucle.** Le garde `source IN ('paiement','systeme')` empêche la ré-entrée. ✅

```
creerEcriture() → JournalEntryCreated → AccountingHandler::onJournalEntryCreated()
  → audit uniquement (pas de re-dispatch)
```
**Pas de boucle.** ✅

### 3.5 Problèmes détectés

| ID | Problème | Sévérité |
|---|---|---|
| ES-001 | 4 paires de clés dupliquées dans config/events.php (PHP last-key-wins, résultat correct mais opaque) | Majeure |
| ES-002 | ExpenseValidated listener wired, jamais dispatché (Phase décaissements absente) | Majeure |
| ES-003 | Dispatch synchrone : PaymentCompleted → 3 handlers s'exécutent dans le thread HTTP | Mineure (contexte scolaire) |
| ES-004 | FiscalYearClosed : audit double (Service + Handler) | Mineure |
| ES-005 | NotificationHandler absent sur PaymentCompleted, InvoiceCreated, BulletinPublished | Majeure |
| ES-006 | InscriptionCreated non écouté par Finance pour la génération automatique de facture | Majeure |

### 3.6 Ordre d'exécution des listeners

L'ordre d'appel des handlers est déterminé par l'ordre dans les tableaux `config/events.php`. **C'est documenté nulle part.** Un développeur ajoutant un handler sans connaître les dépendances d'ordre risque de créer une régression silencieuse.  
**Recommandation** : commenter l'ordre et les dépendances d'exécution pour les événements multi-handlers (PaymentCompleted, InvoiceCancelled, CashMovementCreated).

---

## 4. Shared Services

### 4.1 AuditService — `App\Services\AuditService`

| Critère | Statut |
|---|---|
| Namespace correct | ✅ `App\Services\AuditService` |
| Table `audit_logs` | ✅ Migrée (S001) |
| Méthodes publiques | ✅ `log()`, `logCreate()`, `logUpdate()`, `logDelete()`, `logLogin()`, `logPermissionDenied()` |
| Sanitize clés sensibles | ✅ password, token, api_key… |
| Diff avant/après | ✅ — évite les logs vides |
| Dégradation silencieuse | ✅ — try/catch sur INSERT |
| IP X-Forwarded-For | ✅ — respect des proxies |
| Utilisé par V2 modules | ✅ Finance (10/12 fichiers), Académique, Scolarité |
| Bug FN-C-001 | ⚠️ 2 fichiers Finance utilisent `Core\AuditService` (inexistant) |

**Score AuditService : 9/10** — Le service le plus mature de la plateforme.

### 4.2 NotificationService — `App\Services\NotificationService`

| Critère | Statut |
|---|---|
| Canal interne | ✅ |
| Canal email | ✅ (via EmailService → mail()) |
| Canal SMS | ✅ (via SmsService) |
| Préférences par trigger | ✅ |
| Dépendances | ⚠️ `EleveModel`, `UserModel` — modèles V1 |
| Intégration V2 events | ❌ Aucun — NotificationHandler écoute uniquement `NoteAjoutee`, `AbsenceCreee`, `PaiementValide` (V1) |
| Finance V2 (PaymentCompleted) | ❌ Non connecté |
| Académique V2 (BulletinPublished) | ❌ Non connecté |
| Scolarité V2 | ❌ Non connecté |

**Gap critique** : les triggers métier V2 ne passent pas par NotificationService. Les parents ne reçoivent aucune notification des paiements Finance V2 ni des bulletins Académique V2.

**Score NotificationService : 6/10** — Fonctionnel pour V1, non intégré à V2.

### 4.3 UploadService — `App\Services\UploadService`

| Critère | Statut |
|---|---|
| Validation MIME réelle | ✅ `mime_content_type()` — résiste au spoofing |
| Path traversal protection | ✅ — vérification préfixe `storage/` |
| Redimensionnement GD | ✅ — si extension disponible |
| Types supportés | ✅ avatar, photo_eleve, photo_professeur, logo, justification, import_csv |
| URL sécurisée | ✅ `/uploads/serve/{type}/{filename}` |
| Intégration V2 modules | ❌ Finance, Académique, Scolarité ne l'utilisent pas |
| Type `justificatif_comptable` | ❌ Absent (Finance a des pièces justificatives) |
| Type `document_eleve` | ❌ Absent (Phase 5 Documents) |

**Score UploadService : 7.5/10** — Bien conçu, sous-utilisé par V2.

### 4.4 Services manquants (Phase 5 requis)

| Service | Usage prévu | Priorité |
|---|---|---|
| `PdfService` | Bulletins, factures, rapports — actuellement HTML print + CSS @media | Haute |
| `ExportService` | CSV/Excel — actuellement inline dans FinancialReportService | Haute |
| `SearchService` | Recherche globale multi-modules | Moyenne |
| `StatisticsService` | Agrégations inter-modules (Académique + Finance) | Moyenne |

**PdfService** est le plus urgent : 3 modules (Académique, Finance, Scolarité) gèrent leurs propres exports PDF de façon incohérente. Un service centralisé TCPDF/DomPDF permettrait des PDF serveur réels (vs CSS print actuel).

---

## 5. Performances

### 5.1 N+1 détectés

| Module | Localisation | Requête N+1 |
|---|---|---|
| NotificationService | `notifyBulk()` | `notify()` appelé N fois → N×`userModel->findById()` |
| NotificationService | `onAnnonce()` | `findAllWithRoles()` puis N×`notify()` | 
| V1 Controllers | Plusieurs listings | `findById()` dans boucle foreach (héritage V1) |

**Modules V2 (Finance, Académique, Scolarité) : aucun N+1 confirmé.** Tous les listings utilisent des JOINs.

### 5.2 Routing linéaire

`core/Router.php` fait un `foreach ($this->routes)` linéaire avec `preg_match()` à chaque itération. Avec 50 routes Finance + ~50 routes Scolarité + ~70 routes Académique + ~100 routes V1 = ~270 routes → ~270 preg_match() par requête.  
**Estimation** : < 2 ms sur 270 routes. Acceptable pour ce contexte.  
**Phase 5** : avec +8 modules (~80-120 routes supplémentaires), envisager un trie par premier segment (`/v2/finance/*` vs `/v2/scolarite/*`) avant le matching.

### 5.3 Logger synchrone

`Logger::write()` appelle `file_put_contents($file, $entry, FILE_APPEND | LOCK_EX)` de façon synchrone. Chaque requête avec errors/security events bloque sur le verrou de fichier. Tolérable sur un seul serveur, problématique avec load balancing.

### 5.4 EventDispatcher synchrone

La chaîne `PaymentCompleted → [PaymentHandler + CashRegisterHandler + AccountingHandler]` exécute 3 handlers + 3 INSERT SQL + 1 SELECT avant de renvoyer la réponse HTTP. Sur un paiement normal :
- PaymentHandler : 1 INSERT audit_logs + 1 UPDATE finance_recus
- CashRegisterHandler : 1 INSERT finance_mouvements_caisse + 1 UPDATE session
- AccountingHandler : 1 SELECT (règle comptable) + 1 INSERT finance_ecritures + 2 INSERT finance_lignes_ecriture + 1 INSERT audit_logs

**~9 opérations SQL supplémentaires dans le thread HTTP** post-paiement. Pour un contexte scolaire, c'est acceptable. Pour un déploiement multi-établissements, une queue async serait nécessaire.

### 5.5 Index manquants identifiés (cross-module)

| Table | Colonnes manquantes | Impact |
|---|---|---|
| `audit_logs` | `(module, action, created_at)` | Recherche AuditService lente sur grands volumes |
| `finance_ecritures` | `(exercice_id, statut)` composite | getBalance(), getGrandLivre() |
| `finance_paiements` | `(date_paiement, statut)` composite | getDashboardStats() |
| `finance_lignes_ecriture` | `(compte_id, ecriture_id)` composite | getBalance() avec filtre compte |

### 5.6 Absence de cache applicatif

Aucune couche de cache (APCu, Redis, Memcached, fichier JSON) dans l'application. Les requêtes agrégées (dashboard Finance, analytique Académique) sont recalculées à chaque accès.  
**Recommandation Phase 5** : introduire un cache TTL court (5 min) pour les KPIs dashboard, basé sur un fichier JSON horodaté ou APCu (disponible sur WampServer).

---

## 6. Sécurité

### 6.1 RBAC

| Point de contrôle | Statut | Note |
|---|---|---|
| Vérification permissions | ✅ | `requirePermission()` dans tous les controllers V2 |
| Policies par module | ✅ | 6 policies Finance, n Scolarité, n Académique |
| Source permissions | ⚠️ | Dual system — `config/permissions.php` vs tables RBAC V2 |
| Nommage permissions Finance | ⚠️ | `finance.rapports.*` dans config vs `finance.reports.*` dans code (MS2-C-002) |
| RapportController | ❌ | N'étend pas Core\Controller — pas de `requirePermission()` obligatoire |
| Permission `finance.reports.print` | ⚠️ | Absente de `config/permissions.php` |

### 6.2 CSRF

| Point de contrôle | Statut |
|---|---|
| Token génération | ✅ `bin2hex(random_bytes(32))` — cryptographiquement fort |
| Comparaison timing-safe | ✅ `hash_equals()` |
| Vérification dans Core\Controller | ✅ `verifyCsrf()` disponible mais opt-in |
| Couverture Finance controllers | ⚠️ — Non vérifiable sans lire tous les controllers POST |
| RapportController | ❌ — Hors Core\Controller, pas de CSRF |

### 6.3 Sessions

| Point de contrôle | Statut |
|---|---|
| HttpOnly cookie | ✅ |
| SameSite: Strict | ✅ |
| Régénération ID | ✅ Toutes les 300 secondes |
| Scope path limité à l'app | ✅ |
| Session fixation | ✅ `session_regenerate_id(true)` au login |

### 6.4 Headers de sécurité (Core\Controller)

| Header | Valeur | Statut |
|---|---|---|
| X-Frame-Options | SAMEORIGIN | ✅ |
| X-Content-Type-Options | nosniff | ✅ |
| Referrer-Policy | strict-origin-when-cross-origin | ✅ |
| Content-Security-Policy | self + CDN Tailwind/Lucide | ✅ |
| HSTS | Absent | ⚠️ Recommandé en HTTPS |
| Permissions-Policy | Absent | Mineure |

**Remarque** : la CSP autorise `'unsafe-inline'` pour scripts et styles (nécessaire pour les onclick/style Tailwind). Acceptable pour une app en intranet.

### 6.5 Validation et injection

| Vecteur | Statut |
|---|---|
| SQL injection | ✅ PDO paramétré partout dans V2 (sauf FN-C-003 — DELETE physique) |
| XSS | ✅ `htmlspecialchars()` systématique dans les vues V2 |
| Path traversal (upload) | ✅ UploadService vérifie le préfixe |
| File inclusion | ✅ View::render() vérifie l'existence du fichier |
| MIME spoofing | ✅ UploadService utilise `mime_content_type()` sur le fichier temporaire |

### 6.6 Audit de sécurité

| Événement audité | Statut |
|---|---|
| Connexion (succès/échec) | ✅ `AuditService::logLogin()` |
| Permission refusée | ✅ `AuditService::logPermissionDenied()` + `Logger::security()` |
| CSRF invalide | ✅ `Logger::security('CSRF_INVALID')` |
| Mutations Finance V2 | ✅ (hors FN-C-001) |
| Mutations Académique V2 | ✅ |
| Mutations Scolarité V2 | ✅ |

---

## 7. Base de données

### 7.1 Vue d'ensemble

| Catégorie | Tables | Migrations |
|---|---|---|
| Core V1 (users, eleves, classes…) | ~15-20 | Schéma initial (non versionné dans /database/migrations/) |
| RBAC V2 | 4 (roles, permissions, role_permissions, user_roles) | R001-R006 (RBAC_V2.md) |
| Shared (audit_logs) | 1 | S001-S002 (SHARED_SERVICES.md) |
| Finance V2 | 28 | 5 migrations versionnées ✅ |
| Scolarité V2 | ~8 | Non présentes dans /database/migrations/ |
| Académique V2 | ~13 | Non présentes dans /database/migrations/ |
| **Total estimé** | **~70 tables** | **5 sur ~25 versionnées** |

**Point critique : seules les 5 migrations Finance sont présentes dans `/database/migrations/`**. Les migrations Scolarité, Académique, RBAC, Shared Services ont été appliquées manuellement ou sont dans d'autres fichiers. Sans versionnement complet, le déploiement sur un nouveau serveur est impossible sans intervention manuelle.

### 7.2 Normalisation

| Domaine | Niveau | Observation |
|---|---|---|
| Finance V2 | 3NF | Bien normalisé. `finance_regles_comptables` centralise les règles de journalisation |
| Académique V2 | 3NF | Bien structuré avec périodes/évaluations/notes séparées |
| Scolarité V2 | 2NF-3NF | `niveau` dans `classes` est string direct (non-FK) — délibéré |
| V1 (users, eleves) | 2NF | Quelques colonnes redondantes héritées |

### 7.3 Contraintes et FK

| Vérification | Statut |
|---|---|
| FK CASCADE/RESTRICT explicites | ✅ Finance V2 — FK ON DELETE RESTRICT sur exercice_id |
| Soft delete cohérent | ⚠️ Finance V2 : `deleted_at` sur sessions caisse, statuts sur factures/paiements (hors FN-C-003) |
| UNIQUE constraints | ✅ Finance: numéros séquentiels, références comptables |
| NOT NULL appropriés | ✅ |
| INDEX primaires | ✅ tous PK |
| INDEX secondaires | ⚠️ 4 index composites Finance manquants identifiés |

### 7.4 Rollback et migrations

| Point | Statut |
|---|---|
| Finance V2 — rollback | ✅ DROP TABLE finance_* (28 tables) |
| Finance V2 — migration forward | ✅ 5 fichiers .sql versionnés |
| Académique V2 — rollback | ⚠️ Possible manuellement mais migrations non dans /database/migrations/ |
| Scolarité V2 — rollback | ⚠️ Idem |
| RBAC V2 — rollback | ⚠️ Idem |
| Désactivation module | ✅ `enabled: false` dans config/modules.php |

---

## 8. Compatibilité V1

### 8.1 Coexistence confirmée

| Point | Statut |
|---|---|
| Routes V1 (`/eleves`, `/classes`, `/notes`…) | ✅ Aucun conflit avec `/v2/*` |
| Tables V1 (`users`, `eleves`, `classes`…) | ✅ Aucun DROP ni ALTER destructif |
| Controllers V1 (`app/Controllers/`) | ✅ Préservés intégralement |
| Config V1 (`config/permissions.php`) | ✅ Non supprimée (dual system actif) |
| Session V1 | ✅ Compatible avec V2 (même Session::getUser()) |
| PWA / Service Worker | ✅ Routes /v2/* incluses dans le cache SW |

### 8.2 Risques de régression

| Risque | Probabilité | Mitigation |
|---|---|---|
| Conflit de noms de route | Faible | Préfixe `/v2/` systématique en V2 |
| Conflit de session keys | Faible | `_auth_user`, `_csrf_token` — noms uniques |
| Conflit de classes PHP | Faible | Namespaces distincts `App\Controllers` vs `App\Modules\*` |
| config/events.php trop long | Moyenne | 503 lignes — maintenabilité dégradée à terme |

---

## 9. Préparation Phase 5

### 9.1 Modules prévus et prérequis architecturaux

| Module Phase 5 | Tables nouvelles | Dépend de | Risque |
|---|---|---|---|
| Vie scolaire | absences_v2, sanctions, comportements | Scolarité (élèves/classes), Académique | Faible |
| RH | employes, contrats, salaires | users, classes | Faible |
| Documents | documents, categories_docs, partages | eleves, classes, users | Faible |
| Communication | messages, fils, destinataires | users, roles | Faible |
| Bibliothèque | livres, emprunts, exemplaires | eleves, users | Faible |
| Inventaire | articles, mouvements, salles | — | Faible |
| Portails (parent, élève, enseignant) | — (vues sur données existantes) | Tous modules | **Élevé** |
| Réseau (multi-établissements) | etablissements, tenants | Tous modules | **Très élevé** |

### 9.2 Gaps architecturaux à combler avant Phase 5

| Gap | Urgence | Module le plus impacté |
|---|---|---|
| MS2-C-001 — Pas de middleware obligatoire | Haute | Portails (accès public parent/élève) |
| MS2-C-002 — Dual RBAC ambiguïté | Haute | Tous (permission checks) |
| PdfService centralisé | Haute | Documents, bulletins, RH |
| ExportService centralisé | Moyenne | RH, Bibliothèque, Inventaire |
| NotificationService → V2 events | Haute | Vie scolaire, Communication |
| Migrations Scolarité/Académique versionnées | Haute | Déploiement reproductible |
| SearchService global | Moyenne | Portails |
| Cache applicatif (APCu/fichier) | Moyenne | Portails (requêtes répétitives) |

### 9.3 Portails — Risque spécifique

Les portails Parent, Élève et Enseignant exposeront l'application à des utilisateurs non-admin. Cela exige :
1. **Middleware auth obligatoire au niveau Router** (MS2-C-001 non résolu = risque critique)
2. **Isolation des données** par `view.own` permissions (déjà prévu dans la Policy pattern V2)
3. **Rate limiting** — non implémenté actuellement
4. **CSP plus stricte** — `unsafe-inline` doit être retiré en production publique

### 9.4 Réseau multi-établissements

Ce module implique un tenant system complet (toutes les tables doivent avoir un `etablissement_id` ou être partitionnées). C'est une **refonte architecturale majeure** incompatible avec l'architecture actuelle monosite. Traiter comme un projet V3 distinct, pas une Phase 5.

---

## 10. Dette technique globale

### 10.1 Critique — Bloquant production

| ID | Module | Description |
|---|---|---|
| **MS2-C-001** | Core | Pas de middleware obligatoire — routes non protégées si oubli `requireAuth()` |
| **MS2-C-002** | Core / Finance | Dual RBAC : ambiguïté source authoritative + mismatch nommage `rapports` vs `reports` |
| **FN-C-001** | Finance | `Core\AuditService` → `App\Services\AuditService` (fatal error rapports) |
| **FN-C-002** | Finance | `findPaiementMode()` colonne inexistante → remboursements mal comptabilisés |
| **FN-C-003** | Finance | DELETE physique dans `InvoiceService::supprimer()` |
| **DT-M1** | Scolarité | `MatiereAssigned/MatiereRemoved` jamais dispatché (ClasseController non mis à jour) |
| **AN-C-001** | Académique | (voir ACADEMIQUE_MODULE_FREEZE.md) |
| **AN-C-002** | Académique | (voir ACADEMIQUE_MODULE_FREEZE.md) |
| **AN-C-003** | Académique | (voir ACADEMIQUE_MODULE_FREEZE.md) |

**Total critiques bloquantes : 9 (2 Core + 3 Finance + 1 Scolarité + 3 Académique)**

### 10.2 V2.1 — Avant mise en production (reportée)

| ID | Description | Priorité |
|---|---|---|
| MS2-M-001 | Migrations Scolarité + Académique dans `/database/migrations/` | Haute |
| MS2-M-002 | PdfService centralisé (`app/Services/PdfService.php`) | Haute |
| MS2-M-003 | ExportService centralisé (CSV/Excel) | Haute |
| MS2-M-004 | NotificationService wired sur V2 events (PaymentCompleted, BulletinPublished) | Haute |
| MS2-M-005 | Listener Finance sur `InscriptionCreated` → proposition facture | Haute |
| MS2-M-006 | Activer `scolarite.enabled = true` et `academique.enabled = true` | Haute |
| MS2-M-007 | Fusionner les 4 paires dupliquées dans `config/events.php` | Moyenne |
| MS2-M-008 | Index composites manquants (audit_logs, finance_ecritures, finance_paiements) | Moyenne |
| MS2-M-009 | RapportController extends Core\Controller | Moyenne |
| MS2-M-010 | Double audit FiscalYear (AccountingService) | Faible |
| MS2-M-011 | Model::delete() marqué @deprecated | Faible |
| MS2-M-012 | Cache APCu pour KPIs dashboard | Faible |

---

## 11. Recommandations prioritaires

### 11.1 Avant Phase 5

**1. Résoudre les 9 critiques bloquantes** (FN-C-001/002/003, AN-C-001/002/003, DT-M1, MS2-C-001, MS2-C-002).  
Estimation : ~2h de corrections de code.

**2. Centraliser le RBAC** — Décider si `config/permissions.php` ou les tables RBAC V2 sont authoritative. Corriger le mismatch `finance.rapports` vs `finance.reports`. Documenter la décision dans RBAC_V2.md.

**3. Activer les 3 modules simultanément** — passer `scolarite.enabled = true` et `academique.enabled = true` dans config/modules.php et vérifier l'absence de conflits de routes.

**4. Implémenter PdfService** — les 3 modules utilisent des solutions ad hoc (HTML print, FinancialReportService, BulletinGenerator). Un service centralisé est indispensable avant Phase 5 Documents.

**5. Versionner les migrations manquantes** — rassembler les migrations Scolarité V2, Académique V2, RBAC V2, Shared Services dans `/database/migrations/` avec nommage séquentiel.

### 11.2 Considérer pour Phase 5

- Route Groups avec middleware auth obligatoire (pour les portails)
- Rate limiting sur les endpoints portails parents/élèves
- SearchService global (recherche unifiée élève/paiement/note)
- NotificationService wired sur V2 events (payements, bulletins, absences V2)
- Cache KPIs (APCu ou fichier JSON)

---

## 12. Synthèse — Points forts

1. **Architecture hexagonale stricte et cohérente** sur 3 modules V2 — le pattern Controller/Service/Repository/DTO/Policy/Event est appliqué uniformément.
2. **Event-driven decoupling** — les 3 modules communiquent via events sans dépendances directes de code, permettant des évolutions indépendantes.
3. **AuditService excellent** — piste d'audit complète, sanitisation, diff avant/après, dégradation silencieuse, séparé en service partagé.
4. **Session sécurisée** — HttpOnly, SameSite Strict, régénération périodique, CSRF timing-safe.
5. **Security headers** — X-Frame-Options, CSP, X-Content-Type-Options sur toutes les réponses Core\Controller.
6. **Soft delete généralisé** sur toutes les entités V2 (hors FN-C-003).
7. **Compatibilité V1 totale** — les deux stacks coexistent sans régression, rollback possible module par module.
8. **Transactions PDO** — toutes les opérations multi-table sont atomiques.
9. **Anti-doublon comptable** — `findEcritureByReference()` avant chaque écriture Finance.
10. **PWA intégrée** — Service Worker, push notifications, mode offline — architecture moderne pour un contexte scolaire.

---

## 13. Décision finale

### Score Milestone 2 : **7.4/10**

| Dimension | Score |
|---|---|
| Architecture Core | 7.5/10 |
| Intégration inter-modules | 8.0/10 |
| Event System | 7.0/10 |
| Shared Services | 6.5/10 |
| Performances | 6.5/10 |
| Sécurité | 7.5/10 |
| Base de données | 7.0/10 |
| Compatibilité V1 | 9.0/10 |
| Préparation Phase 5 | 7.0/10 |
| **Global** | **7.4/10** |

### Décision : **NO-GO conditionnel**

Le Milestone 2 ne peut pas être déclaré GO en l'état. Neuf critiques doivent être résolues avant le lancement de la Phase 5.

**Critiques à résoudre :**

| Priorité | ID | Module | Action | Effort |
|---|---|---|---|---|
| 1 | FN-C-001 | Finance | `Core\AuditService` → `App\Services\AuditService` | 5 min |
| 2 | FN-C-002 | Finance | `findPaiementMode()` via JOIN | 10 min |
| 3 | FN-C-003 | Finance | Soft delete dans `supprimer()` | 5 min |
| 4 | MS2-C-002 | Core | Unifier RBAC — corriger `finance.rapports` vs `finance.reports` | 30 min |
| 5 | DT-M1 | Scolarité | Déclencher `MatiereAssigned/Removed` dans ClasseController | 15 min |
| 6 | AN-C-001 | Académique | (voir ACADEMIQUE_MODULE_FREEZE.md) | ~30 min |
| 7 | AN-C-002 | Académique | (voir ACADEMIQUE_MODULE_FREEZE.md) | ~30 min |
| 8 | AN-C-003 | Académique | (voir ACADEMIQUE_MODULE_FREEZE.md) | ~30 min |
| 9 | MS2-C-001 | Core | Middleware auth obligatoire (ou documentation des routes publiques) | 1-2h |

**Après correction des 9 critiques → GO Milestone 2 → Phase 5 autorisée**

### Liste des améliorations V2.1

Après le GO Milestone 2, à planifier avant la mise en production :

| ID | Description |
|---|---|
| MS2-M-001 | Versionner migrations Scolarité + Académique |
| MS2-M-002 | Implémenter `PdfService` centralisé |
| MS2-M-003 | Implémenter `ExportService` centralisé |
| MS2-M-004 | Brancher `NotificationService` sur V2 events |
| MS2-M-005 | Listener Finance sur `InscriptionCreated` |
| MS2-M-006 | Activer scolarite + academique dans config/modules.php |
| MS2-M-007 | Fusionner entrées dupliquées config/events.php |
| MS2-M-008 | Ajouter index composites manquants |
| MS2-M-009 | `RapportController extends Core\Controller` |
| MS2-M-010 | Cache APCu pour KPIs dashboard |
| MS2-M-011 | `Model::delete()` marqué `@deprecated` |
| MS2-M-012 | Commenter ordre et dépendances handlers multi-listeners |

---

*MILESTONE_2_REVIEW.md — Phase 4.0 — 2026-07-01*  
*Analyse de 14 fichiers Core, 3 modules V2 (90+ fichiers PHP), 5 migrations SQL, 503 lignes config/events.php*
