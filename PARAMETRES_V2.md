# PARAMÈTRES V2 — SCOLARIS
## Conception du Module de Configuration Centrale

> Étape 4 de la migration SCOLARIS V2.
> **Aucune implémentation.** Uniquement conception : tables, services, contrôleurs, permissions, interfaces.
> Date : 2026-06-29

---

## TABLE DES MATIÈRES

1. [Contexte et diagnostic de l'existant](#1-contexte-et-diagnostic-de-lexistant)
2. [Architecture du module](#2-architecture-du-module)
3. [Tables](#3-tables)
4. [Services](#4-services)
5. [Contrôleur et routes](#5-contrôleur-et-routes)
6. [Permissions](#6-permissions)
7. [Interfaces utilisateur](#7-interfaces-utilisateur)
8. [Migrations SQL](#8-migrations-sql)
9. [Catalogue des paramètres](#9-catalogue-des-paramètres)

---

## 1. CONTEXTE ET DIAGNOSTIC DE L'EXISTANT

### 1.1 Configuration actuelle — dispersée sur 6 sources

```
SOURCE 1 : .env (fichier serveur — non modifiable depuis l'UI)
  APP_NAME, APP_ENV, APP_DEBUG, APP_URL, APP_KEY, APP_TIMEZONE
  DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
  SESSION_LIFETIME, SESSION_NAME, UPLOAD_MAX_SIZE, UPLOAD_PATH
  MAIL_DRIVER, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM, MAIL_FROM_NAME
  SMS_GATEWAY_URL, SMS_SENDER, SMS_API_KEY
  VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY
  LOG_LEVEL

SOURCE 2 : config/app.php (lit .env)
  name, env, debug, url, key, timezone, session.*, upload.*

SOURCE 3 : config/mail.php (lit .env)
  from_name, from_email, reply_to, driver

SOURCE 4 : config/sms.php (lit .env)
  driver, sender, api_url, api_key, payload_tpl, country_code

SOURCE 5 : public/manifest.json (fichier statique — non modifiable depuis l'UI)
  name, short_name, description, start_url, theme_color, background_color, icons, shortcuts

SOURCE 6 : $GLOBALS['_ENV'] (vars d'environnement directement pour VAPID)
  VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY (lus par PushController et WebPush)
```

### 1.2 Problèmes identifiés

| # | Problème | Impact |
|---|---|---|
| P1 | Aucun paramètre modifiable depuis l'UI | Admin doit éditer `.env` sur le serveur |
| P2 | Nom/logo de l'établissement codé en dur dans les vues | Changer le nom = modifier plusieurs templates |
| P3 | Pas de notion d'année scolaire active dans l'UI | Calculée en PHP (`date('Y')`) |
| P4 | VAPID keys uniquement dans `.env` | Régénération impossible depuis l'interface |
| P5 | PWA `manifest.json` statique | Couleurs/nom non personnalisables sans déploiement |
| P6 | Pas de modules activables/désactivables | Tous les modules sont toujours actifs |
| P7 | Pas de backup automatisé | Sauvegarde manuelle uniquement |
| P8 | Devise/formatage monétaire hardcodé | Application non adaptable à différents pays |
| P9 | Pas de clé API pour intégrations externes | API interne seulement |
| P10 | Pas d'interface pour tester l'envoi Email/SMS | Débug difficile en production |

### 1.3 Séparation .env vs DB — règle V2

```
.env (reste intouché — config d'infrastructure)          DB parametres (nouvelle cible)
──────────────────────────────────────────────────────  ──────────────────────────────
DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD  etablissement.nom
APP_URL (base URL serveur)                               etablissement.logo
APP_KEY (clé de chiffrement)                             etablissement.adresse
SERVER paths (UPLOAD_PATH)                               annee_scolaire.active_id
                                                         devise.symbole / decimales
                                                         mail.driver, smtp_host, smtp_port...
                                                         sms.driver, api_url, api_key...
                                                         pwa.theme_color, pwa.name...
                                                         pwa.vapid_public_key (chiffré)
                                                         pwa.vapid_private_key (chiffré)
                                                         sys.timezone, session_lifetime...
                                                         api.key (chiffré)
                                                         modules.* (actifs/inactifs)
```

---

## 2. ARCHITECTURE DU MODULE

### 2.1 Vue d'ensemble

```
┌─────────────────────────────────────────────────────────────┐
│                    MODULE PARAMÈTRES                         │
│                                                             │
│  ┌──────────────────┐    ┌───────────────────────────────┐  │
│  │  ParametresCtrl  │───▶│         ParametreService       │  │
│  │  (10 sections)   │    │  (get/set/cache/chiffrement)   │  │
│  └──────────────────┘    └───────────────────────────────┘  │
│           │                          │                      │
│           │              ┌───────────┴──────────────────┐   │
│           │              │         DB : parametres        │   │
│           │              │  (cle | valeur | groupe | ...) │   │
│           │              └──────────────────────────────┘   │
│           │                                                  │
│           ├──▶ BackupService ──▶ DB export / mysqldump       │
│           ├──▶ ApiKeyService ──▶ parametres (api.key)        │
│           ├──▶ PwaConfigService ──▶ manifest.json dynamique  │
│           └──▶ ModuleService ──▶ modules (actif TINYINT)     │
│                                                             │
│  Tables : parametres | modules | backup_logs                 │
└─────────────────────────────────────────────────────────────┘
```

### 2.2 Sections du module

| # | Section | Route | Description |
|---|---|---|---|
| 1 | Établissement | `/parametres/etablissement` | Nom, logo, contact, ville |
| 2 | Année scolaire | `/parametres/annee-scolaire` | Sélection de l'année active |
| 3 | Devise | `/parametres/devise` | Monnaie, format numérique |
| 4 | Modules | `/parametres/modules` | Activer/désactiver les modules |
| 5 | Email | `/parametres/email` | Config SMTP + test d'envoi |
| 6 | SMS | `/parametres/sms` | Config gateway + test d'envoi |
| 7 | Sauvegardes | `/parametres/sauvegardes` | Backup DB, liste, téléchargement |
| 8 | API | `/parametres/api` | Clé API, rate limit |
| 9 | PWA | `/parametres/pwa` | Manifest, VAPID, couleurs |
| 10 | Système | `/parametres/systeme` | Timezone, session, debug, maintenance |

---

## 3. TABLES

### 3.1 `parametres` — Table centrale clé-valeur

```sql
parametres (
  id          INT UNSIGNED PK AUTO_INCREMENT,
  cle         VARCHAR(100) UNIQUE NOT NULL    -- 'etablissement.nom', 'mail.smtp_host'
  valeur      TEXT NULL,                      -- valeur brute (chiffrée si is_secret=1)
  groupe      VARCHAR(50) NOT NULL,           -- 'etablissement', 'mail', 'sms', 'pwa'…
  type        ENUM(
                'string',    -- texte libre
                'text',      -- textarea long
                'integer',   -- nombre entier
                'boolean',   -- 0 ou 1
                'email',     -- email validé
                'url',       -- URL validée
                'color',     -- #RRGGBB
                'select',    -- valeur dans une liste
                'path',      -- chemin fichier/image
                'json',      -- JSON structuré
                'secret'     -- chiffré en AES-256-CBC, masqué dans l'UI
              ) NOT NULL DEFAULT 'string',
  label       VARCHAR(200) NOT NULL,          -- Libellé affiché dans l'interface
  description TEXT NULL,                      -- Aide contextuelle sous le champ
  options     JSON NULL,                      -- Pour type=select : {"choices":["tls","ssl","none"]}
  is_secret   TINYINT(1) NOT NULL DEFAULT 0, -- 1 = afficher comme *** dans l'UI
  is_readonly TINYINT(1) NOT NULL DEFAULT 0, -- 1 = affiché mais non modifiable depuis UI
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_param_groupe (groupe)
)
```

**Principes de la table :**
- Tous les paramètres ont une clé unique au format `{groupe}.{sous_groupe}.{nom}`
- Les valeurs de type `secret` sont chiffrées en AES-256-CBC avec `APP_KEY` comme clé de chiffrement
- La valeur dans la DB est toujours une chaîne — le type sert à la validation et au rendu UI
- L'accès aux secrets passe **obligatoirement** par `ParametreService::getSecret()`, jamais par lecture directe

---

### 3.2 `modules` — Registre des modules fonctionnels

```sql
modules (
  id           INT UNSIGNED PK AUTO_INCREMENT,
  slug         VARCHAR(50) UNIQUE NOT NULL,   -- 'scolarite', 'absences', 'comptabilite'…
  label        VARCHAR(100) NOT NULL,          -- 'Gestion scolaire', 'Absences'…
  description  TEXT NULL,                      -- Description fonctionnelle
  icone        VARCHAR(50) NULL,               -- Nom icône Lucide : 'users', 'calendar'…
  actif        TINYINT(1) NOT NULL DEFAULT 1,  -- 0 = désactivé (routes + menus masqués)
  is_core      TINYINT(1) NOT NULL DEFAULT 0,  -- 1 = non désactivable (auth, dashboard)
  dependances  JSON NULL,                      -- ["scolarite"] = ce module en nécessite un autre
  ordre        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_modules_actif (actif)
)
```

**Modules du registre :**

| slug | label | is_core | dependances |
|---|---|---|---|
| `auth` | Authentification | 1 | [] |
| `dashboard` | Tableau de bord | 1 | ["auth"] |
| `scolarite` | Gestion scolaire | 0 | ["auth"] |
| `academique` | Notes & Évaluations | 0 | ["scolarite"] |
| `absences` | Absences | 0 | ["scolarite"] |
| `comptabilite` | Comptabilité | 0 | ["scolarite"] |
| `emploi_du_temps` | Emploi du temps | 0 | ["scolarite"] |
| `rapports` | Rapports & Analytics | 0 | ["auth"] |
| `notifications` | Notifications | 0 | ["auth"] |
| `annonces` | Annonces | 0 | ["auth"] |
| `pwa` | Application mobile (PWA) | 0 | ["auth", "notifications"] |
| `espaces` | Espaces parent & élève | 0 | ["scolarite", "academique"] |
| `utilisateurs` | Gestion utilisateurs | 0 | ["auth"] |
| `parametres` | Paramètres système | 1 | ["auth"] |

---

### 3.3 `backup_logs` — Journal des sauvegardes

```sql
backup_logs (
  id           INT UNSIGNED PK AUTO_INCREMENT,
  type         ENUM('manuelle','automatique','export_csv','export_full') NOT NULL DEFAULT 'manuelle',
  filename     VARCHAR(255) NOT NULL,           -- 'backup_2026-06-29_143000.sql.gz'
  taille       BIGINT UNSIGNED NULL,            -- Taille en octets
  tables_count SMALLINT UNSIGNED NULL,          -- Nombre de tables sauvegardées
  statut       ENUM('en_cours','termine','echec') NOT NULL DEFAULT 'en_cours',
  message      TEXT NULL,                       -- Message d'erreur ou de succès
  created_by   INT UNSIGNED NULL,               -- users.id (NULL si automatique)
  expires_at   DATETIME NULL,                   -- Date de suppression automatique
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_bl_statut (statut),
  INDEX idx_bl_type (type),
  INDEX idx_bl_created (created_at),
  CONSTRAINT fk_bl_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
)
```

---

### 3.4 Relations entre tables

```
annees_scolaires ◄─── parametres['annee_scolaire.active_id'] (valeur = id FK)
users            ◄─── backup_logs.created_by
modules          ──── (standalone, référencé par le Router pour activer/masquer les routes)
parametres       ──── (standalone key-value, pas de FK directes)
```

---

## 4. SERVICES

### 4.1 `ParametreService` — Accès central aux paramètres

```
Namespace : App\Services\ParametreService

Responsabilités :
  - Lecture/écriture des paramètres depuis/vers la DB
  - Cache en mémoire (array statique) pour éviter les requêtes répétées
  - Chiffrement/déchiffrement transparent des secrets (type=secret, is_secret=1)
  - Validation par type avant écriture
  - Lecture de fallback depuis .env si la DB est inaccessible

Interface publique :

  get(string $cle, mixed $default = null): mixed
    → Retourne la valeur du paramètre, déchiffrée si secret
    → Cache en mémoire après première lecture

  set(string $cle, mixed $valeur): bool
    → Valide selon le type déclaré dans la table
    → Chiffre si is_secret = 1
    → Invalide le cache pour cette clé

  getGroup(string $groupe): array
    → Retourne tous les paramètres d'un groupe : ['cle' => valeur, ...]
    → Utile pour charger une section entière en une seule requête

  setMany(array $data): bool
    → Écriture de plusieurs paramètres en une transaction
    → Utilisé lors de la soumission d'un formulaire de section

  getSecret(string $cle): string
    → Lecture déchiffrée d'un secret (déclenche un log d'accès)
    → Différent de get() : log audit + pas de cache

  resetToDefault(string $cle): bool
    → Remet la valeur par défaut définie lors de l'installation

  clearCache(): void
    → Vide le cache mémoire (utile après mise à jour)

Mécanisme de chiffrement interne :
  encrypt(string $valeur): string
    → AES-256-CBC avec APP_KEY comme clé, IV aléatoire préfixé
    → Format stocké : base64(iv . ':' . ciphertext)

  decrypt(string $valeur): string
    → Extrait IV + déchiffre AES-256-CBC
```

---

### 4.2 `BackupService` — Sauvegardes base de données

```
Namespace : App\Services\BackupService

Responsabilités :
  - Génération de dump SQL de la base de données
  - Compression gzip du fichier
  - Stockage dans storage/backups/ (non accessible via HTTP)
  - Gestion de la rétention (suppression des anciens backups)
  - Log dans backup_logs

Interface publique :

  create(int $userId = null, string $type = 'manuelle'): array
    → Génère un dump SQL de toutes les tables
    → Retourne ['ok' => bool, 'filename' => string, 'taille' => int, 'message' => string]
    → Deux implémentations possibles selon l'environnement :
       (a) exec('mysqldump ...') si shell disponible
       (b) Lecture PHP des tables via PDO (toujours disponible)

  createViaPdo(): string
    → Génère le SQL en PHP pur sans mysqldump
    → Pour chaque table : SHOW CREATE TABLE + SELECT * en INSERT INTO
    → Fonctionne sans accès shell (WAMP, shared hosting)

  list(): array
    → Retourne les entrées backup_logs ORDER BY created_at DESC

  getPath(string $filename): string
    → Retourne le chemin absolu vers le fichier backup
    → Vérifie que le fichier existe et est dans storage/backups/

  download(string $filename): never
    → Envoie le fichier en téléchargement (Content-Disposition: attachment)
    → Vérifie l'autorisation avant envoi

  delete(int $backupId): bool
    → Supprime le fichier physique + l'entrée backup_logs

  prune(int $keepCount = 10): int
    → Supprime les backups au-delà du nombre à conserver
    → Retourne le nombre de fichiers supprimés

Répertoire de stockage :
  storage/backups/
  Format fichier : backup_YYYY-MM-DD_HHmmss.sql.gz
  Sécurité : .htaccess dans storage/backups/ interdit l'accès HTTP direct
```

---

### 4.3 `ApiKeyService` — Gestion des clés API

```
Namespace : App\Services\ApiKeyService

Responsabilités :
  - Génération de clés API cryptographiquement sûres
  - Stockage chiffré dans parametres (cle = 'api.key')
  - Vérification des clés reçues dans les requêtes
  - Rate limiting par IP + clé

Interface publique :

  generate(int $userId): string
    → Génère une clé de 64 caractères hex (bin2hex(random_bytes(32)))
    → Stocke dans parametres['api.key'] chiffré
    → Log dans backup_logs (ou audit_logs si créé)
    → Retourne la clé en clair (unique fois — ne peut pas être relue)

  verify(string $key): bool
    → Compare la clé fournie avec le hash stocké (hash_equals)

  revoke(int $userId): bool
    → Efface parametres['api.key']
    → Log de révocation

  getInfo(): array
    → Retourne les métadonnées de la clé active (sans la valeur)
    → ['exists' => bool, 'created_at' => string, 'expires_at' => string|null]
```

---

### 4.4 `PwaConfigService` — Manifest et VAPID dynamiques

```
Namespace : App\Services\PwaConfigService

Responsabilités :
  - Génération du manifest.json à partir des paramètres DB
  - Génération / régénération des clés VAPID
  - Synchronisation manifest.json physique vs DB

Interface publique :

  getManifest(): array
    → Lit les paramètres du groupe 'pwa' depuis DB
    → Retourne le tableau manifest complet prêt pour json_encode
    → Utilisé par la route GET /manifest.json (dynamique en V2)

  writeManifest(): bool
    → Écrit public/manifest.json depuis les paramètres DB
    → Appelé après chaque mise à jour des paramètres PWA

  generateVapidKeys(): array
    → Appelle WebPush::generateKeys()
    → Stocke pwa.vapid_public_key et pwa.vapid_private_key (chiffré) dans parametres
    → Retourne ['public_key' => string] (la clé privée n'est jamais retournée)

  getVapidPublicKey(): string
    → Lit pwa.vapid_public_key depuis parametres

  getVapidPrivateKeyPem(): string
    → Lit et déchiffre pwa.vapid_private_key
    → Appelé UNIQUEMENT par PushController lors de l'envoi d'un push
```

---

### 4.5 `ModuleService` — Activation/désactivation des modules

```
Namespace : App\Services\ModuleService

Responsabilités :
  - Lire l'état d'activation de chaque module
  - Activer/désactiver avec vérification des dépendances
  - Cache de l'état des modules (un seul SELECT au démarrage)

Interface publique :

  isActive(string $slug): bool
    → Vérifie si un module est actif (depuis cache)

  getAll(): array
    → Retourne tous les modules avec leur état

  activate(string $slug): array
    → Vérifie les dépendances avant activation
    → Retourne ['ok' => bool, 'message' => string]

  deactivate(string $slug): array
    → Vérifie qu'aucun module actif ne dépend de lui
    → is_core = 1 → refus toujours
    → Retourne ['ok' => bool, 'message' => string, 'bloqueurs' => array]

  getDependants(string $slug): array
    → Retourne la liste des modules actifs qui dépendent de ce slug
```

---

## 5. CONTRÔLEUR ET ROUTES

### 5.1 Structure du contrôleur

```
Namespace : App\Controllers\ParametresController
Étend     : Core\Controller
Permission: toutes les actions requièrent 'parametres.view'
            Les actions d'écriture requièrent 'parametres.update'
```

### 5.2 Table des routes

```
GET  /parametres                            → index()          - Dashboard paramètres
GET  /parametres/etablissement              → etablissement()  - Formulaire établissement
POST /parametres/etablissement              → updateEtablissement()
POST /parametres/etablissement/logo         → uploadLogo()     - Upload logo (multipart)
POST /parametres/etablissement/logo/delete  → deleteLogo()     - Supprimer le logo

GET  /parametres/annee-scolaire             → anneeScolaire()
POST /parametres/annee-scolaire             → updateAnneeScolaire()
POST /parametres/annee-scolaire/creer       → createAnneeScolaire() - Nouvelle année

GET  /parametres/devise                     → devise()
POST /parametres/devise                     → updateDevise()

GET  /parametres/modules                    → modules()
POST /parametres/modules/{slug}/toggle      → toggleModule()   - Activer/désactiver

GET  /parametres/email                      → email()
POST /parametres/email                      → updateEmail()
POST /parametres/email/test                 → testEmail()      - Envoi email de test

GET  /parametres/sms                        → sms()
POST /parametres/sms                        → updateSms()
POST /parametres/sms/test                   → testSms()        - Envoi SMS de test

GET  /parametres/sauvegardes                → sauvegardes()
POST /parametres/sauvegardes/creer          → createBackup()   ← parametres.backup
GET  /parametres/sauvegardes/{id}/download  → downloadBackup() ← parametres.backup
POST /parametres/sauvegardes/{id}/delete    → deleteBackup()   ← parametres.backup

GET  /parametres/api                        → api()            ← parametres.api
POST /parametres/api/regenerer              → regenerateKey()  ← parametres.api
POST /parametres/api/revoquer               → revokeKey()      ← parametres.api

GET  /parametres/pwa                        → pwa()
POST /parametres/pwa                        → updatePwa()
POST /parametres/pwa/vapid/regenerer        → regenerateVapid()← parametres.api

GET  /parametres/systeme                    → systeme()
POST /parametres/systeme                    → updateSysteme()
POST /parametres/systeme/maintenance/toggle → toggleMaintenance()
```

### 5.3 Méthodes clés — pseudo-code

```php
// index() — tableau de bord des paramètres
public function index(): void
{
    $this->requirePermission('parametres.view');
    $this->render('parametres/index', [
        'sections' => [
            ['slug' => 'etablissement', 'icone' => 'building',    'label' => 'Établissement'],
            ['slug' => 'annee-scolaire','icone' => 'calendar',    'label' => 'Année scolaire'],
            ['slug' => 'devise',        'icone' => 'coins',       'label' => 'Devise'],
            ['slug' => 'modules',       'icone' => 'puzzle',      'label' => 'Modules'],
            ['slug' => 'email',         'icone' => 'mail',        'label' => 'Email'],
            ['slug' => 'sms',           'icone' => 'smartphone',  'label' => 'SMS'],
            ['slug' => 'sauvegardes',   'icone' => 'database',    'label' => 'Sauvegardes'],
            ['slug' => 'api',           'icone' => 'key',         'label' => 'API'],
            ['slug' => 'pwa',           'icone' => 'monitor-smartphone', 'label' => 'PWA'],
            ['slug' => 'systeme',       'icone' => 'settings',    'label' => 'Système'],
        ],
        'stats' => [
            'backups_count' => ...,
            'last_backup'   => ...,
            'annee_active'  => ...,
            'modules_actifs' => ...,
        ],
    ]);
}

// updateEtablissement() — sauvegarde les données établissement
public function updateEtablissement(): void
{
    $this->requirePermission('parametres.update');
    $this->verifyCsrf();
    $data = [
        'etablissement.nom'       => trim($this->request->post('nom')),
        'etablissement.adresse'   => trim($this->request->post('adresse')),
        'etablissement.telephone' => trim($this->request->post('telephone')),
        'etablissement.email'     => trim($this->request->post('email')),
        'etablissement.ville'     => trim($this->request->post('ville')),
        'etablissement.directeur' => trim($this->request->post('directeur')),
        'etablissement.slogan'    => trim($this->request->post('slogan')),
        'etablissement.site_web'  => trim($this->request->post('site_web')),
    ];
    // Validation + ParametreService::setMany($data)
    // Flash + redirect
}

// testEmail() — envoie un email de test à l'admin connecté
public function testEmail(): void
{
    $this->requirePermission('parametres.update');
    $this->verifyCsrf();
    $user  = $this->currentUser();
    $email = new EmailService();
    $ok    = $email->send($user['email'], 'Test SMTP — SCOLARIS', '...');
    $this->json(['ok' => $ok]);
}

// createBackup() — déclenche une sauvegarde manuelle
public function createBackup(): void
{
    $this->requirePermission('parametres.backup');
    $this->verifyCsrf();
    $result = (new BackupService())->create($this->currentUser()['id']);
    $this->json($result);
}

// regenerateVapid() — génère de nouvelles clés VAPID
public function regenerateVapid(): void
{
    $this->requirePermission('parametres.api');
    $this->verifyCsrf();
    $pwa = new PwaConfigService();
    $pwa->generateVapidKeys();
    $pwa->writeManifest();
    Session::flash('success', 'Clés VAPID régénérées. Rechargez le Service Worker.');
    $this->redirect(BASE_URL . '/parametres/pwa');
}
```

---

## 6. PERMISSIONS

### 6.1 Codes de permissions RBAC V2 pour le module

| Code | Action | Scope | Description |
|---|---|---|---|
| `parametres.view` | view | global | Accéder aux pages de paramètres |
| `parametres.update` | update | global | Modifier les paramètres (établissement, devise, modules, email, SMS, PWA, système) |
| `parametres.backup` | approve | global | Créer/télécharger/supprimer des sauvegardes |
| `parametres.api` | delete | global | Gérer les clés API et les clés VAPID |

### 6.2 Attribution par rôle

| Permission | admin | directeur | secretaire | comptable | enseignant | parent | eleve |
|---|---|---|---|---|---|---|---|
| `parametres.view` | ✓ | ✓ | — | — | — | — | — |
| `parametres.update` | ✓ | — | — | — | — | — | — |
| `parametres.backup` | ✓ | — | — | — | — | — | — |
| `parametres.api` | ✓ | — | — | — | — | — | — |

**Règle :** Seul `admin` peut modifier les paramètres. Le `directeur` peut consulter (pour voir l'état des modules, l'année active, etc.) mais pas modifier.

### 6.3 Intégration dans RBAC_V2.md

Ces 4 permissions s'ajoutent au catalogue `R003` :

```sql
-- À ajouter dans R003 :
('parametres.view',   'Accéder aux paramètres système',        'parametres', 'view',    'global'),
('parametres.update', 'Modifier les paramètres système',        'parametres', 'update',  'global'),
('parametres.backup', 'Gérer les sauvegardes de la base',       'parametres', 'approve', 'global'),
('parametres.api',    'Gérer les clés API et VAPID',            'parametres', 'delete',  'global');
```

---

## 7. INTERFACES UTILISATEUR

### 7.1 Dashboard Paramètres (GET /parametres)

```
┌─────────────────────────────────────────────────────────────────────────┐
│  ⚙ Paramètres système                                                   │
│  Configuration générale de SCOLARIS                                      │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐ │
│  │ 🏫        │  │ 📅        │  │ 💰        │  │ 🧩        │  │ 📧        │ │
│  │Établisse-│  │  Année   │  │  Devise  │  │ Modules  │  │  Email   │ │
│  │  ment    │  │ scolaire │  │          │  │          │  │          │ │
│  │          │  │          │  │ DZD (DA) │  │ 11/14    │  │  SMTP ✓  │ │
│  │ SCOLARIS │  │2025-2026 │  │  après   │  │  actifs  │  │          │ │
│  └────[→]───┘  └────[→]───┘  └────[→]───┘  └────[→]───┘  └────[→]───┘ │
│                                                                         │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐ │
│  │ 📱        │  │ 💾        │  │ 🔑        │  │ 📲        │  │ 🔧        │ │
│  │   SMS    │  │Sauvegard.│  │   API    │  │   PWA    │  │ Système  │ │
│  │          │  │          │  │          │  │          │  │          │ │
│  │  stub ⚠  │  │ 3 backups│  │ Clé act.│  │ VAPID ✓  │  │ prod / tz│ │
│  │          │  │ il y a 2j│  │          │  │          │  │ Algiers  │ │
│  └────[→]───┘  └────[→]───┘  └────[→]───┘  └────[→]───┘  └────[→]───┘ │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │ ⚠ Alertes de configuration                                       │   │
│  │  • SMS en mode stub — aucun SMS réel envoyé                      │   │
│  │  • 0 sauvegardes automatiques configurées                        │   │
│  │  • Clé APP_KEY non définie dans .env                             │   │
│  └─────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────┘
```

---

### 7.2 Section Établissement

```
┌─────────────────────────────────────────────────────────────────────────┐
│  ← Paramètres   /   🏫 Établissement                                    │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌────────────────────────────────────────────────────────┐            │
│  │            Informations générales                       │            │
│  ├─────────────────────────────────┬──────────────────────┤            │
│  │                                 │                      │            │
│  │  ┌─────────────────────┐        │  Nom de l'établ. *   │            │
│  │  │                     │        │  [École Ahmed Bey  ] │            │
│  │  │   [Logo 200×200]    │        │                      │            │
│  │  │                     │        │  Code établissement  │            │
│  │  └─────────────────────┘        │  [09-0142-A       ]  │            │
│  │  [Changer le logo]              │                      │            │
│  │  [Supprimer]                    │  Directeur           │            │
│  │                                 │  [M. Benali Karim  ] │            │
│  │  JPG/PNG/SVG · max 2 Mo         │                      │            │
│  │  Recommandé : 500×500 px        │  Slogan              │            │
│  │                                 │  [Excellence & Sav.] │            │
│  └─────────────────────────────────┴──────────────────────┘            │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  Coordonnées                                                      │  │
│  │  Ville           [Bône — Annaba         ]                         │  │
│  │  Adresse         [5, Rue des Martyrs                           ]  │  │
│  │  Téléphone       [038 45 67 89           ]                       │  │
│  │  Email contact   [contact@ecole.dz       ]                       │  │
│  │  Site web        [https://ecole.dz       ]                       │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│                                           [Annuler]  [Enregistrer ✓]   │
└─────────────────────────────────────────────────────────────────────────┘
```

---

### 7.3 Section Année scolaire

```
┌─────────────────────────────────────────────────────────────────────────┐
│  ← Paramètres   /   📅 Année scolaire                                   │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  Année scolaire active                                                  │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  ● 2025-2026   15/09/2025 → 25/06/2026   [ACTIVE]               │  │
│  │  ○ 2024-2025   15/09/2024 → 25/06/2025   [Terminée]             │  │
│  │  ○ 2026-2027   —                          [Future]    [Activer]  │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  + Créer une nouvelle année scolaire                              │  │
│  │  Libellé   [2026-2027]   Début [15/09/2026]   Fin [25/06/2027]   │  │
│  │                                             [Créer]              │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│  ⚠ Changer l'année active affecte les filtres par défaut dans toute    │
│    l'application (notes, absences, comptabilité, emploi du temps).      │
│    Les données des autres années restent accessibles via les filtres.   │
└─────────────────────────────────────────────────────────────────────────┘
```

---

### 7.4 Section Modules

```
┌─────────────────────────────────────────────────────────────────────────┐
│  ← Paramètres   /   🧩 Modules actifs                                   │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  Modules système (non désactivables)                                    │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  🔒 Authentification     Core — toujours actif              ●●●  │  │
│  │  🔒 Tableau de bord      Core — toujours actif              ●●●  │  │
│  │  🔒 Paramètres           Core — toujours actif              ●●●  │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│  Modules fonctionnels                                                   │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  📚 Gestion scolaire     Élèves, classes, matières           [●] │  │
│  │  📝 Notes & Évaluations  Requiert : Gestion scolaire         [●] │  │
│  │  📋 Absences             Requiert : Gestion scolaire         [●] │  │
│  │  💳 Comptabilité         Frais, paiements, dépenses          [●] │  │
│  │  📅 Emploi du temps      Requiert : Gestion scolaire         [●] │  │
│  │  📊 Rapports             Analytics et exports                [●] │  │
│  │  🔔 Notifications        Alertes internes                    [●] │  │
│  │  📣 Annonces             Communication établissement         [●] │  │
│  │  📱 PWA                  Application mobile                  [○] │  │
│  │     ↳ Requiert : Notifications                                   │  │
│  │  👨‍👩‍👧 Espaces parent/élève  Requiert : Scolarité + Académique  [●] │  │
│  │  👥 Utilisateurs         Gestion des comptes                 [●] │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│  [●] = Actif    [○] = Inactif                                           │
│                                                                         │
│  ⚠ Désactiver un module masque ses routes et ses menus.                │
│    Les données sont conservées.                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

### 7.5 Section Email

```
┌─────────────────────────────────────────────────────────────────────────┐
│  ← Paramètres   /   📧 Configuration Email                              │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  Mode d'envoi                                                           │
│  ( ) php mail()  — Serveur local uniquement, pas de suivi               │
│  (●) SMTP        — Recommandé pour la production                        │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  Expéditeur                                                        │  │
│  │  Nom         [SCOLARIS — École Ahmed Bey       ]                  │  │
│  │  Email       [noreply@ecole.dz                  ]                  │  │
│  │  Répondre à  [contact@ecole.dz                  ]                  │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  Serveur SMTP                                                      │  │
│  │  Hôte        [smtp.gmail.com      ]   Port [587  ]                 │  │
│  │  Chiffrement ( ) Aucun  (●) TLS  ( ) SSL                          │  │
│  │  Utilisateur [noreply@ecole.dz    ]                                │  │
│  │  Mot de passe [••••••••••••••••••]  [Afficher]                    │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  Test d'envoi                                                      │  │
│  │  Destinataire  [admin@ecole.dz    ]   [→ Envoyer un email test]   │  │
│  │                                                                    │  │
│  │  ✅ Email de test envoyé avec succès (il y a 3 min)               │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│                                           [Annuler]  [Enregistrer ✓]   │
└─────────────────────────────────────────────────────────────────────────┘
```

---

### 7.6 Section Sauvegardes

```
┌─────────────────────────────────────────────────────────────────────────┐
│  ← Paramètres   /   💾 Sauvegardes                                      │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  Créer une sauvegarde                                             │  │
│  │  Sauvegarde complète de la base de données (format SQL compressé) │  │
│  │                                        [💾 Créer maintenant]      │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│  Sauvegardes disponibles                                 [Purger (>10)] │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  # │ Fichier                          │ Taille │ Date       │    │  │
│  ├────┼──────────────────────────────────┼────────┼────────────┼────┤  │
│  │  1 │ backup_2026-06-29_143000.sql.gz  │ 2.1 Mo │ 29/06 14h  │[↓][✗]│
│  │  2 │ backup_2026-06-28_090000.sql.gz  │ 2.0 Mo │ 28/06 09h  │[↓][✗]│
│  │  3 │ backup_2026-06-25_180000.sql.gz  │ 1.8 Mo │ 25/06 18h  │[↓][✗]│
│  └──────────────────────────────────────────────────────────────────┘  │
│  [↓] Télécharger   [✗] Supprimer                                        │
│                                                                         │
│  ℹ Les sauvegardes sont stockées dans storage/backups/                  │
│    et ne sont pas accessibles publiquement.                             │
│    Conservation automatique : 10 fichiers maximum.                      │
└─────────────────────────────────────────────────────────────────────────┘
```

---

### 7.7 Section API & PWA

```
┌─────────────────────────────────────────────────────────────────────────┐
│  ← Paramètres   /   🔑 API & PWA                                        │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ── Clé API ─────────────────────────────────────────────────────────── │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  Clé API active                                                   │  │
│  │  sk_••••••••••••••••••••••••••••••••    Créée il y a 12 jours    │  │
│  │  [Régénérer la clé]   [Révoquer]                                  │  │
│  │                                                                   │  │
│  │  ⚠ La clé n'est affichée qu'une seule fois à la génération.      │  │
│  │    En cas de perte, régénérer une nouvelle clé.                   │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│  ── Notifications Push (VAPID) ─────────────────────────────────────── │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  Clé publique VAPID                                               │  │
│  │  [BNjF2xK7...Rk4T == ] (affichée — nécessaire côté client)       │  │
│  │                                                                   │  │
│  │  Clé privée VAPID  [••••••••••••••••••••••••] Stockée chiffrée   │  │
│  │                                                                   │  │
│  │  Sujet VAPID   [mailto:admin@ecole.dz        ]                    │  │
│  │                                                                   │  │
│  │  [🔄 Régénérer les clés VAPID]                                    │  │
│  │  ⚠ Régénérer invalide TOUTES les souscriptions push existantes.   │  │
│  │    Les utilisateurs devront réactiver les notifications.          │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│  ── Paramètres PWA ─────────────────────────────────────────────────── │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  Nom application   [SCOLARIS — École Ahmed Bey          ]         │  │
│  │  Nom court         [SCOLARIS  ]                                   │  │
│  │  Description       [Gestion scolaire complète...        ]         │  │
│  │  Couleur thème     [#7C3AED  ▓]   Arrière-plan [#FFFFFF  □]      │  │
│  │                                                                   │  │
│  │                            [Aperçu manifest]  [Enregistrer ✓]    │  │
│  └──────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────┘
```

---

### 7.8 Section Devise

```
┌─────────────────────────────────────────────────────────────────────────┐
│  ← Paramètres   /   💰 Devise & Format numérique                        │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  Devise                                                           │  │
│  │  Code ISO    [DZD     ▼]   Symbole  [DA       ]                  │  │
│  │  Position    (●) Après le montant   ( ) Avant le montant         │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  Format numérique                                                 │  │
│  │  Séparateur décimal    ( ) Point (.)   (●) Virgule (,)           │  │
│  │  Séparateur milliers   (●) Espace      ( ) Point (.)  ( ) Aucun  │  │
│  │  Décimales             [2  ▼]                                    │  │
│  │                                                                   │  │
│  │  Aperçu :    25 000,00 DA   /   1 250 000,00 DA                  │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│                                           [Annuler]  [Enregistrer ✓]   │
└─────────────────────────────────────────────────────────────────────────┘
```

---

### 7.9 Section Système

```
┌─────────────────────────────────────────────────────────────────────────┐
│  ← Paramètres   /   🔧 Système                                          │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  Configuration générale                                           │  │
│  │  Fuseau horaire  [Africa/Algiers   ▼]                            │  │
│  │  Durée session   [7200  ] secondes (2h)                          │  │
│  │  Niveau de log   [error ▼]  debug|info|warning|error|critical     │  │
│  │  Taille upload   [5242880  ] octets (5 Mo)                       │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  Mode maintenance                                           [OFF] │  │
│  │  En activant ce mode, seul l'administrateur peut se connecter.   │  │
│  │  Message affiché aux autres utilisateurs :                        │  │
│  │  [L'application est en maintenance. Réouverture à 14h00.      ]  │  │
│  │                                          [Activer la maintenance] │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  Informations système (lecture seule)                             │  │
│  │  Version PHP         8.2.x                                        │  │
│  │  Version MySQL       8.0.x                                        │  │
│  │  Espace disque       2.3 Go libres / 10 Go total                  │  │
│  │  Extensions PHP      ✓ openssl   ✓ curl   ✓ pdo_mysql            │  │
│  │  Environnement       production                                   │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                         │
│                                           [Annuler]  [Enregistrer ✓]   │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 8. MIGRATIONS SQL

### P001 — Créer les 3 tables du module

```sql
-- ════════════════════════════════════════════════════════════════════════
-- P001 : Création des tables parametres, modules, backup_logs
-- Prérequis : ecole_app.sql + M001 (schema_migrations)
-- ════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

-- ── Table principale des paramètres ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `parametres` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `cle`         VARCHAR(100)  NOT NULL UNIQUE,
    `valeur`      TEXT          NULL,
    `groupe`      VARCHAR(50)   NOT NULL,
    `type`        ENUM('string','text','integer','boolean','email','url',
                       'color','select','path','json','secret')
                  NOT NULL DEFAULT 'string',
    `label`       VARCHAR(200)  NOT NULL,
    `description` TEXT          NULL,
    `options`     JSON          NULL,
    `is_secret`   TINYINT(1)    NOT NULL DEFAULT 0,
    `is_readonly` TINYINT(1)    NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_param_groupe` (`groupe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table des modules fonctionnels ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `modules` (
    `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `slug`        VARCHAR(50)      NOT NULL UNIQUE,
    `label`       VARCHAR(100)     NOT NULL,
    `description` TEXT             NULL,
    `icone`       VARCHAR(50)      NULL,
    `actif`       TINYINT(1)       NOT NULL DEFAULT 1,
    `is_core`     TINYINT(1)       NOT NULL DEFAULT 0,
    `dependances` JSON             NULL,
    `ordre`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_modules_actif` (`actif`),
    INDEX `idx_modules_ordre` (`ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Table des logs de sauvegarde ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `backup_logs` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `type`         ENUM('manuelle','automatique','export_csv','export_full')
                   NOT NULL DEFAULT 'manuelle',
    `filename`     VARCHAR(255)    NOT NULL,
    `taille`       BIGINT UNSIGNED NULL,
    `tables_count` SMALLINT UNSIGNED NULL,
    `statut`       ENUM('en_cours','termine','echec')
                   NOT NULL DEFAULT 'en_cours',
    `message`      TEXT            NULL,
    `created_by`   INT UNSIGNED    NULL,
    `expires_at`   DATETIME        NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_bl_statut`  (`statut`),
    INDEX `idx_bl_type`    (`type`),
    INDEX `idx_bl_created` (`created_at`),
    CONSTRAINT `fk_bl_user`
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('P001', 'Création tables parametres, modules, backup_logs');
```

---

### P002 — Insérer le registre des modules

```sql
-- ════════════════════════════════════════════════════════════════════════
-- P002 : Initialisation du registre des modules
-- Prérequis : P001
-- ════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

INSERT IGNORE INTO `modules` (`slug`, `label`, `description`, `icone`, `actif`, `is_core`, `dependances`, `ordre`) VALUES
('auth',            'Authentification',     'Connexion, profil, reset mot de passe',         'lock',              1, 1, '[]',                           1),
('dashboard',       'Tableau de bord',      'Vue d\'ensemble et statistiques',               'layout-dashboard',  1, 1, '["auth"]',                     2),
('parametres',      'Paramètres',           'Configuration système',                          'settings',          1, 1, '["auth"]',                     3),
('scolarite',       'Gestion scolaire',     'Élèves, classes, matières, enseignants',        'school',            1, 0, '["auth"]',                     4),
('academique',      'Notes & Évaluations',  'Contrôles, notes, bulletins, classement',       'clipboard-list',    1, 0, '["scolarite"]',                5),
('absences',        'Absences',             'Pointage, justifications, statistiques',         'user-x',            1, 0, '["scolarite"]',                6),
('comptabilite',    'Comptabilité',         'Frais, paiements, dépenses, caisse',            'coins',             1, 0, '["scolarite"]',                7),
('emploi_du_temps', 'Emploi du temps',      'Planning hebdo, salles, créneaux',              'calendar',          1, 0, '["scolarite"]',                8),
('rapports',        'Rapports',             'Analytics, export, statistiques',                'chart-bar',         1, 0, '["auth"]',                     9),
('notifications',   'Notifications',        'Alertes internes, email, SMS',                  'bell',              1, 0, '["auth"]',                    10),
('annonces',        'Annonces',             'Communication de l\'établissement',             'megaphone',         1, 0, '["auth"]',                    11),
('pwa',             'PWA',                  'Application mobile, push notifications',         'smartphone',        1, 0, '["auth","notifications"]',    12),
('espaces',         'Espaces connectés',    'Espace parent et espace élève',                 'users',             1, 0, '["scolarite","academique"]',  13),
('utilisateurs',    'Utilisateurs',         'Gestion des comptes et des rôles',              'user-cog',          1, 0, '["auth"]',                    14);

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('P002', 'Initialisation du registre des 14 modules SCOLARIS');
```

---

### P003 — Insérer les paramètres par défaut

```sql
-- ════════════════════════════════════════════════════════════════════════
-- P003 : Paramètres par défaut — toutes les sections
-- Prérequis : P001
-- NOTE : Les valeurs sensibles (secrets) sont stockées en clair ici
--        car APP_KEY n'est pas encore disponible en SQL pur.
--        ParametreService chiffre à la première écriture depuis l'UI.
-- ════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

INSERT IGNORE INTO `parametres` (`cle`, `valeur`, `groupe`, `type`, `label`, `description`, `is_secret`, `is_readonly`) VALUES

-- ── ÉTABLISSEMENT ────────────────────────────────────────────────────────
('etablissement.nom',        'SCOLARIS',            'etablissement', 'string',  'Nom de l\'établissement',    NULL, 0, 0),
('etablissement.code',       '',                    'etablissement', 'string',  'Code établissement',          'Code officiel (ex: 09-0142-A)', 0, 0),
('etablissement.directeur',  '',                    'etablissement', 'string',  'Nom du directeur',            NULL, 0, 0),
('etablissement.adresse',    '',                    'etablissement', 'text',    'Adresse',                     NULL, 0, 0),
('etablissement.ville',      '',                    'etablissement', 'string',  'Ville',                       NULL, 0, 0),
('etablissement.telephone',  '',                    'etablissement', 'string',  'Téléphone',                   NULL, 0, 0),
('etablissement.email',      '',                    'etablissement', 'email',   'Email de contact',            NULL, 0, 0),
('etablissement.site_web',   '',                    'etablissement', 'url',     'Site web',                    NULL, 0, 0),
('etablissement.slogan',     '',                    'etablissement', 'string',  'Slogan',                      NULL, 0, 0),
('etablissement.logo',       '',                    'etablissement', 'path',    'Logo principal',              'Chemin relatif depuis public/', 0, 0),
('etablissement.logo_small', '',                    'etablissement', 'path',    'Logo compact',                'Pour la barre de navigation (carré, 64×64)', 0, 0),

-- ── DEVISE ───────────────────────────────────────────────────────────────
('devise.symbole',           'DA',                  'devise', 'string',  'Symbole monétaire',        'Ex: DA, €, $, FCFA', 0, 0),
('devise.code_iso',          'DZD',                 'devise', 'string',  'Code ISO 4217',            'Ex: DZD, EUR, USD, XOF', 0, 0),
('devise.position',          'apres',               'devise', 'select',  'Position du symbole',      NULL, 0, 0),
('devise.separateur_decimal','.',                   'devise', 'select',  'Séparateur décimal',       NULL, 0, 0),
('devise.separateur_milliers',' ',                  'devise', 'select',  'Séparateur de milliers',   NULL, 0, 0),
('devise.decimales',         '2',                   'devise', 'integer', 'Nombre de décimales',      NULL, 0, 0),

-- ── EMAIL ─────────────────────────────────────────────────────────────────
('mail.driver',              'mail',                'mail', 'select',  'Driver email',             'mail = PHP mail() | smtp = serveur SMTP', 0, 0),
('mail.from_name',           'SCOLARIS',            'mail', 'string',  'Nom expéditeur',           NULL, 0, 0),
('mail.from_email',          'noreply@ecole.local', 'mail', 'email',   'Email expéditeur',         NULL, 0, 0),
('mail.reply_to',            '',                    'mail', 'email',   'Répondre à',               NULL, 0, 0),
('mail.smtp_host',           '',                    'mail', 'string',  'Serveur SMTP',             'Ex: smtp.gmail.com', 0, 0),
('mail.smtp_port',           '587',                 'mail', 'integer', 'Port SMTP',                '587=TLS | 465=SSL | 25=sans chiffrement', 0, 0),
('mail.smtp_username',       '',                    'mail', 'string',  'Identifiant SMTP',         NULL, 0, 0),
('mail.smtp_password',       '',                    'mail', 'secret',  'Mot de passe SMTP',        NULL, 1, 0),
('mail.smtp_encryption',     'tls',                 'mail', 'select',  'Chiffrement SMTP',         NULL, 0, 0),
('mail.smtp_timeout',        '10',                  'mail', 'integer', 'Timeout SMTP (secondes)',  NULL, 0, 0),

-- ── SMS ──────────────────────────────────────────────────────────────────
('sms.driver',               'stub',                'sms', 'select',  'Mode d\'envoi SMS',        'stub = aucun envoi | rest = API REST', 0, 0),
('sms.sender',               'SCOLARIS',            'sms', 'string',  'Expéditeur SMS',           'Max 11 caractères alphanumériques', 0, 0),
('sms.api_url',              '',                    'sms', 'url',     'URL de l\'API SMS',        NULL, 0, 0),
('sms.api_key',              '',                    'sms', 'secret',  'Clé API SMS',              NULL, 1, 0),
('sms.country_code',         '213',                 'sms', 'string',  'Code pays par défaut',     'Ex: 213 (Algérie), 33 (France), 221 (Sénégal)', 0, 0),
('sms.payload_tpl',          '{"to":"{phone}","message":"{message}","from":"{sender}"}',
                                                     'sms', 'json',   'Template JSON',            NULL, 0, 0),

-- ── API ──────────────────────────────────────────────────────────────────
('api.key',                  '',                    'api', 'secret',  'Clé API principale',       NULL, 1, 0),
('api.key_created_at',       '',                    'api', 'string',  'Date de création clé',     NULL, 0, 1),
('api.rate_limit',           '60',                  'api', 'integer', 'Limite requêtes/minute',   NULL, 0, 0),

-- ── PWA ──────────────────────────────────────────────────────────────────
('pwa.name',                 'SCOLARIS',                        'pwa', 'string', 'Nom complet PWA',          NULL, 0, 0),
('pwa.short_name',           'SCOLARIS',                        'pwa', 'string', 'Nom court PWA',            'Max 12 caractères', 0, 0),
('pwa.description',          'Gestion scolaire complète',       'pwa', 'text',   'Description PWA',          NULL, 0, 0),
('pwa.theme_color',          '#7C3AED',                         'pwa', 'color',  'Couleur de thème',         'Barre de navigateur sur mobile', 0, 0),
('pwa.background_color',     '#FFFFFF',                         'pwa', 'color',  'Couleur de fond',          'Écran de démarrage PWA', 0, 0),
('pwa.vapid_public_key',     '',                                'pwa', 'string', 'Clé publique VAPID',       NULL, 0, 0),
('pwa.vapid_private_key',    '',                                'pwa', 'secret', 'Clé privée VAPID (PEM)',   NULL, 1, 0),
('pwa.vapid_subject',        'mailto:admin@ecole.local',        'pwa', 'email',  'Sujet VAPID',              'Email de contact push notifications', 0, 0),

-- ── SYSTÈME ──────────────────────────────────────────────────────────────
('sys.timezone',             'Africa/Algiers',      'sys', 'string',  'Fuseau horaire',           NULL, 0, 0),
('sys.session_lifetime',     '7200',                'sys', 'integer', 'Durée de session (s)',     '7200 = 2 heures', 0, 0),
('sys.upload_max_size',      '5242880',             'sys', 'integer', 'Taille max upload (octets)','5242880 = 5 Mo', 0, 0),
('sys.log_level',            'error',               'sys', 'select',  'Niveau de journalisation', 'debug|info|warning|error|critical', 0, 0),
('sys.maintenance',          '0',                   'sys', 'boolean', 'Mode maintenance',         '0=off | 1=on', 0, 0),
('sys.maintenance_message',  'Application en maintenance. Veuillez réessayer plus tard.',
                                                     'sys', 'text',   'Message maintenance',      NULL, 0, 0),
('sys.backup_retention',     '10',                  'sys', 'integer', 'Nombre de backups gardés', NULL, 0, 0);

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('P003', 'Insertion des 55 paramètres par défaut — toutes sections');
```

---

### P004 — Ajouter les permissions parametres dans RBAC

```sql
-- ════════════════════════════════════════════════════════════════════════
-- P004 : Ajout des permissions du module Paramètres dans RBAC V2
-- Prérequis : R001, R002, R003
-- ════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

INSERT IGNORE INTO `permissions` (`code`, `libelle`, `module`, `action`, `scope`) VALUES
('parametres.view',   'Accéder aux paramètres système',     'parametres', 'view',    'global'),
('parametres.update', 'Modifier les paramètres système',     'parametres', 'update',  'global'),
('parametres.backup', 'Gérer les sauvegardes de la base',    'parametres', 'approve', 'global'),
('parametres.api',    'Gérer les clés API et VAPID',         'parametres', 'delete',  'global');

-- Attribuer au rôle admin uniquement
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT id FROM `roles` WHERE slug = 'admin'), p.id
FROM `permissions` p
WHERE p.code IN ('parametres.view','parametres.update','parametres.backup','parametres.api');

-- Directeur : lecture seule
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT (SELECT id FROM `roles` WHERE slug = 'directeur'), p.id
FROM `permissions` p
WHERE p.code = 'parametres.view';

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('P004', 'Permissions module Paramètres insérées dans RBAC V2');
```

---

## 9. CATALOGUE DES PARAMÈTRES

### Résumé par groupe

| Groupe | Clés | Secrets | Description |
|---|---|---|---|
| `etablissement` | 11 | 0 | Identité de l'école |
| `devise` | 6 | 0 | Formatage monétaire |
| `mail` | 10 | 1 (`smtp_password`) | Configuration email |
| `sms` | 6 | 1 (`api_key`) | Gateway SMS |
| `api` | 3 | 1 (`key`) | Clé API externe |
| `pwa` | 8 | 1 (`vapid_private_key`) | Application mobile |
| `sys` | 7 | 0 | Paramètres système |
| **Total** | **51** | **4** | |

### Clés de référence rapide

```
etablissement.nom          → Utilisé dans les templates (en-têtes, bulletins, emails)
etablissement.logo         → <img> dans layout principal et bulletins PDF
annees_scolaires.actif=1   → Filtre par défaut dans toutes les requêtes
devise.symbole             → Formatage dans ComptabiliteController et vues
mail.smtp_*                → EmailService lit depuis ParametreService (au lieu de config/)
sms.api_key                → SmsService lit depuis ParametreService (au lieu de config/)
pwa.vapid_public_key       → GET /api/push/vapid-key retourne cette valeur
pwa.vapid_private_key      → PushController::sendPing() via PwaConfigService::getVapidPrivateKeyPem()
sys.maintenance            → Vérifié par Application::run() avant dispatch
modules.*.actif            → Vérifié par Router pour inclure/exclure les routes de module
```

### Intégration avec les services existants

```
AVANT V2                          APRÈS V2
─────────────────────────────     ──────────────────────────────────────────
EmailService lit config/mail.php  EmailService lit ParametreService::getGroup('mail')
SmsService lit config/sms.php     SmsService lit ParametreService::getGroup('sms')
PushController lit $_ENV[VAPID]   PushController lit PwaConfigService::getVapidPublicKey()
WebPush::sendPing($_ENV[VAPID])    WebPush::sendPing(PwaConfigService::getVapidPrivateKeyPem())
manifest.json statique            manifest.json généré par PwaConfigService::writeManifest()
config/app.php (timezone)         ParametreService::get('sys.timezone')
currentAnnee() calcul PHP         ParametreService::get('annee_scolaire.active_id') + jointure
```

---

*PARAMETRES_V2.md — SCOLARIS | Conception complète. Migrations SQL préparées. Pas d'implémentation. En attente de validation.*
