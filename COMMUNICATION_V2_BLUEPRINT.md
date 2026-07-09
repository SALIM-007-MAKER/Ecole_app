# COMMUNICATION V2 — BLUEPRINT
**Phase :** 8.1  
**Date :** 2026-07-02  
**Statut :** BLUEPRINT — Aucune implémentation

---

## Vision

Le module Communication est la **plateforme centralisée des échanges** de Scolaris V2.  
Il est passif vis-à-vis des autres modules : il **écoute leurs événements** et les traduit en  
actions de communication (notification, email, SMS, push) selon les préférences utilisateurs.  
Aucun module existant ne doit importer directement des services Communication.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           MODULE COMMUNICATION V2                           │
│                                                                             │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐                 │
│  │  Inbox interne│    │  Messagerie  │    │   Templates  │                 │
│  │  (notifications)  │  (threads)   │    │   multicanal │                 │
│  └──────┬───────┘    └──────┬───────┘    └──────┬───────┘                 │
│         │                   │                   │                          │
│  ┌──────▼───────────────────▼───────────────────▼───────┐                 │
│  │               RoutageService (cerveau)                │                 │
│  │  Event → Destinataires → Préférences → Canaux → Queue│                 │
│  └──────┬──────────────────────────────────────────────┘                  │
│         │                                                                   │
│  ┌──────▼──────┐  ┌──────────┐  ┌────────┐  ┌──────────┐                 │
│  │  EmailService│  │SmsService│  │PushSvc │  │QueueService│                │
│  └─────────────┘  └──────────┘  └────────┘  └──────────┘                 │
└─────────────────────────────────────────────────────────────────────────────┘
          ↑
          │ Événements entrants (141 events, 7 modules)
          │
  ┌───────┴──────────────────────────────────────────────────────┐
  │  Scolarité · Académique · Finance · Vie Scolaire · RH · Doc  │
  └──────────────────────────────────────────────────────────────┘
```

---

## 1. Architecture

### Principes fondamentaux

| Principe | Description |
|---------|-------------|
| **Zero coupling** | Aucun module existant n'importe Communication — ils dispatchen des events |
| **Event-driven** | Communication écoute les events via `CrossModuleListener` |
| **Canal abstrait** | `ChannelInterface` — Email, SMS, Push, Internal implémentent le même contrat |
| **Queue async** | Envois Email/SMS/Push passent par `com_queue` — traités par cron |
| **Préférences** | Chaque utilisateur contrôle quels types sur quels canaux |
| **Template-driven** | Tout contenu passe par `TemplateService::render()` |
| **Soft delete** | `deleted_at` sur messages et threads |
| **Multi-établissement** | `etablissement_id` sur toutes les tables |

### Namespace et structure

```
App\Modules\Communication\
├── module.json
├── routes.php
├── Controllers/          (9)
├── Services/             (11)
├── Repositories/         (9)
├── DTO/                  (9)
├── Policies/             (2)
├── Events/               (12)
├── Listeners/            (3)
├── Models/               (5)
├── Channels/             (4)          ← Nouveauté architecturale
└── Views/                (~20)
database/migrations/com_001_communication.sql
```

### Channels (contrat abstrait)

```php
namespace App\Modules\Communication\Channels;

interface ChannelInterface
{
    public function envoyer(array $job): bool;  // job depuis com_queue
    public function disponible(): bool;         // check de santé du canal
    public function canal(): string;            // 'email','sms','push','internal'
}

