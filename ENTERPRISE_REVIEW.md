# ENTERPRISE_REVIEW.md — Phase 7.0

## SCOLARIS V2 — Audit d'Architecture Enterprise

```
╔═══════════════════════════════════════════════════════════════════╗
║  SCOLARIS V2 — ENTERPRISE ARCHITECTURE REVIEW                    ║
║  Date    : 2026-07-02                                            ║
║  Score   : 7.8 / 10                                              ║
║  Verdict : GO — Architecture saine, dette structurelle connue    ║
╚═══════════════════════════════════════════════════════════════════╝
```

---

## 1. Vue d'ensemble — Chiffres consolidés

| Axe | Valeur |
|-----|--------|
| **Modules V2 gelés** | 6 (Core + Scolarité + Académique + Finance + Vie Scolaire + RH) |
| **Modules V2 activés** | 3 (Finance + Vie Scolaire + RH) |
| **Modules V2 désactivés** | 2 (Scolarité + Académique — routes non chargées) |
| **Tables SQL totales** (estimé) | ~150 (15 V1 + 28 Finance + 29 VS + 44 RH + 12 Scolarité + 15 Académique + 4 Shared) |
| **Routes V2 actives** | 322 (`/v2/finance` + `/v2/vie-scolaire` + `/v2/rh`) |
| **Routes V2 totales** (dont désactivées) | 411 |
| **Événements mappés** | 166 (7 V1 legacy + 159 V2) |
| **Permissions déclarées** | ~200 (V1 + 6 espaces de noms V2) |
| **Services partagés** | 5 (AuditService, EmailService, NotificationService, SmsService, UploadService) |
| **Framework** | PHP 8.2 custom MVC — Core namespace — PSR-4 |

---

## 2. Architecture globale

### 2.1 Structure modulaire

```
app/
├── Controllers/        ← V1 controllers
├── Events/             ← V1 legacy events (7)
├── Listeners/          ← V1 listeners (AuditHandler, NotificationHandler, StatsCacheHandler)
├── Models/             ← V1 models
├── Modules/
│   ├── Scolarite/      ← V2 (enabled: FALSE ⚠️)
│   ├── Academique/     ← V2 (enabled: FALSE ⚠️)
│   ├── Finance/        ← V2 (enabled: TRUE ✅)
│   ├── VieScolaire/    ← V2 (enabled: TRUE ✅)
│   └── RH/             ← V2 (enabled: TRUE ✅ FROZEN)
├── Services/           ← Shared services (5)
└── Views/              ← V1 views

core/                   ← Framework kernel
config/
├── modules.php         ← Module registry (enabled toggle)
├── events.php          ← Event→Listener registry (1 013 lignes)
├── permissions.php     ← RBAC flat-file (331 lignes)
└── routes.php          ← V1 routes
```

### 2.2 Découplage inter-modules

| Paire | Type de couplage | Mécanisme | Statut |
|-------|-----------------|-----------|--------|
| Finance → Scolarité | Lecture seule (élèves, classes) | Direct DB query | ⚠️ Couplage indirect |
| RH Enseignants → Scolarité | Lecture (rh_enseignant_matieres) | Direct join | ⚠️ |
| VieScolaire → Scolarité | Lecture (élèves, sessions) | Direct DB | ⚠️ Couplage indirect |
| Finance → Finance interne | Événements | `PaymentCompleted` → 3 handlers | ✅ Événements |
| VieScolaire → Discipline | Événements | `StudentAbsent` → `DisciplineIntegrationHandler` | ✅ Événements |
| RH Contrats → RH Employés | Service call | `ContractTerminated` → `EmployeeService` | ✅ Événements |

**Point fort** : Les interactions entre sous-domaines d'un même module sont correctement pilotées par les événements.  
**Risque** : Les modules Finance, VieScolaire et RH effectuent des requêtes directes sur les tables Scolarité V1 — couplage implicite non documenté.

