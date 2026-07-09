# MULTI_TENANT_V2_BLUEPRINT.md
## SCOLARIS V2 — Architecture Multi-Établissements

> **Type :** Blueprint d'architecture — aucune implémentation  
> **Phase :** 14.1  
> **Date :** 2026-07-06  
> **Auteur :** SCOLARIS Architecture Team  
> **Statut :** DRAFT — En attente de validation

---

## TABLE DES MATIÈRES

1. [Résumé exécutif](#1-résumé-exécutif)
2. [Stratégie Multi-Tenant](#2-stratégie-multi-tenant)
3. [État actuel et dette d'isolation](#3-état-actuel-et-dette-disolation)
4. [Modèle de données — Tables de fondation](#4-modèle-de-données--tables-de-fondation)
5. [Isolation des données](#5-isolation-des-données)
6. [Résolution du tenant (Routing)](#6-résolution-du-tenant-routing)
7. [Utilisateurs multi-établissements](#7-utilisateurs-multi-établissements)
8. [RBAC par établissement](#8-rbac-par-établissement)
9. [Paramètres par établissement](#9-paramètres-par-établissement)
10. [Branding et personnalisation](#10-branding-et-personnalisation)
11. [Domaines personnalisés](#11-domaines-personnalisés)
12. [Stockage des fichiers](#12-stockage-des-fichiers)
13. [Notifications](#13-notifications)
14. [Rapports et Analytics](#14-rapports-et-analytics)
15. [Cache](#15-cache)
16. [Files d'attente (Workers)](#16-files-dattente-workers)
17. [API Platform — Multi-tenant](#17-api-platform--multi-tenant)
18. [Événements et Webhooks](#18-événements-et-webhooks)
19. [Sauvegardes et Restauration](#19-sauvegardes-et-restauration)
20. [Infrastructure Cloud / SaaS](#20-infrastructure-cloud--saas)
21. [Montée en charge](#21-montée-en-charge)
22. [Haute Disponibilité](#22-haute-disponibilité)
23. [Schémas SQL — Nouvelles tables](#23-schémas-sql--nouvelles-tables)
24. [Plan de migration](#24-plan-de-migration)
25. [Feuille de route d'implémentation](#25-feuille-de-route-dimplémentation)

---

## 1. Résumé Exécutif

### 1.1 Objectif

Transformer SCOLARIS V2 d'un système mono-établissement en une plateforme SaaS
multi-tenant capable d'héberger N établissements scolaires sur une infrastructure
partagée, avec une isolation totale des données, une personnalisation complète par
établissement, et une architecture prête pour le cloud.

### 1.2 Diagnostic de l'état actuel

SCOLARIS V2 est aujourd'hui **logiquement mono-tenant** mais **architecturalement
préparé** pour la multi-tenancy :

| Critère | État actuel | Cible |
|---------|-------------|-------|
| Tables V2 (Finance, RH…) | `etablissement_id` présent | ✅ Déjà en place |
| Tables V1 (users, classes, eleves…) | Pas de `etablissement_id` | ⚠️ Migration requise |
| Table `etablissements` | Absente | ✅ À créer |
| RBAC par tenant | Flat file PHP global | ✅ À porter en DB |
| Résolution tenant | Hardcodée (session) | ✅ Subdomain / URL |
| Stockage fichiers | Local `storage/` unique | ✅ Partitionné par tenant |
| Cache | Aucun (WAMP local) | ✅ Redis avec namespace |
| Queues | Aucune | ✅ Async workers |

### 1.3 Stratégie retenue en un mot

> **Shared Database, Shared Schema** avec `etablissement_id` sur chaque table,
> résolution par sous-domaine, RBAC DB-driven par tenant.

---

## 2. Stratégie Multi-Tenant

### 2.1 Les trois modèles et le choix

```
┌─────────────────────────────────────────────────────────────────┐
│ MODÈLE 1 : Database-per-tenant                                  │
│   Pro : Isolation maximale, backup individuel simple            │
│   Con : N bases × M tables = complexité opérationnelle élevée   │
│         Migration de schéma = N exécutions parallèles           │
│   ✗ Écarté : trop coûteux à opérer pour 50→5000 établissements  │
│                                                                 │
│ MODÈLE 2 : Schema-per-tenant (MySQL: database-per-tenant)       │
│   Pro : Isolation correcte, backup par schéma                   │
│   Con : Connexion pool complexe, queries cross-tenant impossibles│
│   ✗ Écarté : même problème que Modèle 1 à grande échelle        │
│                                                                 │
│ MODÈLE 3 : Shared Schema + etablissement_id (RETENU ✓)         │
│   Pro : Une migration = tous les tenants mis à jour             │
│         Pool de connexions partagé, coût infra réduit           │
│         Cross-tenant analytics pour la plateforme SaaS          │
│   Con : Risque de fuite de données si filtre manquant           │
│         Performance : index sur etablissement_id obligatoire    │
│   ✓ Choix : déjà 90% implémenté dans V2                        │
└─────────────────────────────────────────────────────────────────┘
```

### 2.2 Principes d'isolation (les 5 règles)

```
RÈGLE 1 — COLONNE OBLIGATOIRE
  Chaque table métier porte un NOT NULL etablissement_id.
  Aucune exception. Violation = PR bloquée en CI.

RÈGLE 2 — PREMIER FILTRE
  La clause WHERE establishement_id = :etab est TOUJOURS
  le premier prédicat de chaque requête.

RÈGLE 3 — INDEX COMPOSITE
  Chaque index fonctionnel commence par etablissement_id :
  INDEX idx_(etablissement_id, champ_filtrable)

RÈGLE 4 — CONTEXT TENANT OBLIGATOIRE
  TenantContext::require() lève une exception si l'etab_id
  n'est pas résolu AVANT tout accès aux données.

RÈGLE 5 — AUDIT SYSTÉMATIQUE
  Toute écriture hors session (jobs, webhooks, imports)
  doit journaliser l'etablissement_id dans audit_logs.
```

### 2.3 Identifiant de tenant

```
Champ     : etablissement_id (INT UNSIGNED NOT NULL)
Référence : etablissements.id (table centrale, voir §4)
Alias     : tenant_id dans la couche infrastructure
            school_id dans la couche API publique
```

**Cohérence d'appellation dans le code :**
```
Couche          | Variable           | Description
----------------|--------------------|--------------------------
DB              | etablissement_id   | Colonne SQL
PHP Services    | $etablissementId   | camelCase PHP
API JSON        | school_id          | snake_case JSON publique
JWT payload     | etab               | Claim court (space-saving)
Cache key       | etab:{N}:...       | Préfixe namespace Redis
Queue job       | tenant_id          | Champ de routage
```

---

## 3. État actuel et dette d'isolation

### 3.1 Tables V2 — Audit de conformité

```
CONFORMES (etablissement_id présent) ✓
───────────────────────────────────────────────────────────────────
MODULE FINANCE (8 tables)
  finance_frais_types       ✓   finance_factures          ✓
  finance_lignes_facture    ✓   finance_paiements         ✓
  finance_avoirs            ✓   finance_caisse            ✓
  finance_mouvements_caisse ✓   finance_ecritures         ✓

MODULE VIE SCOLAIRE (18 tables)
  vs_absences               ✓   vs_sessions_presences     ✓
  vs_pointages              ✓   vs_retards                ✓
  vs_incidents_discipline   ✓   vs_sanctions              ✓
  vs_recompenses            ✓   vs_types_recompenses      ✓
  vs_emplois_du_temps       ✓   vs_creneaux_edt           ✓
  (+ toutes les tables vs_)

MODULE RH (11 tables avec etab_id)
  rh_employes               ✓   rh_enseignants            ✓
  rh_contrats               ✓   rh_affectations           ✓
  rh_presences_rh           ✓   rh_conges                 ✓
  rh_evaluations_rh         ✓   rh_formations             ✓
  rh_participants_formation ✓   rh_documents_rh           ✓
  rh_organigramme_postes    ✓

MODULE DOCUMENTS (3 tables)
  doc_documents             ✓   doc_versions              ✓
  doc_permissions_doc       ✓

MODULE COMMUNICATION (5 tables)
  com_messages              ✓   com_threads               ✓
  com_notifications         ✓   com_annonces              ✓
  com_push_subscriptions    ✓

MODULE BIBLIOTHÈQUE (14 tables)   MODULE INVENTAIRE (16 tables)
  (toutes avec etab_id ✓)          (toutes avec etab_id ✓)

MODULE RAPPORTS/BI (5 tables)   MODULE PORTAILS (4 tables)
  (toutes avec etab_id ✓)         (toutes avec etab_id ✓)

MODULE API (7 tables)
  api_keys                  ✓   api_refresh_tokens        ✓
  webhook_subscriptions     ✓   api_uploads               ✓
  api_request_logs          ✓   api_metrics_snapshots     ✓

NON-CONFORMES (etablissement_id absent) ⚠️
───────────────────────────────────────────────────────────────────
V1 TABLES (sans etab_id — migration phase 24.2)
  users          ⚠️   classes        ⚠️   eleves         ⚠️
  professeurs    ⚠️   matieres       ⚠️   enseignements  ⚠️
  notes (V1)     ⚠️   absences (V1)  ⚠️   periodes (V1)  ⚠️

V2 TABLES RÉFÉRENTIELLES (partagées — à examiner cas par cas)
  rh_departements         ⚠️  → Ajouter etablissement_id (nullable pour dept globaux)
  rh_postes               ⚠️  → Ajouter etablissement_id (nullable pour postes standards)
  finance_categories_frais ⚠️ → Ajouter etablissement_id (nullable pour catégories globales)
```

### 3.2 Stratégie pour les données référentielles partagées

Certaines tables référentielles (`rh_postes`, `finance_categories_frais`) contiennent
des données **globales** (seeds) valides pour tous les tenants ET des données **locales**
spécifiques à un établissement. Solution : colonne nullable + convention :

```sql
etablissement_id INT UNSIGNED NULL
  -- NULL  → Donnée globale partagée (visible par tous les tenants)
  -- N     → Donnée privée de l'établissement N

INDEX idx_etab_scope (etablissement_id) -- NULL inclus dans l'index
```

Règle de lecture : `WHERE etablissement_id IS NULL OR etablissement_id = :etab`

---

## 4. Modèle de données — Tables de fondation

### 4.1 Vue d'ensemble du modèle tenant

```
┌─────────────────────────────────────────────────────────────────┐
│                    COUCHE PLATFORM                               │
│                                                                  │
│  platform_operators          platform_plans                     │
│  (admins SaaS)               (Starter/Pro/Enterprise)           │
└──────────────────────────────┬──────────────────────────────────┘
                               │ 1:N
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                    COUCHE TENANT                                  │
│                                                                  │
│  etablissements  ←──── etablissement_settings                   │
│       │          ←──── etablissement_modules                    │
│       │          ←──── etablissement_domains                    │
│       │          ←──── etablissement_branding                   │
│       │                                                         │
│       └─── user_etablissements ───→ users                       │
│                                      │                          │
│                                      └─── user_roles_etab       │
│                                            (RBAC par tenant)    │
└─────────────────────────────────────────────────────────────────┘
                               │ 1:N
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                    COUCHE DONNÉES                                 │
│                                                                  │
│  [Toutes les tables métier avec etablissement_id]               │
│  classes / eleves / notes / absences / rh_* / finance_* / …    │
└─────────────────────────────────────────────────────────────────┘
```

### 4.2 Table `etablissements` (tenant registry)

```sql
CREATE TABLE etablissements (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,

    -- Identité
    slug                VARCHAR(60)     NOT NULL UNIQUE,        -- 'lycee-ibn-badis'
    nom                 VARCHAR(200)    NOT NULL,
    nom_court           VARCHAR(60)     NOT NULL,               -- 'IBN BADIS'
    code_etablissement  VARCHAR(30)     NULL UNIQUE,            -- Identifiant officiel MEN
    type                ENUM('ecole_primaire','college','lycee',
                             'universite','formation_pro','groupe_scolaire') NOT NULL,
    pays                CHAR(2)         NOT NULL DEFAULT 'DZ',  -- ISO 3166-1 alpha-2
    wilaya              VARCHAR(100)    NULL,
    commune             VARCHAR(100)    NULL,
    adresse             TEXT            NULL,
    telephone           VARCHAR(20)     NULL,
    email               VARCHAR(191)    NULL,
    site_web            VARCHAR(255)    NULL,

    -- Plan SaaS
    plan_id             INT UNSIGNED    NULL,                   -- FK → platform_plans
    plan_expires_at     DATE            NULL,
    storage_quota_mb    INT UNSIGNED    NOT NULL DEFAULT 1024,  -- 1 Go par défaut
    max_users           SMALLINT UNSIGNED NOT NULL DEFAULT 50,
    max_eleves          SMALLINT UNSIGNED NOT NULL DEFAULT 500,

    -- État
    statut              ENUM('trial','active','suspended','cancelled') NOT NULL DEFAULT 'trial',
    trial_ends_at       DATE            NULL,
    suspended_at        DATETIME        NULL,
    suspension_reason   VARCHAR(255)    NULL,

    -- Timestamps
    activated_at        DATETIME        NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          DATETIME        NULL,                   -- Soft delete

    PRIMARY KEY (id),
    KEY idx_slug   (slug),
    KEY idx_statut (statut),
    KEY idx_plan   (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.3 Table `user_etablissements` (membership multi-école)

```sql
CREATE TABLE user_etablissements (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id             INT UNSIGNED    NOT NULL,
    etablissement_id    INT UNSIGNED    NOT NULL,
    is_primary          TINYINT(1)      NOT NULL DEFAULT 0,    -- établissement principal
    joined_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    left_at             DATETIME        NULL,                   -- fin d'appartenance
    invited_by          INT UNSIGNED    NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_user_etab (user_id, etablissement_id),
    KEY idx_user (user_id),
    KEY idx_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.4 Table `platform_plans` (plans SaaS)

```sql
CREATE TABLE platform_plans (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    code                VARCHAR(30)     NOT NULL UNIQUE,        -- 'starter', 'pro', 'enterprise'
    nom                 VARCHAR(100)    NOT NULL,
    description         TEXT            NULL,

    -- Limites
    max_users           SMALLINT UNSIGNED NOT NULL DEFAULT 50,
    max_eleves          SMALLINT UNSIGNED NOT NULL DEFAULT 500,
    storage_quota_mb    INT UNSIGNED    NOT NULL DEFAULT 1024,  -- Mo
    api_calls_per_day   INT UNSIGNED    NOT NULL DEFAULT 10000,
    modules_inclus      JSON            NOT NULL DEFAULT ('[]'), -- liste des module codes

    -- Tarification (mensuel)
    prix_mensuel        DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    prix_annuel         DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    devise              CHAR(3)         NOT NULL DEFAULT 'DZD',

    actif               TINYINT(1)      NOT NULL DEFAULT 1,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.5 Table `platform_operators` (admins SaaS)

```sql
CREATE TABLE platform_operators (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED    NOT NULL UNIQUE,               -- FK → users.id
    niveau      ENUM('support','admin','super_admin') NOT NULL DEFAULT 'support',
    actif       TINYINT(1)      NOT NULL DEFAULT 1,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Isolation des données

### 5.1 Couche d'accès — TenantContext

```
┌─────────────────────────────────────────────────────────────────┐
│                     TenantContext (Singleton)                    │
│                                                                  │
│  Résolution (une seule fois par requête) :                       │
│    1. Résout l'établissement depuis l'URL / JWT / Session        │
│    2. Valide que l'étab est ACTIVE                               │
│    3. Stocke $tenantId dans une propriété statique               │
│                                                                  │
│  Accès :                                                         │
│    TenantContext::id()       → int (ou lève TenantException)     │
│    TenantContext::require()  → int (lève si non résolu)          │
│    TenantContext::isSet()    → bool                              │
│    TenantContext::clear()    → void (tests uniquement)           │
└─────────────────────────────────────────────────────────────────┘
```

### 5.2 BaseRepository — filtre automatique

Chaque Repository hérite de `TenantAwareRepository` :

```php
abstract class TenantAwareRepository
{
    protected function tenantWhere(array $extra = []): array
    {
        return array_merge(
            ['etablissement_id' => TenantContext::require()],
            $extra
        );
    }

    protected function applyTenant(string &$sql, array &$params): void
    {
        // Injecte "AND etablissement_id = :__etab" si absent
        if (!str_contains($sql, 'etablissement_id')) {
            $sql    .= ' AND etablissement_id = :__etab';
            $params[':__etab'] = TenantContext::require();
        }
    }
}
```

### 5.3 Règles de contrôle dans le CI/CD

```yaml
# .github/workflows/tenant-audit.yml
steps:
  - name: Check all new SQL queries for etablissement_id
    run: |
      # Toute SELECT/UPDATE/DELETE sur une table métier
      # sans WHERE etablissement_id → FAIL
      php artisan tenant:audit --fail-on-missing
```

**Tables exclues du contrôle** (non-métier) :
```
platform_plans, platform_operators, config_*, audit_logs (global),
api_rate_limit_buckets (par bucket_key), password_resets
```

### 5.4 Tests d'isolation (cross-tenant leak detection)

Pour chaque module, un test de non-régression vérifie l'isolation :

```
SCÉNARIO TYPE :
  1. Créer éléments dans Établissement A (etab_id=1)
  2. Créer éléments dans Établissement B (etab_id=2)
  3. Requêter depuis le contexte de l'Établissement A
  4. Vérifier que AUCUN élément de B n'est retourné

ASSERTION : count(résultats) === count(éléments_A)
```

---

## 6. Résolution du tenant (Routing)

### 6.1 Stratégies supportées

```
PRIORITÉ 1 — Sous-domaine (production SaaS)
  URL      : https://{slug}.scolaris.app/
  Résolution : extract subdomain → lookup etablissements.slug
  DNS     : Wildcard A/CNAME *.scolaris.app → Load Balancer
  Avantage : Isolation visuelle, SSL wildcard, bookmark distinct
  Config  : APP_MULTITENANCY_MODE=subdomain

PRIORITÉ 2 — Domaine personnalisé (plan Entreprise)
  URL      : https://ecole.institutibadis.dz/
  Résolution : lookup etablissement_domains.domain = Host header
  SSL     : Let's Encrypt par tenant (ACME API)
  Config  : APP_MULTITENANCY_MODE=custom_domain (après résolution échouée)

PRIORITÉ 3 — Chemin URL (hébergement mutualisé / dev)
  URL      : https://scolaris.app/s/{slug}/
  Résolution : extract path segment → lookup etablissements.slug
  Usage   : Développement, démo, hébergement partagé
  Config  : APP_MULTITENANCY_MODE=path

PRIORITÉ 4 — Header HTTP (API serveur-à-serveur)
  Header   : X-Tenant-Slug: {slug}
  Usage   : Intégrations backend, tests automatisés
  Config  : Auto-détecté en mode API_ONLY
```

### 6.2 Résolveur (`TenantResolver`)

```
Request arrives
      │
      ▼
TenantResolver::resolve(Request $r): ?Etablissement
      │
      ├─ 1. JWT claim 'etab' → bypass (déjà résolu lors du login)
      │
      ├─ 2. Session '__etab_id' → cache session
      │
      ├─ 3. Subdomain detection
      │      host = 'lycee-ibn-badis.scolaris.app'
      │      slug = 'lycee-ibn-badis'
      │      lookup etablissements WHERE slug = :slug AND statut IN ('trial','active')
      │
      ├─ 4. Custom domain detection
      │      host = 'ecole.institutibadis.dz'
      │      lookup etablissement_domains WHERE domain = :host
      │
      ├─ 5. Path detection (/s/{slug}/...)
      │
      └─ 6. X-Tenant-Slug header (API only)

Résultat :
  Etablissement trouvé → TenantContext::set($etab->id)
  Non trouvé          → 404 "Établissement introuvable"
  Suspendu            → 503 "Établissement suspendu"
  Plan expiré         → 402 "Abonnement expiré"
```

### 6.3 Middleware d'application

```
Ordre d'exécution dans le pipeline HTTP :

1. CorsMiddleware          (avant tout)
2. TenantResolutionMiddleware  ← résout + stocke TenantContext
3. AuthMiddleware          (JWT / Session — post-tenant)
4. RateLimitMiddleware     (bucketé par tenant)
5. ApiVersionMiddleware
6. Controller
```

---

## 7. Utilisateurs multi-établissements

### 7.1 Modèle de données

```
users (1) ──< user_etablissements >── (N) etablissements
```

Un utilisateur peut appartenir à N établissements. Cas d'usage :
- Directeur réseau de plusieurs lycées d'un groupe scolaire
- Enseignant vacataire dans deux établissements
- Parent avec enfants dans deux écoles

```sql
users
  id, email (UNIQUE global), nom, prenom, telephone, ...
  ⚠️ Pas de 'role' dans users — le rôle est par tenant (user_roles_etab)
  ⚠️ etablissement_id SUPPRIMÉ de users → porté par user_etablissements

user_etablissements
  user_id, etablissement_id, is_primary, joined_at, left_at

user_roles_etab
  user_id, etablissement_id, role_id  ← rôle DANS cet établissement
```

### 7.2 Sélection de l'établissement actif

```
FLUX LOGIN :

1. Authentification email/mot de passe (global)
2. Récupérer user_etablissements WHERE user_id = :uid AND left_at IS NULL
3a. Si 1 établissement : activer automatiquement
3b. Si N établissements : page de sélection (school picker UI)
3c. Si URL contient slug : pré-sélectionner l'établissement correspondant

FLUX CHANGEMENT D'ÉTABLISSEMENT (multi-école) :
  POST /auth/switch-school   { school_id: N }
  → Invalider JWT actuel
  → Émettre nouveau JWT avec claim etab = N
  → Mettre à jour session
```

### 7.3 Modifications à `users`

```sql
-- Ajouts requis sur la table users
ALTER TABLE users
  ADD COLUMN etablissement_id INT UNSIGNED NULL        -- PHASE DE TRANSITION uniquement
  COMMENT 'Établissement primaire — Déprecated: utiliser user_etablissements',
  ADD COLUMN deleted_at       DATETIME NULL,           -- Soft delete
  ADD COLUMN statut           ENUM('actif','inactif','suspendu') NOT NULL DEFAULT 'actif',
  ADD INDEX  idx_email        (email),
  ADD INDEX  idx_statut       (statut);
```

**Règle de transition :** `users.etablissement_id` reste pour la compatibilité V1.
À terme (Phase 24.x), cette colonne sera supprimée au profit de `user_etablissements`.

---

## 8. RBAC par établissement

### 8.1 Architecture RBAC multi-tenant

```
NIVEAU 1 — Rôles système (globaux, non-modifiables)
  admin_platform   : Opérateur SaaS, accès total
  admin_etab       : Directeur/Admin de l'établissement
  enseignant       : Accès pédagogique standard
  secretaire       : Accès administratif
  comptable        : Accès financier
  parent           : Portail parent (ses enfants uniquement)
  eleve            : Portail élève (ses propres données)

NIVEAU 2 — Rôles personnalisés par établissement
  Créés par admin_etab via l'interface
  Exemple : 'responsable_internat', 'coordinateur_niveau', 'cpe'
  Stockés dans : etab_roles (etablissement_id NOT NULL)
```

### 8.2 Schéma RBAC V2 multi-tenant

```sql
-- Rôles (système + custom par tenant)
etab_roles (
    id               INT UNSIGNED PK,
    etablissement_id INT UNSIGNED NULL,   -- NULL = rôle système global
    code             VARCHAR(60)  UNIQUE per tenant,
    nom              VARCHAR(100) NOT NULL,
    description      TEXT NULL,
    is_system        TINYINT(1) DEFAULT 0,
    created_at       DATETIME
)

-- Permissions (système — non modifiables)
etab_permissions (
    id      INT UNSIGNED PK,
    code    VARCHAR(120) UNIQUE,          -- 'eleves.create', 'notes.export'
    libelle VARCHAR(255),
    module  VARCHAR(50),
    action  VARCHAR(50)                   -- 'view','create','edit','delete','export'...
)

-- Association rôle → permissions (par tenant)
etab_role_permissions (
    role_id       INT UNSIGNED,
    permission_id INT UNSIGNED,
    granted_at    DATETIME,
    granted_by    INT UNSIGNED,
    PK(role_id, permission_id)
)

-- Association utilisateur → rôle dans un établissement
user_roles_etab (
    id               BIGINT UNSIGNED PK,
    user_id          INT UNSIGNED NOT NULL,
    etablissement_id INT UNSIGNED NOT NULL,
    role_id          INT UNSIGNED NOT NULL,
    assigned_at      DATETIME,
    assigned_by      INT UNSIGNED NULL,
    UNIQUE(user_id, etablissement_id, role_id)
)
```

### 8.3 Résolution des permissions

```
TenantAuthContext::permissions() :
  1. Lookup user_roles_etab WHERE user_id = :uid AND etablissement_id = :etab
  2. Pour chaque role_id → lookup etab_role_permissions JOIN etab_permissions
  3. Retourner tableau flat de codes : ['eleves.view', 'notes.create', ...]
  4. Mettre en cache Redis : 'perms:{etab_id}:{user_id}' (TTL 5 min)
```

### 8.4 Permissions spéciales multi-tenant

```
PERMISSION TRANSVERSALE (portée multi-établissements)
  Code : *.manage (admin_platform uniquement)

PERMISSION LIMITÉE (portée propre à l'utilisateur)
  notes.view_own     → Ne voir que ses propres notes (élève)
  absences.view_own  → Ne voir que ses propres absences (élève/parent)
  paiements.view_own → Ne voir que ses propres paiements (parent/élève)

DÉLÉGATION DE PERMISSION
  Un admin_etab peut déléguer n'importe quelle permission
  SAUF les permissions système (is_system=1)
  SAUF les permissions au-delà de ses propres droits
  (no privilege escalation)
```

---

## 9. Paramètres par établissement

### 9.1 Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    SettingsService (par tenant)                  │
│                                                                  │
│  get(string $key, mixed $default = null): mixed                 │
│  set(string $key, mixed $value): void                           │
│  section(string $section): array                                │
│  flush(string $key = null): void  // vide le cache              │
│                                                                  │
│  Résolution : Redis cache → DB etab_settings → valeur par défaut│
└─────────────────────────────────────────────────────────────────┘
```

### 9.2 Table `etab_settings`

```sql
CREATE TABLE etab_settings (
    id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    etablissement_id INT UNSIGNED    NOT NULL,
    section          VARCHAR(60)     NOT NULL,   -- 'general', 'academique', 'finance'...
    cle              VARCHAR(100)    NOT NULL,
    valeur           TEXT            NULL,        -- JSON si complexe, string sinon
    type             ENUM('string','integer','boolean','json','datetime') NOT NULL DEFAULT 'string',
    modifiable_ui    TINYINT(1)      NOT NULL DEFAULT 1,
    updated_by       INT UNSIGNED    NULL,
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_etab_setting (etablissement_id, section, cle),
    KEY idx_etab_section (etablissement_id, section)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 9.3 Catalogue des paramètres par section

```
SECTION : general
  school.name              VARCHAR   Nom affiché dans l'UI
  school.short_name        VARCHAR   Acronyme
  school.address           VARCHAR
  school.phone             VARCHAR
  school.email             VARCHAR
  school.timezone          VARCHAR   'Africa/Algiers'
  school.locale            VARCHAR   'fr_DZ', 'ar_DZ'
  school.academic_year     VARCHAR   '2026-2027'
  school.currency          CHAR(3)   'DZD'
  school.date_format       VARCHAR   'DD/MM/YYYY'

SECTION : academique
  grades.scale             INTEGER   20 (sur 20), 100 (sur 100), 5 (sur 5)
  grades.pass_threshold    FLOAT     10.0
  grades.honor_threshold   FLOAT     16.0
  bulletin.show_ranking    BOOLEAN   true
  bulletin.show_absences   BOOLEAN   true
  periods.count            INTEGER   3 (trimestres) ou 2 (semestres)
  periods.names            JSON      ['Trimestre 1', 'Trimestre 2', 'Trimestre 3']

SECTION : finance
  fees.late_penalty_rate   FLOAT     0.05 (5%)
  fees.grace_period_days   INTEGER   15
  fees.receipt_prefix      VARCHAR   'RECU-'
  invoice.prefix           VARCHAR   'FACT-'
  accounting.fiscal_year   CHAR(9)   '2026-2027'
  accounting.currency      CHAR(3)   'DZD'
  payment.methods          JSON      ['especes','virement','cheque']

SECTION : notifications
  email.enabled            BOOLEAN   true
  email.from_name          VARCHAR   'SCOLARIS'
  sms.enabled              BOOLEAN   false
  push.enabled             BOOLEAN   true
  alerts.absence_threshold INTEGER   3 (alerter après N absences)

SECTION : branding
  logo_url                 VARCHAR   '/storage/{etab}/branding/logo.png'
  logo_dark_url            VARCHAR
  favicon_url              VARCHAR
  primary_color            VARCHAR   '#6366f1' (violet Tailwind)
  secondary_color          VARCHAR   '#0ea5e9'
  app_name                 VARCHAR   'MonEcole'
  welcome_message          TEXT
```

---

## 10. Branding et personnalisation

### 10.1 Niveaux de personnalisation

```
NIVEAU 1 — Couleurs et logo (tous plans)
  primary_color, secondary_color, logo, favicon
  Appliqué via CSS variables injectées dans le <head> de chaque page

NIVEAU 2 — Nom de l'application (plan Pro+)
  app_name → affiché dans le titre de la page, les emails, les PDFs
  welcome_message → affiché sur la page de connexion

NIVEAU 3 — Templates email et PDF (plan Enterprise)
  Remplacement du template d'en-tête/pied de page des bulletins
  Logo personnalisé dans les relevés de compte
  Signature email personnalisée

NIVEAU 4 — Domaine personnalisé (plan Enterprise)
  Voir §11
```

### 10.2 Injection du branding

```
PHP Bootstrap :
  BrandingService::load(TenantContext::id())
  → Charge logo_url, primary_color, secondary_color depuis etab_settings
  → Stocke dans une variable globale accessible aux vues

Vues Blade/PHP :
  <link rel="icon" href="<?= $branding->faviconUrl ?>">
  <style>
    :root {
      --color-primary: <?= $branding->primaryColor ?>;
      --color-secondary: <?= $branding->secondaryColor ?>;
    }
  </style>

PDFs (TCPDF/mPDF) :
  BulletinTemplate::setLogo($branding->logoUrl)
  BulletinTemplate::setColors($branding->primaryColor)
```

### 10.3 Stockage branding

```
storage/{etablissement_id}/branding/
  logo.png          (max 2 Mo, min 200×200px)
  logo-dark.png     (variante fond sombre, optionnel)
  favicon.ico       (ou .png 32×32)
  email-header.png  (pour templates email, optionnel)
```

---

## 11. Domaines personnalisés

### 11.1 Table `etablissement_domains`

```sql
CREATE TABLE etablissement_domains (
    id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    etablissement_id INT UNSIGNED    NOT NULL,
    domain           VARCHAR(255)    NOT NULL UNIQUE,   -- 'ecole.institutibadis.dz'
    type             ENUM('custom','subdomain') NOT NULL DEFAULT 'subdomain',
    verified         TINYINT(1)      NOT NULL DEFAULT 0,
    ssl_status       ENUM('pending','issued','error') NOT NULL DEFAULT 'pending',
    ssl_expires_at   DATE            NULL,
    verified_at      DATETIME        NULL,
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_domain (domain),
    KEY idx_etab   (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 11.2 Processus de validation

```
ÉTAPE 1 — L'admin ajoute le domaine dans l'interface
  POST /settings/domains { domain: 'ecole.institutibadis.dz' }

ÉTAPE 2 — SCOLARIS génère un token de vérification
  Challenge : TXT record  scolaris-verify=<token32>
  ou         : Fichier     /.well-known/scolaris-verify.txt

ÉTAPE 3 — Vérification DNS (job asynchrone toutes les 5 min)
  DomainVerificationJob::handle()
  → dns_get_record($domain, DNS_TXT)
  → Si token trouvé → verified=1, déclenche SSL provisioning

ÉTAPE 4 — SSL automatique (Let's Encrypt ACME)
  AcmeSslJob::provision($domain)
  → ACME challenge HTTP-01
  → Stockage certificat dans /etc/ssl/scolaris/{domain}/
  → Mise à jour Nginx/Caddy dynamique
  → ssl_status = 'issued'
```

---

## 12. Stockage des fichiers

### 12.1 Architecture Storage multi-tenant

```
PRINCIPE : Isolation par tenant + Interface StorageAdapter

StorageInterface::
  put(string $path, mixed $content, string $disk = 'tenant'): string
  get(string $path): mixed
  url(string $path, int $expiresIn = 3600): string   // URL signée
  delete(string $path): void
  exists(string $path): bool
```

### 12.2 Structure des chemins

```
DISQUE 'tenant' (S3-compatible) :
  {bucket}/tenants/{etablissement_id}/{module}/{context}/{filename}

Exemples :
  tenants/42/documents/contrats/contrat-prof-123.pdf
  tenants/42/branding/logo.png
  tenants/42/bulletins/2026-2027/trimestre-1/bulletin-eleve-456.pdf
  tenants/42/rh/photos/employe-789.jpg
  tenants/42/bibliotheque/couvertures/livre-101.jpg
  tenants/42/uploads/2026/07/abc123def456.pdf

DISQUE 'shared' (assets globaux) :
  shared/templates/bulletin-default.html
  shared/fonts/
  shared/icons/
```

### 12.3 Adapters Storage

```
LOCAL (développement WAMP) :
  Chemin : ROOT_PATH/storage/tenants/{etab_id}/{module}/
  URL    : https://{app_url}/storage/tenants/{etab_id}/...
  Sécurité : .htaccess deny all + token signé en PHP

S3 (AWS / Wasabi / MinIO — production) :
  Bucket : scolaris-{environment}
  Path   : tenants/{etab_id}/{module}/...
  ACL    : private (toujours)
  URL    : Signed URL (15min pour documents, 1h pour médias)
  Région : eu-west-3 (Paris) pour données DZ/FR

OVH Object Storage / Scaleway :
  Même interface S3-compatible
  Région : Algérie / Europe

Configuration (env) :
  STORAGE_DRIVER=local|s3|ovh
  AWS_BUCKET=scolaris-prod
  AWS_REGION=eu-west-3
  AWS_ACCESS_KEY_ID=...
  AWS_SECRET_ACCESS_KEY=...
```

### 12.4 Quotas et limites par plan

```
Plan       | Quota       | Fichier max | Types autorisés
-----------|-------------|-------------|---------------------------
Trial      | 512 Mo      | 5 Mo        | PDF, Images
Starter    | 2 Go        | 10 Mo       | PDF, Images, Excel, Word
Pro        | 20 Go       | 50 Mo       | Tous types
Enterprise | Illimité    | 200 Mo      | Tous types

Contrôle :
  StorageQuotaChecker::check($etabId, $fileSize)
  → SELECT SUM(size_bytes) FROM api_uploads WHERE etablissement_id = :etab
  → Comparer avec etablissements.storage_quota_mb * 1024 * 1024
  → Lancer StorageQuotaExceededException si dépassé
```

---

## 13. Notifications

### 13.1 Architecture multi-canal multi-tenant

```
NotificationService::send(Notification $notif, int $etabId):
      │
      ├─ Canal EMAIL
      │    EmailDriver::send()
      │    Config : etab_settings.email.* (from, SMTP ou SES)
      │    Template : tenant-branded (logo + couleurs du tenant)
      │    Queue : email_queue (priorité normale)
      │
      ├─ Canal SMS
      │    SmsDriver::send()
      │    Config : etab_settings.sms.* (gateway, sender)
      │    Queue : sms_queue (priorité haute)
      │
      ├─ Canal PUSH (Web Push / FCM)
      │    PushDriver::send()
      │    Config : VAPID keys (partagées ou par tenant)
      │    Filtre : push_subscriptions WHERE etablissement_id = :etab
      │
      └─ Canal IN-APP
           Insertion dans com_notifications (etablissement_id filtré)
           Diffusion via SSE ou WebSocket (long-polling fallback)
```

### 13.2 Configuration SMTP par tenant

```sql
-- Dans etab_settings :
SECTION 'notifications' :
  email.driver      : 'smtp' | 'ses' | 'platform'
  email.host        : 'mail.etab.dz' (SMTP propre) ou NULL (utilise plateforme)
  email.port        : 587
  email.username    : ...
  email.password    : (chiffré en DB via AES-256)
  email.from_address: 'no-reply@ecole.dz'
  email.from_name   : 'Lycée Ibn Badis'

FALLBACK : Si email.driver = 'platform' → SMTP mutualisé SCOLARIS
```

### 13.3 Templates email multi-tenant

```
templates/email/
  base.html              ← Template master (header/footer injected per tenant)
  partials/
    header.html          ← {{logo_url}}, {{primary_color}}, {{school_name}}
    footer.html          ← {{address}}, {{phone}}, {{website}}
  modules/
    absence_alert.html
    bulletin_disponible.html
    paiement_recu.html
    ...

Rendu :
  EmailTemplateRenderer::render('absence_alert', $data, $etabId)
  → Charge branding du tenant
  → Substitue {{variables}} dans le template
```

---

## 14. Rapports et Analytics

### 14.1 Isolation des rapports

Toutes les queries analytiques portent `etablissement_id` en premier prédicat :

```sql
-- Exemple : taux de réussite par établissement
SELECT
    AVG(CASE WHEN valeur >= seuil.valeur THEN 1.0 ELSE 0.0 END) AS taux_reussite
FROM notes n
JOIN etab_settings seuil ON seuil.etablissement_id = n.etablissement_id
    AND seuil.cle = 'grades.pass_threshold'
WHERE n.etablissement_id = :etab      -- PREMIER FILTRE OBLIGATOIRE
  AND n.periode_id = :periode
```

### 14.2 Analytics plateforme (multi-tenant, opérateur seulement)

```
Super Admin Dashboard SaaS :
  - Nombre d'établissements actifs
  - Répartition par plan
  - Usage storage global
  - Appels API / tenant
  - Taux d'erreur par tenant
  - Tenants proches du quota

Stockés dans :
  platform_analytics_snapshots (
    snapshot_at, period, total_tenants, active_tenants,
    total_users, total_eleves, total_api_calls,
    storage_used_gb, top_tenants JSON, errors_total
  )
```

### 14.3 Export de données par tenant

```
Tenant peut exporter ses propres données :
  GET /api/v1/exports/{type}?format=csv|xlsx|json

Types d'export :
  eleves, notes, absences, paiements, employes, bulletins

Contraintes :
  - Toujours filtré par etablissement_id = contexte courant
  - Export JSON complet (RGPD portabilité) : job asynchrone → notification email
  - Rate limit : 10 exports/heure/tenant
```

---

## 15. Cache

### 15.1 Architecture cache Redis multi-tenant

```
NAMESPACE PATTERN : {prefix}:{etab_id}:{key}

Exemples :
  scolaris:etab:42:settings:general       → paramètres généraux etab 42
  scolaris:etab:42:permissions:user:15    → permissions user 15 dans etab 42
  scolaris:etab:42:branding               → branding du tenant 42
  scolaris:etab:42:stats:dashboard        → données dashboard (TTL 5 min)
  scolaris:etab:42:eleves:count           → nombre d'élèves (TTL 1h)
  scolaris:platform:plans                 → plans tarifaires (TTL 1h)
  scolaris:tenant:slug:lycee-ibn-badis    → résolution slug→id (TTL 1h)
```

### 15.2 Règles de TTL

```
Données             | TTL    | Invalidation
--------------------|--------|------------------------------------------
Résolution tenant   | 1h     | Sur mise à jour etablissements.slug
Branding            | 30min  | Sur PUT /settings/branding
Paramètres          | 15min  | Sur PUT /settings/{section}/{key}
Permissions         | 5min   | Sur modification de rôle
Dashboard stats     | 2min   | Refresh actif
Count queries       | 5min   | Sur création/suppression
Sessions utilisateur| 24h    | Sur logout
```

### 15.3 Purge d'un tenant

```php
CacheManager::flushTenant(int $etablissementId): void
{
    // Redis SCAN + DEL pattern : scolaris:etab:{id}:*
    // Utilisé lors :
    //   - Suspension d'un tenant
    //   - Migration de données
    //   - Demande de purge admin
}
```

### 15.4 Drivers cache supportés

```
LOCAL (développement) : PHP array cache (APCu ou tableau en mémoire)
PRODUCTION           : Redis 7.x (Valkey fork)
HAUTE DISPO          : Redis Sentinel (3 nodes) ou Redis Cluster

Configuration :
  CACHE_DRIVER=redis
  REDIS_HOST=redis-master.internal
  REDIS_PORT=6379
  REDIS_PASSWORD=...
  REDIS_DB=0
  CACHE_PREFIX=scolaris:
```

---

## 16. Files d'attente (Workers)

### 16.1 Architecture Queue multi-tenant

```
QUEUES PAR PRIORITÉ ET TYPE :

  critical          Livraison emails urgents, paiements
  high              SMS, notifications push
  default           Génération bulletins, exports
  low               Analytics, cleanup, purge cache
  webhooks          Livraison webhooks (tenant-isolated)
  maintenance       Migrations, sauvegardes automatiques

ISOLATION TENANT : Chaque job porte tenant_id
  class AbstractTenantJob implements ShouldQueue {
      public int $tenantId;

      public function handle(): void {
          TenantContext::set($this->tenantId);
          $this->execute();
          TenantContext::clear();
      }
  }
```

### 16.2 Jobs prévus par module

```
MODULE              | JOB                          | QUEUE
--------------------|------------------------------|----------
WebhooksWorker      | ProcessWebhookDelivery       | webhooks
Communication       | SendEmailJob                 | high
                    | SendSmsJob                   | critical
                    | SendPushNotificationJob      | high
Académique          | GenerateBulletinJob          | default
                    | ComputeClassementJob         | low
Finance             | SendPaymentReceiptJob        | high
                    | SendInvoiceReminderJob       | default
                    | GenerateFinancialReportJob   | low
Documents           | ProcessDocumentUploadJob     | default
Storage             | PurgeExpiredUploadsJob       | low
Platform            | DomainVerificationJob        | low
                    | SslProvisioningJob           | low
                    | TenantBackupJob              | maintenance
                    | StorageQuotaAlertJob         | low
                    | AnalyticsSnapshotJob         | low
```

### 16.3 Workers (déploiement)

```
DÉVELOPPEMENT (WAMP) :
  Cron Windows toutes les minutes :
  C:\wamp64\bin\php\php8.2.29\php.exe artisan queue:work --queue=critical,high,default

PRODUCTION (Linux) :
  Supervisord :
    [program:scolaris-worker-critical]
    command=php artisan queue:work redis --queue=critical,high --sleep=1 --tries=3
    numprocs=2
    autostart=true
    autorestart=true

    [program:scolaris-worker-default]
    command=php artisan queue:work redis --queue=default,low --sleep=3 --tries=3
    numprocs=4

    [program:scolaris-webhooks]
    command=php artisan queue:work redis --queue=webhooks --sleep=2 --tries=5
    numprocs=2

    [program:scolaris-maintenance]
    command=php artisan queue:work redis --queue=maintenance --sleep=30
    numprocs=1
```

---

## 17. API Platform — Multi-tenant

### 17.1 Résolution tenant dans l'API

```
Triple source pour résoudre l'établissement en contexte API :

1. JWT claim 'etab'       → Priority 1, déjà résolu
2. API Key lookup         → etab = api_keys.etablissement_id
3. Header X-Tenant-Slug  → lookup etablissements.slug

AuthContext.etablissementId est TOUJOURS renseigné.
ApiBaseController::getEtabId() throw si 0.
```

### 17.2 URL API par tenant

```
PRODUCTION :
  https://api.scolaris.app/v1/...          (avec X-Tenant-Slug header)
  ou
  https://lycee-ibn-badis.scolaris.app/api/v1/... (subdomain-based)

CUSTOM DOMAIN :
  https://ecole.institutibadis.dz/api/v1/...
```

### 17.3 Rate Limiting multi-tenant

```
Bucket key : rl:{group}:{etab_id}:{user_id}
             rl:api_key_read:{etab_id}:{api_key_id}

Quotas par plan :
  Plan      | default (req/min) | api_key_read (req/h)
  ----------|-------------------|---------------------
  Trial     |   60              |   500
  Starter   |  300              |  2000
  Pro       | 1000              | 10000
  Enterprise| illimité          | illimité

Implémentation : config/api.php rate_limit étendu avec plan-aware lookup
```

### 17.4 Réponse API enrichie avec tenant info

```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "school_id": 42,
    "school_name": "Lycée Ibn Badis",
    "request_id": "uuid-...",
    "api_version": "1"
  }
}
```

---

## 18. Événements et Webhooks

### 18.1 Événements multi-tenant

Tous les événements du projet portent `etablissementId` :

```php
interface TenantEvent
{
    public function getEtablissementId(): int;
}

// Exemple :
class EleveCreated implements TenantEvent {
    public readonly int $etablissementId;
    public readonly int $eleveId;

    public function getEtablissementId(): int {
        return $this->etablissementId;
    }

    public function toArray(): array {
        return [
            'etablissement_id' => $this->etablissementId,
            'eleve_id'         => $this->eleveId,
            // ...
        ];
    }
}
```

### 18.2 WebhookDispatcher — isolation garantie

```
WebhookDispatcher::onEvent($name, $event)
  → TenantEvent::getEtablissementId() → $etabId
  → WebhookRepository::findActiveForEvent($name, $etabId)
    ← SELECT ... WHERE etablissement_id = :etab  ← ISOLATION GARANTIE
```

### 18.3 Catalogue d'événements webhooks par module

```
MODULE SCOLARITÉ
  scolarite.eleve.created       scolarite.eleve.archived
  scolarite.inscription.created scolarite.classe.full

MODULE ACADÉMIQUE
  academique.note.created       academique.bulletin.generated
  academique.periode.closed

MODULE FINANCE
  finance.facture.created       finance.paiement.completed
  finance.facture.overdue

MODULE VIE SCOLAIRE
  vie_scolaire.absence.recorded vie_scolaire.absence.threshold_reached
  vie_scolaire.incident.created

MODULE RH
  rh.employe.hired              rh.conge.approved
  rh.contrat.expiring

PLATEFORME
  platform.tenant.created       platform.plan.upgraded
  platform.storage.quota_near   platform.backup.completed
```

---

## 19. Sauvegardes et Restauration

### 19.1 Stratégie de sauvegarde multi-tenant

```
NIVEAU 1 — Sauvegarde globale (opérateur SaaS)
  Fréquence  : Quotidienne à 02h00 UTC
  Cible      : Dump MySQL complet + S3 objects
  Rétention  : 30 jours quotidiens + 12 mois mensuels + 7 ans annuels
  Stockage   : S3 Glacier (coût réduit)

NIVEAU 2 — Sauvegarde par tenant (admin établissement)
  Fréquence  : Hebdomadaire (dimanche 03h00 UTC)
  Cible      : mysqldump --where="etablissement_id=N" + fichiers /tenants/N/
  Format     : ZIP chiffré AES-256 + clé derivée du plan
  Rétention  : 4 semaines
  Interface  : "Télécharger ma sauvegarde" dans les paramètres

NIVEAU 3 — Snapshot avant migration (automatique)
  Déclencheur : Avant toute migration SQL
  Cible       : mysqldump complet
  Rétention   : 7 jours
```

### 19.2 Isolation du dump par tenant

```sql
-- Script de dump tenant (exemple mysqldump partiel) :
SELECT * FROM eleves           WHERE etablissement_id = N;
SELECT * FROM classes          WHERE etablissement_id = N;
SELECT * FROM users            WHERE id IN (SELECT user_id FROM user_etablissements WHERE etablissement_id = N);
SELECT * FROM notes            WHERE etablissement_id = N;
SELECT * FROM finance_factures WHERE etablissement_id = N;
-- ... toutes les tables métier
```

### 19.3 Restauration

```
RESTAURATION COMPLÈTE (sinistre total) :
  1. Restaurer dump MySQL global → nouvelle instance DB
  2. Restaurer S3 objects → nouveau bucket

RESTAURATION PAR TENANT :
  1. Identifier le ZIP tenant chiffré (date/heure)
  2. Déchiffrer avec clé du plan
  3. Exécuter les INSERTs avec ON DUPLICATE KEY UPDATE
  4. Restaurer les fichiers dans /tenants/{id}/

RESTAURATION POINT-IN-TIME (plan Enterprise) :
  MySQL Binlog replay jusqu'au timestamp désiré
  Filtré sur tables WHERE etablissement_id = N
```

### 19.4 Table `platform_backups`

```sql
CREATE TABLE platform_backups (
    id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    etablissement_id INT UNSIGNED    NULL,          -- NULL = global backup
    type             ENUM('global','tenant','pre_migration','manual') NOT NULL,
    statut           ENUM('pending','running','success','failed') NOT NULL DEFAULT 'pending',
    storage_path     VARCHAR(500)    NOT NULL,
    size_bytes       BIGINT UNSIGNED NULL,
    checksum         VARCHAR(64)     NULL,          -- SHA-256
    started_at       DATETIME        NULL,
    completed_at     DATETIME        NULL,
    expires_at       DATE            NULL,
    error_message    TEXT            NULL,
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_etab   (etablissement_id),
    KEY idx_type   (type, statut),
    KEY idx_expire (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 20. Infrastructure Cloud / SaaS

### 20.1 Architecture cible

```
┌──────────────────────────────────────────────────────────────────┐
│                         DNS WILDCARD                              │
│              *.scolaris.app → Load Balancer IP                    │
└─────────────────────────────┬────────────────────────────────────┘
                              │
┌─────────────────────────────▼────────────────────────────────────┐
│                      LOAD BALANCER                                │
│            Nginx / HAProxy / AWS ALB                              │
│   SSL Termination (wildcard *.scolaris.app + custom domains)     │
└───┬─────────────────────────────────────────────────┬────────────┘
    │                                                 │
┌───▼───────────────────────────────┐   ┌─────────────▼────────────┐
│    WEB SERVERS (PHP-FPM)          │   │  WORKERS (PHP CLI)        │
│    Stateless — scale horizontal   │   │  Queue consumers          │
│    PHP 8.2, OPcache ON            │   │  Cron scheduler           │
│    Min 2 — Max N (auto-scale)     │   │  Webhook processor        │
└───┬─────────────────────────────┬─┘   └─────────────┬────────────┘
    │                             │                   │
┌───▼──────────────┐  ┌──────────▼──────┐  ┌─────────▼────────────┐
│  MySQL Primary   │  │  Redis Cluster  │  │  S3 Object Storage   │
│  (Writes)        │  │  Cache + Queue  │  │  Files / Backups     │
│  + Read Replicas │  │  3 nodes        │  │  Private ACL + CDN   │
└──────────────────┘  └─────────────────┘  └──────────────────────┘
```

### 20.2 Variables d'environnement spécifiques multi-tenant

```env
# Mode multi-tenant
APP_MULTITENANCY_ENABLED=true
APP_MULTITENANCY_MODE=subdomain           # subdomain|path|header
APP_DOMAIN=scolaris.app
APP_URL=https://scolaris.app

# Base de données
DB_HOST=mysql-primary.internal
DB_READ_HOST=mysql-replica.internal       # Lecture sur replica
DB_PORT=3306
DB_DATABASE=scolaris_prod
DB_USERNAME=scolaris_app
DB_PASSWORD=...

# Cache Redis
REDIS_HOST=redis-master.internal
REDIS_SENTINEL=redis-sentinel-1:26379,redis-sentinel-2:26379
CACHE_DRIVER=redis
QUEUE_DRIVER=redis
SESSION_DRIVER=redis

# Storage
STORAGE_DRIVER=s3
AWS_BUCKET=scolaris-prod
AWS_REGION=eu-west-3
AWS_USE_PATH_STYLE_ENDPOINT=false         # true pour MinIO

# SSL Automatique (Let's Encrypt)
ACME_EMAIL=ops@scolaris.app
ACME_DRIVER=letsencrypt                   # letsencrypt|buypass|zerossl

# Monitoring
SENTRY_DSN=https://...@sentry.io/...
OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318
METRICS_ENABLED=true
```

---

## 21. Montée en charge

### 21.1 Goulots d'étranglement identifiés et solutions

```
GOULOT 1 : MySQL — lectures multi-tenant
Solution :
  → Read replicas pour les SELECT (DB::connection('read'))
  → Index composite obligatoire : (etablissement_id, date_creation)
  → Pagination cursor-based pour grandes collections (>100k lignes)
  → Partitionnement par RANGE sur DATE (déjà sur api_request_logs)
  → ProxySQL pour connection pooling

GOULOT 2 : Génération de bulletins (CPU-intensif)
Solution :
  → Job asynchrone (queue 'default')
  → Cache HTML généré (S3) avec TTL = durée de la période
  → Génération en masse : BulkBulletinJob (batches de 50)

GOULOT 3 : Fichiers statiques (images, PDFs)
Solution :
  → CDN devant S3 (CloudFront / BunnyCDN)
  → Headers Cache-Control: public, max-age=31536000 pour assets immuables
  → Signed URLs courtes (15min) pour docs sensibles

GOULOT 4 : Résolution tenant (1 DB lookup / requête)
Solution :
  → Cache Redis : scolaris:tenant:slug:{slug} → {id} (TTL 1h)
  → Cache Redis : scolaris:tenant:domain:{domain} → {id} (TTL 30min)
  → Invalidé uniquement sur changement de slug/domaine

GOULOT 5 : Résolution permissions (1 DB lookup / requête)
Solution :
  → Cache Redis : scolaris:etab:{id}:perms:{user_id} (TTL 5min)
  → Invalidé sur modification de rôle ou de role_permissions
```

### 21.2 Métriques cibles

```
Tenant moyen (500 élèves, 30 enseignants) :
  Requêtes / jour    : ~5 000
  Stockage           : ~2 Go
  Pic concurrent     : ~50 utilisateurs simultanés

Plateforme cible (Phase 1) :
  100 établissements
  50 000 élèves
  5 000 utilisateurs simultanés au pic (rentrée)
  Uptime : 99.9% (8h45 d'indisponibilité max/an)

Plateforme cible (Phase 2) :
  1 000 établissements
  500 000 élèves
  Scale-out horizontal automatique (Kubernetes HPA)
```

### 21.3 Indices SQL obligatoires par table (multi-tenant)

```sql
-- Patron d'index pour TOUTE table métier
CREATE INDEX idx_{table}_{pk}    ON {table} (etablissement_id);
CREATE INDEX idx_{table}_date    ON {table} (etablissement_id, created_at);
CREATE INDEX idx_{table}_status  ON {table} (etablissement_id, statut)   -- si colonne statut

-- Indices critiques identifiés :
users           : (email)  — déjà présent dans V1 ; ajouter (etablissement_id)
eleves          : (etablissement_id, classe_id), (etablissement_id, matricule)
notes           : (etablissement_id, eleve_id, periode_id)
absences        : (etablissement_id, eleve_id, date_absence)
finance_factures: (etablissement_id, statut, date_echeance)
rh_employes     : (etablissement_id, matricule)
api_request_logs: déjà partitionné, idx (etablissement_id, created_at) présent ✓
```

---

## 22. Haute Disponibilité

### 22.1 Composants et stratégie HA

```
COMPOSANT        | STRATÉGIE HA             | RTO | RPO
-----------------|--------------------------|-----|-----
Web servers      | N actif / Load Balanced  | 0s  | 0
MySQL            | Primary + 2 Read Replicas| 30s | <1min
                 | Failover auto (Orchestrator)
Redis            | Redis Sentinel (3 nodes) | 10s | 0 (append-only)
S3 Storage       | Multi-AZ natif           | 0   | 0
Queue Workers    | N instances Supervisord  | 30s | at-least-once

RTO : Recovery Time Objective — temps pour restaurer le service
RPO : Recovery Point Objective — perte de données maximale
```

### 22.2 Health Checks

```
GET /api/v1/health    → Standard (public)
GET /internal/health  → Détaillé (interne, IP whitelist)

Réponse détaillée :
{
  "status": "healthy",
  "checks": {
    "database_write": {"status":"ok","latency_ms":2},
    "database_read":  {"status":"ok","latency_ms":1},
    "redis":          {"status":"ok","latency_ms":0},
    "storage":        {"status":"ok"},
    "queue_depth":    {"status":"ok","pending":12}
  },
  "tenants_active": 87,
  "version": "2.0.0"
}
```

### 22.3 Circuit Breaker par tenant

```
Si un tenant génère une erreur rate anormalement haute :
  → Circuit breaker : suspendre le traitement de ses jobs 5min
  → Alerter l'équipe ops
  → Logger dans platform_anomalies

Implémentation : TenantCircuitBreaker
  track(int $etabId, string $operation): void
  isOpen(int $etabId): bool
  reset(int $etabId): void
```

---

## 23. Schémas SQL — Nouvelles tables

### 23.1 Récapitulatif complet des tables Phase 14

```sql
-- GROUPE PLATFORM (nouvelles tables)
etablissements              (§4.2)
user_etablissements         (§4.3)
platform_plans              (§4.4)
platform_operators          (§4.5)

-- GROUPE TENANT CONFIG
etab_settings               (§9.2)
etab_roles                  (§8.2)
etab_permissions            (§8.2) -- seeded from permissions.php
etab_role_permissions       (§8.2)
user_roles_etab             (§8.2)
etablissement_domains       (§11.1)
etablissement_branding      (inclus dans etab_settings)

-- GROUPE PLATFORM OPS
platform_backups            (§19.4)
platform_anomalies          (nouveau — circuit breaker logs)
platform_analytics_snapshots (§14.2 — agrégats cross-tenant)
```

### 23.2 Table `etablissement_branding` (cache dénormalisé)

```sql
CREATE TABLE etablissement_branding (
    etablissement_id  INT UNSIGNED    NOT NULL,
    logo_url          VARCHAR(500)    NULL,
    logo_dark_url     VARCHAR(500)    NULL,
    favicon_url       VARCHAR(500)    NULL,
    primary_color     CHAR(7)         NOT NULL DEFAULT '#6366f1',
    secondary_color   CHAR(7)         NOT NULL DEFAULT '#0ea5e9',
    app_name          VARCHAR(100)    NOT NULL DEFAULT 'SCOLARIS',
    welcome_message   TEXT            NULL,
    updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 23.3 Table `platform_anomalies`

```sql
CREATE TABLE platform_anomalies (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    etablissement_id INT UNSIGNED    NULL,
    type             VARCHAR(60)     NOT NULL,  -- 'error_rate_spike', 'quota_exceeded'
    details          JSON            NULL,
    resolved         TINYINT(1)      NOT NULL DEFAULT 0,
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_etab    (etablissement_id),
    KEY idx_type    (type, resolved),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 24. Plan de migration

### 24.1 Migration en 4 phases (zero-downtime)

```
PHASE A — Platform Foundation (sans impact sur V1)
  A1. Créer tables : etablissements, platform_plans, platform_operators
  A2. Créer tables : user_etablissements, user_roles_etab
  A3. Créer tables : etab_roles, etab_permissions, etab_role_permissions
  A4. Créer tables : etab_settings, etablissement_branding, etablissement_domains
  A5. Seed : 1 établissement de démo dans `etablissements`
  A6. Seed : plans Starter/Pro/Enterprise dans `platform_plans`
  A7. Seed : permissions depuis config/permissions.php → etab_permissions
  A8. Seed : rôles système → etab_roles (etablissement_id = NULL)

PHASE B — Migration tables V1 (fenêtre de maintenance courte)
  B1. ALTER TABLE users ADD etablissement_id INT NULL
  B2. ALTER TABLE classes ADD etablissement_id INT NULL
  B3. ALTER TABLE eleves  ADD etablissement_id INT NULL
  B4. ALTER TABLE professeurs ADD etablissement_id INT NULL
  B5. ALTER TABLE matieres    ADD etablissement_id INT NULL
  B6. UPDATE * SET etablissement_id = 1 (l'établissement existant)
  B7. ALTER TABLE ... MODIFY etablissement_id INT NOT NULL
  B8. CREATE INDEX idx_etab ON users(etablissement_id)
  B9. Même chose pour classes, eleves, professeurs, matieres, notes, absences

PHASE C — Migration tables référentielles partagées
  C1. ALTER TABLE rh_departements        ADD etablissement_id INT NULL
  C2. ALTER TABLE rh_postes              ADD etablissement_id INT NULL
  C3. ALTER TABLE finance_categories_frais ADD etablissement_id INT NULL
  C4. UPDATE V1 data : SET etablissement_id = 1 pour les enregistrements existants
  C5. Garder NULL pour les seeds globaux (postes standards, catégories frais globales)

PHASE D — Code migration
  D1. Implémenter TenantContext + TenantResolver
  D2. Modifier Bootstrap : appeler TenantResolver avant tout controller
  D3. Mettre à jour LoginController : émettre JWT avec claim 'etab'
  D4. Activer TenantMiddleware sur toutes les routes métier
  D5. Mettre à jour config/permissions.php → charger depuis DB si etab_id
```

### 24.2 Scripts SQL de migration V1 → multi-tenant

```sql
-- MIGRATION MT-B1 : users
ALTER TABLE users
    ADD COLUMN etablissement_id INT UNSIGNED NULL AFTER id,
    ADD COLUMN deleted_at       DATETIME NULL,
    ADD COLUMN statut           ENUM('actif','inactif','suspendu') NOT NULL DEFAULT 'actif',
    ADD INDEX  idx_users_etab   (etablissement_id);

-- MIGRATION MT-B2 : classes
ALTER TABLE classes
    ADD COLUMN etablissement_id INT UNSIGNED NULL AFTER id,
    ADD INDEX  idx_classes_etab (etablissement_id, annee_scolaire);

-- MIGRATION MT-B3 : eleves
ALTER TABLE eleves
    ADD COLUMN etablissement_id INT UNSIGNED NULL AFTER id,
    ADD INDEX  idx_eleves_etab  (etablissement_id, classe_id);

-- MIGRATION MT-B4 : matieres
ALTER TABLE matieres
    ADD COLUMN etablissement_id INT UNSIGNED NULL AFTER id;

-- MIGRATION MT-B5 : professeurs
ALTER TABLE professeurs
    ADD COLUMN etablissement_id INT UNSIGNED NULL AFTER id;

-- DATA : Assigner l'établissement existant (etab ID=1) à toutes les lignes V1
-- À exécuter APRÈS création de l'enregistrement dans etablissements (id=1)
UPDATE users       SET etablissement_id = 1 WHERE etablissement_id IS NULL;
UPDATE classes     SET etablissement_id = 1 WHERE etablissement_id IS NULL;
UPDATE eleves      SET etablissement_id = 1 WHERE etablissement_id IS NULL;
UPDATE matieres    SET etablissement_id = 1 WHERE etablissement_id IS NULL;
UPDATE professeurs SET etablissement_id = 1 WHERE etablissement_id IS NULL;

-- CONTRAINTE : Rendre NOT NULL après data fix
ALTER TABLE users       MODIFY etablissement_id INT UNSIGNED NOT NULL;
ALTER TABLE classes     MODIFY etablissement_id INT UNSIGNED NOT NULL;
ALTER TABLE eleves      MODIFY etablissement_id INT UNSIGNED NOT NULL;
ALTER TABLE matieres    MODIFY etablissement_id INT UNSIGNED NOT NULL;
ALTER TABLE professeurs MODIFY etablissement_id INT UNSIGNED NOT NULL;
```

### 24.3 Risques de migration et mitigation

```
RISQUE 1 : Fuite de données cross-tenant après migration
Mitigation : Tests automatisés d'isolation (§5.4) avant activation multi-tenant
             Feature flag : APP_MULTITENANCY_ENABLED=false pendant tests

RISQUE 2 : Performance dégradée après ajout de l'index
Mitigation : Ajout d'index en ALGORITHM=INPLACE sur MySQL 8.0+ (non-bloquant)
             Fenêtre de maintenance courte (<5min) pour ALTER TABLE

RISQUE 3 : Séquences d'ID partagées (risque de confusion)
Mitigation : Les IDs restent auto-increment partagés (pas de collision possible
             car le WHERE etablissement_id filtre toujours)
             ID BIGINT sur tables à fort volume pour éviter dépassement

RISQUE 4 : Rollback difficile après ajout de données multi-tenant
Mitigation : Snapshot DB complet avant Phase B (platform_backups type=pre_migration)
```

---

## 25. Feuille de route d'implémentation

### 25.1 Phases d'implémentation recommandées

```
PHASE 14.2 — Infrastructure Tenant (Fondation)
  Objectif    : Tables + TenantContext + TenantResolver fonctionnels
  Durée       : 2 jours
  Livrable    : Migrations MT-A1 à MT-A8 + TenantContext + TenantResolver
  Validation  : Test unitaire TenantResolver (subdomain, path, header)

PHASE 14.3 — Migration V1 Tables
  Objectif    : Ajout etablissement_id sur tables V1
  Durée       : 1 jour (dont fenêtre maintenance 15min)
  Livrable    : Migrations MT-B1 à MT-B9 + data fix scripts
  Validation  : Zero regression sur tests existants 458/458

PHASE 14.4 — RBAC Multi-tenant
  Objectif    : Rôles et permissions DB-driven par tenant
  Durée       : 3 jours
  Livrable    : etab_roles, user_roles_etab, SettingsService
  Validation  : Tests RBAC (chaque rôle × chaque permission)

PHASE 14.5 — Branding & Paramètres
  Objectif    : Interface admin par établissement
  Durée       : 2 jours
  Livrable    : SettingsController, BrandingService, vues settings
  Validation  : Upload logo, changement couleurs, reload UI

PHASE 14.6 — Utilisateurs Multi-Établissements
  Objectif    : School picker, JWT multi-tenant
  Durée       : 2 jours
  Livrable    : LoginController multi-tenant, school-picker UI
  Validation  : User appartient à 2 écoles, peut switcher sans re-login

PHASE 14.7 — Domaines Personnalisés
  Objectif    : DNS custom + SSL auto
  Durée       : 3 jours (infrastructure lourde)
  Livrable    : DomainVerificationJob, AcmeSslJob, Nginx dynamic conf
  Validation  : Ajouter domaine custom → SSL émis en <5min

PHASE 14.8 — Stockage S3 + Quotas
  Objectif    : Storage multi-tenant isolé
  Durée       : 2 jours
  Livrable    : StorageService S3-adapter, QuotaChecker, migration fichiers
  Validation  : Upload dans tenant A → invisible depuis tenant B

PHASE 14.9 — Cache Redis + Queue Workers
  Objectif    : Performance + async
  Durée       : 2 jours
  Livrable    : Redis namespaced, workers Supervisord, jobs listés §16.2
  Validation  : Génération bulletin → async → notification email

PHASE 14.10 — Super Admin Dashboard SaaS
  Objectif    : Interface opérateur plateforme
  Durée       : 3 jours
  Livrable    : Portail /platform/ (CRUD établissements, plans, analytics)
  Validation  : Créer tenant, assigner plan, suspendre, restaurer

PHASE 14.11 — Sauvegardes Automatiques
  Objectif    : Backup quotidien + export tenant
  Durée       : 1 jour
  Livrable    : TenantBackupJob, UI téléchargement backup
  Validation  : Backup créé → restauré sur DB test → données intactes
```

### 25.2 Dépendances entre phases

```
14.2 (Fondation) → 14.3 (Migration V1) → 14.4 (RBAC) → 14.6 (Multi-user)
                ↘                      ↘              ↘
                 14.5 (Branding)        14.7 (Domaines) 14.8 (Storage)
                                                        ↓
                                                      14.9 (Cache/Queue)
                                                        ↓
                                                      14.10 (SuperAdmin)
                                                        ↓
                                                      14.11 (Backups)
```

### 25.3 Critères de GO / NO-GO pour la Phase 14

```
GO si :
  ✓ Tests d'isolation : 0 fuite cross-tenant détectée
  ✓ 458/458 tests existants PASS après migration V1
  ✓ Performance : p95 latence < 200ms sur endpoints critiques
  ✓ Tenant resolver : subdomain + custom_domain + path testés
  ✓ RBAC : chaque rôle système testé sur chaque permission

NO-GO si :
  ✗ Un SELECT retourne des données d'un autre tenant
  ✗ Un utilisateur peut accéder à l'établissement d'un autre
  ✗ Régression sur les modules existants
  ✗ Performance dégradée > 20% après ajout des index
```

---

## Annexe A — Inventaire complet des tables et leur statut multi-tenant

```
Catégorie : CONFORMES (etablissement_id présent — 83 tables)
  Finance (8), Vie Scolaire (18), RH V2 (11), Documents (3),
  Communication (5), Bibliothèque (14), Inventaire (16),
  Rapports (5), Portails (4), API (7)

Catégorie : À MIGRER PHASE 14.3 (V1 tables — 9 tables)
  users, classes, eleves, professeurs, matieres, enseignements,
  notes (V1), absences (V1), periodes (V1)

Catégorie : RÉFÉRENTIELLES PARTAGÉES PHASE 14.3 (nullable etab_id — 3 tables)
  rh_departements, rh_postes, finance_categories_frais

Catégorie : NOUVELLES PHASE 14 (16 tables)
  etablissements, user_etablissements, platform_plans, platform_operators,
  etab_settings, etab_roles, etab_permissions, etab_role_permissions,
  user_roles_etab, etablissement_branding, etablissement_domains,
  platform_backups, platform_anomalies, platform_analytics_snapshots
  (+ 2 tables RBAC support)

TOTAL FINAL : ~115 tables (9 V1 migrées + 83 conformes + 3 référentielles + 16 nouvelles + 4 RBAC)
```

---

## Annexe B — Glossaire

```
Terme               | Définition
--------------------|-------------------------------------------------------
Tenant              | Établissement scolaire hébergé sur la plateforme
etablissement_id    | Clé primaire du tenant dans SCOLARIS (= tenant_id)
TenantContext       | Singleton PHP portant l'etab_id de la requête en cours
TenantResolver      | Service qui détermine le tenant depuis l'URL/JWT/Session
Shared Schema       | Architecture où tous les tenants partagent les mêmes tables
RBAC                | Role-Based Access Control — contrôle d'accès par rôle
Soft Delete         | Suppression logique : deleted_at != NULL (données conservées)
Circuit Breaker     | Pattern de résilience — stoppe les appels vers un service défaillant
Plan SaaS           | Niveau d'abonnement (Trial/Starter/Pro/Enterprise)
Slug                | Identifiant URL-safe de l'établissement ('lycee-ibn-badis')
```

---

*Document produit pour Phase 14.1 — SCOLARIS Multi-Tenant V2*  
*Aucun fichier de code n'a été modifié dans cette phase.*
