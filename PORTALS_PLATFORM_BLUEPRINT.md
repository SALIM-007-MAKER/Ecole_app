# PORTALS PLATFORM BLUEPRINT
## Architecture Plateforme Portails — SCOLARIS V2

**Date :** 2026-07-04  
**Statut :** ARCHITECTURE — Aucune implémentation  
**Prérequis :** PRE_PORTAL_REVIEW.md validé (Score 7.4/10 GO WITH FIXES)  
**Modules backend :** 11 modules terminés et gelés  

---

## TABLE DES MATIÈRES

1. [Vue d'ensemble](#1-vue-densemble)
2. [Principes architecturaux](#2-principes-architecturaux)
3. [Portal Framework — 7 moteurs](#3-portal-framework--7-moteurs)
4. [Base de données portails](#4-base-de-données-portails)
5. [Architecture des permissions portails](#5-architecture-des-permissions-portails)
6. [Structure des routes](#6-structure-des-routes)
7. [Portail Administration](#7-portail-administration)
8. [Portail Direction](#8-portail-direction)
9. [Portail Enseignant](#9-portail-enseignant)
10. [Portail Élève](#10-portail-élève)
11. [Portail Parent](#11-portail-parent)
12. [Portail Comptabilité](#12-portail-comptabilité)
13. [Portail RH](#13-portail-rh)
14. [Catalogue de widgets](#14-catalogue-de-widgets)
15. [API First Layer](#15-api-first-layer)
16. [Système de notifications portails](#16-système-de-notifications-portails)
17. [Recherche globale](#17-recherche-globale)
18. [Responsive, Mobile & PWA](#18-responsive-mobile--pwa)
19. [Multi-établissements & SaaS](#19-multi-établissements--saas)
20. [Événements portails](#20-événements-portails)
21. [Plan d'implémentation](#21-plan-dimplémentation)
22. [Dépendances inter-modules](#22-dépendances-inter-modules)

---

## 1. VUE D'ENSEMBLE

### Contexte

SCOLARIS V2 dispose de 11 modules backend complets. La plateforme Portails est une couche de **présentation contextuelle** au-dessus de ces modules — elle ne contient aucune logique métier, mais orchestre la consommation des services existants selon le profil de l'utilisateur.

### Les 7 portails

| Portail | Rôle(s) cible | URL préfixe | Couleur thème |
|---|---|---|---|
| Administration | `admin` | `/v2/portals/admin` | Violet `#7c3aed` |
| Direction | `directeur` | `/v2/portals/direction` | Indigo `#4338ca` |
| Enseignant | `enseignant` | `/v2/portals/enseignant` | Teal `#0d9488` |
| Élève | `eleve` | `/v2/portals/eleve` | Blue `#2563eb` |
| Parent | `parent` | `/v2/portals/parent` | Emerald `#059669` |
| Comptabilité | `comptable` + `secretaire` | `/v2/portals/comptabilite` | Amber `#d97706` |
| RH | `directeur` + `admin` | `/v2/portals/rh` | Rose `#e11d48` |

### Positionnement dans l'architecture globale

```
┌─────────────────────────────────────────────────────────────────────┐
│                        SCOLARIS V2                                   │
├─────────────────────────────────────────────────────────────────────┤
│  COUCHE PORTAILS (Phase 12.x)                                       │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ │
│  │  Admin   │ │Direction │ │Enseignant│ │  Élève   │ │  Parent  │ │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘ │
│  ┌──────────────────────────┐ ┌────────────────────────────────────┐│
│  │    Comptabilité          │ │          RH                        ││
│  └──────────────────────────┘ └────────────────────────────────────┘│
├─────────────────────────────────────────────────────────────────────┤
│  PORTAL FRAMEWORK                                                    │
│  DashboardEngine │ WidgetEngine │ MenuEngine │ NotificationCenter   │
│  ShortcutEngine  │ GlobalSearch │ UserPreferences                   │
├─────────────────────────────────────────────────────────────────────┤
│  MODULES BACKEND (consommés en lecture seule depuis les portails)   │
│  Scolarité │ Académique │ Finance │ Vie Scolaire │ RH               │
│  Documents │ Communication │ Bibliothèque │ Inventaire │ Rapports   │
├─────────────────────────────────────────────────────────────────────┤
│  CORE FRAMEWORK                                                      │
│  Router │ EventDispatcher │ Controller │ Session │ View             │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 2. PRINCIPES ARCHITECTURAUX

### 2.1 Lecture seule

Les portails **ne contiennent aucune logique métier**. Ils appellent exclusivement les Services des modules existants, exactement comme le fait le backend admin V2.

```
INTERDIT dans les portails :
  ✗ Requêtes SQL directes
  ✗ Écriture dans les tables modules
  ✗ Duplication de calculs (moyennes, soldes, etc.)
  ✗ Règles métier dupliquées

AUTORISÉ :
  ✓ Appel de Services V2 (read) : EleveService, NoteService, etc.
  ✓ Appel de Repositories (read) pour agrégations portail-spécifiques
  ✓ Dispatch d'Events portails : PortalAccessed, DashboardViewed, etc.
  ✓ Écriture dans les tables portail_* (préférences, raccourcis, cache)
```

### 2.2 Un module Portals

Tous les portails appartiennent à un seul module `App\Modules\Portals\` :

```
app/Modules/Portals/
├── Framework/
│   ├── DashboardEngine.php
│   ├── WidgetEngine.php
│   ├── MenuEngine.php
│   ├── ShortcutEngine.php
│   ├── NotificationCenter.php
│   ├── GlobalSearch.php
│   └── UserPreferences.php
├── Admin/
│   ├── Controllers/
│   ├── Widgets/
│   └── Views/
├── Direction/
├── Enseignant/
├── Eleve/
├── Parent/
├── Comptabilite/
├── RH/
├── Shared/
│   ├── Widgets/      ← Widgets partagés entre portails
│   ├── Views/layouts/
│   └── DTO/
├── Events/
├── Listeners/
├── routes.php
└── module.json
```

### 2.3 Layouts portail

Chaque portail a son propre layout PHP héritant d'un `portal-base.php` :

```
app/Modules/Portals/Shared/Views/layouts/
├── portal-base.php          ← Base commune (head, meta, JS commun)
├── portal-admin.php         ← Sidebar pleine, admin tools
├── portal-direction.php     ← Wide layout, KPI-focused
├── portal-enseignant.php    ← Sidebar + quick actions
├── portal-eleve.php         ← Card-based, mobile-first
├── portal-parent.php        ← Child-switcher, notification-heavy
├── portal-comptabilite.php  ← Table-dense, numbers
└── portal-rh.php            ← Approval workflows visible
```

### 2.4 API First

Chaque action portail a deux rendus : HTML (session) et JSON (token/API) via le même Controller :

```php
// Exemple — PortalEleveController
public function dashboard(): void
{
    $data = $this->dashboardEngine->build('eleve', $etab, $userId, $perms);

    if ($this->request->wantsJson()) {
        $this->json($data->toArray());
        return;
    }
    $this->render('Portals::Eleve/dashboard', $data->toArray(), 'portal-eleve');
}
```

### 2.5 Règle de nommage des routes

```
HTML  : /v2/portals/{portal}/{action}
API   : /api/v2/portals/{portal}/{resource}
```

Exemples :
- `GET /v2/portals/eleve/dashboard` → HTML
- `GET /api/v2/portals/eleve/dashboard` → JSON
- `GET /api/v2/portals/enseignant/classes/{id}/notes` → JSON

---

## 3. PORTAL FRAMEWORK — 7 MOTEURS

### 3.1 DashboardEngine

Orchestre la construction des dashboards.

```
Interface DashboardEngine
├── build(portal, etab, userId, perms): DashboardDTO
├── getWidgets(portal): WidgetInterface[]
├── filterByPermissions(widgets, perms): WidgetInterface[]
├── applyUserLayout(widgets, savedLayout): WidgetInterface[]
└── renderWidget(widget, etab, userId): WidgetDataDTO
```

**DashboardDTO :**
```
DashboardDTO
├── portal: string
├── titre: string
├── widgets: WidgetDataDTO[]
├── alertes: AlerteDTO[]
├── layout: array          ← positions grille sauvegardées
└── metadata: array
```

**Grille de widgets :** Responsive CSS Grid — 12 colonnes.

| Taille widget | Colonnes desktop | Colonnes mobile |
|---|---|---|
| `xs` | 3 | 12 |
| `sm` | 4 | 12 |
| `md` | 6 | 12 |
| `lg` | 8 | 12 |
| `xl` | 12 | 12 |

---

### 3.2 WidgetEngine

Registre et rendu de tous les widgets disponibles.

```
Interface WidgetInterface
├── getId(): string                        ← slug unique ex: 'eleve.notes_recentes'
├── getTitle(): string
├── getIcon(): string                      ← Lucide icon name
├── getPortals(): string[]                 ← portails où ce widget est disponible
├── getPermissions(): string[]             ← permissions requises
├── getDefaultSize(): string               ← xs|sm|md|lg|xl
├── getDefaultOrder(): int
├── isRefreshable(): bool
├── getRefreshInterval(): int              ← secondes (0 = pas de refresh auto)
├── getData(etab, userId, config): array
└── getTemplate(): string                  ← 'Portals::Shared/Widgets/notes_recentes'
```

**WidgetRegistry :**
```
WidgetRegistry
├── register(WidgetInterface widget): void
├── get(id): WidgetInterface
├── getForPortal(portal): WidgetInterface[]
└── getAvailable(portal, perms): WidgetInterface[]
```

---

### 3.3 MenuEngine

Construction dynamique de la navigation sidebar.

```
Interface MenuEngine
├── build(portal, uri, perms): MenuDTO
├── addBadge(itemId, count): void
└── render(menu): string
```

**MenuItemDTO :**
```
MenuItemDTO
├── id: string
├── label: string
├── icon: string           ← Lucide icon
├── url: string
├── permission: ?string    ← null = toujours visible
├── badge: ?int            ← null = pas de badge
├── active: bool
├── children: MenuItemDTO[]
└── separator: bool
```

**Badges dynamiques :**

| Item | Source du badge |
|---|---|
| Notifications | `NotificationCenter::getUnreadCount()` |
| Messages | Communication module — threads non lus |
| Congés à valider | `LeaveService::getPendingCount()` (portail RH) |
| Factures impayées | `InvoiceService::getOverdueCount()` (portail Compta) |
| Bulletins disponibles | `BulletinRepository::getAvailableCount()` (portail Parent/Élève) |
| Emprunts en retard | `EmpruntRepository::getLateCount()` (portail Élève) |

---

### 3.4 ShortcutEngine

Raccourcis personnalisables sur le dashboard de chaque portail.

```
Interface ShortcutEngine
├── getDefaults(portal): ShortcutDTO[]   ← Raccourcis par défaut par portail
├── getUserShortcuts(userId, portal, etab): ShortcutDTO[]
├── saveShortcuts(userId, portal, etab, shortcuts): void
└── getSuggestedShortcuts(portal, perms): ShortcutDTO[]
```

**ShortcutDTO :**
```
ShortcutDTO
├── id: int
├── label: string
├── url: string
├── icon: string           ← Lucide icon
├── color: string          ← Tailwind color class
└── ordre: int
```

**Raccourcis par défaut (exemples) :**

| Portail | Raccourcis par défaut |
|---|---|
| Administration | Utilisateurs, Paramètres, Audit, Modules |
| Direction | Rapport mensuel, Bulletins, Effectifs, Finance |
| Enseignant | Saisir notes, Faire appel, Mon EDT, Mes classes |
| Élève | Mes notes, Mon EDT, Bibliothèque, Messagerie |
| Parent | Notes enfant, Absences, Paiements, Messagerie |
| Comptabilité | Nouvelle facture, Encaissement, Caisse, Rapports |
| RH | Pointage, Valider congés, Contrats, Formations |

---

### 3.5 NotificationCenter

Agrège les notifications de tous les modules pour l'utilisateur courant.

```
Interface NotificationCenter
├── getUnreadCount(userId, etab): int
├── getRecent(userId, etab, limit): NotifDTO[]
├── getAll(userId, etab, page, perPage): PaginatedResult
├── markRead(notifId, userId): void
├── markAllRead(userId, etab): void
└── getPreferences(userId, etab): NotifPrefsDTO
```

**Sources de notifications :**

| Module | Types de notifications |
|---|---|
| Communication | Messages reçus, diffusions |
| Académique | Bulletin disponible, note publiée |
| Finance | Facture émise, retard de paiement |
| Vie Scolaire | Absence détectée, sanction, récompense |
| RH | Congé validé/refusé, contrat expirant, évaluation |
| Documents | Document partagé, signature requise |
| Bibliothèque | Réservation disponible, emprunt en retard |
| Inventaire | Alerte stock, maintenance requise |

**Rendu Notification Center :**
- Bell icon dans le header portail avec badge compteur
- Dropdown : 10 dernières notifs avec mark-as-read
- Page dédiée `/v2/portals/{portal}/notifications`
- API JSON : `GET /api/v2/portals/{portal}/notifications`

---

### 3.6 GlobalSearch

Recherche unifiée à travers les données du portail courant.

```
Interface GlobalSearch
├── register(SearchHandlerInterface handler): void
├── search(query, portal, etab, userId, perms, limit): SearchResultsDTO
└── getHandlersForPortal(portal): SearchHandlerInterface[]
```

```
Interface SearchHandlerInterface
├── getModule(): string
├── getPortal(): string[]
├── getPermissions(): string[]
├── search(query, etab, userId, limit): SearchResultDTO[]
└── getPriority(): int          ← ordre des résultats, 1=plus important
```

**SearchResultDTO :**
```
SearchResultDTO
├── module: string
├── type: string          ← 'eleve', 'enseignant', 'facture', etc.
├── id: int
├── titre: string
├── sousTitre: string
├── url: string           ← lien direct vers la ressource
├── icon: string
└── highlight: string     ← extrait avec termes surlignés
```

**Handlers de recherche par portail :**

| Portail | Handlers actifs |
|---|---|
| Administration | Élèves, Enseignants, Utilisateurs, Documents, Factures |
| Direction | Élèves, Classes, Rapports, Bulletins |
| Enseignant | Mes élèves, Mes classes, Notes, Documents |
| Élève | Notes propres, Documents propres, Ouvrages biblio |
| Parent | Enfants, Notes, Factures, Absences |
| Comptabilité | Factures, Paiements, Élèves (facturation), Dépenses |
| RH | Employés, Enseignants, Contrats, Formations |

---

### 3.7 UserPreferences

Persistance des préférences de personnalisation par utilisateur et par portail.

```
Interface UserPreferences
├── get(userId, portal, etab): PreferencesDTO
├── save(userId, portal, etab, data): void
├── getWidgetLayout(userId, portal, etab): array
├── saveWidgetLayout(userId, portal, etab, layout): void
├── getTheme(userId, portal): string
├── saveTheme(userId, portal, theme): void
└── reset(userId, portal, etab): void
```

**PreferencesDTO :**
```
PreferencesDTO
├── widgetLayout: array       ← [{id, col, row, w, h}, ...]
├── hiddenWidgets: string[]   ← widgets masqués par l'utilisateur
├── shortcuts: ShortcutDTO[]
├── theme: string             ← 'default' | 'compact' | 'comfort'
├── defaultPage: string       ← route de la page d'accueil
├── notifPrefs: array         ← préférences par type de notif
└── lang: string              ← 'fr' (fr par défaut, extensible)
```

---

## 4. BASE DE DONNÉES PORTAILS

### Tables SQL

```sql
-- Table 1 : Préférences portails
CREATE TABLE portal_preferences (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    user_id          INT          NOT NULL,
    portal           VARCHAR(50)  NOT NULL,
    widget_layout    JSON,
    hidden_widgets   JSON,
    shortcuts        JSON,
    theme            VARCHAR(20)  NOT NULL DEFAULT 'default',
    default_page     VARCHAR(200),
    notif_prefs      JSON,
    lang             VARCHAR(5)   NOT NULL DEFAULT 'fr',
    etablissement_id INT          NOT NULL,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at       TIMESTAMP    NULL,
    UNIQUE KEY uq_user_portal_etab (user_id, portal, etablissement_id),
    INDEX idx_user_id (user_id),
    INDEX idx_portal (portal),
    INDEX idx_etab (etablissement_id)
);

-- Table 2 : Cache widgets (TTL-based)
CREATE TABLE portal_widget_cache (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    portal           VARCHAR(50)  NOT NULL,
    widget_id        VARCHAR(100) NOT NULL,
    user_id          INT,                    -- NULL = cache partagé établissement
    etablissement_id INT          NOT NULL,
    data             JSON         NOT NULL,
    expires_at       TIMESTAMP    NOT NULL,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_widget_cache (portal, widget_id, etablissement_id, user_id),
    INDEX idx_expires (expires_at),
    INDEX idx_portal_etab (portal, etablissement_id)
);

-- Table 3 : Log accès portails (analytics UX)
CREATE TABLE portal_access_logs (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    user_id          INT          NOT NULL,
    portal           VARCHAR(50)  NOT NULL,
    page             VARCHAR(200) NOT NULL,
    action           VARCHAR(100),
    ip               VARCHAR(45),
    user_agent       VARCHAR(500),
    etablissement_id INT          NOT NULL,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_portal (user_id, portal),
    INDEX idx_etab_date (etablissement_id, created_at),
    INDEX idx_created (created_at)
);
```

### Dépendances tables existantes (lecture seule)

Les portails **lisent** les tables suivantes sans jamais les modifier :

| Module | Tables lues |
|---|---|
| Scolarité | `eleves`, `classes`, `inscriptions`, `familles`, `matieres` |
| Académique | `notes_v2`, `evaluations_v2`, `moyennes_v2`, `bulletins_v2`, `classements_v2` |
| Finance | `finance_invoices`, `finance_payments`, `finance_cashregister_sessions`, `finance_journals` |
| Vie Scolaire | `vs_absences`, `vs_presences`, `vs_retards`, `vs_incidents_discipline`, `vs_recompenses`, `vs_emplois_du_temps`, `vs_activites` |
| RH | `rh_employees`, `rh_contracts`, `rh_leaves`, `rh_presences`, `rh_evaluations`, `rh_trainings` |
| Documents | `doc_documents`, `doc_folders` |
| Communication | `comm_notifications`, `comm_threads`, `comm_messages` |
| Bibliothèque | `biblio_ouvrages`, `biblio_emprunts`, `biblio_reservations` |
| Inventaire | `inv_articles`, `inv_stock_alertes` |
| Rapports & BI | `bi_kpi_snapshots`, `bi_exports` |

---

## 5. ARCHITECTURE DES PERMISSIONS PORTAILS

### 5.1 Nouvelles permissions portails

Ces permissions s'ajoutent à `config/permissions.php` pour chaque rôle :

```php
// Portail Administration — rôle admin
'portal.admin.access'

// Portail Direction — rôles directeur, admin
'portal.direction.access'

// Portail Enseignant — rôle enseignant
'portal.enseignant.access'

// Portail Élève — rôle eleve
'portal.eleve.access'

// Portail Parent — rôle parent
'portal.parent.access'

// Portail Comptabilité — rôles comptable, secretaire
'portal.comptabilite.access'

// Portail RH — rôles directeur, admin
'portal.rh.access'
```

### 5.2 Matrix permissions → portail

| Permission portail | Rôles autorisés |
|---|---|
| `portal.admin.access` | `admin` |
| `portal.direction.access` | `directeur`, `admin` |
| `portal.enseignant.access` | `enseignant` |
| `portal.eleve.access` | `eleve` |
| `portal.parent.access` | `parent` |
| `portal.comptabilite.access` | `comptable`, `secretaire` |
| `portal.rh.access` | `directeur`, `admin` |

### 5.3 Permissions widget

Chaque widget déclare ses permissions requises. Un widget non autorisé est **invisible** (non rendu), pas masqué via CSS.

```
Exemple : FinanceWidget dans le portail Direction
  permissions: ['finance.dashboard.view', 'finance.rapports.view']
  → Visible pour: admin, directeur
  → Invisible pour: enseignant, comptable, parent, eleve
```

### 5.4 Isolation des données

Les portails Élève et Parent appliquent un filtrage strict :

- **Portail Élève** : données filtrées par `eleve_id = currentUser()['eleve_id']`
- **Portail Parent** : données filtrées par `famille_id = currentUser()['famille_id']` → tous les enfants liés
- Aucune donnée d'un autre élève accessible même en manipulant l'URL

```php
// PortalEleveController — exemple d'isolation
private function getEleveId(): int
{
    $user = $this->currentUser();
    $eleveId = (int)($user['eleve_id'] ?? 0);
    if ($eleveId === 0) {
        $this->redirect(BASE_URL . '/v2/portals/eleve/error-no-profile');
    }
    return $eleveId;
}
```

---

## 6. STRUCTURE DES ROUTES

### 6.1 Routes HTML (vues web)

```
/v2/portals/admin
    GET  /                          → AdminPortalController@dashboard
    GET  /utilisateurs              → AdminPortalController@utilisateurs
    GET  /modules                   → AdminPortalController@modules
    GET  /audit                     → AdminPortalController@audit
    GET  /parametres                → AdminPortalController@parametres
    GET  /notifications             → AdminPortalController@notifications

/v2/portals/direction
    GET  /                          → DirectionPortalController@dashboard
    GET  /rapports                  → DirectionPortalController@rapports
    GET  /scolarite                 → DirectionPortalController@scolarite
    GET  /academique                → DirectionPortalController@academique
    GET  /finance                   → DirectionPortalController@finance
    GET  /rh                        → DirectionPortalController@rh
    GET  /vie-scolaire              → DirectionPortalController@vieScolaire
    GET  /notifications             → DirectionPortalController@notifications

/v2/portals/enseignant
    GET  /                          → EnseignantPortalController@dashboard
    GET  /mes-classes               → EnseignantPortalController@mesClasses
    GET  /mes-classes/{id}          → EnseignantPortalController@classeDetail
    GET  /notes                     → EnseignantPortalController@notes
    GET  /notes/{classeId}/saisie   → EnseignantPortalController@saisieNotes
    GET  /appel/{classeId}          → EnseignantPortalController@appel
    POST /appel/{classeId}/soumettre → EnseignantPortalController@soumettreAppel
    GET  /emploi-du-temps           → EnseignantPortalController@emploiDuTemps
    GET  /bulletins                 → EnseignantPortalController@bulletins
    GET  /documents                 → EnseignantPortalController@documents
    GET  /messagerie                → EnseignantPortalController@messagerie
    GET  /notifications             → EnseignantPortalController@notifications
    GET  /profil                    → EnseignantPortalController@profil

/v2/portals/eleve
    GET  /                          → ElevePortalController@dashboard
    GET  /notes                     → ElevePortalController@notes
    GET  /bulletins                 → ElevePortalController@bulletins
    GET  /emploi-du-temps           → ElevePortalController@emploiDuTemps
    GET  /absences                  → ElevePortalController@absences
    GET  /bibliotheque              → ElevePortalController@bibliotheque
    GET  /activites                 → ElevePortalController@activites
    GET  /messagerie                → ElevePortalController@messagerie
    GET  /recompenses               → ElevePortalController@recompenses
    GET  /notifications             → ElevePortalController@notifications
    GET  /profil                    → ElevePortalController@profil

/v2/portals/parent
    GET  /                          → ParentPortalController@dashboard
    GET  /enfants/{id}/notes        → ParentPortalController@notes
    GET  /enfants/{id}/bulletins    → ParentPortalController@bulletins
    GET  /enfants/{id}/absences     → ParentPortalController@absences
    POST /enfants/{id}/absences/{aid}/justifier → ParentPortalController@justifierAbsence
    GET  /enfants/{id}/emploi-du-temps → ParentPortalController@emploiDuTemps
    GET  /paiements                 → ParentPortalController@paiements
    GET  /messagerie                → ParentPortalController@messagerie
    GET  /notifications             → ParentPortalController@notifications
    GET  /profil                    → ParentPortalController@profil

/v2/portals/comptabilite
    GET  /                          → ComptabilitePortalController@dashboard
    GET  /facturation               → ComptabilitePortalController@facturation
    GET  /encaissements             → ComptabilitePortalController@encaissements
    GET  /caisse                    → ComptabilitePortalController@caisse
    GET  /depenses                  → ComptabilitePortalController@depenses
    GET  /comptabilite              → ComptabilitePortalController@comptabilite
    GET  /rapports                  → ComptabilitePortalController@rapports
    GET  /notifications             → ComptabilitePortalController@notifications

/v2/portals/rh
    GET  /                          → RHPortalController@dashboard
    GET  /employes                  → RHPortalController@employes
    GET  /enseignants               → RHPortalController@enseignants
    GET  /contrats                  → RHPortalController@contrats
    GET  /conges                    → RHPortalController@conges
    POST /conges/{id}/valider       → RHPortalController@validerConge
    POST /conges/{id}/refuser       → RHPortalController@refuserConge
    GET  /presences                 → RHPortalController@presences
    GET  /evaluations               → RHPortalController@evaluations
    GET  /formations                → RHPortalController@formations
    GET  /documents                 → RHPortalController@documents
    GET  /notifications             → RHPortalController@notifications
```

### 6.2 Routes Préférences (communes à tous les portails)

```
POST /v2/portals/{portal}/preferences/widget-layout   → PreferencesController@saveWidgetLayout
POST /v2/portals/{portal}/preferences/shortcuts        → PreferencesController@saveShortcuts
POST /v2/portals/{portal}/preferences/theme            → PreferencesController@saveTheme
POST /v2/portals/{portal}/preferences/reset            → PreferencesController@reset
```

### 6.3 Routes API JSON

```
GET  /api/v2/portals/{portal}/dashboard
GET  /api/v2/portals/{portal}/widgets/{widgetId}
GET  /api/v2/portals/{portal}/menu
GET  /api/v2/portals/{portal}/notifications
GET  /api/v2/portals/{portal}/notifications/unread-count
POST /api/v2/portals/{portal}/notifications/{id}/read
POST /api/v2/portals/{portal}/notifications/read-all
GET  /api/v2/portals/{portal}/search?q={query}
GET  /api/v2/portals/{portal}/shortcuts
POST /api/v2/portals/{portal}/shortcuts
GET  /api/v2/portals/{portal}/preferences
POST /api/v2/portals/{portal}/preferences
```

---

## 7. PORTAIL ADMINISTRATION

### Accès
- Rôle requis : `admin`
- Permission : `portal.admin.access`
- URL : `/v2/portals/admin`

### Dashboard — Widgets

| Widget | ID | Taille | Refresh |
|---|---|---|---|
| Santé système | `admin.system_health` | md | 60s |
| Effectifs globaux | `admin.global_effectif` | sm | non |
| Activité récente (audit) | `admin.recent_audit` | lg | 30s |
| Alertes critiques | `admin.alerts` | md | 30s |
| État des modules | `admin.modules_status` | md | non |
| Taux de paiement global | `admin.payment_rate` | sm | non |
| Approbations en attente | `admin.pending_approvals` | sm | 60s |
| Notifications | `shared.notifications` | sm | 30s |
| Raccourcis | `shared.shortcuts` | sm | non |
| Graphique activité (7j) | `admin.activity_chart` | xl | non |

### Navigation sidebar

```
Dashboard
├── Vue d'ensemble
├── Statistiques système

Gestion
├── Utilisateurs          [badge: users.view]
├── Rôles & permissions   [admin]
├── Paramètres système

Modules V2
├── Scolarité
├── Académique
├── Finance
├── Vie Scolaire
├── RH
├── Documents
├── Communication
├── Bibliothèque
├── Inventaire
└── Rapports & BI

Supervision
├── Logs d'audit
├── Journaux d'erreurs
└── Performances

─────────────────
Notifications [badge]
Messages [badge]
```

### Sections principales

| Section | Description | Données sources |
|---|---|---|
| Vue d'ensemble | KPIs systèmes, santé modules | config/modules.php, audit_logs |
| Utilisateurs | Liste + création users, rôles | users, roles tables |
| Audit | Journal complet des actions | AuditService::search() |
| Paramètres | Configuration établissement | parametres module |
| Modules | Status + activation/désactivation | config/modules.php |

---

## 8. PORTAIL DIRECTION

### Accès
- Rôles : `directeur`, `admin`
- Permission : `portal.direction.access`
- URL : `/v2/portals/direction`

### Dashboard — Widgets

| Widget | ID | Taille | Permissions requises |
|---|---|---|---|
| KPIs stratégiques | `direction.kpis` | xl | `rapports.kpis.voir` |
| Taux de réussite | `direction.success_rate` | md | `academique.analytics.view` |
| Taux de recouvrement | `direction.recovery_rate` | md | `finance.rapports.view` |
| Effectif par niveau | `direction.effectif_niveau` | md | `eleves.view` |
| Présences globales | `direction.attendance_global` | sm | `attendance.view` |
| Congés RH en cours | `direction.leaves_active` | sm | `leave.view` |
| Bulletins statut | `direction.bulletins_status` | sm | `academique.bulletin.view` |
| Tendances (Chart.js) | `direction.trends` | xl | `rapports.kpis.voir` |
| Alertes pédagogiques | `direction.academic_alerts` | md | `academique.analytics.view` |
| Notifications | `shared.notifications` | sm | — |

### Navigation sidebar

```
Tableau de bord

Pilotage
├── Rapports & BI           [rapports.dashboard.direction]
├── KPIs                    [rapports.kpis.voir]
├── Exportations

Pédagogie
├── Scolarité (synthèse)    [eleves.view]
├── Résultats scolaires     [academique.analytics.view]
├── Bulletins               [academique.bulletin.view]
├── Vie scolaire            [attendance.view]

Finance
├── Synthèse financière     [finance.dashboard.view]
├── Rapports financiers     [finance.rapports.view]

Ressources Humaines
├── Tableau RH              [employee.view]
├── Congés à valider        [leave.approve] [badge]

─────────────────
Notifications [badge]
```

---

## 9. PORTAIL ENSEIGNANT

### Accès
- Rôle : `enseignant`
- Permission : `portal.enseignant.access`
- URL : `/v2/portals/enseignant`

### Dashboard — Widgets

| Widget | ID | Taille | Description |
|---|---|---|---|
| Emploi du temps du jour | `enseignant.today_schedule` | lg | Créneaux du jour avec classes |
| Classes à noter | `enseignant.pending_grades` | md | Évaluations sans notes |
| Absences du jour | `enseignant.today_absences` | md | Élèves absents dans mes classes |
| Semaine en cours | `enseignant.week_timetable` | xl | Grille hebdomadaire |
| Messages reçus | `shared.messages` | sm | Derniers messages |
| Bulletins à valider | `enseignant.bulletins_pending` | sm | Bulletins en attente signature |
| Mes classes | `enseignant.classes_summary` | md | Liste classes + effectifs |
| Activités encadrées | `enseignant.activities` | sm | Prochaines activités |
| Notifications | `shared.notifications` | sm | — |
| Raccourcis | `shared.shortcuts` | sm | — |

### Navigation sidebar

```
Mon tableau de bord

Mes classes
├── Vue d'ensemble
├── Effectifs
└── Affectations matières

Pédagogie
├── Saisie des notes        [academique.notes.manage]
├── Évaluations             [academique.evaluations.manage]
├── Bulletins               [academique.bulletin.view]

Présences
├── Faire l'appel           [attendance.session.create]
├── Historique absences     [attendance.view]
├── Retards                 [late.view]

Emploi du temps
├── Mon planning            [timetable.view]

Vie scolaire
├── Incidents discipline    [discipline.view]
├── Récompenses             [reward.view]
├── Activités               [activity.view]

Ressources
├── Documents               [document.view]
├── Bibliothèque            [biblio.view]

─────────────────
Messagerie [badge]  [communication.view]
Notifications [badge]
Mon profil RH
```

### Fonctionnalité clé — Appel en ligne

```
GET  /v2/portals/enseignant/appel/{classeId}
     → Affiche la liste des élèves de la classe
     → Statuts: Présent | Absent | En retard
POST /v2/portals/enseignant/appel/{classeId}/soumettre
     → Appelle PresenceService::openSession() + créé StudentAbsent events
     → Redirige vers confirmation
```

### Fonctionnalité clé — Saisie des notes

```
GET  /v2/portals/enseignant/notes/{evaluationId}/saisie
     → Table de saisie: élèves × notes × observations
POST /v2/portals/enseignant/notes/{evaluationId}/sauvegarder
     → Appelle NoteService::saveGrades()
     → Dispatch NoteCreated/NoteUpdated events
```

---

## 10. PORTAIL ÉLÈVE

### Accès
- Rôle : `eleve`
- Permission : `portal.eleve.access`
- URL : `/v2/portals/eleve`

### Dashboard — Widgets

| Widget | ID | Taille | Description |
|---|---|---|---|
| Mes dernières notes | `eleve.recent_grades` | md | 5 dernières notes avec matière |
| Emploi du temps du jour | `eleve.today_schedule` | lg | Cours d'aujourd'hui |
| Mes absences | `eleve.absences_summary` | sm | Nb absences / retards période |
| Mes emprunts biblio | `eleve.library_loans` | sm | Livres empruntés + date retour |
| Mes activités | `eleve.activities` | md | Activités inscrites |
| Bulletins disponibles | `eleve.bulletins` | sm | Bulletins publiés |
| Mes récompenses | `eleve.rewards` | sm | Dernières récompenses reçues |
| Annonces | `eleve.annonces` | md | Annonces établissement |
| Messages | `shared.messages` | sm | Derniers messages reçus |
| Notifications | `shared.notifications` | sm | — |

### Navigation (Mobile-first, bottom bar sur mobile)

```
Desktop sidebar :              Mobile bottom bar :
  Accueil                        🏠 Accueil
  Mes notes & bulletins          📊 Notes
  Mon emploi du temps            📅 Emploi du temps
  Mes absences                   📭 Messages
  Bibliothèque                   🔔 Notifications
  Activités scolaires
  Messagerie [badge]
  Récompenses
  Mon profil
```

### Données affichées et isolation

Toutes les données sont filtrées par `eleve_id` extrait de la session :

| Section | Données | Source |
|---|---|---|
| Notes | Notes propres par matière/période | `notes_v2 WHERE eleve_id = ?` |
| Bulletins | Bulletins publiés | `bulletins_v2 WHERE eleve_id = ? AND statut = 'publié'` |
| EDT | Emploi du temps de sa classe | `vs_emplois_du_temps WHERE classe_id = eleve.classe_id` |
| Absences | Ses absences uniquement | `vs_absences WHERE eleve_id = ?` |
| Biblio | Ses emprunts actifs | `biblio_emprunts WHERE emprunteur_id = ? AND statut != 'retourné'` |

---

## 11. PORTAIL PARENT

### Accès
- Rôle : `parent`
- Permission : `portal.parent.access`
- URL : `/v2/portals/parent`

### Fonctionnalité clé — Sélecteur d'enfant

Un parent peut avoir plusieurs enfants. Un `ChildSwitcherWidget` persistent permet de basculer entre les enfants :

```php
// Session parent enrichie
$_SESSION['_auth_user']['enfants'] = [
    ['id' => 12, 'prenom' => 'Amina', 'classe' => '6ème A'],
    ['id' => 15, 'prenom' => 'Karim', 'classe' => '4ème B'],
];
$_SESSION['_auth_user']['enfant_actif'] = 12; // Enfant sélectionné

// Changer d'enfant
POST /v2/portals/parent/changer-enfant { enfant_id: 15 }
```

### Dashboard — Widgets

| Widget | ID | Taille | Description |
|---|---|---|---|
| Sélecteur d'enfant | `parent.child_switcher` | md | Onglets enfants si plusieurs |
| Notes récentes | `parent.recent_grades` | md | Notes de l'enfant actif |
| Absences récentes | `parent.absences` | sm | Absences non justifiées alertées |
| Factures en attente | `parent.unpaid_invoices` | sm | Montant total impayé |
| EDT enfant | `parent.child_schedule` | lg | Planning semaine |
| Bulletins disponibles | `parent.bulletins` | sm | Badge si nouveau bulletin |
| Messages | `shared.messages` | sm | Derniers messages |
| Discipline | `parent.discipline_alert` | sm | Incidents récents si actifs |
| Notifications | `shared.notifications` | sm | — |
| Raccourcis | `shared.shortcuts` | sm | — |

### Navigation sidebar

```
Tableau de bord
[Sélecteur enfant si multiple]

Mon enfant (prénom actif)
├── Notes & Résultats       [notes.view_own]
├── Bulletins               [academique.bulletin.view]
├── Emploi du temps         [timetable.view]
├── Absences & Retards      [attendance.view]
│   └── Justifier absence   [attendance.justify]

Finances
├── Mes paiements           [finance.paiements.view.own]
├── Historique              [finance.paiements.view.own]

Vie scolaire
├── Activités               [activity.view]
├── Récompenses             [reward.view]

Communication
├── Messagerie [badge]      [communication.view]
├── Annonces

─────────────────
Notifications [badge]
Mon profil
```

---

## 12. PORTAIL COMPTABILITÉ

### Accès
- Rôles : `comptable`, `secretaire`
- Permission : `portal.comptabilite.access`
- URL : `/v2/portals/comptabilite`

### Dashboard — Widgets

| Widget | ID | Taille | Description |
|---|---|---|---|
| Recettes du jour | `compta.daily_revenue` | sm | Total paiements reçus aujourd'hui |
| Factures impayées | `compta.unpaid_invoices` | md | Nb + montant total impayé |
| État caisse | `compta.cashregister` | sm | Solde caisse session en cours |
| Derniers encaissements | `compta.recent_payments` | lg | 10 derniers paiements |
| Balance comptable | `compta.accounting_balance` | md | Actif / Passif période |
| Dépenses en attente | `compta.pending_expenses` | sm | Dépenses à valider |
| Courbe trésorerie | `compta.treasury_chart` | xl | Recettes/dépenses 30j (Chart.js) |
| Taux recouvrement | `compta.recovery_rate` | sm | % factures payées période |
| Notifications | `shared.notifications` | sm | — |
| Raccourcis | `shared.shortcuts` | sm | — |

### Navigation sidebar

```
Tableau de bord financier

Facturation
├── Toutes les factures     [finance.factures.view]
├── Nouvelle facture        [finance.factures.create]
├── Génération en masse     [finance.factures.masse]
├── Impayés / Alertes

Encaissements
├── Tous les paiements      [finance.paiements.view]
├── Enregistrer paiement    [finance.paiements.create]
├── Trop-perçus             [finance.paiements.trop_percu]

Caisse
├── Session du jour         [finance.caisse.view]
├── Mouvements              [finance.caisse.view]
├── Rapprochement           [finance.caisse.rapprocher]

Dépenses
├── Liste dépenses          [finance.decaissements.view]
├── Valider dépenses        [finance.decaissements.valider]

Comptabilité
├── Grand livre             [finance.comptabilite.view]
├── Balance générale        [finance.comptabilite.view]
├── Saisie manuelle         [finance.comptabilite.saisir]
├── Clôture exercice        [finance.comptabilite.exercice]

Rapports
├── Rapports financiers     [finance.rapports.view]
├── Exporter                [finance.rapports.export]

─────────────────
Notifications [badge]
```

---

## 13. PORTAIL RH

### Accès
- Rôles : `directeur`, `admin`
- Permission : `portal.rh.access`
- URL : `/v2/portals/rh`

### Dashboard — Widgets

| Widget | ID | Taille | Description |
|---|---|---|---|
| Effectif actuel | `rh.effectif` | sm | Total actifs (employés + enseignants) |
| Congés à valider | `rh.leaves_pending` | sm | [badge] Demandes en attente |
| Contrats expirants | `rh.expiring_contracts` | md | Expirent dans 30j |
| Présences du jour | `rh.today_attendance` | md | Pointages de la journée |
| Évaluations en cours | `rh.ongoing_evaluations` | sm | Campagnes ouvertes |
| Documents expirés | `rh.expired_documents` | sm | Docs RH à renouveler |
| Formations actives | `rh.active_trainings` | sm | Sessions en cours |
| Organigramme (mini) | `rh.orgchart` | lg | Aperçu hiérarchique |
| Notifications | `shared.notifications` | sm | — |
| Raccourcis | `shared.shortcuts` | sm | — |

### Navigation sidebar

```
Tableau de bord RH

Personnel
├── Employés                [employee.view]
├── Enseignants             [teacher.view]
├── Organisation            [organization.view]

Contrats
├── Tous les contrats       [contract.view]
├── Expirants prochainement
├── Créer contrat           [contract.create]

Présences
├── Pointage du jour        [rh.presence.view]
├── Historique              [rh.presence.view]
├── Régularisations         [rh.presence.update]

Congés
├── Demandes en attente     [leave.approve] [badge]
├── Calendrier des congés   [leave.view]
├── Soldes par employé      [leave.view]

Évaluations
├── Campagnes               [evaluation.view]
├── Plans de développement  [evaluation.view]

Formations
├── Catalogue               [training.view]
├── Sessions actives        [training.view]
├── Compétences validées    [training.validate]

Documents RH
├── Tous les documents      [hr_document.view]
├── Expirants               [hr_document.view]

─────────────────
Notifications [badge]
```

---

## 14. CATALOGUE DE WIDGETS

### Widgets partagés (Shared Widgets)

Ces widgets peuvent être ajoutés à n'importe quel portail selon les permissions.

| ID | Titre | Icon | Permissions | Taille | Refresh |
|---|---|---|---|---|---|
| `shared.notifications` | Notifications | `bell` | — | sm | 30s |
| `shared.messages` | Messagerie | `mail` | `communication.view` | sm | 60s |
| `shared.shortcuts` | Raccourcis | `zap` | — | sm | non |
| `shared.announcements` | Annonces | `megaphone` | `annonces.view` | md | non |
| `shared.calendar` | Calendrier | `calendar` | — | md | non |

### Widgets Administration

| ID | Titre | Icon | Permissions | Taille |
|---|---|---|---|---|
| `admin.system_health` | Santé système | `activity` | `admin` | md |
| `admin.global_effectif` | Effectifs | `users` | `eleves.view` | sm |
| `admin.recent_audit` | Activité récente | `list` | `users.view` | lg |
| `admin.alerts` | Alertes critiques | `alert-triangle` | `admin` | md |
| `admin.modules_status` | État modules | `layers` | `admin` | md |
| `admin.payment_rate` | Taux paiement | `trending-up` | `finance.dashboard.view` | sm |
| `admin.pending_approvals` | En attente | `clock` | varies | sm |
| `admin.activity_chart` | Activité 7j | `bar-chart-2` | `admin` | xl |

### Widgets Direction

| ID | Titre | Icon | Permissions | Taille |
|---|---|---|---|---|
| `direction.kpis` | KPIs stratégiques | `target` | `rapports.kpis.voir` | xl |
| `direction.success_rate` | Taux réussite | `award` | `academique.analytics.view` | md |
| `direction.recovery_rate` | Recouvrement | `dollar-sign` | `finance.rapports.view` | md |
| `direction.effectif_niveau` | Effectif/niveau | `users` | `eleves.view` | md |
| `direction.attendance_global` | Présences | `user-check` | `attendance.view` | sm |
| `direction.leaves_active` | Congés RH | `umbrella` | `leave.view` | sm |
| `direction.bulletins_status` | Bulletins | `file-text` | `academique.bulletin.view` | sm |
| `direction.trends` | Tendances | `line-chart` | `rapports.kpis.voir` | xl |
| `direction.academic_alerts` | Alertes péda | `alert-circle` | `academique.analytics.view` | md |

### Widgets Enseignant

| ID | Titre | Icon | Permissions | Taille |
|---|---|---|---|---|
| `enseignant.today_schedule` | EDT du jour | `calendar` | `timetable.view` | lg |
| `enseignant.pending_grades` | Notes à saisir | `edit` | `academique.notes.manage` | md |
| `enseignant.today_absences` | Absences du jour | `user-x` | `attendance.view` | md |
| `enseignant.week_timetable` | Semaine | `grid` | `timetable.view` | xl |
| `enseignant.bulletins_pending` | Bulletins | `file-check` | `academique.bulletin.view` | sm |
| `enseignant.classes_summary` | Mes classes | `book-open` | `classes.view` | md |
| `enseignant.activities` | Activités | `star` | `activity.view` | sm |

### Widgets Élève

| ID | Titre | Icon | Permissions | Taille |
|---|---|---|---|---|
| `eleve.recent_grades` | Mes notes | `bar-chart` | `notes.view_own` | md |
| `eleve.today_schedule` | EDT du jour | `calendar` | `emploi_du_temps.view_own` | lg |
| `eleve.absences_summary` | Mes absences | `user-x` | `absences.view_own` | sm |
| `eleve.library_loans` | Bibliothèque | `book` | `biblio.view` | sm |
| `eleve.activities` | Activités | `activity` | `activity.view` | md |
| `eleve.bulletins` | Bulletins | `file-text` | `academique.bulletin.view` | sm |
| `eleve.rewards` | Récompenses | `award` | `reward.view` | sm |
| `eleve.annonces` | Annonces | `megaphone` | `annonces.view` | md |

### Widgets Parent

| ID | Titre | Icon | Permissions | Taille |
|---|---|---|---|---|
| `parent.child_switcher` | Mes enfants | `users` | `portal.parent.access` | md |
| `parent.recent_grades` | Notes enfant | `bar-chart` | `notes.view_own` | md |
| `parent.absences` | Absences | `user-x` | `attendance.view` | sm |
| `parent.unpaid_invoices` | Paiements | `credit-card` | `finance.paiements.view.own` | sm |
| `parent.child_schedule` | EDT enfant | `calendar` | `timetable.view` | lg |
| `parent.bulletins` | Bulletins | `file-text` | `academique.bulletin.view` | sm |
| `parent.discipline_alert` | Discipline | `alert-circle` | `discipline.view` | sm |

### Widgets Comptabilité

| ID | Titre | Icon | Permissions | Taille |
|---|---|---|---|---|
| `compta.daily_revenue` | Recettes jour | `trending-up` | `finance.paiements.view` | sm |
| `compta.unpaid_invoices` | Impayés | `alert-circle` | `finance.factures.view` | md |
| `compta.cashregister` | Caisse | `database` | `finance.caisse.view` | sm |
| `compta.recent_payments` | Paiements | `list` | `finance.paiements.view` | lg |
| `compta.accounting_balance` | Balance | `scale` | `finance.comptabilite.view` | md |
| `compta.pending_expenses` | Dépenses | `minus-circle` | `finance.decaissements.view` | sm |
| `compta.treasury_chart` | Trésorerie | `line-chart` | `finance.rapports.view` | xl |
| `compta.recovery_rate` | Recouvrement | `percent` | `finance.rapports.view` | sm |

### Widgets RH

| ID | Titre | Icon | Permissions | Taille |
|---|---|---|---|---|
| `rh.effectif` | Effectif | `users` | `employee.view` | sm |
| `rh.leaves_pending` | Congés | `umbrella` | `leave.approve` | sm |
| `rh.expiring_contracts` | Contrats | `file-minus` | `contract.view` | md |
| `rh.today_attendance` | Présences | `user-check` | `rh.presence.view` | md |
| `rh.ongoing_evaluations` | Évaluations | `clipboard` | `evaluation.view` | sm |
| `rh.expired_documents` | Documents | `file-x` | `hr_document.view` | sm |
| `rh.active_trainings` | Formations | `book-open` | `training.view` | sm |
| `rh.orgchart` | Organigramme | `git-branch` | `organization.view` | lg |

---

## 15. API FIRST LAYER

### Principes

Chaque endpoint Portal Controller détecte `Accept: application/json` ou le flag `?format=json` pour retourner du JSON au lieu du HTML.

```php
// Dans PortalBaseController (trait ou classe abstraite)
protected function wantsJson(): bool
{
    return $this->request->hasHeader('Accept', 'application/json')
        || $this->request->get('format') === 'json';
}
```

### Authentification API

Pour les clients mobiles (PWA, future app native), un système de tokens stateless est requis :

```
POST /api/v2/auth/token
Body: { email, password, portal }
Response: { token: 'xxx', expires_in: 3600, portal: 'eleve', user: {...} }

GET /api/v2/portals/{portal}/dashboard
Header: Authorization: Bearer {token}
```

**Token Storage (table) :**
```sql
CREATE TABLE api_tokens (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    user_id          INT          NOT NULL,
    token            VARCHAR(64)  NOT NULL,
    portal           VARCHAR(50)  NOT NULL,
    expires_at       TIMESTAMP    NOT NULL,
    etablissement_id INT          NOT NULL,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at)
);
```

### Endpoints API principaux

```
── Dashboard ──────────────────────────────────────────────────────────
GET /api/v2/portals/eleve/dashboard
Response: {
  portal: "eleve",
  user: { id, prenom, nom, classe, photo_url },
  widgets: [
    { id: "eleve.recent_grades", titre: "...", data: [...], updated_at: "..." },
    { id: "eleve.today_schedule", titre: "...", data: [...] }
  ],
  alertes: [],
  unread_notifications: 3
}

── Widgets individuels (refresh partiel) ──────────────────────────────
GET /api/v2/portals/enseignant/widgets/enseignant.today_schedule
Response: {
  id: "enseignant.today_schedule",
  data: { creneaux: [...] },
  cached_until: "2026-07-04T09:30:00"
}

── Notifications ──────────────────────────────────────────────────────
GET /api/v2/portals/{portal}/notifications?page=1&per_page=20
Response: {
  data: [ { id, type, titre, message, lien, lu, created_at } ],
  total: 47,
  unread: 3
}

── Recherche ──────────────────────────────────────────────────────────
GET /api/v2/portals/admin/search?q=ahmed&limit=10
Response: {
  query: "ahmed",
  results: [
    { module: "eleves", type: "eleve", id: 42, titre: "Ahmed Benali",
      sousTitre: "6ème A", url: "/v2/portals/admin/eleves/42", icon: "user" }
  ],
  total: 3,
  time_ms: 12
}

── Préférences ────────────────────────────────────────────────────────
GET /api/v2/portals/enseignant/preferences
Response: {
  widget_layout: [{ id: "...", col: 1, row: 1, w: 6, h: 4 }],
  hidden_widgets: [],
  shortcuts: [...],
  theme: "default"
}

POST /api/v2/portals/enseignant/preferences/widget-layout
Body: { layout: [{ id: "...", col: 1, row: 1, w: 6, h: 4 }] }
Response: { success: true }
```

---

## 16. SYSTÈME DE NOTIFICATIONS PORTAILS

### Architecture

```
NotificationCenter (Portal Framework)
    ↓ lit
Communication module (comm_notifications table)
    ↓ écrit via Events
Tous les modules → CrossModuleListener → NotificationService → DB
```

### Types de notifications par portail

| Portail | Types prioritaires |
|---|---|
| Enseignant | Nouveau message, Bulletin à valider, Absence dans ma classe |
| Élève | Nouveau bulletin, Note publiée, Réservation disponible, Message |
| Parent | Absence enfant, Bulletin disponible, Facture impayée, Message |
| Comptabilité | Paiement reçu, Facture en retard, Caisse à fermer |
| RH | Demande de congé, Contrat expirant, Document RH expiré |
| Direction | Alerte KPI (seuil dépassé), Rapport généré, Bulletin publié |
| Administration | Module error, Utilisateur créé, Paramètre modifié |

### Rendu Notification Center

```html
<!-- Header portail — commun à tous -->
<button id="notif-bell" class="relative">
  <i data-lucide="bell"></i>
  <span id="notif-badge" class="badge">3</span>  <!-- masqué si 0 -->
</button>

<!-- Dropdown -->
<div id="notif-dropdown" class="hidden">
  <!-- 10 dernières notifications -->
  <div class="notif-item [unread]">
    <i data-lucide="file-text"></i>
    <div>
      <p class="title">Bulletin disponible</p>
      <p class="meta">Amina — 2026-07-04 08:30</p>
    </div>
  </div>
  <!-- ... -->
  <a href="/v2/portals/{portal}/notifications">Voir tout</a>
</div>
```

**Polling / Refresh :**
```javascript
// Polling léger toutes les 30s pour mise à jour du badge
setInterval(() => {
    fetch('/api/v2/portals/{portal}/notifications/unread-count')
        .then(r => r.json())
        .then(data => updateBadge(data.count));
}, 30000);
```

---

## 17. RECHERCHE GLOBALE

### Activation

La recherche globale est disponible dans le header de tous les portails via un input `Cmd+K` / `Ctrl+K`.

```html
<!-- Header portail -->
<input type="text"
       id="global-search"
       placeholder="Rechercher... (Ctrl+K)"
       hx-get="/api/v2/portals/{portal}/search"
       hx-trigger="keyup changed delay:300ms"
       hx-target="#search-results">
```

### SearchHandlers par portail

| Portail | Handlers actifs |
|---|---|
| Administration | Élèves, Enseignants, Utilisateurs, Documents, Factures, Emprunts |
| Direction | Élèves, Classes, Rapports, Bulletins, Employés |
| Enseignant | Élèves (mes classes), Évaluations (mes classes), Documents |
| Élève | Mes notes (matières), Ouvrages bibliothèque |
| Parent | Enfants liés, Factures famille, Documents partagés |
| Comptabilité | Factures, Paiements, Élèves (par facturation) |
| RH | Employés, Enseignants, Contrats, Formations |

### Résultat de recherche

```
┌──────────────────────────────────────────┐
│ 🔍 "ahmed"                               │
├──────────────────────────────────────────┤
│ 👤 Élèves                                │
│   Ahmed Benali — 6ème A     →            │
│   Ahmed Chakib — 5ème B     →            │
├──────────────────────────────────────────┤
│ 👨‍🏫 Enseignants                         │
│   M. Ahmed Sefiani — Maths  →            │
├──────────────────────────────────────────┤
│ 📄 Documents                             │
│   Règlement_Ahmed.pdf       →            │
└──────────────────────────────────────────┘
```

---

## 18. RESPONSIVE, MOBILE & PWA

### Layout Responsive

```
Desktop (≥1024px) :
  [Sidebar fixe 260px] [Contenu principal 100%-260px]

Tablette (768px-1023px) :
  [Sidebar collapsible 260px] [Contenu 100%]
  Sidebar masquée par défaut, ouverte via hamburger

Mobile (<768px) :
  [Contenu full width]
  [Bottom navigation bar 5 items]
  Sidebar remplacée par bottom nav
```

### Bottom Navigation Mobile (Élève & Parent)

```
┌─────────────────────────────────────────┐
│ 🏠 Accueil  📊 Notes  📅 EDT  📬 Msg  🔔 │
└─────────────────────────────────────────┘
```

### Grille de widgets responsive

```css
/* Tailwind classes */
.widget-grid {
  @apply grid grid-cols-12 gap-4;
}
.widget-xs  { @apply col-span-12 md:col-span-3; }
.widget-sm  { @apply col-span-12 md:col-span-4; }
.widget-md  { @apply col-span-12 md:col-span-6; }
.widget-lg  { @apply col-span-12 md:col-span-8; }
.widget-xl  { @apply col-span-12; }
```

### PWA — Service Worker Cache Strategy

| Ressource | Stratégie | Durée cache |
|---|---|---|
| CSS / JS / Images | Cache First | 7 jours |
| Layouts HTML portail | Network First | 1h |
| Dashboard JSON (`/api/v2/portals/*/dashboard`) | Stale While Revalidate | 5min |
| Notifications (`/api/v2/portals/*/notifications`) | Network First | non |
| Widgets individuels | Cache First (with refresh) | selon widget |
| Formulaires POST | Network Only | — |

### Offline Mode Portails

| Portail | Fonctionnel offline |
|---|---|
| Élève | EDT du jour (cached), Dernières notes (cached) |
| Parent | EDT enfant (cached), Dernières notes (cached) |
| Enseignant | Mon EDT (cached), Liste classes (cached) |
| Autres | Page offline informative avec données en cache |

**Tailwind CSS :** Doit être compilé (non CDN) pour être mis en cache par le Service Worker.

---

## 19. MULTI-ÉTABLISSEMENTS & SAAS

### Scoping tenant

```php
// PortalBaseController — abstract method
abstract protected function getEtablissementId(): int;

// Implémentation par défaut
protected function getEtablissementId(): int
{
    $user = $this->currentUser();
    return (int)($user['etablissement_id'] ?? throw new \RuntimeException('No tenant'));
}
```

### Isolation des données

Toutes les requêtes portails passent systématiquement `etablissement_id` :

```php
// Exemple — PortalEleveController
public function notes(): void
{
    $etab   = $this->getEtablissementId();  // jamais de ?? 1 hardcodé
    $eleveId = $this->getEleveId();

    $notes  = $this->noteService->getNotesByEleve($eleveId, $etab);
    $this->render('Portals::Eleve/notes', compact('notes'), 'portal-eleve');
}
```

### Personnalisation par établissement (V3 ready)

```sql
-- Future : configuration portail par établissement
CREATE TABLE portal_config_etab (
    etablissement_id  INT          NOT NULL,
    portal            VARCHAR(50)  NOT NULL,
    logo_url          VARCHAR(500),
    couleur_primaire  VARCHAR(7),    -- hex ex: #7c3aed
    modules_actifs    JSON,          -- widgets autorisés
    PRIMARY KEY (etablissement_id, portal)
);
```

---

## 20. ÉVÉNEMENTS PORTAILS

### Nouveaux événements à créer

```
app/Modules/Portals/Events/
├── PortalAccessed.php         ← Connexion à un portail
├── DashboardViewed.php        ← Consultation dashboard
├── WidgetRefreshed.php        ← Refresh widget individuel
├── SearchPerformed.php        ← Recherche globale
├── PreferencesSaved.php       ← Sauvegarde préférences
├── ShortcutCreated.php        ← Raccourci ajouté
├── ShortcutDeleted.php        ← Raccourci supprimé
└── PortalError.php            ← Erreur accès portail
```

### Event payload exemple

```php
class PortalAccessed extends Event
{
    public function __construct(
        public readonly string $portal,
        public readonly int    $userId,
        public readonly int    $etablissementId,
        public readonly string $ip,
    ) {}

    public function toArray(): array
    {
        return [
            'portal'           => $this->portal,
            'user_id'          => $this->userId,
            'etablissement_id' => $this->etablissementId,
            'ip'               => $this->ip,
        ];
    }
}
```

### Listeners

```
app/Modules/Portals/Listeners/
├── PortalAuditListener.php    ← AuditService::log() pour chaque event
└── PortalAnalyticsListener.php ← Écriture portal_access_logs
```

### Mapping config/events.php (ajout)

```php
// Module Portals — Events
\App\Modules\Portals\Events\PortalAccessed::class => [
    new \App\Modules\Portals\Listeners\PortalAuditListener(),
    new \App\Modules\Portals\Listeners\PortalAnalyticsListener(),
],
\App\Modules\Portals\Events\SearchPerformed::class => [
    new \App\Modules\Portals\Listeners\PortalAuditListener(),
],
\App\Modules\Portals\Events\PreferencesSaved::class => [
    new \App\Modules\Portals\Listeners\PortalAuditListener(),
],
```

---

## 21. PLAN D'IMPLÉMENTATION

### Phases

| Phase | Contenu | Dépendances | Estimation |
|---|---|---|---|
| **12.0** | Blueprint (ce document) | PRE_PORTAL_REVIEW GO | ✅ Terminé |
| **12.1** | Portal Framework (7 moteurs + tables SQL + events) | Aucune | Phase 12.2 |
| **12.2** | Portail Administration | Framework 12.1 | Phase 12.3 |
| **12.3** | Portail Direction | Framework 12.1, Rapports module | Phase 12.4 |
| **12.4** | Portail Enseignant | Framework 12.1, Scolarité+Académique activés | Phase 12.5 |
| **12.5** | Portail Élève | Framework 12.1, Scolarité+Académique+Biblio activés | Phase 12.6 |
| **12.6** | Portail Parent | Phase 12.5 (partage composants élève) | Phase 12.7 |
| **12.7** | Portail Comptabilité | Framework 12.1, Finance activé | Phase 12.8 |
| **12.8** | Portail RH | Framework 12.1, RH activé | Phase 12.9 |
| **12.9** | API First Layer (tokens, endpoints JSON) | Phases 12.2-12.8 | Phase 12.10 |
| **12.10** | Portal Integration Review | Phases 12.1-12.9 | Phase 12.11 |
| **12.11** | Portal Platform Freeze | Phase 12.10 GO | Production |

### Prérequis avant Phase 12.1

| Prérequis | Statut | Priorité |
|---|---|---|
| Fix PA-C-001 (routes 4 modules) | ❌ À faire | BLOQUANT |
| Fix PA-C-002 ($this->user 44 controllers) | ❌ À faire | BLOQUANT |
| Activer Scolarité + Académique | ❌ Disabled | Requis Phase 12.4 |
| Activer Communication (après fix routes) | ❌ Broken | Requis notifications |
| Compiler Tailwind CSS | ❌ CDN | Requis PWA |

### Modules à activer par phase

| Phase | Modules requis activés |
|---|---|
| 12.2 Admin | Finance ✅, RH ✅, VieScolaire ✅ |
| 12.3 Direction | + Rapports (après fix routes) |
| 12.4 Enseignant | + Scolarité, Académique |
| 12.5 Élève | + Bibliothèque (après fix routes) |
| 12.6 Parent | même que 12.5 |
| 12.7 Comptabilité | Finance ✅ (déjà) |
| 12.8 RH | RH ✅ (déjà) |
| 12.9 API | + Communication (messagerie) |

---

## 22. DÉPENDANCES INTER-MODULES

### Matrice Portail × Modules backend

| Portail | Scolarité | Académique | Finance | Vie Scolaire | RH | Documents | Communication | Bibliothèque | Inventaire | Rapports |
|---|---|---|---|---|---|---|---|---|---|---|
| Administration | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Direction | ✓ | ✓ | ✓ | ✓ | ✓ | ○ | ○ | ○ | ○ | ✓ |
| Enseignant | ✓ | ✓ | ○ | ✓ | ✓* | ✓ | ✓ | ✓ | ○ | ○ |
| Élève | ✓ | ✓ | ○ | ✓ | ○ | ✓ | ✓ | ✓ | ○ | ○ |
| Parent | ✓ | ✓ | ✓ | ✓ | ○ | ✓ | ✓ | ○ | ○ | ○ |
| Comptabilité | ✓ | ○ | ✓ | ○ | ○ | ✓ | ✓ | ○ | ○ | ✓ |
| RH | ✓ | ○ | ○ | ○ | ✓ | ✓ | ✓ | ○ | ○ | ○ |

*RH pour Enseignant : lecture seule — propres données (fiche employé, congés personnels)

`✓` = dépendance directe / `○` = optionnel (graceful degradation si module désactivé)

### Dégradation gracieuse

Les portails doivent fonctionner **partiellement** si un module est désactivé :

```php
// Exemple : widget biblio dans portail élève, module désactivé
class EleveBiblioWidget implements WidgetInterface
{
    public function getData(int $etab, int $userId, array $config = []): array
    {
        if (!$this->isModuleEnabled('bibliotheque')) {
            return ['available' => false, 'message' => 'Module bibliothèque non activé'];
        }
        // ... données normales
    }
}
```

Le widget affichera alors un message "Module non disponible" sans planter.

### Couplage Portal → Service

Les portails ne font jamais appel directement aux Repositories. Ils passent **toujours** par les Services des modules :

```
CORRECT :
  PortalEleveController → NoteService::getNotesByEleve() → NoteRepository

INTERDIT :
  PortalEleveController → NoteRepository::findByEleve()  (direct)
  PortalEleveController → PDO query directe
```

Les Services existants exposent déjà les méthodes nécessaires. Si une méthode manque, elle est **ajoutée au Service du module concerné** (pas dans le portail).

---

## RÉCAPITULATIF TECHNIQUE

### Structure arborescente complète

```
app/Modules/Portals/
├── Framework/
│   ├── DashboardEngine.php
│   ├── WidgetEngine.php
│   ├── WidgetRegistry.php
│   ├── MenuEngine.php
│   ├── ShortcutEngine.php
│   ├── NotificationCenter.php
│   ├── GlobalSearch.php
│   ├── UserPreferences.php
│   └── PortalBaseController.php      ← Contrôleur abstrait portails
│
├── Shared/
│   ├── Widgets/
│   │   ├── NotificationWidget.php
│   │   ├── MessageWidget.php
│   │   ├── ShortcutsWidget.php
│   │   └── AnnouncementsWidget.php
│   ├── Views/
│   │   ├── layouts/
│   │   │   ├── portal-base.php
│   │   │   ├── portal-admin.php
│   │   │   ├── portal-direction.php
│   │   │   ├── portal-enseignant.php
│   │   │   ├── portal-eleve.php
│   │   │   ├── portal-parent.php
│   │   │   ├── portal-comptabilite.php
│   │   │   └── portal-rh.php
│   │   └── partials/
│   │       ├── header.php
│   │       ├── sidebar.php
│   │       ├── bottom-nav.php
│   │       ├── notification-center.php
│   │       ├── global-search.php
│   │       └── widget-wrapper.php
│   ├── DTO/
│   │   ├── DashboardDTO.php
│   │   ├── WidgetDataDTO.php
│   │   ├── MenuDTO.php
│   │   ├── MenuItemDTO.php
│   │   ├── ShortcutDTO.php
│   │   ├── PreferencesDTO.php
│   │   └── SearchResultDTO.php
│   └── Search/
│       ├── SearchHandlerInterface.php
│       ├── EleveSearchHandler.php
│       ├── EnseignantSearchHandler.php
│       ├── FactureSearchHandler.php
│       └── DocumentSearchHandler.php
│
├── Admin/
│   ├── Controllers/AdminPortalController.php
│   ├── Widgets/ (8 widgets admin)
│   └── Views/
│
├── Direction/
│   ├── Controllers/DirectionPortalController.php
│   ├── Widgets/ (9 widgets direction)
│   └── Views/
│
├── Enseignant/
│   ├── Controllers/EnseignantPortalController.php
│   ├── Widgets/ (7 widgets enseignant)
│   └── Views/
│
├── Eleve/
│   ├── Controllers/ElevePortalController.php
│   ├── Widgets/ (8 widgets eleve)
│   └── Views/
│
├── Parent/
│   ├── Controllers/ParentPortalController.php
│   ├── Widgets/ (7 widgets parent)
│   └── Views/
│
├── Comptabilite/
│   ├── Controllers/ComptabilitePortalController.php
│   ├── Widgets/ (8 widgets compta)
│   └── Views/
│
├── RH/
│   ├── Controllers/RHPortalController.php
│   ├── Widgets/ (8 widgets rh)
│   └── Views/
│
├── Api/
│   ├── Controllers/
│   │   ├── PortalApiController.php   ← Dashboard + widgets JSON
│   │   ├── AuthTokenController.php   ← Token auth pour mobile
│   │   └── PreferencesApiController.php
│   └── Middleware/
│       └── BearerTokenMiddleware.php
│
├── Events/
│   ├── PortalAccessed.php
│   ├── DashboardViewed.php
│   ├── WidgetRefreshed.php
│   ├── SearchPerformed.php
│   ├── PreferencesSaved.php
│   ├── ShortcutCreated.php
│   ├── ShortcutDeleted.php
│   └── PortalError.php
│
├── Listeners/
│   ├── PortalAuditListener.php
│   └── PortalAnalyticsListener.php
│
├── routes.php
└── module.json
```

### Statistiques blueprint

| Dimension | Valeur |
|---|---|
| Portails | 7 |
| Widgets total | 57 (5 partagés + 52 spécifiques) |
| Moteurs Framework | 7 |
| Layouts portail | 8 (base + 7 spécifiques) |
| Events portails | 8 |
| Tables SQL nouvelles | 3 (portal_preferences, portal_widget_cache, portal_access_logs) |
| Permissions nouvelles | 7 (portal.*.access) |
| Routes HTML | ~60 |
| Routes API | ~18 |
| Phases implémentation | 11 (12.1 → 12.11) |
| Modules backend consommés | 10/10 |

---

## DÉCISION FINALE

```
╔══════════════════════════════════════════════════════════════════════╗
║                                                                      ║
║    PORTALS PLATFORM BLUEPRINT — VALIDÉ                               ║
║                                                                      ║
║    Architecture complète définie pour 7 portails.                    ║
║    Prêt pour Phase 12.1 — Portal Framework.                          ║
║                                                                      ║
║    Prérequis GO Phase 12.1 :                                         ║
║    ✅ Fix PA-C-001 (routes 4 modules)                                 ║
║    ✅ Fix PA-C-002 ($this->user → currentUser())                      ║
║                                                                      ║
║    Prérequis GO Phase 12.4+ :                                        ║
║    ⚡ Activer Scolarité + Académique                                  ║
║    ⚡ Activer Communication + Bibliothèque + Rapports                 ║
║                                                                      ║
╚══════════════════════════════════════════════════════════════════════╝
```

---

*PORTALS_PLATFORM_BLUEPRINT.md — Architecture Plateforme Portails SCOLARIS V2 — Phase 12.0 — 2026-07-04*