// Implémentations :
// EmailChannel    — PHPMailer / SMTP (stub V2 : log dans fichier)
// SmsChannel      — API HTTP SMS (stub V2 : log dans fichier)
// PushChannel     — VAPID via PWA existant
// InternalChannel — Insert direct dans com_notifications
```

---

## 2. Base de données — 13 tables `com_*`

### Schéma complet

#### `com_notifications` — Inbox interne utilisateur
```sql
CREATE TABLE com_notifications (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL,
    type             VARCHAR(60)  NOT NULL,   -- 'info','success','warning','alert','message','system'
    module_source    VARCHAR(30)  NOT NULL,
    entite_type      VARCHAR(60)  NULL,
    entite_id        INT UNSIGNED NULL,
    titre            VARCHAR(255) NOT NULL,
    corps            TEXT         NULL,
    url_action       VARCHAR(500) NULL,        -- lien cible au clic
    icone            VARCHAR(60)  NULL DEFAULT 'bell',
    priorite         ENUM('basse','normale','haute','critique') NOT NULL DEFAULT 'normale',
    lu               TINYINT(1)   NOT NULL DEFAULT 0,
    lu_at            DATETIME     NULL,
    expire_at        DATETIME     NULL,
    metadata         JSON         NULL,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user       (user_id, lu, created_at DESC),
    INDEX idx_module     (module_source, entite_type, entite_id),
    INDEX idx_etab       (etablissement_id),
    INDEX idx_expire     (expire_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `com_threads` — Fils de conversation
```sql
CREATE TABLE com_threads (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sujet            VARCHAR(255) NOT NULL,
    type             ENUM('direct','groupe','broadcast') NOT NULL DEFAULT 'direct',
    module_source    VARCHAR(30)  NULL,         -- thread lié à un contexte métier
    entite_type      VARCHAR(60)  NULL,
    entite_id        INT UNSIGNED NULL,
    created_by       INT UNSIGNED NOT NULL,
    archive          TINYINT(1)   NOT NULL DEFAULT 0,
    dernier_message_at DATETIME   NULL,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    deleted_at       DATETIME     NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_creator (created_by),
    INDEX idx_etab    (etablissement_id),
    INDEX idx_module  (module_source, entite_type, entite_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `com_thread_messages` — Messages dans un fil
```sql
CREATE TABLE com_thread_messages (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thread_id   INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    corps       TEXT         NOT NULL,
    type        ENUM('texte','fichier','systeme') NOT NULL DEFAULT 'texte',
    metadata    JSON         NULL,               -- pièces jointes (doc_id[])
    deleted_at  DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY fk_tm_thread (thread_id) REFERENCES com_threads(id) ON DELETE CASCADE,
    INDEX idx_thread (thread_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `com_thread_participants` — Participants à un fil
```sql
CREATE TABLE com_thread_participants (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thread_id     INT UNSIGNED NOT NULL,
    user_id       INT UNSIGNED NOT NULL,
    role          ENUM('membre','moderateur') NOT NULL DEFAULT 'membre',
    lu_at         DATETIME     NULL,            -- dernière lecture
    archive       TINYINT(1)   NOT NULL DEFAULT 0,
    joined_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY fk_tp_thread (thread_id) REFERENCES com_threads(id) ON DELETE CASCADE,
    UNIQUE KEY uq_thread_user (thread_id, user_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `com_templates` — Templates de messages
```sql
CREATE TABLE com_templates (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code             VARCHAR(100) NOT NULL,     -- 'bulletin_publie_parent', 'absence_sms'
    nom              VARCHAR(200) NOT NULL,
    canal            ENUM('email','sms','push','internal') NOT NULL,
    langue           CHAR(2)      NOT NULL DEFAULT 'fr',
    module_source    VARCHAR(30)  NULL,          -- NULL = global
    sujet            VARCHAR(255) NULL,           -- NULL pour SMS/push
    corps_html       MEDIUMTEXT   NULL,           -- email HTML
    corps_texte      TEXT         NULL,           -- sms / push / email texte
    variables        JSON         NULL,           -- ex: ["nom","classe","date"]
    actif            TINYINT(1)   NOT NULL DEFAULT 1,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_by       INT UNSIGNED NOT NULL DEFAULT 0,
    deleted_at       DATETIME     NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_code_canal_lang (code, canal, langue, etablissement_id),
    INDEX idx_module (module_source),
    INDEX idx_canal  (canal, actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `com_queue` — File d'attente asynchrone
```sql
CREATE TABLE com_queue (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    canal               ENUM('email','sms','push') NOT NULL,
    type                VARCHAR(60)  NOT NULL,      -- ex: 'bulletin_publie'
    user_id             INT UNSIGNED NULL,           -- destinataire interne
    destinataire_email  VARCHAR(150) NULL,
    destinataire_tel    VARCHAR(20)  NULL,
    push_token          VARCHAR(512) NULL,
    sujet               VARCHAR(255) NULL,
    corps               MEDIUMTEXT   NULL,
    template_id         INT UNSIGNED NULL,
    variables_json      JSON         NULL,
    statut              ENUM('pending','processing','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    priorite            ENUM('basse','normale','haute','critique') NOT NULL DEFAULT 'normale',
    tentatives          TINYINT      NOT NULL DEFAULT 0,
    max_tentatives      TINYINT      NOT NULL DEFAULT 3,
    planifie_at         DATETIME     NULL,           -- NULL = immédiat
    traite_at           DATETIME     NULL,
    prochain_essai_at   DATETIME     NULL,           -- retry backoff
    erreur              TEXT         NULL,
    etablissement_id    INT UNSIGNED NOT NULL DEFAULT 1,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pending   (statut, priorite, planifie_at),
    INDEX idx_retry     (statut, prochain_essai_at),
    INDEX idx_user      (user_id),
    INDEX idx_etab      (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `com_logs` — Journal de livraison
```sql
CREATE TABLE com_logs (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue_id         INT UNSIGNED NULL,            -- référence job source
    canal            ENUM('email','sms','push','internal') NOT NULL,
    type             VARCHAR(60)  NOT NULL,
    user_id          INT UNSIGNED NULL,
    destinataire     VARCHAR(200) NULL,             -- email / tel / user_id
    sujet            VARCHAR(255) NULL,
    statut           ENUM('sent','failed','read','bounced','unsubscribed') NOT NULL,
    module_source    VARCHAR(30)  NULL,
    entite_type      VARCHAR(60)  NULL,
    entite_id        INT UNSIGNED NULL,
    metadata         JSON         NULL,             -- provider_id, error_code
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_canal  (canal, statut, created_at DESC),
    INDEX idx_user   (user_id),
    INDEX idx_module (module_source, entite_type, entite_id),
    INDEX idx_etab   (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `com_groupes` — Groupes de diffusion
```sql
CREATE TABLE com_groupes (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom              VARCHAR(150) NOT NULL,
    description      TEXT         NULL,
    type             ENUM('manuel','role','classe','niveau','etablissement') NOT NULL DEFAULT 'manuel',
    criteres         JSON         NULL,             -- pour types auto (role_code, classe_id, etc.)
    actif            TINYINT(1)   NOT NULL DEFAULT 1,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    created_by       INT UNSIGNED NOT NULL DEFAULT 0,
    deleted_at       DATETIME     NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type, actif),
    INDEX idx_etab (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `com_groupe_membres` — Membres des groupes (groupes manuels)
```sql
CREATE TABLE com_groupe_membres (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    groupe_id  INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    added_by   INT UNSIGNED NOT NULL DEFAULT 0,
    added_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY fk_gm_groupe (groupe_id) REFERENCES com_groupes(id) ON DELETE CASCADE,
    UNIQUE KEY uq_groupe_user (groupe_id, user_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `com_preferences` — Préférences de notification utilisateur
```sql
CREATE TABLE com_preferences (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED NOT NULL,
    type_notification VARCHAR(60) NOT NULL,   -- 'bulletin','absence','facture', etc.
    canal_internal   TINYINT(1)  NOT NULL DEFAULT 1,
    canal_email      TINYINT(1)  NOT NULL DEFAULT 1,
    canal_sms        TINYINT(1)  NOT NULL DEFAULT 0,
    canal_push       TINYINT(1)  NOT NULL DEFAULT 1,
    etablissement_id INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_type (user_id, type_notification, etablissement_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `com_push_tokens` — Tokens push (PWA/FCM)
```sql
CREATE TABLE com_push_tokens (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    token       VARCHAR(512) NOT NULL,
    plateforme  ENUM('web','android','ios') NOT NULL DEFAULT 'web',
    actif       TINYINT(1)   NOT NULL DEFAULT 1,
    last_used_at DATETIME    NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user  (user_id, actif),
    UNIQUE KEY uq_token (token(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `com_campagnes` — Campagnes de communication masse
```sql
CREATE TABLE com_campagnes (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom                  VARCHAR(200) NOT NULL,
    description          TEXT         NULL,
    template_id          INT UNSIGNED NULL,
    canaux               JSON         NOT NULL,    -- ['email','sms','push','internal']
    cible_type           ENUM('groupe','role','classe','niveau','tous') NOT NULL,
    cible_id             INT UNSIGNED NULL,         -- groupe_id ou classe_id
    statut               ENUM('brouillon','planifiee','en_cours','terminee','annulee') NOT NULL DEFAULT 'brouillon',
    total_destinataires  INT UNSIGNED NOT NULL DEFAULT 0,
    total_envoyes        INT UNSIGNED NOT NULL DEFAULT 0,
    total_echecs         INT UNSIGNED NOT NULL DEFAULT 0,
    planifie_at          DATETIME     NULL,
    lance_at             DATETIME     NULL,
    termine_at           DATETIME     NULL,
    variables_json       JSON         NULL,
    etablissement_id     INT UNSIGNED NOT NULL DEFAULT 1,
    created_by           INT UNSIGNED NOT NULL DEFAULT 0,
    deleted_at           DATETIME     NULL,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_statut (statut, planifie_at),
    INDEX idx_etab   (etablissement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### `com_campagne_destinataires` — Tracking par destinataire
```sql
CREATE TABLE com_campagne_destinataires (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campagne_id  INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    statut       ENUM('pending','sent','failed','read') NOT NULL DEFAULT 'pending',
    canal        ENUM('email','sms','push','internal') NOT NULL,
    envoi_at     DATETIME NULL,
    lu_at        DATETIME NULL,
    erreur       TEXT     NULL,
    FOREIGN KEY fk_cd_camp (campagne_id) REFERENCES com_campagnes(id) ON DELETE CASCADE,
    INDEX idx_campagne (campagne_id, statut),
    INDEX idx_user     (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Sub-domaines (8)

| # | Domaine | Responsabilité |
|---|---------|---------------|
| 1 | **Inbox** | Notifications internes, lecture, compteur badge |
| 2 | **Messagerie** | Fils de conversation, réponses, archivage |
| 3 | **Templates** | CRUD templates, rendu avec variables, multilingue |
| 4 | **Queue** | File d'attente async, retry backoff, traitement cron |
| 5 | **Canaux** | Abstractions Email / SMS / Push / Internal |
| 6 | **Diffusion** | Groupes de diffusion, broadcast ciblé |
| 7 | **Campagnes** | Envois masse, planification, tracking par destinataire |
| 8 | **Préférences** | Opt-in/out par type et canal, push tokens |

---

## 4. Services — 11 services

### RoutageService — Cerveau du module ⭐
```php
class RoutageService
{
    // Reçoit un événement de n'importe quel module et l'orchestre
    public function router(Event $event, string $type): void;

    // Résout les user_ids destinataires depuis les données de l'event
    private function resoudreDestinataires(string $type, array $data): array; // retourne int[]

    // Vérifie les préférences utilisateur pour type+canal
    private function peutRecevoir(int $userId, string $type, string $canal): bool;

    // Crée la notification interne + les jobs queue selon préférences
    private function dispatcher(int $userId, string $type, array $contexte): void;

    // Mappe un type de notification vers son template
    private function template(string $type, string $canal, string $langue): ?array;
}
```

### NotificationService
```php
public function creer(int $userId, string $type, string $titre, string $corps,
                      string $moduleSource, ?string $entiteType = null,
                      ?int $entiteId = null, ?string $urlAction = null,
                      string $priorite = 'normale'): int;

public function marquerLu(int $notificationId, int $userId): void;
public function marquerTousLus(int $userId, int $etablissementId = 1): int;
public function compterNonLus(int $userId): int;
public function lister(int $userId, int $page = 1, int $perPage = 20): array;  // paginé
public function supprimer(int $notificationId, int $userId): void;
public function purgerExpires(): int;   // cron — supprime les notifications expirées
```

### ThreadService
```php
public function creer(ThreadDTO $dto, int $userId): int;
public function reply(ThreadMessageDTO $dto, int $userId): int;
public function archiver(int $threadId, int $userId): void;
public function quitter(int $threadId, int $userId): void;
public function listerPourUser(int $userId, int $page = 1): array;
public function messagesDuThread(int $threadId, int $userId, int $page = 1): array;
public function marquerLu(int $threadId, int $userId): void;
public function compterNonLus(int $userId): int;
```

### TemplateService
```php
public function creer(TemplateDTO $dto, int $userId): int;
public function modifier(int $templateId, TemplateDTO $dto, int $userId): void;
public function trouver(int $templateId): ?array;
public function trouverParCode(string $code, string $canal, string $langue = 'fr'): ?array;
public function render(array $template, array $variables): array;   // retourne ['sujet','corps_html','corps_texte']
public function lister(?string $canal = null, ?string $moduleSource = null): array;
public function archiver(int $templateId, int $userId): void;
```

### QueueService
```php
public function enqueue(string $canal, string $type, array $destinataire,
                        ?int $templateId = null, array $variables = [],
                        string $priorite = 'normale', ?\DateTime $planifieAt = null): int;

public function traiterBatch(int $limit = 50): array;   // retourne ['processed','success','failed']
public function relancerEchecs(int $maxAge = 3600): int; // relance les failed < $maxAge secondes
public function annuler(int $jobId): void;
public function statistiques(int $etablissementId = 1): array;
```

### EmailService
```php
public function envoyer(array $job): bool;         // implémente ChannelInterface
public function disponible(): bool;
public function canal(): string;                   // 'email'
```

### SmsService
```php
public function envoyer(array $job): bool;         // stub V2 : log seulement
public function disponible(): bool;                // false en V2 (pas de provider configuré)
public function canal(): string;                   // 'sms'
```

### PushService
```php
public function envoyer(array $job): bool;         // utilise VAPID (PWA existant)
public function enregistrerToken(int $userId, string $token, string $plateforme): void;
public function revoquerToken(string $token): void;
public function tokensActifs(int $userId): array;
public function disponible(): bool;
public function canal(): string;                   // 'push'
```

### DiffusionService
```php
public function diffuser(DiffusionDTO $dto, int $userId): array;   // retourne ['queued','total']
public function resoudreDestinataires(DiffusionDTO $dto): array;   // retourne int[] userIds
public function creerGroupe(GroupeDTO $dto, int $userId): int;
public function modifierGroupe(int $groupeId, GroupeDTO $dto, int $userId): void;
public function supprimerGroupe(int $groupeId, int $userId): void;
public function ajouterMembres(int $groupeId, array $userIds, int $userId): void;
public function retirerMembre(int $groupeId, int $userId, int $adminId): void;
```

### PreferenceService
```php
public function obtenirPourUser(int $userId): array;               // toutes les préférences
public function mettreAJour(int $userId, PreferenceDTO $dto): void;
public function peutRecevoir(int $userId, string $type, string $canal): bool;
public function reinitialiser(int $userId): void;                   // reset aux défauts
```

### CampagneService
```php
public function creer(CampagneDTO $dto, int $userId): int;
public function modifier(int $campagneId, CampagneDTO $dto, int $userId): void;
public function lancer(int $campagneId, int $userId): array;       // retourne stats
public function annuler(int $campagneId, int $userId): void;
public function statistiques(int $campagneId): array;              // total/envoyés/échecs/taux lecture
public function lister(int $etablissementId = 1, int $page = 1): array;
```

---

## 5. Repositories — 9 repositories

| Repository | Méthodes principales |
|-----------|---------------------|
| `NotificationRepository` | insert, findByUser (paginé), countUnread, markRead, markAllRead, deleteExpired |
| `ThreadRepository` | insert, findForUser (paginé), findById, updateDernierMessage, archive, delete |
| `ThreadMessageRepository` | insert, findByThread (paginé), softDelete |
| `ThreadParticipantRepository` | insert, findByThread, findByUser, updateLuAt, remove |
| `TemplateRepository` | insert, update, findById, findByCode, findAll, softDelete |
| `QueueRepository` | insert, findPending, findForRetry, updateStatut, countByStatut |
| `LogRepository` | insert, findForReport, countByCanal, statistiques |
| `GroupeRepository` | insert, update, findById, findAll, softDelete, membres, insertMembre, deleteMembre |
| `PreferenceRepository` | upsert, findByUser, findByUserAndType |
| `PushTokenRepository` | insert, findByUser, findByToken, revoke |
| `CampagneRepository` | insert, update, findById, findAll, findDestinataires, updateStats |

> Note : 11 repositories comptés (incluant PushTokenRepository et CampagneRepository).  
> Split en 9 fichiers ou 11 selon granularité retenue en Phase 8.x.

---

## 6. DTOs — 9 DTOs

### NotificationDTO
```php
readonly class NotificationDTO {
    string $type,           // 'info','success','warning','alert'
    string $titre,
    string $corps,
    string $moduleSource,
    ?string $entiteType,
    ?int $entiteId,
    ?string $urlAction,
    string $priorite = 'normale',
    ?\DateTime $expireAt,
}
```

### ThreadDTO
```php
readonly class ThreadDTO {
    string $sujet,
    array $participantIds,   // int[]
    string $corpsInitial,
    string $type = 'direct',
    ?string $moduleSource,
    ?string $entiteType,
    ?int $entiteId,
}
```

### ThreadMessageDTO
```php
readonly class ThreadMessageDTO {
    int $threadId,
    string $corps,
    string $type = 'texte',
    array $documentIds = [],  // pièces jointes doc_documents
}
```

### TemplateDTO
```php
readonly class TemplateDTO {
    string $code,
    string $nom,
    string $canal,           // 'email','sms','push','internal'
    string $langue = 'fr',
    ?string $moduleSource,
    ?string $sujet,
    ?string $corpsHtml,
    ?string $corpsTexte,
    array $variables = [],
}
```

### DiffusionDTO
```php
readonly class DiffusionDTO {
    string $sujet,
    string $corps,
    array $canaux,           // ['internal','email']
    string $cibleType,       // 'groupe','role','classe','niveau','tous'
    ?int $cibleId,
    ?string $roleCode,
    ?int $templateId,
    array $variables = [],
    string $priorite = 'normale',
}
```

### GroupeDTO
```php
readonly class GroupeDTO {
    string $nom,
    ?string $description,
    string $type = 'manuel',  // 'manuel','role','classe','niveau'
    array $criteres = [],
}
```

### PreferenceDTO
```php
readonly class PreferenceDTO {
    string $typeNotification,
    bool $canalInternal = true,
    bool $canalEmail = true,
    bool $canalSms = false,
    bool $canalPush = true,
}
```

### CampagneDTO
```php
readonly class CampagneDTO {
    string $nom,
    ?string $description,
    array $canaux,
    string $cibleType,
    ?int $cibleId,
    ?int $templateId,
    array $variables = [],
    ?\DateTime $planifieAt,
}
```

### QueueJobDTO (interne)
```php
readonly class QueueJobDTO {
    string $canal,
    string $type,
    ?int $userId,
    ?string $destinataireEmail,
    ?string $destinataireTel,
    ?string $pushToken,
    ?string $sujet,
    ?string $corps,
    ?int $templateId,
    array $variables = [],
    string $priorite = 'normale',
    ?\DateTime $planifieAt,
}
```

---

## 7. Models — 5 models

### NotificationModel — Constantes inbox
```php
const TYPES    = ['info','success','warning','alert','message','system'];
const PRIORITES = ['basse','normale','haute','critique'];
// Helper : isExpired(?string $expireAt): bool
// Helper : iconePourType(string $type): string
// Helper : couleurPourPriorite(string $priorite): string
```

### MessageModel — Constantes canaux
```php
const CANAUX  = ['email','sms','push','internal'];
const STATUTS = ['pending','processing','sent','failed','cancelled'];
// Helper : labelStatut(string $statut): string
// Helper : delaiRetry(int $tentative): int  // 60, 300, 1800 (backoff exponentiel)
```

### ThreadModel — Constantes messagerie
```php
const TYPES   = ['direct','groupe','broadcast'];
// Helper : nomAffichage(array $thread, int $currentUserId): string
```

### TemplateModel — Constantes templates
```php
const CANAUX  = ['email','sms','push','internal'];
const LANGUES = ['fr','ar','en'];
// Helper : variables par défaut pour chaque type
const VARIABLES_COMMUNES = ['nom_complet','prenom','nom_etablissement','date','url_plateforme'];
```

### CampagneModel — Constantes campagnes
```php
const STATUTS = ['brouillon','planifiee','en_cours','terminee','annulee'];
const CIBLES  = ['groupe','role','classe','niveau','tous'];
```

---

## 8. Policies — 2 policies

### CommunicationPolicy
```php
canView(array $user): bool                          // communication.view
canSend(array $user): bool                          // communication.send
canBroadcast(array $user): bool                     // communication.broadcast
canManageTemplates(array $user): bool               // communication.manage_templates
canManageGroups(array $user): bool                  // communication.manage_groups
canLaunchCampaign(array $user): bool                // communication.campaign
canAdmin(array $user): bool                         // communication.admin
canViewAll(array $user): bool                       // communication.view_all
```

### ThreadPolicy
```php
canView(array $user, array $thread): bool           // participant du thread
canReply(array $user, array $thread): bool          // participant non archivé
canArchive(array $user, array $thread): bool        // créateur OU modérateur
canDelete(array $user, array $thread): bool         // modérateur OU communication.admin
```

---

## 9. RBAC — 8 permissions

| Permission | Description | admin | directeur | secrétaire | comptable | enseignant | parent | élève |
|-----------|-------------|:-----:|:---------:|:----------:|:---------:|:----------:|:------:|:-----:|
| `communication.view` | Voir notifications et messages | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `communication.send` | Envoyer un message individuel | ✅ | ✅ | ✅ | — | ✅ | — | — |
| `communication.broadcast` | Diffuser à des groupes | ✅ | ✅ | ✅ | — | — | — | — |
| `communication.manage_templates` | Gérer les templates | ✅ | ✅ | — | — | — | — | — |
| `communication.manage_groups` | Gérer les groupes de diffusion | ✅ | ✅ | ✅ | — | — | — | — |
| `communication.campaign` | Créer et lancer des campagnes | ✅ | ✅ | — | — | — | — | — |
| `communication.admin` | Logs, queue, stats, purge | ✅ | — | — | — | — | — | — |
| `communication.view_all` | Voir toutes les communications | ✅ | ✅ | — | — | — | — | — |

---

## 10. Events dispatched — 12 événements propres

Tous étendent `Core\Event`, appellent `parent::__construct()`, implémentent `toArray()`.

| Événement | Paramètres constructeur | Déclencheur |
|-----------|------------------------|-------------|
| `NotificationCreated` | notificationId, userId, type, titre, moduleSource | NotificationService::creer() |
| `NotificationRead` | notificationId, userId | NotificationService::marquerLu() |
| `NotificationsBulkRead` | userId, count | NotificationService::marquerTousLus() |
| `ThreadCreated` | threadId, createdById, participantIds[] | ThreadService::creer() |
| `ThreadMessageSent` | threadId, messageId, senderId, participantIds[] | ThreadService::reply() |
| `MessageQueued` | queueId, canal, type, userId | QueueService::enqueue() |
| `MessageSent` | queueId, canal, type, userId, destinataire | QueueService::traiterBatch() |
| `MessageFailed` | queueId, canal, type, userId, raison, tentative | QueueService::traiterBatch() |
| `DiffusionEnvoyee` | groupeId, type, totalDestinataires, sentById | DiffusionService::diffuser() |
| `CampagneLancee` | campagneId, totalDestinataires, launchedById | CampagneService::lancer() |
| `CampagneTerminee` | campagneId, totalEnvoyes, totalEchecs | QueueService (callback) |
| `PreferencesUpdated` | userId, typeNotification, changes[] | PreferenceService::mettreAJour() |

---

## 11. Listeners — 3 listeners

### AuditListener
```
Écoute : 12 events propres (NotificationCreated, MessageSent, etc.)
Action : AuditService::log(userId, action, 'communication', type, id, old, new)
```

### CrossModuleListener ⭐ — Cœur de l'intégration
```
Écoute : ~28 events des autres modules (voir section 13)
Action : CrossModuleListener::handle(Event) → RoutageService::router(event, type)
Pattern : match(true) { $event instanceof XxxEvent => $this->onXxx($event) }
```

### RealTimeListener (stub V2)
```
Écoute : NotificationCreated, ThreadMessageSent
Action V2 : met à jour un compteur en session / no-op
Action V3 : WebSocket push via Mercure ou SSE
```

---

## 12. API — 35 routes (`/v2/communication/*`, `/v2/notifications/*`, `/v2/messages/*`)

### Notifications — Inbox (5 routes)
```
GET  /v2/notifications                     → NotificationController::index
POST /v2/notifications/{id}/read           → NotificationController::markRead
POST /v2/notifications/read-all            → NotificationController::markAllRead
GET  /v2/notifications/unread-count        → NotificationController::unreadCount    (JSON badge)
DELETE /v2/notifications/{id}              → NotificationController::destroy
```

### Messagerie interne — Threads (7 routes)
```
GET  /v2/messages                          → ThreadController::index
POST /v2/messages                          → ThreadController::store
GET  /v2/messages/{threadId}               → ThreadController::show
POST /v2/messages/{threadId}/reply         → ThreadController::reply
POST /v2/messages/{threadId}/read          → ThreadController::markRead
POST /v2/messages/{threadId}/archive       → ThreadController::archive
DELETE /v2/messages/{threadId}             → ThreadController::destroy
```

### Diffusion — Broadcasts (6 routes)
```
POST /v2/communication/diffuser            → DiffusionController::send
GET  /v2/communication/groupes             → DiffusionController::groupes
POST /v2/communication/groupes             → DiffusionController::creerGroupe
POST /v2/communication/groupes/{id}/membres → DiffusionController::ajouterMembres
DELETE /v2/communication/groupes/{id}/membres/{userId} → DiffusionController::retirerMembre
DELETE /v2/communication/groupes/{id}      → DiffusionController::supprimerGroupe
```

### Templates (6 routes)
```
GET  /v2/communication/templates           → TemplateController::index
POST /v2/communication/templates           → TemplateController::store
GET  /v2/communication/templates/{id}      → TemplateController::show
POST /v2/communication/templates/{id}      → TemplateController::update
POST /v2/communication/templates/{id}/preview → TemplateController::preview
DELETE /v2/communication/templates/{id}    → TemplateController::destroy
```

### Campagnes (5 routes)
```
GET  /v2/communication/campagnes           → CampagneController::index
POST /v2/communication/campagnes           → CampagneController::store
GET  /v2/communication/campagnes/{id}      → CampagneController::show
POST /v2/communication/campagnes/{id}/launch → CampagneController::launch
GET  /v2/communication/campagnes/{id}/stats  → CampagneController::stats
```

### Préférences & Push (4 routes)
```
GET  /v2/communication/preferences         → PreferenceController::show
POST /v2/communication/preferences         → PreferenceController::update
POST /v2/communication/preferences/reset   → PreferenceController::reset
POST /v2/communication/push/register       → PreferenceController::registerPush
```

### Administration (5 routes)
```
GET  /v2/communication/admin/queue         → AdminController::queue
POST /v2/communication/admin/queue/process → AdminController::processQueue
POST /v2/communication/admin/queue/{id}/cancel → AdminController::cancelJob
GET  /v2/communication/admin/logs          → AdminController::logs
GET  /v2/communication/admin/stats         → AdminController::stats
```

### API privée (3 routes — appels depuis frontend JS)
```
GET  /v2/notifications/poll                → NotificationController::poll    (AJAX badge)
GET  /v2/messages/unread-count             → ThreadController::unreadCount    (AJAX)
GET  /v2/communication/templates/{code}/render → TemplateController::renderPreview
```

---

## 13. Carte d'intégration — Cross-Module Events

### Workflow de routage
```
Événement externe dispatché
    ↓
CrossModuleListener::handle(Event)
    ↓
RoutageService::router($event, $type)
    ↓
Résolution des destinataires (depuis data de l'event)
    ↓
Pour chaque destinataire :
    ↓
PreferenceService::peutRecevoir($userId, $type, $canal)
    ↓
Si oui :
  NotificationService::creer()      → com_notifications (canal 'internal')
  QueueService::enqueue('email',…)  → com_queue
  QueueService::enqueue('push',…)   → com_queue
    ↓
Dispatch NotificationCreated + MessageQueued
    ↓
CRON → QueueService::traiterBatch(50)
    ↓
EmailService/SmsService/PushService::envoyer()
    ↓
LogRepository::insert() → com_logs
```

### Intégrations par module

#### MODULE ACADÉMIQUE (9 events)

| Événement | Type notification | Destinataires | Canaux |
|-----------|------------------|---------------|--------|
| `BulletinPublished` | `bulletin_publie` | Parents + Élève | internal + email |
| `NotePublished` | `note_publie` | Élève (opt-in par défaut) | internal |
| `EvaluationCreated` | `evaluation_planifiee` | Élèves de la classe | internal + push |
| `AverageCalculated` | `moyenne_calculee` | Élève (opt-in) | internal |
| `RankingGenerated` | `classement` | Parents (opt-in) | internal |

> Résolution destinataires : `entite_id` = classe → requête `eleves WHERE classe_id = X` + récupération `user_id` parents via table familles

#### MODULE FINANCE (6 events)

| Événement | Type notification | Destinataires | Canaux |
|-----------|------------------|---------------|--------|
| `InvoiceCreated` | `facture_emise` | Parents (débiteurs) | internal + email |
| `PaymentCompleted` | `paiement_recu` | Parent payeur | internal + email |
| `PaymentRefunded` | `remboursement` | Parent | internal + email |
| `InvoiceCancelled` | `facture_annulee` | Parent | internal |

#### MODULE VIE SCOLAIRE (7 events)

| Événement | Type notification | Destinataires | Canaux | Urgence |
|-----------|------------------|---------------|--------|---------|
| `StudentAbsent` | `absence_eleve` | Parents | internal + SMS + push | haute |
| `AbsenceJustified` | `justificatif_accepte` | Parent | internal | normale |
| `AbsenceRejected` | `justificatif_refuse` | Parent | internal + push | normale |
| `RewardGranted` | `recompense` | Élève + Parents | internal | basse |
| `StudentRegisteredToActivity` | `inscription_activite` | Élève + Parents | internal + email | normale |
| `ActivityCancelled` | `activite_annulee` | Inscrits | internal + email + push | haute |
| `TimetablePublished` | `emploi_du_temps` | Classes concernées | internal | normale |

#### MODULE RH (8 events)

| Événement | Type notification | Destinataires | Canaux |
|-----------|------------------|---------------|--------|
| `LeaveRequested` | `conge_demande` | Responsable RH | internal + email |
| `LeaveApproved` | `conge_approuve` | Employé | internal + push |
| `LeaveRejected` | `conge_rejete` | Employé | internal + push |
| `ContractExpired` | `contrat_expire` | Employé + HR Admin | internal + email |
| `EvaluationCreated` (RH) | `evaluation_rh` | Employé évalué | internal + email |
| `EvaluationPublished` (RH) | `rapport_evaluation` | Employé | internal |
| `CertificationExpired` | `certification_expire` | Employé + HR | internal + email |
| `TrainingCreated` | `formation_disponible` | Employés éligibles | internal |

#### MODULE DOCUMENTS (3 events)

| Événement | Type notification | Destinataires | Canaux |
|-----------|------------------|---------------|--------|
| `DocumentShared` | `document_partage` | Destinataire partage | internal + email |
| `DocumentSignatureRequested` | `signature_requise` | Signataires | internal + email |
| `DocumentExpired` | `document_expire` | Admin documents | internal |

---

## 14. Workflows clés

### Workflow 1 — Notification d'absence élève (urgent)

```
1. VieScolaire dispatche StudentAbsent(eleveId, classeId, date, ...)
2. CrossModuleListener::onStudentAbsent(event)
3. RoutageService::router(event, 'absence_eleve')
4. Résolution : SELECT user_id FROM familles WHERE eleve_id = X → parent_ids[]
5. Pour chaque parent :
   a. NotificationService::creer(userId, 'alert', 'Absence signalée', ..., priorite='haute')
   b. QueueService::enqueue('push', ..., priorite='haute', planifieAt=null)     → immédiat
   c. IF preference.canal_sms: QueueService::enqueue('sms', ..., priorite='haute')
6. QueueService::traiterBatch() (cron 1 min) → PushService::envoyer() + SmsService::envoyer()
7. LogRepository::insert(statut='sent')
8. Dispatch MessageSent(queueId, 'push', 'absence_eleve', parentId, ...)
```

### Workflow 2 — Publication d'un bulletin (batch)

```
1. Academique dispatche BulletinPublished(eleveId, periodeId, ...)  [1 event par élève]
2. CrossModuleListener::onBulletinPublished(event)
3. RoutageService — résout parent_ids + eleve user_id
4. Crée notification interne (priorite='normale')
5. QueueService::enqueue('email', ..., templateId=bulletin_template, planifieAt=null)
6. QueueService::traiterBatch(50) → EmailService::envoyer()
   → Template rendu avec {nom_eleve, periode, mention, moyenne, url_bulletin}
7. Dispatch MessageSent par email pour chaque famille
```

### Workflow 3 — Campagne de rentrée scolaire

```
1. Admin → POST /v2/communication/campagnes → CampagneService::creer()
2. Admin → POST /v2/communication/campagnes/{id}/launch → CampagneService::lancer()
3. DiffusionService::resoudreDestinataires(dto) → tous les parents (cibleType='role')
4. Pour chaque parent : QueueService::enqueue('email', ...) + enqueue('internal', ...)
5. com_campagne_destinataires INSERT bulk avec statut='pending'
6. QueueService::traiterBatch() — traitement progressif (50 par cron)
7. Mise à jour com_campagnes.total_envoyes au fur et à mesure
8. Quand total_envoyes = total_destinataires → statut='terminee'
9. Dispatch CampagneTerminee(campagneId, totalEnvoyes, totalEchecs)
```

### Workflow 4 — Retry email échoué

```
1. EmailService::envoyer() → false (SMTP timeout)
2. QueueRepository::updateStatut(jobId, 'failed', erreur='SMTP timeout')
3. tentatives++ → si tentatives < max_tentatives :
   prochain_essai_at = NOW() + delaiRetry(tentatives)  // 60s, 300s, 1800s
   statut = 'pending'
4. Prochain cron → findForRetry() → ré-essai
5. Si tentatives >= max_tentatives → statut='failed' définitif
6. Dispatch MessageFailed(queueId, 'email', type, userId, raison, tentative)
7. AuditListener → log l'échec
```

---

## 15. Vues (14 vues prévues)

| Vue | Description |
|-----|-------------|
| `notifications/index.php` | Inbox complète avec filtres et pagination |
| `notifications/panel.php` | Panneau dropdown (header badge) |
| `messages/index.php` | Liste des fils de conversation |
| `messages/show.php` | Vue conversation avec messages |
| `messages/create.php` | Nouveau message / nouveau fil |
| `diffusion/index.php` | Groupes et diffusion ciblée |
| `diffusion/composer.php` | Formulaire de diffusion |
| `templates/index.php` | Liste des templates |
| `templates/form.php` | Création / édition template |
| `templates/preview.php` | Prévisualisation rendu |
| `campagnes/index.php` | Liste des campagnes |
| `campagnes/show.php` | Détail campagne + statistiques |
| `preferences/index.php` | Préférences notification utilisateur |
| `admin/dashboard.php` | Queue + logs + statistiques admin |

---

## 16. Seeds — Templates par défaut

### Templates à seeder (19 templates — 3 canaux × types clés)

| Code | Canal | Module | Description |
|------|-------|--------|-------------|
| `bulletin_publie` | internal | academique | Bulletin disponible (interne) |
| `bulletin_publie` | email | academique | Bulletin disponible (email HTML) |
| `absence_eleve` | internal | vie_scolaire | Absence signalée (interne) |
| `absence_eleve` | sms | vie_scolaire | Absence signalée (SMS court) |
| `absence_eleve` | push | vie_scolaire | Absence signalée (push) |
| `facture_emise` | internal | finance | Nouvelle facture (interne) |
| `facture_emise` | email | finance | Nouvelle facture (email HTML) |
| `paiement_recu` | internal | finance | Confirmation paiement (interne) |
| `paiement_recu` | email | finance | Confirmation paiement (email) |
| `conge_approuve` | internal | rh | Congé approuvé (interne) |
| `conge_approuve` | push | rh | Congé approuvé (push) |
| `conge_rejete` | internal | rh | Congé refusé (interne) |
| `document_partage` | internal | documents | Document partagé (interne) |
| `document_partage` | email | documents | Document partagé (email) |
| `signature_requise` | email | documents | Signature électronique requise |
| `activite_annulee` | internal | vie_scolaire | Activité annulée (interne) |
| `formation_disponible` | internal | rh | Nouvelle formation (interne) |
| `contrat_expire` | internal | rh | Contrat expirant (interne) |
| `emploi_du_temps` | internal | vie_scolaire | Emploi du temps publié |

Variables communes disponibles dans tous les templates : `{{nom_complet}}`, `{{prenom}}`, `{{nom_etablissement}}`, `{{date}}`, `{{url_plateforme}}`

---

## 17. Contraintes techniques

### Quotas et limites
- SMS max 160 caractères par message (validation dans SmsService)
- Push max 100 caractères titre, 200 caractères corps
- Email sans limite de corps (HTML complet)
- `com_queue` : max 1 000 jobs simultanément en `processing` (lock applicatif)
- Batch cron : 50 jobs par exécution maximum
- Retry backoff : 60s → 5min → 30min → définitivement `failed`

### Sécurité
- Templates : échappement HTML systématique dans `TemplateService::render()` avant injection
- Diffusion : `communication.broadcast` requis — pas de diffusion par rôle < secrétaire
- Préférences : chaque utilisateur ne peut modifier que SES préférences
- Threads : vérification participation avant lecture/réponse
- Queue admin : `communication.admin` uniquement

### Performance
- Index `(user_id, lu, created_at DESC)` sur `com_notifications` — O(log n) pour le badge
- `com_queue` : index `(statut, priorite, planifie_at)` pour le cron batch
- Pas de N+1 sur les threads : JOIN participants dans `findForUser()`
- Notifications expirées : purge via `NotificationService::purgerExpires()` (cron hebdo)

---

## 18. Dépendances

### Dépendances techniques V2 (sans librairies externes)
| Dépendance | Usage | Statut |
|-----------|-------|--------|
| `App\Services\AuditService` | Logs audit via AuditListener | ✅ disponible |
| `Core\EventDispatcher` | Dispatch des 12 events propres | ✅ disponible |
| VAPID existant (PWA) | PushService::envoyer() | ✅ disponible via PWA module |
| PDO (via `Core\Database`) | Tous les repositories | ✅ disponible |
| `mail()` PHP | EmailService V2 (stub) | ✅ natif PHP |
| `ROOT_PATH`, `BASE_URL` | Liens dans templates | ✅ constants framework |

### Dépendances V3 (hors scope V2)
| Dépendance | Usage |
|-----------|-------|
| Redis / BeanstalkD | Queue haute performance (remplace com_queue) |
| Mailgun / SendGrid / AWS SES | Provider email transactionnel |
| Twilio / OVH SMS | Provider SMS |
| FCM (Firebase) | Push Android natif |
| Mercure / SSE | Notifications temps réel |
| PHPMailer | SMTP avancé |

---

## 19. Phases d'implémentation

| Phase | Contenu | Priorité |
|-------|---------|---------|
| **8.2** | Sub-domaine Inbox — `com_notifications`, NotificationService, NotificationController, 2 vues | P0 |
| **8.3** | Sub-domaine Templates — `com_templates`, TemplateService, rendu variables, seeds 19 templates | P0 |
| **8.4** | Sub-domaine Queue — `com_queue`, `com_logs`, QueueService, EmailService stub, cron batch, retry | P0 |
| **8.5** | Sub-domaine Messagerie — `com_threads` + `com_thread_messages` + `com_thread_participants`, ThreadService, 3 vues | P1 |
| **8.6** | Sub-domaine Diffusion — `com_groupes` + `com_groupe_membres`, DiffusionService, 2 vues | P1 |
| **8.7** | Sub-domaine Préférences & Push — `com_preferences`, `com_push_tokens`, PreferenceService, PushService | P1 |
| **8.8** | Sub-domaine Campagnes — `com_campagnes` + `com_campagne_destinataires`, CampagneService, 2 vues | P2 |
| **8.9** | Intégrations cross-modules — CrossModuleListener (28 events), RoutageService, SmsService stub | P0 |
| **8.10** | Integration Review + Module Freeze | — |

### Ordre recommandé
> **8.2 → 8.3 → 8.4 → 8.9** (inbox + templates + queue + intégrations = valeur maximale)  
> **8.5 → 8.6 → 8.7 → 8.8** (messagerie + diffusion + préférences + campagnes)

---

## 20. Dette technique anticipée

| ID | Sévérité | Description | Cible |
|----|----------|-------------|-------|
| DT-COM-001 | Mineure | EmailService V2 = `mail()` PHP — sans SMTP sécurisé ni tracking bounces | V3 |
| DT-COM-002 | Mineure | SmsService V2 = stub (log fichier) — sans provider réel | V3 |
| DT-COM-003 | Mineure | RealTimeListener V2 = no-op — pas de WebSocket/SSE | V3 |
| DT-COM-004 | Mineure | Queue V2 = table SQL + cron — pas de worker Redis | V3 |
| DT-COM-005 | Info | CrossModuleListener monolithique — scindable en handlers par domaine V3 | V3 |
| DT-COM-006 | Info | Résolution destinataires via requêtes directes (pas d'API cross-module formalisée) | V3 |

---

## Résumé Blueprint

| Métrique | Valeur |
|---------|--------|
| Tables SQL | 13 (`com_*`) |
| Services | 11 |
| Repositories | 9 |
| DTOs | 9 |
| Models | 5 |
| Policies | 2 |
| Events propres | 12 |
| Events consommés | ~28 (cross-module) |
| Listeners | 3 |
| Permissions | 8 |
| Routes | 35 |
| Vues | 14 |
| Templates seeds | 19 |
| Phases d'implémentation | 9 (8.2 → 8.10) |

---

*Blueprint COMMUNICATION_V2 produit le 2026-07-02. Prochain jalon : Phase 8.2 — Inbox.*