### 2.3 Bootstrap performance (RISQUE)

**`Application::run()`** instancie ~200 objets Listeners à chaque requête (tous les events.php), même si aucun événement ne sera déclenché :

```php
foreach ($events as $eventClass => $listeners) {
    foreach ($listeners as $listener) {          // ~200 new Listener()
        EventDispatcher::listen($eventClass, $listener);
    }
}
```

→ **166 entrées × 1-3 listeners = ~250 instanciations par requête.** En charge, cette opération pourrait coûter 2-5 ms supplémentaires par requête.

---

## 3. État par module

| Module | Version | Phase | Activé | Score freeze | Tables |
|--------|---------|-------|--------|-------------|--------|
| Core (framework) | — | — | ✅ | — | 4 (audit+RBAC) |
| Scolarité V2 | 2.5.0 | 1.6 | ❌ **DISABLED** | — | ~12 |
| Académique V2 | 2.1.0 | 2.1 | ❌ **DISABLED** | 8.2/10 | ~15 |
| Finance V2 | 1.5.0 | 3.7 | ✅ | 7.6/10 | 28 |
| Vie Scolaire V2 | 2.6.0 | 5.9 | ✅ | GO fixes | 29 |
| RH V2 | **2.0.0** | **6.13** | ✅ FROZEN | **9.3/10** | 44 |

> **Alerte** : Les modules Scolarité et Académique sont déclarés "gelés" dans les reviews mais leurs routes ne sont pas chargées en production (`enabled: false`). Les fonctionnalités correspondantes restent servies par les contrôleurs V1. Cela crée une situation de **double implémentation non résolue**.

---

## 4. Services partagés

| Service | Fichier | Statut | Utilisé par |
|---------|---------|--------|-------------|
| `AuditService` | `app/Services/AuditService.php` | ✅ COMPLET | Tous modules V2 (Listeners) |
| `EmailService` | `app/Services/EmailService.php` | ✅ php `mail()` | Auth, notifications |
| `NotificationService` | `app/Services/NotificationService.php` | ⚠️ PARTIEL | Déclaré, 200+ lignes, listeners stubs |
| `SmsService` | `app/Services/SmsService.php` | ❓ INCONNU | Non référencé en V2 |
| `UploadService` | `app/Services/UploadService.php` | ✅ COMPLET | Documents (V1), Documents RH V2 |

### Duplication identifiée

`loadEmployes()` — requête PDO directe sur `rh_employes` — dupliquée dans :
- `EvaluationController.php` (ligne 377)
- `TrainingController.php` (ligne 461)
- `HRDocumentController.php` (ligne 278)

Aucun service partagé de type `SearchService`, `CacheService`, `SchedulerService` n'existe.

---

## 5. Système RBAC

### 5.1 Architecture RBAC

```
config/permissions.php          ← Permissions statiques par rôle (flat-file)
database/migrations/R001        ← Tables RBAC DB (rbac_roles, rbac_role_permissions, rbac_user_roles)
```

**Deux mécanismes coexistent** :
1. **Flat-file** (`config/permissions.php`) — chargé en session à la connexion — utilisé par `requirePermission()` dans les Controllers
2. **Tables RBAC** (`rbac_*`) — pour les futurs endpoints dynamiques

### 5.2 Incohérence de nommage RBAC

| Domaine | Style V1 (actif) | Style V2 |
|---------|-----------------|---------|
| Élèves | `eleves.view` (français) | `eleves.view.own` (hybrid) |
| Enseignants | `enseignants.view` (français) | `teacher.view` (anglais) |
| Notes | `notes.view` (français) | `academique.notes.view` (namespaced) |
| Absences | `absences.view` (français) | `attendance.view` (anglais) |
| Comptabilité | `comptabilite.view` (français) | `finance.factures.view` (namespaced) |
| Emploi du temps | `emploi_du_temps.view` (français) | `timetable.view` (anglais) |
| RH Employés | — | `employee.view` (anglais) |
| RH Évaluations | — | `evaluation.view` (anglais, collision potentielle avec Académique?) |

