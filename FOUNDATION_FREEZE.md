# FOUNDATION FREEZE — SCOLARIS V2
## Audit de stabilité des fondations avant développement des modules

> Date : 2026-06-30
> Version : V2.0-foundation
> Auditeur : Claude (ÉTAPE 10)
> **Décision : ✅ GO — Fondations gelées**

---

## SYNTHÈSE EXÉCUTIVE

| Couche | Statut | Score |
|---|---|---|
| Architecture Core | Stable | 9/10 |
| Base de données | Stable | 8/10 |
| RBAC | Stable | 10/10 |
| Services partagés | Partiel | 6/10 |
| Event System | Stable | 9/10 |
| Audit | Stable | 10/10 |
| Sécurité | Solide | 8/10 |
| Compatibilité V1 | Totale | 10/10 |
| **GLOBAL** | **Stable** | **8.8/10** |

---

## 1. ARCHITECTURE CORE

### Éléments validés

| Composant | Fichier | Statut |
|---|---|---|
| Application | `core/Application.php` | ✅ Singleton, bootstrap, error handlers, events chargés dans `run()` |
| Router | `core/Router.php` | ✅ GET/POST/PUT/DELETE, params nommés `{id}`, sous-namespaces `Api\` |
| Request | `core/Request.php` | ✅ Accès propre à GET/POST/FILES/headers |
| Controller | `core/Controller.php` | ✅ CSRF, auth, permissions, JSON, redirect, safe() XSS |
| Model | `core/Model.php` | ✅ CRUD générique, pagination, requêtes brutes — tout préparé |
| Database | `core/Database.php` | ✅ Singleton PDO, charset utf8mb4, exception mode |
| View | `core/View.php` | ✅ Layout `main`, rendu PHP natif, `View::json()` |
| Session | `core/Session.php` | ✅ samesite=Strict, httponly, regen 300s, CSRF bin2hex(32) |
| Logger | `core/Logger.php` | ✅ Niveaux (debug/info/warning/error/critical/security) |
| Event | `core/Event.php` | ✅ Classe abstraite avec `getName()` |
| Listener | `core/Listener.php` | ✅ Interface `handle(Event $event): void` |
| EventDispatcher | `core/EventDispatcher.php` | ✅ Statique, synchrone, isolation try/catch par handler |
| WebPush | `core/WebPush.php` | ✅ VAPID, push PWA |

### Observations
- Autoloader PSR-4 hand-written (7 namespaces enregistrés) — fonctionnel, sans Composer.
- Pas de conteneur DI : services instanciés avec `new` directement dans contrôleurs/handlers.
- Pas de pipeline middleware : `requireAuth()` / `requirePermission()` appelés manuellement par méthode.
- CSP envoie `'unsafe-inline'` — inévitable avec Tailwind CDN + icônes Lucide inline.

---

## 2. BASE DE DONNÉES

### 35 tables confirmées en production

```
absences            annees_scolaires    annonces            audit_logs
classes             controles           creneaux            depenses
depenses_categories eleves              emplois_du_temps    enseignements
frais_eleves        frais_types         justifications      matieres
migrations_log      moyennes_generales  moyennes_matieres   notes
notification_logs   notification_prefs  notifications       paiements
password_resets     periodes            permissions         professeurs
push_subscriptions  rbac_role_perms     rbac_roles          rbac_user_roles
role_permissions    salles              users
```

### 6 migrations appliquées avec succès

| Migration | Durée | Résultat |
|---|---|---|
| M001 — notes V2 | 34ms | Schema V2 (`controle_id`, FK→controles) |
| M002 — absences V2 | 2ms | Schema V2 (`session`, `statut_justif`, FK→classes/users) |
| M003 — annees_scolaires | 74ms | Référentiel peuplé (2024-2025, 2025-2026 active) |
| R001 — RBAC V2 | 1033ms | 7 rôles, 90 permissions, 267 associations |
| S001 — audit_logs | 75ms | Table BIGINT + FK users ON DELETE SET NULL |
| P001 — bulletins.view | 9ms | Correction gap RBAC (eleve+parent) |

### Schémas V2 validés

- `notes` : `controle_id` ✅, `matiere_id` absent ✅, FK→controles.id + eleves.id ✅
- `absences` : `session` ✅, `justifiee` absent ✅, FK→classes.id + eleves.id + users.id ✅
- `annees_scolaires` : 2 entrées, `2025-2026` active ✅

### Observations
- `role_permissions` V1 coexiste avec `rbac_role_permissions` V2 — pas de conflit, prefixes différents.
- `push_subscriptions` : table existante sans migration formelle (créée avant V2).
- `annees_scolaires.date_debut` / `date_fin` non peuplées (M003 n'insère que le libellé et active).
- `audit_logs` : 0 entrées (normal — aucune action HTTP depuis la migration).

---

## 3. RBAC

### Éléments validés

| Élément | Valeur | Statut |
|---|---|---|
| Rôles | 7 (admin, directeur, secretaire, comptable, enseignant, parent, eleve) | ✅ |
| Permissions DB | 90 codes (V1 + V2 + aliases) | ✅ |
| Associations rôle↔permission | 267 | ✅ |
| Utilisateurs affectés (rbac_user_roles) | 7/7 | ✅ |
| Audit gaps V1 vs RBAC V2 | 0 manquants (7/7 OK après P001) | ✅ |

### Stratégie de compatibilité

```
Login → UserModel::getPermissions($role, $userId)
  ├─ rbac_user_roles existe ET user a des entrées → RBAC V2 DB (actif)
  │   └─ Retourne codes V2 (inclut codes V1 comme bulletins.view, notes.edit…)
  └─ Sinon → config/permissions.php (fallback V1 silencieux)
      └─ + v1ToV2Aliases() ajoute notes.update, absences.approve.own…
