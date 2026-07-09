# SHARED SERVICES V2 — SCOLARIS
## Couche de services communs inter-modules

> Étape 5 de la migration SCOLARIS V2.
> **Aucune implémentation.** Conception uniquement : responsabilités, interfaces, dépendances, tables.
> Date : 2026-06-29

---

## TABLE DES MATIÈRES

1. [Diagnostic de l'existant](#1-diagnostic-de-lexistant)
2. [Architecture de la couche Shared](#2-architecture-de-la-couche-shared)
3. [AuditService](#3-auditservice)
4. [NotificationService](#4-notificationservice)
5. [PdfService](#5-pdfservice)
6. [ExportService](#6-exportservice)
7. [SearchService](#7-searchservice)
8. [UploadService](#8-uploadservice)
9. [StatisticsService](#9-statisticsservice)
10. [Table SQL : audit_logs](#10-table-sql--audit_logs)
11. [Migration SQL](#11-migration-sql)
12. [Matrice des dépendances](#12-matrice-des-dépendances)

---

## 1. DIAGNOSTIC DE L'EXISTANT

### 1.1 Services existants dans `app/Services/`

| Fichier | Statut | Notes |
|---|---|---|
| `EmailService.php` | ✅ Conservé tel quel | V2 : lira la config depuis `ParametreService` |
| `SmsService.php` | ✅ Conservé tel quel | V2 : lira la config depuis `ParametreService` |
| `NotificationService.php` | ✅ Conservé, étendu | Ajouter canal push VAPID, voir §4 |

### 1.2 Services manquants — logique actuellement dupliquée

| Service manquant | Où la logique existe aujourd'hui | Problème |
|---|---|---|
| `UploadService` | `AuthController:393`, `ProfesseurController:386`, `EleveController:466`, `AbsenceController:522` | 4 implémentations distinctes, 2 chemins (`public/uploads/` vs `storage/uploads/`), sans validation MIME réelle |
| `ExportService` | `RapportController::exportExcel()`, `RapportController::exportPdf()`, `EleveController` (CSV) | Logique CSV inlinée dans 2+ contrôleurs, aucun service partagé |
| `PdfService` | `BulletinController::printBulletin()`, `RapportController::exportPdf()` | 100% navigateur (`layout=print` → `window.print()`). Pas de génération serveur → impossible d'envoyer des bulletins par email ou de faire du téléchargement groupé |
| `AuditService` | Néant | Aucune trace d'action utilisateur enregistrée |
| `SearchService` | Néant | Aucune recherche globale |
| `StatisticsService` | `RapportModel` (méthodes spécifiques) | Stats couplées au module Rapports, non réutilisables depuis Dashboard ou Espaces |

### 1.3 Problèmes de stockage des fichiers uploadés

```
ACTUEL (incohérent)                     V2 (cible unifiée)
────────────────────────────────────    ──────────────────────────────────────
public/uploads/eleves/                  storage/uploads/eleves/       (non public)
public/uploads/professeurs/             storage/uploads/professeurs/   (non public)
storage/uploads/avatars/                storage/uploads/avatars/
storage/uploads/justifications/         storage/uploads/justifications/
(établissement logo — aucun standard)   storage/uploads/etablissement/

Accès HTTP aux photos : via une route GET /uploads/{type}/{filename}
contrôlée (vérifie auth + permissions) plutôt qu'accès direct au filesystem.
```

---

## 2. ARCHITECTURE DE LA COUCHE SHARED

### 2.1 Position dans l'arborescence V2

```
app/
  Services/                   ← ACTUEL (plat)
    EmailService.php
    SmsService.php
    NotificationService.php

CIBLE V2 (même dossier, noms clairs — le Router ne voit pas ces fichiers)
app/
  Services/
    EmailService.php            ← conservé (lit config depuis ParametreService en V2)
    SmsService.php              ← conservé (idem)
    NotificationService.php     ← étendu (push VAPID)
    AuditService.php            ← NOUVEAU
    PdfService.php              ← NOUVEAU
    ExportService.php           ← NOUVEAU
    SearchService.php           ← NOUVEAU
    UploadService.php           ← NOUVEAU
    StatisticsService.php       ← NOUVEAU
```

> Le PSR-4 autoloader enregistre `App\Services` → `app/Services/`. Aucune modification de l'autoloader nécessaire.

### 2.2 Règles de la couche Shared

```
1. Un service partagé NE connaît PAS de contrôleur.
2. Un service partagé PEUT appeler d'autres services partagés.
3. Un service partagé NE fait PAS de redirect() ni de render().
4. Un service partagé retourne des données ou lève des exceptions.
5. L'instanciation reste manuelle (new ServiceName()) — pas d'injection de dépendances.
6. Chaque service est utilisable depuis n'importe quel module sans couplage.
```

---

## 3. AUDITSERVICE

### 3.1 Responsabilité

Enregistrer un journal immuable de toutes les actions significatives des utilisateurs : qui a fait quoi, sur quel enregistrement, avec quel résultat, depuis quelle IP.

Utilisé pour : conformité, débogage, détection d'anomalies, audit interne.

### 3.2 Interface publique

```php
namespace App\Services;

class AuditService
{
    // ── Actions standard ─────────────────────────────────────────────────────

    /**
     * Enregistre une action utilisateur.
     *
     * @param int         $userId     Utilisateur auteur de l'action
     * @param string      $action     'create' | 'update' | 'delete' | 'login' | 'logout'
     *                                | 'export' | 'print' | 'login_failed' | 'permission_denied'
     * @param string      $module     'eleves' | 'notes' | 'paiements' | ...
     * @param string|null $entite     Nom de la table cible ('eleves', 'paiements', ...)
     * @param int|null    $entiteId   ID de l'enregistrement affecté
     * @param array|null  $avant      État avant modification (pour update/delete)
     * @param array|null  $apres      État après modification (pour create/update)
     */
    public function log(
        int    $userId,
        string $action,
        string $module,
        string $entite   = null,
        int    $entiteId = null,
        array  $avant    = null,
        array  $apres    = null
    ): void;

    /**
     * Raccourci : log une création.
     * Retire les champs sensibles (mot_de_passe, token...) avant stockage.
     */
    public function logCreate(int $userId, string $module, string $entite, int $newId, array $data): void;

    /**
     * Raccourci : log une modification avec diff automatique.
     * Stocke uniquement les champs qui ont changé (avant ≠ après).
     */
    public function logUpdate(int $userId, string $module, string $entite, int $id, array $avant, array $apres): void;

    /**
     * Raccourci : log une suppression. Stocke l'état complet avant suppression.
     */
    public function logDelete(int $userId, string $module, string $entite, int $id, array $snapshot): void;

    /**
     * Raccourci : log une connexion (réussie ou échouée).
     */
    public function logLogin(int|null $userId, string $email, bool $success, string $ip): void;

    /**
     * Raccourci : log un refus de permission.
     */
    public function logPermissionDenied(int $userId, string $permission, string $route): void;

    // ── Consultation ─────────────────────────────────────────────────────────

    /**
     * Historique des actions sur un enregistrement précis.
     */
    public function getEntityHistory(string $entite, int $id, int $limit = 50): array;

    /**
     * Historique des actions d'un utilisateur.
     */
    public function getUserHistory(int $userId, int $limit = 100): array;

    /**
     * Recherche dans les logs avec filtres.
     * @return array ['data' => [], 'total' => int]
     */
    public function search(array $filters, int $page = 1, int $perPage = 50): array;
        // filters: ['user_id', 'module', 'action', 'date_debut', 'date_fin', 'entite']
}
```

### 3.3 Comportement interne

```
CHAMPS FILTRÉS AVANT STOCKAGE (ne jamais logger en clair) :
  mot_de_passe, password, mdp, token, reset_token, api_key, vapid_private_key,
  smtp_password, sms_api_key, _csrf_token

DIFF logUpdate :
  Ne stocke que les clés dont la valeur a changé.
  Exemple : avant = {nom:'Dupont', prenom:'Ali'}, après = {nom:'Dupont', prenom:'Ahmed'}
  → diff = {avant:{prenom:'Ali'}, apres:{prenom:'Ahmed'}}

IP :
  Lue depuis $_SERVER['REMOTE_ADDR'], après détection de proxy X-Forwarded-For
  (liste blanche de proxies de confiance seulement).

WRITE-ONLY :
  AuditService ne fait que des INSERT. Jamais de UPDATE ou DELETE sur audit_logs.
  La suppression des anciens logs est faite par une commande d'administration
  séparée (pas depuis l'UI normale).
```

### 3.4 Dépendances

```
AuditService → DB (table audit_logs)
AuditService → $_SERVER['REMOTE_ADDR'] (IP)
AuditService → php://input ou tableau de données (appelant)
```

### 3.5 Modules utilisateurs

| Module | Actions auditées |
|---|---|
| Auth | login, login_failed, logout, password_reset |
| Utilisateurs | create, update, delete user ; permission_denied |
| Élèves | create, update, delete, import_csv |
| Notes | upsert note, recalcul moyennes |
| Paiements | create paiement, delete paiement |
| Paramètres | update (toute section), regenerate_vapid, regenerate_api_key, backup_create |
| RBAC | role create/update/delete, permission assign/revoke |

---

## 4. NOTIFICATIONSERVICE

### 4.1 État actuel (V1)

Le service existe et est bien conçu. Il gère :
- **3 canaux** : interne (table `notifications`), email (`EmailService`), SMS (`SmsService`)
- **4 déclencheurs métier** : `onAbsence`, `onPaiement`, `onNote`, `onAnnonce`
- **Préférences par utilisateur** : `notification_preferences` (canal activé par trigger)
- **Log complet** : `notification_logs` (statut : envoye/echoue)

### 4.2 Ajouts V2

```php
// AJOUT 1 — Canal push notifications (VAPID)
// À ajouter dans notify() après le bloc SMS :

// ── Canal push ─────────────────────────────────────────────────────────
$canPush = $prefs['push'] ?? false;
if ($canPush) {
    try {
        $pwa        = new PwaConfigService();
        $privateKey = $pwa->getVapidPrivateKeyPem();
        $publicKey  = $pwa->getVapidPublicKey();
        $subject    = ParametreService::get('pwa.vapid_subject', 'mailto:admin@ecole.local');

        $subscriptions = (new PushSubscriptionModel())->getForUser($userId);
        foreach ($subscriptions as $sub) {
            WebPush::sendPing($sub, $publicKey, $privateKey, $subject);
        }
        $this->logModel->log($userId, $trigger, 'push', $titre, $message, null, 'envoye');
    } catch (\Throwable $e) {
        $this->logModel->log($userId, $trigger, 'push', $titre, $message, null, 'echoue', $e->getMessage());
    }
}


// AJOUT 2 — Nouveau déclencheur : rappel impayé
public function onImpaye(int $eleveId, float $montant, string $fraisNom, int $joursRetard): void;
// Notifie le parent avec le montant et le retard en jours.


// AJOUT 3 — Envoi groupé par classe (pour les enseignants / bulletins)
public function notifyClasse(int $classeId, string $trigger, string $titre, string $message, string $lien = ''): void;
// Récupère tous les parents + élèves d'une classe → notifyBulk()


// AJOUT 4 — Déclencheur emploi du temps modifié
public function onEmploiDuTempsChange(int $classeId, string $detail): void;
// Notifie les enseignants + élèves de la classe concernée.
```

### 4.3 Triggers V2 complets

| Code | Événement | Destinataires |
|---|---|---|
| `note` | Notes/bulletin disponibles | parent + élève |
| `absence` | Absence ou retard signalé | parent + élève |
| `paiement` | Paiement enregistré | parent |
| `impaye` | Rappel impayé (NEW) | parent |
| `annonce` | Nouvelle annonce | selon audience |
| `emploi_du_temps` | Modification planning (NEW) | enseignants + élèves de la classe |
| `systeme` | Maintenance, mise à jour (NEW) | admin + directeur |

### 4.4 Dépendances

```
NotificationService → EmailService
NotificationService → SmsService
NotificationService → WebPush (V2 push)
NotificationService → PwaConfigService (VAPID keys en V2)
NotificationService → PushSubscriptionModel
NotificationService → NotificationModel, NotificationLogModel, NotificationPreferenceModel
NotificationService → UserModel, EleveModel
```

---

## 5. PDFSERVICE

### 5.1 Responsabilité

Générer des documents PDF côté serveur à partir de templates HTML PHP. Permet :
- Envoi de bulletins par email (sans navigateur)
- Téléchargement groupé de bulletins (classe entière)
- Reçus de paiement PDF
- Rapports PDF téléchargeables sans passer par le navigateur

### 5.2 Stratégie — sans Composer

```
V1 (conservée)
  → layout=print → window.print() dans le navigateur
  → Fonctionne pour impression manuelle, ne permet pas d'email/batch

V2 ajout — Dompdf standalone
  → https://github.com/dompdf/dompdf — téléchargé manuellement dans lib/dompdf/
  → Supporte HTML + CSS, images base64, UTF-8
  → Compatible PHP 8.2, pas de dépendances système (pas de wkhtmltopdf)
  → Autoloader manuel : require ROOT_PATH . '/lib/dompdf/autoload.inc.php'

Alternative si Dompdf trop lourd : TCPDF
  → lib/tcpdf/ — pur PHP, très stable, moins bon rendu CSS
  → Recommandé si les bulletins sont construits programmatiquement (pas HTML template)

Décision à prendre lors de l'implémentation.
PdfService abstrait le choix du moteur : le reste du code n'appelle que PdfService.
```

### 5.3 Interface publique

```php
namespace App\Services;

class PdfService
{
    /**
     * Génère un PDF à partir d'une vue PHP.
     *
     * @param string $view    Chemin de la vue (ex: 'bulletins/print')
     * @param array  $data    Données passées à la vue (même convention que render())
     * @param array  $options ['orientation'=>'P'|'L', 'format'=>'A4', 'margin'=>[10,10,10,10]]
     * @return string         Contenu PDF binaire
     */
    public function generate(string $view, array $data, array $options = []): string;

    /**
     * Génère et envoie immédiatement en téléchargement.
     * Appelle generate() puis exit après envoi des headers.
     */
    public function download(string $view, array $data, string $filename, array $options = []): never;

    /**
     * Génère et sauvegarde sur le filesystem.
     * @return string Chemin absolu du fichier créé
     */
    public function save(string $view, array $data, string $path, array $options = []): string;

    /**
     * Génère et retourne en base64 (pour attacher à un email).
     */
    public function toBase64(string $view, array $data, array $options = []): string;

    // ── Méthodes métier de haut niveau ───────────────────────────────────────

    /**
     * Génère le bulletin d'un élève.
     * Retourne le PDF binaire.
     */
    public function bulletin(int $eleveId, int $classeId, int $periodeId): string;

    /**
     * Génère tous les bulletins d'une classe en un seul PDF (multi-pages).
     */
    public function bulletinsClasse(int $classeId, int $periodeId): string;

    /**
     * Génère un reçu de paiement.
     */
    public function recuPaiement(int $paiementId): string;
}
```

### 5.4 Fonctionnement interne — rendu HTML

```
generate(view, data, options)
  1. Appeler View::render() avec layout='pdf' pour obtenir le HTML pur
     (layout pdf = pas de navbar, pas de sidebar, CSS inline/embed)
  2. Passer ce HTML à Dompdf::loadHtml()
  3. Dompdf::setPaper('A4', 'portrait')
  4. Dompdf::render()
  5. Retourner Dompdf::output()

Layout PDF spécial (resources/layouts/pdf.php) :
  → Inclut uniquement les styles CSS nécessaires (pas de Tailwind CDN)
  → CSS embarqué en <style> inline (Dompdf ne charge pas les CSS externes)
  → Police : DejaVu (incluse dans Dompdf, supporte UTF-8 + caractères arabes/latins)
```

### 5.5 Dépendances

```
PdfService → lib/dompdf/ (bibliothèque externe, à télécharger)
PdfService → Core\View (pour le rendu HTML des templates)
PdfService → NoteModel, EleveModel, PaiementModel (via méthodes métier)
```

### 5.6 Modules utilisateurs

| Module | Utilisation |
|---|---|
| Bulletins | `download()` bulletin individuel, `save()` + email pour envoi par lot |
| Comptabilité | `download()` reçu de paiement |
| Rapports | `download()` rapport analytique |
| Paramètres | `download()` facture de backup (si besoin) |

---

## 6. EXPORTSERVICE

### 6.1 Responsabilité

Centraliser tous les exports de données : CSV, Excel (XLSX simplifié via CSV UTF-8 + BOM), et déléguer le PDF à `PdfService`. Remplace la logique CSV dupliquée dans `RapportController` et `EleveController`.

### 6.2 Interface publique

```php
namespace App\Services;

class ExportService
{
    /**
     * Exporte un tableau de données en CSV et envoie en téléchargement.
     *
     * @param array  $rows       Tableau de tableaux associatifs ou d'objets
     * @param array  $columns    ['label' => 'clé_données'] — définit l'ordre et les en-têtes
     * @param string $filename   Nom du fichier sans extension (ex: 'eleves_2025-2026')
     * @param string $separator  Séparateur de champs (défaut: ';' pour compatibilité Excel FR)
     */
    public function toCsv(array $rows, array $columns, string $filename, string $separator = ';'): never;

    /**
     * Génère le contenu CSV en chaîne (sans envoi HTTP).
     * Utile pour attacher à un email ou sauvegarder sur disque.
     */
    public function buildCsv(array $rows, array $columns, string $separator = ';'): string;

    /**
     * Alias de toCsv avec Content-Type Excel.
     * Produit un CSV UTF-8 avec BOM — s'ouvre directement dans Excel.
     */
    public function toExcel(array $rows, array $columns, string $filename): never;

    /**
     * Délègue à PdfService::download().
     */
    public function toPdf(string $view, array $data, string $filename, array $options = []): never;

    // ── Exports métier pré-configurés ─────────────────────────────────────────

    public function exportEleves(array $filters = []): never;
    // Colonnes : Matricule, Nom, Prénom, Sexe, Date naissance, Classe, Statut, Parent

    public function exportProfesseurs(): never;
    // Colonnes : Matricule, Nom, Prénom, Grade, Matières, Date recrutement

    public function exportNotes(int $classeId, int $periodeId): never;
    // Colonnes : Rang, Nom, Prénom, [matière1], [matière2]..., Moyenne, Mention

    public function exportAbsences(array $filters = []): never;
    // Colonnes : Date, Élève, Classe, Type, Justifié, Motif

    public function exportPaiements(string $annee, array $filters = []): never;
    // Colonnes : Date, Élève, Classe, Type frais, Montant, Mode paiement, Ref

    public function exportRapport(string $type, array $params = []): never;
    // Consolide les exports RapportController::exportExcel() existants

    /**
     * Enregistre un log d'export dans audit_logs.
     */
    private function logExport(string $type, string $filename, int $rows): void;
}
```

### 6.3 Convention de la colonne `$columns`

```php
// Format simple : label => clé
$columns = [
    'Matricule'    => 'matricule',
    'Nom'          => 'nom',
    'Prénom'       => 'prenom',
    'Classe'       => 'classe_nom',  // clé dans l'objet/tableau retourné par la requête
];

// Format avec transformateur : label => ['key' => ..., 'format' => callable]
$columns = [
    'Statut' => ['key' => 'actif', 'format' => fn($v) => $v ? 'Actif' : 'Inactif'],
    'Date'   => ['key' => 'date', 'format' => fn($v) => date('d/m/Y', strtotime($v))],
];
```

### 6.4 Dépendances

```
ExportService → EleveModel, ProfesseurModel, NoteModel, AbsenceModel, PaiementModel
ExportService → PdfService (pour toPdf)
ExportService → AuditService (log export)
ExportService → ParametreService (devise.symbole pour formatage monétaire)
```

### 6.5 Modules utilisateurs

| Module | Méthode appelée |
|---|---|
| Élèves | `exportEleves()` |
| Professeurs | `exportProfesseurs()` |
| Notes/Bulletins | `exportNotes()` |
| Absences | `exportAbsences()` |
| Comptabilité | `exportPaiements()` |
| Rapports | `exportRapport()` |

---

## 7. SEARCHSERVICE

### 7.1 Responsabilité

Recherche globale textuelle à travers plusieurs entités du système depuis une seule barre de recherche. Retourne des résultats groupés par type avec lien direct vers la fiche.

### 7.2 Interface publique

```php
namespace App\Services;

class SearchService
{
    // Modules activables dans la recherche
    public const SEARCHABLE = [
        'eleves'      => ['model' => EleveModel::class,      'permission' => 'eleves.view'],
        'professeurs' => ['model' => ProfesseurModel::class, 'permission' => 'professeurs.view'],
        'classes'     => ['model' => ClasseModel::class,     'permission' => 'classes.view'],
        'paiements'   => ['model' => PaiementModel::class,   'permission' => 'paiements.view'],
        'utilisateurs'=> ['model' => UserModel::class,       'permission' => 'users.view'],
        'annonces'    => ['model' => AnnonceModel::class,    'permission' => 'annonces.view'],
    ];

    /**
     * Recherche globale dans tous les modules accessibles à l'utilisateur.
     *
     * @param string $query      Terme recherché (min 2 caractères)
     * @param array  $userPerms  Permissions de l'utilisateur connecté (depuis session)
     * @param array  $modules    Modules à inclure (null = tous les accessibles)
     * @param int    $limit      Max résultats par module (défaut: 5)
     * @return array [
     *   'eleves'      => [['id'=>1, 'label'=>'Dupont Ali', 'url'=>'/eleves/1', 'meta'=>'6ème A'], ...],
     *   'professeurs' => [...],
     *   'total'       => 12,
     *   'query'       => 'dupont',
     * ]
     */
    public function search(string $query, array $userPerms, array $modules = null, int $limit = 5): array;

    /**
     * Recherche dans un seul module (pour les suggestions autocomplete).
     * @return array Tableau plat d'objets ['id', 'label', 'url', 'meta']
     */
    public function searchModule(string $module, string $query, int $limit = 10): array;

    /**
     * Suggestions rapides pour autocomplete (GET /api/search?q=).
     * Retourne JSON directement — 150ms max.
     */
    public function autocomplete(string $query, array $userPerms, int $limit = 8): array;
}
```

### 7.3 Implémentation des requêtes

```
STRATÉGIE : MySQL LIKE avec index partiel (pas de FULLTEXT pour rester compatible)

Par module :
  eleves :
    SELECT id, CONCAT(prenom,' ',nom) AS label, matricule AS meta, ...
    WHERE actif=1 AND (nom LIKE ? OR prenom LIKE ? OR matricule LIKE ?)
    ORDER BY nom LIMIT ?
    Paramètre : '%{query}%' pour chaque champ

  professeurs :
    WHERE (nom LIKE ? OR prenom LIKE ? OR specialite LIKE ?)

  classes :
    WHERE (nom LIKE ? OR niveau LIKE ?)

  paiements :
    JOIN eleves ON ... WHERE (eleves.nom LIKE ? OR eleves.matricule LIKE ?)

  utilisateurs :
    WHERE (nom LIKE ? OR prenom LIKE ? OR email LIKE ?) AND role != 'eleve'

  annonces :
    WHERE (titre LIKE ? OR contenu LIKE ?) AND actif = 1

FORMAT résultat uniforme :
  ['id' => int, 'label' => string, 'url' => string, 'meta' => string|null]
  url = BASE_URL . '/{module}/{id}'
  meta = info secondaire (matricule, classe, email, montant...)
```

### 7.4 Protection contre les abus

```
- Longueur minimum : 2 caractères (retourne [] si trop court)
- Longueur maximum : 100 caractères (tronqué avant requête)
- Paramètres PDO bindés (jamais de concaténation directe)
- Rate limiting : max 30 appels/minute par session (géré par SearchService::rateLimitCheck)
- Aucun résultat d'un module si l'utilisateur n'a pas la permission correspondante
```

### 7.5 Dépendances

```
SearchService → EleveModel, ProfesseurModel, ClasseModel, PaiementModel, UserModel, AnnonceModel
SearchService → Session (pour vérifier les permissions avant d'inclure un module)
SearchService → ModuleService (ne cherche que dans les modules actifs)
```

### 7.6 Modules utilisateurs

| Module | Utilisation |
|---|---|
| Dashboard | Barre de recherche globale header |
| Admin | Recherche rapide depuis n'importe quelle page |
| API | GET /api/search?q= pour autocomplete |

---

## 8. UPLOADSERVICE

### 8.1 Responsabilité

Valider, traiter et stocker les fichiers uploadés de façon uniforme. Remplace les 4 implémentations dupliquées dans `AuthController`, `ProfesseurController`, `EleveController`, `AbsenceController`.

### 8.2 Interface publique

```php
namespace App\Services;

class UploadService
{
    // Types d'upload prédéfinis avec règles associées
    public const TYPES = [
        'avatar' => [
            'dir'        => 'storage/uploads/avatars/',
            'mimes'      => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'max_size'   => 2097152,   // 2 Mo
            'resize'     => [200, 200], // largeur, hauteur max
            'public'     => false,      // accès via route, pas URL directe
        ],
        'photo_eleve' => [
            'dir'        => 'storage/uploads/eleves/',
            'mimes'      => ['image/jpeg', 'image/png', 'image/webp'],
            'max_size'   => 2097152,
            'resize'     => [300, 400],
            'public'     => false,
        ],
        'photo_professeur' => [
            'dir'        => 'storage/uploads/professeurs/',
            'mimes'      => ['image/jpeg', 'image/png', 'image/webp'],
            'max_size'   => 2097152,
            'resize'     => [300, 400],
            'public'     => false,
        ],
        'logo_etablissement' => [
            'dir'        => 'storage/uploads/etablissement/',
            'mimes'      => ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'],
            'max_size'   => 5242880,   // 5 Mo
            'resize'     => [800, 800],
            'public'     => false,
        ],
        'justification' => [
            'dir'        => 'storage/uploads/justifications/',
            'mimes'      => ['image/jpeg', 'image/png', 'application/pdf'],
            'max_size'   => 5242880,
            'resize'     => null,       // pas de redimensionnement pour les PDF
            'public'     => false,
        ],
        'import_csv' => [
            'dir'        => 'storage/uploads/imports/',
            'mimes'      => ['text/csv', 'text/plain', 'application/vnd.ms-excel'],
            'max_size'   => 10485760,  // 10 Mo
            'resize'     => null,
            'public'     => false,
        ],
    ];

    /**
     * Valide et stocke un fichier uploadé.
     *
     * @param array  $file   $_FILES['champ'] — tableau PHP standard
     * @param string $type   Clé dans self::TYPES
     * @param string $prefix Préfixe du nom de fichier final (ex: 'eleve_42_')
     * @return string        Chemin relatif depuis ROOT_PATH (ex: 'storage/uploads/eleves/eleve_42_abc123.jpg')
     * @throws \RuntimeException Si validation échoue
     */
    public function upload(array $file, string $type, string $prefix = ''): string;

    /**
     * Supprime un fichier uploadé.
     * Vérifie que le chemin est dans storage/ (protection path traversal).
     */
    public function delete(string $relativePath): bool;

    /**
     * Retourne l'URL publique d'un fichier uploadé via la route sécurisée.
     * Exemple : '/uploads/serve/avatars/abc123.jpg'
     */
    public function url(string $relativePath): string;

    /**
     * Valide uniquement (sans stocker) — pour afficher les erreurs avant traitement.
     * @return array ['ok' => bool, 'errors' => string[]]
     */
    public function validate(array $file, string $type): array;
}
```

### 8.3 Comportement interne

```
upload($file, $type, $prefix)
  1. Vérifier UPLOAD_ERR_OK
  2. Vérifier taille <= max_size
  3. Vérifier MIME réel via mime_content_type($file['tmp_name'])
     (JAMAIS se fier à $file['type'] fourni par le navigateur)
  4. Générer nom unique : $prefix . bin2hex(random_bytes(8)) . '.' . extension
  5. Créer le répertoire cible si inexistant (mkdir 0755 récursif)
  6. move_uploaded_file() vers la destination
  7. Si resize != null et fichier image : redimensionner avec GD (imagecopyresampled)
     → Conserver ratio, remplir en blanc si portrait/paysage
  8. Retourner le chemin relatif

url($relativePath)
  → BASE_URL . '/uploads/serve/' . basename(dirname($relativePath)) . '/' . basename($relativePath)
  Route correspondante : GET /uploads/serve/{type}/{filename}
  → Contrôleur vérifie auth + permission avant d'envoyer le fichier (readfile)

Redimensionnement GD :
  - Extension PHP gd doit être activée (standard WAMP)
  - Supporte JPEG, PNG, GIF, WEBP
  - Pour SVG et PDF : pas de redimensionnement, stockage direct
```

### 8.4 Dépendances

```
UploadService → PHP extensions : fileinfo, gd
UploadService → ROOT_PATH (constante définie dans index.php)
UploadService → BASE_URL (constante)
UploadService → Aucun modèle directement
```

### 8.5 Modules utilisateurs

| Module | Type d'upload | Type clé |
|---|---|---|
| Auth (profil) | Photo de profil | `avatar` |
| Élèves | Photo élève | `photo_eleve` |
| Professeurs | Photo professeur | `photo_professeur` |
| Paramètres (établissement) | Logo | `logo_etablissement` |
| Absences (justification) | Document justificatif | `justification` |
| Élèves (import) | Fichier CSV import | `import_csv` |

---

## 9. STATISTICSSERVICE

### 9.1 Responsabilité

Fournir des statistiques agrégées et transversales à tous les modules qui en ont besoin (Dashboard, Espaces parent/élève, Rapports). Centralise la logique de calcul dispersée dans `RapportModel` et `HomeController::getStats()`.

La différence avec `RapportModel` :
- `RapportModel` : requêtes spécifiques aux vues Rapports, liées à l'affichage des graphes
- `StatisticsService` : indicateurs simples, rapides, réutilisables partout, avec cache

### 9.2 Interface publique

```php
namespace App\Services;

class StatisticsService
{
    /**
     * KPIs globaux — Dashboard administrateur
     * Résultat mis en cache 5 minutes (cache mémoire dans la session ou APC si dispo)
     */
    public function getGlobalKpis(int $anneeId = null): array;
    // Retourne: ['nb_eleves', 'nb_classes', 'nb_professeurs', 'taux_presence',
    //            'recettes_mois', 'impayes', 'taux_recouvrement', 'annee_active']

    /**
     * KPIs pour le tableau de bord d'un enseignant
     */
    public function getTeacherKpis(int $professeurId, int $anneeId = null): array;
    // Retourne: ['nb_classes', 'nb_eleves', 'nb_matieres', 'notes_saisies_pct',
    //            'absences_this_week', 'prochains_controles']

    /**
     * KPIs pour l'espace parent
     */
    public function getParentKpis(int $parentId, int $anneeId = null): array;
    // Retourne: ['nb_enfants', 'enfants' => [['nom', 'classe', 'moy_gen',
    //            'rang', 'nb_absences', 'montant_du']]]

    /**
     * KPIs pour l'espace élève
     */
    public function getStudentKpis(int $eleveId, int $periodeId = null): array;
    // Retourne: ['moy_generale', 'rang', 'mention', 'nb_absences',
    //            'nb_retards', 'matieres' => [['nom', 'moy', 'coef']]]

    /**
     * Statistiques financières pour le comptable / directeur
     */
    public function getFinanceKpis(string $annee = null): array;
    // Retourne: ['recettes', 'depenses', 'solde', 'impayes', 'taux_recouvrement',
    //            'par_mois' => [...]]

    /**
     * Taux de présence global ou par classe
     */
    public function getPresenceRate(int $classeId = null, string $dateDebut = null, string $dateFin = null): float;

    /**
     * Distribution des mentions dans une période
     */
    public function getMentionsDistribution(int $periodeId, int $classeId = null): array;
    // Retourne: [['mention'=>'Très Bien', 'count'=>12, 'pct'=>15.4], ...]

    /**
     * Progression mensuelle des paiements sur l'année
     */
    public function getMonthlyRevenue(string $annee): array;
    // Retourne: [['mois'=>'Septembre', 'recettes'=>450000, 'depenses'=>120000], ...]

    // ── Cache ────────────────────────────────────────────────────────────────

    /**
     * Invalide tout le cache des statistiques (appelé après import ou modification massive)
     */
    public function clearCache(): void;
}
```

### 9.3 Stratégie de cache

```
CONTEXTE : pas de Redis, pas d'APCu en WAMP de base.
SOLUTION : cache dans la session PHP avec TTL.

Implémentation interne :
  private function cached(string $key, int $ttl, callable $fn): mixed
  {
      $cacheKey = '_stats_cache_' . $key;
      $cached   = $_SESSION[$cacheKey] ?? null;

      if ($cached && (time() - $cached['ts']) < $ttl) {
          return $cached['data'];
      }

      $data = $fn();
      $_SESSION[$cacheKey] = ['data' => $data, 'ts' => time()];
      return $data;
  }

TTL par méthode :
  getGlobalKpis      → 300s  (5 min)
  getTeacherKpis     → 120s  (2 min)
  getParentKpis      → 180s  (3 min)
  getStudentKpis     → 180s  (3 min)
  getFinanceKpis     → 300s  (5 min)
  getPresenceRate    → 60s   (1 min — changements fréquents)
  getMentionsDistrib → 300s  (5 min)
  getMonthlyRevenue  → 600s  (10 min)

clearCache() : supprime toutes les clés $_SESSION préfixées par '_stats_cache_'
```

### 9.4 Dépendances

```
StatisticsService → EleveModel, ClasseModel, ProfesseurModel
StatisticsService → NoteModel (moyennes, classement)
StatisticsService → AbsenceModel (taux présence)
StatisticsService → PaiementModel, DepenseModel (finance)
StatisticsService → ParametreService (annee_scolaire.active_id, devise.*)
StatisticsService → $_SESSION (cache)
```

### 9.5 Modules utilisateurs

| Module | Méthodes utilisées |
|---|---|
| Dashboard (admin/directeur) | `getGlobalKpis()`, `getFinanceKpis()`, `getPresenceRate()` |
| Dashboard (enseignant) | `getTeacherKpis()` |
| Espace parent | `getParentKpis()` |
| Espace élève | `getStudentKpis()` |
| Rapports | Toutes (remplace `RapportModel` progressivement) |
| Comptabilité | `getFinanceKpis()`, `getMonthlyRevenue()` |

---

## 10. TABLE SQL : `audit_logs`

```sql
audit_logs (
  id          BIGINT UNSIGNED PK AUTO_INCREMENT,     -- BIGINT : volume potentiellement élevé
  user_id     INT UNSIGNED NULL,                     -- NULL si action système ou login_failed
  action      VARCHAR(50) NOT NULL,                  -- 'create'|'update'|'delete'|'login'|'logout'
                                                     -- |'export'|'print'|'login_failed'
                                                     -- |'permission_denied'|'import'
  module      VARCHAR(50) NOT NULL,                  -- 'eleves'|'notes'|'paiements'|'parametres'...
  entite      VARCHAR(50) NULL,                      -- Nom de la table affectée
  entite_id   INT UNSIGNED NULL,                     -- ID de l'enregistrement affecté
  avant       JSON NULL,                             -- État avant (pour update/delete) — secrets exclus
  apres       JSON NULL,                             -- État après (pour create/update) — secrets exclus
  ip          VARCHAR(45) NULL,                      -- IPv4 ou IPv6
  user_agent  VARCHAR(500) NULL,                     -- Navigateur (tronqué à 500 chars)
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_al_user    (user_id),
  INDEX idx_al_action  (action),
  INDEX idx_al_module  (module),
  INDEX idx_al_entite  (entite, entite_id),
  INDEX idx_al_date    (created_at),
  CONSTRAINT fk_al_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

**Note rétention :** La table peut grossir vite (1 action/s = 30M lignes/an). Prévoir une commande de purge dans le module Paramètres → Système → Purger les logs anciens (> N jours).

---

## 11. MIGRATION SQL

### S001 — Créer la table audit_logs

```sql
-- ════════════════════════════════════════════════════════════════════════
-- S001 : Création de la table audit_logs (couche Shared Services)
-- Prérequis : ecole_app.sql + M001 (schema_migrations)
-- ════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED    NULL,
    `action`     VARCHAR(50)     NOT NULL,
    `module`     VARCHAR(50)     NOT NULL,
    `entite`     VARCHAR(50)     NULL,
    `entite_id`  INT UNSIGNED    NULL,
    `avant`      JSON            NULL,
    `apres`      JSON            NULL,
    `ip`         VARCHAR(45)     NULL,
    `user_agent` VARCHAR(500)    NULL,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_al_user`   (`user_id`),
    INDEX `idx_al_action` (`action`),
    INDEX `idx_al_module` (`module`),
    INDEX `idx_al_entite` (`entite`, `entite_id`),
    INDEX `idx_al_date`   (`created_at`),
    CONSTRAINT `fk_al_user`
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('S001', 'Création table audit_logs — couche Shared Services');
```

### S002 — Ajouter les permissions Shared dans RBAC

```sql
-- ════════════════════════════════════════════════════════════════════════
-- S002 : Permissions pour l'accès aux logs d'audit
-- Prérequis : R001, R002, R003, S001
-- ════════════════════════════════════════════════════════════════════════

USE `ecole_app`;

INSERT IGNORE INTO `permissions` (`code`, `libelle`, `module`, `action`, `scope`) VALUES
('audit.view',   'Consulter les logs d\'audit',      'audit', 'view',   'global'),
('audit.export', 'Exporter les logs d\'audit',       'audit', 'export', 'global');

-- Admin et directeur peuvent consulter les logs
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.slug IN ('admin', 'directeur') AND p.code = 'audit.view';

-- Admin seul peut exporter
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.slug = 'admin' AND p.code = 'audit.export';

INSERT IGNORE INTO `schema_migrations` (`version`, `description`) VALUES
('S002', 'Permissions audit.view et audit.export dans RBAC V2');
```

---

## 12. MATRICE DES DÉPENDANCES

### 12.1 Dépendances entre services Shared

```
                  ┌──────────┬────────┬────────┬─────────┬────────┬────────┬───────────┐
                  │  Audit   │ Notif  │  PDF   │ Export  │ Search │ Upload │   Stats   │
  ──────────────  ├──────────┼────────┼────────┼─────────┼────────┼────────┼───────────┤
  AuditService   │    ─     │        │        │         │        │        │           │
  NotifService   │          │   ─    │        │         │        │        │           │
  PdfService     │          │        │   ─    │         │        │        │           │
  ExportService  │    ←     │        │   ←    │    ─    │        │        │           │
  SearchService  │          │        │        │         │   ─    │        │           │
  UploadService  │          │        │        │         │        │   ─    │           │
  StatisticsService│        │        │        │         │        │        │     ─     │
  ──────────────  ├──────────┼────────┼────────┼─────────┼────────┼────────┼───────────┤
  ParametreService│          │   ←    │        │    ←    │        │        │     ←     │
  EmailService   │          │   ←    │        │         │        │        │           │
  SmsService     │          │   ←    │        │         │        │        │           │
  WebPush        │          │   ←    │        │         │        │        │           │
  ──────────────  └──────────┴────────┴────────┴─────────┴────────┴────────┴───────────┘

  ← = colonne dépend de la ligne
```

### 12.2 Services par module consommateur

```
MODULE              AUDIT  NOTIF  PDF   EXPORT  SEARCH  UPLOAD  STATS
Auth                  ✓              
Élèves               ✓             ✓     ✓      ✓(auto) ✓
Professeurs          ✓             ✓     ✓      ✓(auto) ✓
Classes              ✓                          ✓(auto)
Notes/Bulletins      ✓      ✓      ✓     ✓
Absences             ✓      ✓      ✓     ✓              ✓       
Comptabilité         ✓      ✓      ✓     ✓                       ✓
Emploi du temps      ✓      ✓
Rapports                           ✓     ✓                       ✓
Dashboard            ✓                   ✓       ✓               ✓
Paramètres           ✓             ✓
Utilisateurs         ✓                          ✓(auto) ✓
Annonces             ✓      ✓
Espaces parent/élève        ✓      ✓                             ✓
```

### 12.3 Ordre d'implémentation recommandé

```
1. UploadService    — Prérequis de plusieurs autres. Pas de dépendances internes.
2. AuditService     — Prérequis de ExportService. S001 à exécuter en premier.
3. StatisticsService— Réutilise modèles existants. Améliore Dashboard immédiatement.
4. ExportService    — Remplace logique inline dans RapportController + EleveController.
5. NotificationService (push VAPID) — Extension de l'existant. Prérequis : PARAMETRES_V2.
6. SearchService    — Nouvelle fonctionnalité. Aucun prérequis bloquant.
7. PdfService       — Nécessite de choisir et télécharger Dompdf/TCPDF avant implémentation.
```

---

*SHARED_SERVICES.md — SCOLARIS | Conception complète. 1 table SQL, 2 migrations. Pas d'implémentation. En attente de validation.*