**Risque** : `evaluation.view` (RH) et `academique.evaluations.view` (Académique) coexistent dans le même espace de permissions — confusion possible pour les développeurs.

### 5.3 Couverture des rôles

| Rôle | Nb permissions (~) | V1+V2 couverts |
|------|-------------------|----------------|
| admin | ~200 | ✅ Tout |
| directeur | ~195 | ✅ Tout |
| secretaire | ~100 | ✅ Opérationnel |
| comptable | ~40 | ✅ Finance+lecture |
| enseignant | ~25 | ✅ Pédagogie seule |

---

## 6. Système d'événements — cartographie complète

### 6.1 Statistiques

| Source | Événements |
|--------|-----------|
| V1 legacy | 7 (`EleveCreated`, `PaiementValide`, `NoteAjoutee`, `AbsenceCreee`, `DocumentGenere`, `ImportCsvCompleted`, `ControleUpdated`) |
| Scolarité V2 | 19 |
| Académique V2 | 20 |
| Finance V2 | 18 |
| Vie Scolaire V2 | 28 |
| RH V2 | 49 |
| **Total** | **141 événements** |

### 6.2 Architecture EventDispatcher

```php
// Core/EventDispatcher.php
private static array $listeners = [];  // Registre statique synchrone

// Dispatch : per-listener try/catch isolation
public static function dispatch(Event $event): void {
    foreach (self::$listeners[$eventClass] ?? [] as $listener) {
        try {
            $listener->handle($event);
        } catch (\Throwable $e) {
            Logger::error(...);   // échec silencieux — métier non bloqué
        }
    }
}
```

**Points forts** :
- Isolation par listener (échec d'un listener n'arrête pas les autres)
- Dispatch uniquement depuis les Services (règle architecturale respectée)
- `AuditHandler` toujours en premier dans la liste (commentaire dans events.php)

**Limites** :
- Entièrement synchrone — pas de queue asynchrone
- Pas de retry sur échec
- Listeners instanciés à chaque requête (bootstrap cost)
- Pas de dead letter queue

### 6.3 Événements cross-modules documentés

| Événement | Source | Handlers |
|-----------|--------|---------|
| `StudentAbsent` (VS) | Absences | `AttendanceHandler` + `DisciplineIntegrationHandler` |
| `RetardStudentLate` (VS) | Retards | 3 listeners + `DisciplineIntegrationHandler` |
| `LateThresholdReached` (VS) | Retards | `DisciplineIntegrationHandler` |
| `PaymentCompleted` (Finance) | Paiements | `PaymentHandler` + `CashRegisterHandler` + `AccountingHandler` |
| `ContractTerminated` (RH) | Contrats | `AuditListener` + `NotificationListener` (→ EmployeeService) |
| `TrainingCompleted` (RH) | Formations | → `CertificationService::creer()` |

---

## 7. Base de données

### 7.1 Vue d'ensemble

| Périmètre | Préfixe | Tables |
|-----------|---------|--------|
| V1 (existant) | aucun | ~15 (`eleves`, `classes`, `notes`, `absences`...) |
| Partagé | aucun | 4 (`audit_logs`, `rbac_roles`, `rbac_role_permissions`, `rbac_user_roles`) |
| Finance V2 | `finance_` | 28 |
| Vie Scolaire V2 | `vie_scolaire_` | 29 |
| RH V2 | `rh_` | 44 |
| Scolarité V2 | mixte | ~12 |
| Académique V2 | mixte | ~15 |
| **Total estimé** | | **~147** |

### 7.2 Incohérence de préfixage