```

Résultat : contrôleurs V1 (`.edit`, `.view_own`) et V2 (`.update`, `.view.own`) fonctionnent simultanément **sans modification des contrôleurs**.

---

## 4. SERVICES PARTAGÉS

### Implémentés et validés

| Service | Fichier | Fonctionnalités |
|---|---|---|
| **AuditService** | `app/Services/AuditService.php` | log, logCreate, logUpdate, logDelete, logLogin, logPermissionDenied, search, getEntityHistory, sanitize clés sensibles, diff avant/après |
| **NotificationService** | `app/Services/NotificationService.php` | notify, notifyBulk, onAbsence, onPaiement, onNote, onAnnonce, sendTest — canaux : interne + email + SMS |
| **UploadService** | `app/Services/UploadService.php` | upload, delete, url, validate — 6 types (avatar, photo_eleve, photo_professeur, logo_etablissement, justification, import_csv) — GD resize, MIME réel |
| EmailService | `app/Services/EmailService.php` | Wrappeur `mail()` avec HTML builder |
| SmsService | `app/Services/SmsService.php` | API SMS externe configurable |

### Non implémentés (reportés V2.1)

| Service | Raison |
|---|---|
| PdfService | Décision D1 (Dompdf vs TCPDF) non prise — PDF actuel = `window.print()` |
| ExportService | Hors scope ÉTAPE 9 |
| SearchService | Hors scope |
| StatisticsService | Hors scope |
| BackupService | Hors scope — approche non décidée (D4) |
| ParametreService | 3 tables + 51 paramètres + 10 sections — module entier |

---

## 5. EVENT SYSTEM

### Architecture validée

```
EventDispatcher::dispatch(new EvenementXxx(...))
  └─ foreach $listeners[$eventClass] as $listener
       └─ try { $listener->handle($event) } catch(\Throwable) { error_log() }
          // Isolation garantie : un handler en erreur n'interrompt pas les suivants
```

### Registre des événements

| Événement | Handlers | Dispatché depuis |
|---|---|---|
| `EleveCreated` | Audit, StatsCache | `EleveController::store()`, `::import()` |
| `PaiementValide` | Audit, Notification | `PaiementController::store()` |
| `NoteAjoutee` | Audit, Notification | `NoteController::storeSaisie()` |
| `AbsenceCreee` | Audit, Notification, StatsCache | `AbsenceController::store()` |
| `DocumentGenere` | Audit | ⚠️ Jamais dispatché (handler enregistré, aucun contrôleur ne dispatch) |
| `ImportCsvCompleted` | Audit, StatsCache | `EleveController::import()` |
| `ControleUpdated` | Audit | `NoteController::updateControle()` |

### Observations
- `DocumentGenere` : événement orphelin — handler en attente d'un `ExportService` ou `BackupService`.
- Dispatching synchrone uniquement — pas de queue asynchrone.

---

## 6. AUDIT

### Validé complet

- Table `audit_logs` : 11 colonnes, BIGINT id, JSON avant/apres, FK users ON DELETE SET NULL.
- Masquage automatique : password, token, api_key, vapid_private_key, smtp_password, _csrf_token, secret.
- Dégradation silencieuse : échec INSERT → `error_log()`, contrôleur non impacté.
- `logUpdate()` : diff automatique avant/après — ne log que si changement réel.
- `logPermissionDenied()` : enregistre les tentatives d'accès refusées.
- `logLogin()` : succès et échecs de connexion.

---

## 7. SÉCURITÉ

### Éléments validés

| Mécanisme | Implémentation | Statut |
|---|---|---|
| CSRF | `hash_equals()` + `bin2hex(random_bytes(32))` | ✅ |
| Session fixation | `session_regenerate_id(true)` toutes les 5 min | ✅ |
| Cookie session | `httponly=true`, `samesite=Strict`, `secure=HTTPS` | ✅ |
| XSS sorties | `htmlspecialchars(ENT_QUOTES\|ENT_SUBSTITUTE, UTF-8)` | ✅ |
| SQL injection | 100% requêtes préparées PDO | ✅ |
| Upload MIME | `mime_content_type()` — pas le type navigateur | ✅ |
| Path traversal upload | `basename()` + regex `^[\w\-\.]+$` | ✅ |
| En-têtes sécurité | X-Frame-Options, X-Content-Type-Options, Referrer-Policy, CSP | ✅ |
| Accès fichiers | `UploadController::serve()` — auth requise, type validé | ✅ |
| Mots de passe | bcrypt via `password_hash(PASSWORD_BCRYPT)` | ✅ |

### Points d'attention (non bloquants)

| # | Point | Niveau | Mitigation |
|---|---|---|---|
| S1 | CSP avec `'unsafe-inline'` | Faible | Nécessaire pour Tailwind CDN + icônes — acceptable tant que CDN = source de confiance |
| S2 | Pas de rate limiting sur `/login` | Modéré | Pas d'exposition publique signalée — à ajouter en V2.1 |
| S3 | X-Forwarded-For non filtré sur liste d'IP de confiance | Faible | Correctif simple — ajouter une whitelist de proxies |
| S4 | `cookie secure` conditionnel à `$_SERVER['HTTPS']` | Faible | OK en dev WAMP ; en prod HTTPS obligatoire |

---

## 8. COMPATIBILITÉ V1

| Élément V1 | État | Garantie |
|---|---|---|
| `config/permissions.php` | Présent, non modifié | Fallback actif si RBAC V2 absent |
| Codes V1 (`notes.edit`, `absences.justify`…) | Dans RBAC V2 DB | Contrôleurs V1 fonctionnent sans toucher |
| `v1ToV2Aliases()` | Actif | Futurs contrôleurs V2 fonctionnent aussi |
| Tables V1 (`role_permissions`) | Présente, non supprimée | Aucun conflit avec rbac_ tables |
| Données V1 (`notes_v1_backup`, `absences_v1_backup`) | Non créées (tables étaient déjà V2) | Pas de perte de données |
| Upload legacy (`public/uploads/`) | Servi par `UploadController` | Compatibilité transparente pour photos existantes |
| Format session `['permissions' => [...]]` | Inchangé | Aucune déconnexion forcée des utilisateurs |

---

## 9. DETTE TECHNIQUE RESTANTE

### Priorité Haute (à traiter en Phase 1 ou 2)

| # | Dette | Impact |
|---|---|---|
| DT1 | Pas de conteneur DI — services instanciés avec `new` | Tests unitaires difficiles ; couplage fort |
| DT2 | `DocumentGenere` jamais dispatché | Event handler orphelin — trompe l'équipe |
| DT3 | `annees_scolaires.date_debut/fin` vides | Filtrage par plage de dates impossible |
| DT4 | Pas de rate limiting sur `/login` | Risque brute-force si exposé publiquement |

### Priorité Moyenne (V2.1)

| # | Dette | Impact |
|---|---|---|
| DT5 | `push_subscriptions` sans migration formelle | Pas de tracking de schema, risque en migration |
| DT6 | Dispatching événements synchrone uniquement | Notifications bloquent le thread HTTP si lentes |
| DT7 | `role_permissions` V1 et `rbac_role_permissions` V2 coexistent | Confusion développeur futur |
| DT8 | CSP `'unsafe-inline'` | Score sécurité sous-optimal |
| DT9 | Pas de middleware pipeline | Auth/CSRF vérifiés manuellement dans chaque méthode |

---

## 10. ÉLÉMENTS REPORTÉS EN V2.1

| Élément | Raison du report | Prérequis |
|---|---|---|
| `PdfService` | Décision D1 (moteur) non prise | Choix Dompdf vs TCPDF |
| `ExportService` | Hors scope fondations | PdfService |
| `SearchService` | Non critique V2 | — |
| `StatisticsService` | Tableau de bord V2 | — |
| `BackupService` | Approche non décidée (mysqldump vs PHP) | — |
| `ParametreService` | Module entier (51 params, 3 tables) | — |
| Rate limiting login | Simple à ajouter, non bloquant | — |
| RBAC admin UI | Gestion des rôles/permissions en interface | ParametreService |
| Push V2 (VAPID canal NotificationService) | PwaConfigService non implémenté | ParametreService |
| `annees_scolaires` date_debut/fin | Non requis par modules immédiats | — |

---

## 11. RISQUES CONNUS

| # | Risque | Probabilité | Impact | Mitigation |
|---|---|---|---|---|
| R1 | Notes V1 historiques dans `notes_v1_backup` (si existaient) | Faible | Modéré | Migration manuelle possible — `controle_id` requis |
| R2 | `DocumentGenere` enregistré mais jamais dispatché | Faible | Faible | Nettoyer si `ExportService` pas en scope |
| R3 | CSP `'unsafe-inline'` contourne la politique XSS pour les scripts inline | Faible | Modéré | Remplacer CDN par bundle local pour tightening |
| R4 | `AuditService` en dégradation silencieuse → perte de logs si DB saturée | Faible | Modéré | Surveiller les `error_log` en prod |
| R5 | `NoteAjoutee` : `periodePubliee` doit être renseigné sinon pas de notification | Faible | Faible | Vérifier les champs optionnels dans NotificationHandler |

---

## 12. RECOMMANDATION

### ✅ GO — ARCHITECTURE FREEZE DÉCLARÉE

Les fondations SCOLARIS V2 sont **stables et gelées** à compter du **2026-06-30**.

**Critères satisfaits :**
- [x] Core MVC fonctionnel et testé
- [x] Base de données V2 migrée (6 migrations, 0 erreur)
- [x] RBAC V2 actif, 7/7 rôles validés, aucun gap de permissions
- [x] Services critiques implémentés (Audit, Notification, Upload)
- [x] Event System câblé (7 événements, 3 handlers, isolation garantie)
- [x] Compatibilité V1 totale (contrôleurs, permissions, session, uploads)
- [x] Sécurité : CSRF, session, XSS, SQL, upload, en-têtes HTTP

**Règle de gel :**
> Toute modification des couches Core, RBAC, Event System ou schéma DB doit passer par une migration versionnée et une revue avant merge. Les modules métier ne modifient pas les fondations — ils s'appuient dessus.

**Prochaines étapes autorisées :**
1. Développement des modules métier V2 (ParametreService, module Bulletins V2, etc.)
2. Résolution des dettes DT1–DT4 en parallèle
3. Décision D1 (moteur PDF) avant d'implémenter les exports

---

*FOUNDATION_FREEZE.md — SCOLARIS V2 | Architecture Freeze 2026-06-30*