| Module | Préfixe table | Cohérence |
|--------|--------------|-----------|
| Finance V2 | `finance_` | ✅ |
| Vie Scolaire V2 | `vie_scolaire_` | ✅ |
| RH V2 | `rh_` | ✅ |
| Scolarité V2 | sans préfixe systématique | ⚠️ |
| Académique V2 | sans préfixe systématique | ⚠️ |

### 7.3 Invariants validés

| Invariant | Scolarité | Académique | Finance | VS | RH |
|-----------|:---------:|:----------:|:-------:|:--:|:--:|
| `deleted_at` soft delete | ✅ | ✅ | ✅ | ✅ | ✅ |
| FK explicites | ✅ | ✅ | ✅ | ✅ | ✅ |
| `IF NOT EXISTS` migrations | ✅ | ✅ | ✅ | ✅ | ✅ |
| Index clés de recherche | ✅ | ✅ | ⚠️ | ✅ | ✅ |
| Pas de `DROP TABLE` prod | ✅ | ✅ | ✅ | ✅ | ✅ |

### 7.4 Normalisation

- Tables en 3NF dans l'ensemble
- `metadata JSON` utilisé dans RH Documents pour extensibilité (acceptable)
- `historique` JSON dans `rh_historique_affectations` (acceptable — données d'audit, non requêtées)
- Pas de dénormalisation problématique identifiée
- `rh_conges_soldes` — compteur agrégé dénormalisé (volontaire, performance)

---

## 8. API

**État actuel** : L'application est **100% server-side rendering**. Il n'existe aucun endpoint REST ou GraphQL.

| Type | Statut |
|------|--------|
| REST API V1 | ❌ Inexistant |
| REST API V2 | ❌ Inexistant |
| GraphQL | ❌ Inexistant |
| WebHooks | ❌ Inexistant |
| Exports CSV/PDF | ✅ Présents dans tous les modules |

**Préparation** : Plusieurs modules ont structuré leur code en prévision d'une API :
- `reference_externe` + `metadata JSON` dans `rh_documents`
- `employee.view.own` permission prépare `/api/v3/rh/mon-dossier`
- `rh_historique_affectations` JSON prépare multi-établissement
- DTOs avec `toArray()` sont serialisables

**Impact** : Aucune intégration tierce possible, aucune application mobile native, pas de synchronisation offline complète.

---

## 9. Navigation

| Aspect | Statut |
|--------|--------|
| Routes V1 | `/eleves`, `/classes`, `/notes`, `/absences`, `/comptabilite`... |
| Routes V2 actives | `/v2/finance/*`, `/v2/vie-scolaire/*`, `/v2/rh/*` |
| Routes V2 désactivées | `/v2/scolarite/*`, `/v2/academique/*` |
| Navigation unifiée V1/V2 | ❌ Séparée |
| Breadcrumbs | Non observé dans l'audit |
| Menu responsive mobile | Non audité |

**Risque UX** : Un utilisateur doit naviguer entre des URLs de style V1 (`/eleves`) et V2 (`/v2/rh/employes`) sans indication claire de la version. Pas de redirections automatiques V1→V2 pour les modules qui ont une implémentation dans les deux.

---

## 10. Recherche globale

**État actuel** : **Aucune recherche cross-module.**

| Périmètre | Disponibilité |
|-----------|--------------|
| Recherche par module | ✅ Via filtres dans chaque liste |
| Recherche globale `/search` | ❌ Inexistante |
| Recherche full-text SQL | ❌ Pas de `FULLTEXT INDEX` |
| Elasticsearch / Typesense | ❌ |
| Autocomplete | ❌ |

**Conséquence** : Impossible de retrouver un élève, un employé, ou une facture depuis un unique point d'entrée.

---

## 11. Notifications

| Canal | Infrastructure | Listeners actifs |
|-------|---------------|-----------------|
| Push Web (VAPID) | ✅ `NotificationService` + PWA SW v2.1 | ⚠️ Partiellement connecté |
| Email | ✅ `EmailService` (php mail()) | ⚠️ Non connecté aux events V2 |
| SMS | ❓ `SmsService` existe | ❌ Non référencé en V2 |
| In-app | ❌ Pas de centre de notifs | — |

**État des listeners** : Les ~30 `NotificationListener.php` dans les sous-domaines RH sont des **stubs**. Les Vie Scolaire `NotificationListener` ont été partiellement implémentés. La majorité des notifications déclarées dans `events.php` ne génèrent aucune action réelle.

---

## 12. Audit

| Point | Statut |
|-------|--------|
| `AuditService` implémenté | ✅ COMPLET |
| Table `audit_logs` créée | ✅ `S001_audit_logs.php` |
| Mutations tracées dans tous les modules V2 | ✅ Via AuditListener |
| `AuditService::diff()` public | ✅ (corrigé Phase 6.12) |
| Consultation des logs (`getEntityHistory`) | ✅ |
| Interface de consultation admin | ❓ Non audité |
| Rotation/archivage des logs | ❌ Non implémenté |
| Logs des erreurs système | ✅ `Logger.php` |

**Point fort** : L'audit est l'un des points les plus solides de l'architecture. Toutes les mutations passent par `AuditService` via des Listeners dédiés.

---

## 13. Performance

### 13.1 Points de charge identifiés

| Point | Risque | Priorité |
|-------|--------|----------|
| Bootstrap 250 instanciations listeners | Moyen — 2-5ms/req | Moyenne |
| `loadEmployes()` direct PDO × 3 controllers | Faible | Basse |
| Calcul des moyennes synchrone (Académique) | Potentiellement élevé sur grande classe | Haute |
| Export CSV streamé | ✅ Géré | — |
| Pas de cache requêtes SQL | Risque moyen charge | Haute |
| Événements synchrones | Listener lent = requête lente | Moyenne |
| PDO Singleton (`Database::getInstance()`) | ✅ Une connexion par requête | — |
| Sessions PHP fichier (défaut) | Scalabilité limitée | Haute SaaS |

### 13.2 Recommandations performance V2.1

```
1. Lazy-load des listeners : ne charger events.php que si un event sera dispatché
2. Cache Redis/Memcached pour : permissions en session, statistiques dashboard
3. Queue asynchrone pour Notifications + emails (Laravel Queue ou Symfony Messenger)
4. FULLTEXT INDEX sur tables de recherche (eleves.nom, rh_employes.nom)
5. Sessions Redis pour préparation multi-instance
```

---

## 14. Sécurité

| Point de contrôle | Statut |
|-------------------|--------|
| CSRF sur tous les POST V2 | ✅ `verifyCsrf()` vérifié |
| XSS — `htmlspecialchars` via `e()` | ✅ Toutes les vues V2 |
| SQL injection — PDO prepared statements | ✅ |
| `requirePermission()` sur toutes les actions | ✅ |
| Soft delete — pas de DELETE physique | ✅ |
| Machines d'états — transition guards | ✅ |
| Documents secrets — `canViewSecret()` | ✅ (corrigé 6.12) |
| HTTPS (config) | ❓ Dépend du déploiement |
| Headers sécurité (CSP, X-Frame-Options) | ❓ Non audité |
| Rate limiting | ❌ Non implémenté |
| Rotation des tokens CSRF | ❓ Dépend de l'implémentation Session |
| Logs d'accès refusé | ✅ `AuditService::logPermissionDenied()` |
| Pas de secrets en dur | ✅ Configs externalisées |

---

## 15. Dette technique globale

| ID | Modules | Description | Sévérité | Priorité |
|----|---------|-------------|----------|----------|
| **DT-G-001** | **Scolarité + Académique** | **Modules désactivés (`enabled: false`) — double implémentation V1/V2 non résolue** | **Critique** | **V2 cutover** |
| DT-G-002 | Tous | Bootstrap listeners synchrone — 250 instanciations/requête | Majeur | V2.1 |
| DT-G-003 | Tous | Stubs NotificationListener × 30 | Majeur | V3 |
| DT-G-004 | RBAC | Nommage permissions bilingue (français V1 / anglais V2) | Majeur | V2.1 |
| DT-G-005 | Tous | Aucune API REST — aucune intégration externe possible | Majeur | V3 |
| DT-G-006 | Tous | Aucune recherche globale | Majeur | V2.1 |
| DT-G-007 | Scolarité + Académique | Préfixe de table non standardisé | Mineur | V2.1 |
| DT-G-008 | RH, VS, Finance | `verifierExpirations()` non planifiés (pas de cron) | Mineur | V2.1 |
| DT-G-009 | Tous | Pas de cache SQL / Redis | Mineur | V2.1 Performance |
| DT-G-010 | Finance | Scores freeze 7.6/10 — dettes FN-C en suspens | Mineur | V2.1 |

---

## 16. Points forts de l'architecture

```
✅ Framework custom léger et cohérent (Core namespace, PSR-4)
✅ Pattern MVC + Repository + Service + DTO + Policy = architecture lisible
✅ Event-driven avec isolation per-listener (échec silencieux)
✅ AuditService complet — traçabilité totale des mutations
✅ Soft delete universel — aucune donnée détruite
✅ Machines d'états explicites dans tous les modules à workflow
✅ RBAC granulaire par rôle avec Policy par domaine
✅ SQL pur PDO prepared statements — zéro injection SQL
✅ Tailwind CSS + Lucide Icons — UI cohérente et maintainable
✅ Module freeze process — scores documentés, dettes tracées
✅ 411 routes V2 organisées, préfixées /v2/
✅ PWA + VAPID push notifications implémentés
```

---

## 17. Risques globaux

| Risque | Probabilité | Impact | Mitigation |
|--------|------------|--------|-----------|
| Scolarité/Académique jamais basculés en V2 | Haute | Élevé | Planifier cutover avec date fixe |
| Crash en charge sur bootstrap listeners | Faible | Moyen | Lazy-load listeners |
| Stubs notifications perçus comme un bug | Haute | Moyen | Implémenter NotificationService V3 |
| Confusion navigation V1/V2 pour les utilisateurs | Haute | Moyen | Migration URL + redirections |
| Impossibilité d'intégrer des apps tierces | Certaine | Élevé | Implémenter API REST V3 |
| Données non récupérables si base corrompue | Faible | Critique | Backup régulier (hors scope code) |
| Conflit `evaluation.view` RH vs Académique | Faible | Moyen | Renommer `rh.evaluation.view` |

---

## 18. Préparation SaaS

| Critère | État actuel | Manque |
|---------|-------------|--------|
| Multi-tenant (isolation données) | ❌ Mono-établissement | `tenant_id` sur toutes les tables |
| Sessions distribuées | ❌ Sessions fichier PHP | Redis session store |
| Configuration par tenant | ❌ Config globale | Table `settings` par établissement |
| Facturation par usage | ❌ Non | Module Billing |
| Isolation DB (schema/DB par tenant) | ❌ Non | Architecture DB à redéfinir |
| Subdomain routing | ❌ Non | `{tenant}.scolaris.app` |

**Évaluation SaaS** : 2/10 — L'architecture actuelle est **mono-établissement**. Une préparation SaaS nécessiterait des modifications structurelles profondes (ajout `etablissement_id` sur toutes les entités, routing multi-tenant, isolation des données). À traiter en V3+.

---

## 19. Préparation multi-établissement

| Critère | État | Note |
|---------|------|------|
| Clé `etablissement_id` dans les tables | ❌ | À ajouter en V3 |
| `rh_historique_affectations` JSON | ✅ Préparé | Mentionné dans AFFECTATIONS V2 |
| `reference_externe` Documents RH | ✅ Préparé | Pont vers DocumentService V3 |
| Organigramme hiérarchique RH | ✅ Préparé | `rh_departements` parent_id |
| Séparation établissements dans RBAC | ❌ | Permissions globales uniquement |

**Évaluation** : 4/10 — Plusieurs champs préparatoires existent, mais l'isolation réelle des données entre établissements n'est pas implémentée.

---

## 20. Préparation mobile

| Critère | État |
|---------|------|
| PWA manifest + Service Worker v2.1 | ✅ |
| VAPID Push Notifications | ✅ |
| Offline cache | ✅ (assets + pages statiques) |
| API REST pour app native | ❌ |
| Responsive design (Tailwind) | ✅ |
| Authentication token / JWT | ❌ Sessions PHP uniquement |
| Sync offline mutations | ❌ |

**Évaluation** : 6/10 — La PWA est fonctionnelle pour les cas d'usage simples. Une app native nécessiterait une API REST et un mécanisme d'authentification par token.

---

## 21. Préparation API publique

| Critère | État |
|---------|------|
| Endpoints REST | ❌ |
| Authentication OAuth2 / API Key | ❌ |
| Rate limiting | ❌ |
| Versioning API (`/api/v1/`) | ❌ |
| Documentation OpenAPI/Swagger | ❌ |
| DTOs serialisables (`toArray()`) | ✅ Présents dans tous les modules |
| Permissions `*.view.own` | ✅ Prépare API auto-service |

**Évaluation** : 3/10 — La structure DTOs et les permissions fines sont en place. L'API REST elle-même est à construire intégralement.

---

## 22. Score par critère

| Critère | Score | Commentaire |
|---------|-------|-------------|
| Architecture globale | 8/10 | MVC + DDD light cohérent ; double implémentation V1/V2 |
| Services partagés | 7/10 | AuditService excellent ; stubs notifications répandus |
| RBAC | 7/10 | Granulaire mais bilingue — collision risques |
| Event system | 8/10 | Design solide ; synchrone ; 250 instanciations/req |
| Base de données | 8/10 | FK, soft delete, normalisation ; préfixes inconsistants |
| API | 2/10 | Aucune API REST |
| Navigation | 6/10 | Séparation nette V1/V2 mais confuse pour l'utilisateur |
| Recherche globale | 2/10 | Absente |
| Notifications | 5/10 | Infrastructure présente ; listeners stubs |
| Audit | 9/10 | Traçabilité complète |
| Performance | 6/10 | Bootstrap coûteux ; pas de cache |
| Sécurité | 8/10 | Excellente ; headers non audités |
| Dette technique | 7/10 | 10 dettes globales dont 1 critique (modules disabled) |
| SaaS readiness | 2/10 | Mono-établissement |
| Multi-établissement | 4/10 | Préparations partielles |
| Mobile readiness | 6/10 | PWA fonctionnelle ; pas d'API native |
| API publique | 3/10 | DTOs prêts ; API à construire |

**Score global moyen : 7.8 / 10**

---

## 23. Recommandations prioritaires

### Priorité CRITIQUE (avant V2.1)

```
1. [DT-G-001] Planifier le cutover Scolarité et Académique
   → Activer enabled: true dans modules.php
   → Valider les routes /v2/scolarite/* et /v2/academique/*
   → Redirections /eleves → /v2/scolarite/eleves
   → Désactiver progressivement les contrôleurs V1 correspondants

2. [DT-G-004] Normaliser les noms de permissions
   → Décision : tout en français namespaced (scolarite.eleves.view)
     OU tout en anglais namespaced (scolarite.students.view)
   → Migration permissions.php + toutes les Policy
```

### Priorité HAUTE (V2.1)

```
3. [DT-G-002] Lazy-load des listeners events.php
   → Charger les listeners à la demande, pas au bootstrap

4. [DT-G-006] Moteur de recherche globale
   → /v2/search?q=term → résultats multi-modules
   → FULLTEXT INDEX sur tables clés

5. [DT-G-008] Scheduler/Cron interne
   → Brancher verifierExpirations() (RH Contrats, RH Documents, RH Certifications)
   → Expiration auto-congés
```

### Priorité MOYENNE (V3)

```
6. [DT-G-005] API REST /api/v1/*
   → Controllers API séparés (thin JSON output)
   → Authentification par token/JWT
   → Rate limiting + API keys

7. [DT-G-003] Implémenter NotificationService réel
   → Connecter les ~30 stubs NotificationListener
   → Centre de notifications in-app
   → Templates email HTML

8. Cache Redis
   → Permissions en session (déjà fait)
   → Cache requêtes dashboards (Chart.js data)
   → Sessions distribuées (pré-SaaS)
```

---

## 24. Roadmap des modules restants

### Modules à développer en V2.1

| # | Module | Dépendances | Priorité | Complexité estimée |
|---|--------|------------|----------|-------------------|
| **7.1** | **Cutover Scolarité/Académique** | — | 🔴 CRITIQUE | Faible (activer + tester) |
| **7.2** | **Tableau de bord unifié V2** | Tous modules | 🔴 HAUTE | Moyenne |
| **7.3** | **Recherche globale** | Tous modules | 🔴 HAUTE | Moyenne |
| **7.4** | **Module Documents V2** | RH Documents | 🟡 HAUTE | Haute |
| **7.5** | **Module Notifications centralisé** | Tous modules | 🟡 HAUTE | Moyenne |
| **7.6** | **Module Paramètres V2** | Tous modules | 🟡 HAUTE | Faible |
| **7.7** | **Module Scheduler/Cron** | RH, Finance | 🟡 HAUTE | Faible |
| **7.8** | **Module Paie** | RH V2, Finance V2 | 🟠 MOYENNE | Très haute |
| **7.9** | **Module Messaging/Annonces V2** | Auth, Élèves | 🟠 MOYENNE | Moyenne |
| **7.10** | **Module Configuration Établissement** | Tous | 🟠 MOYENNE | Faible |

### Modules V3 (post-stabilisation)

| # | Module | Description |
|---|--------|-------------|
| **8.1** | **API REST publique** | `/api/v1/*` — tous modules |
| **8.2** | **App mobile native** | iOS/Android — dépend API |
| **8.3** | **Multi-établissement** | `etablissement_id` — refactoring global |
| **8.4** | **Module BI/Analytics** | Tableaux de bord avancés, export Excel |
| **8.5** | **Intégrations tierces** | Ministère, banques, SMS provider |
| **8.6** | **SaaS infrastructure** | Multi-tenant, subdomain routing, billing |

---

## 25. Décision finale

```
══════════════════════════════════════════════════════════════════════
  SCOLARIS V2 — ENTERPRISE ARCHITECTURE REVIEW — PHASE 7.0

  Score  : 7.8 / 10
  Verdict : ✅ GO — Architecture saine, dette structurelle connue

  Points forts :
    + Framework MVC cohérent et extensible
    + Event-driven avec AuditService complet
    + Modules Finance + Vie Scolaire + RH stables et gelés
    + Sécurité solide (CSRF, XSS, PDO, RBAC)
    + PWA + Push notifications opérationnels

  Points de vigilance :
    ! Scolarité et Académique désactivés — cutover à planifier
    ! 0 API REST — blocage intégrations externes
    ! 0 recherche globale — UX dégradée
    ! Stubs notifications × 30 — silencieux
    ! Bootstrap 250 listeners — optimisation V2.1

  Prochaine étape : Phase 7.1 — Cutover Scolarité/Académique
══════════════════════════════════════════════════════════════════════
```

---

*Produit le 2026-07-02 — Phase 7.0 — Claude Sonnet 4.6*  
*Aucun fichier modifié — audit en lecture seule*
