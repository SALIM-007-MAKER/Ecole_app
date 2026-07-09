# EVENTS V2 — SCOLARIS
## Système d'événements inter-modules

> Étape 6 de la migration SCOLARIS V2.
> **Aucune implémentation.** Conception uniquement : Event, Listener, Handler, registration.
> Date : 2026-06-29

---

## TABLE DES MATIÈRES

1. [Contexte et problème](#1-contexte-et-problème)
2. [Architecture du système](#2-architecture-du-système)
3. [Composants Core](#3-composants-core)
4. [EleveCreated](#4-elevecreated)
5. [PaiementValide](#5-paiementvalide)
6. [NoteAjoutee](#6-noteajoutee)
7. [AbsenceCreee](#7-absencecreee)
8. [DocumentGenere](#8-documentgenere)
9. [Handlers partagés](#9-handlers-partagés)
10. [Registration — config/events.php](#10-registration--configeventphp)
11. [Intégration dans Application::run()](#11-intégration-dans-applicationrun)
12. [Migration avant/après dans les contrôleurs](#12-migration-avantaprès-dans-les-contrôleurs)
13. [Catalogue complet des événements](#13-catalogue-complet-des-événements)
14. [Aucune migration SQL](#14-aucune-migration-sql)

---

## 1. CONTEXTE ET PROBLÈME

### 1.1 Couplage actuel — diagnostic du code

```
AbsenceController::savePointage()
  ↓ insert absences en DB
  ↓ try {
      new NotificationService()->onAbsence()   ← couplage direct
    } catch (\Throwable) {}                    ← erreurs silencieuses

PaiementController::store()
  ↓ insert paiement en DB
  ↓ try {
      new NotificationService()->onPaiement()  ← couplage direct
    } catch (\Throwable) {}

AnnonceController::store()
  ↓ insert annonce en DB
  ↓ new NotificationService()->onAnnonce()     ← couplage direct

EleveController::store()
  ↓ insert eleve en DB
  ↓ (rien — pas d'audit, pas de notification)  ← lacune

NoteController (saisie de notes)
  ↓ upsert note en DB
  ↓ (rien — pas d'audit, pas de notification)  ← lacune

BulletinController::printBulletin()
  ↓ render HTML
  ↓ (rien — génération non tracée)             ← lacune
```

### 1.2 Problèmes identifiés

| # | Problème | Conséquence |
|---|---|---|
| E1 | Controller connaît `NotificationService` directement | Ajouter AuditService implique de toucher chaque contrôleur |
| E2 | try/catch global silencieux | Un listener défaillant est invisible — impossible à déboguer |
| E3 | Plusieurs actions sans aucun effet de bord | Aucune trace de création d'élève, de saisie de note |
| E4 | Effets de bord dans la requête HTTP principale | Si `NotificationService::send()` est lent (SMTP), la réponse HTTP attend |
| E5 | Pas d'extensibilité | Ajouter export automatique après paiement = modifier `PaiementController` |

### 1.3 Ce que le système d'événements résout

```
AVANT                              APRÈS
─────────────────────────────      ─────────────────────────────────────────
Controller → NotifService (direct) Controller → EventDispatcher::dispatch()
                                              ↘ AuditHandler::handle()
                                              ↘ NotificationHandler::handle()
                                              ↘ StatsCacheHandler::handle()
                                              ↘ (futur: ReceiptHandler, WebhookHandler...)

Ajouter un effet de bord = créer un Handler + l'enregistrer dans config/events.php
Ne jamais toucher le contrôleur existant.
```

---

## 2. ARCHITECTURE DU SYSTÈME

### 2.1 Vue d'ensemble

```
Core/
  Event.php           ← Classe abstraite parente de tous les événements
  Listener.php        ← Interface que tous les handlers implémentent
  EventDispatcher.php ← Registre + dispatch (statique, sans état HTTP)

app/
  Events/
    EleveCreated.php
    PaiementValide.php
    NoteAjoutee.php
    AbsenceCreee.php
    DocumentGenere.php
    UserLoggedIn.php      ← (extension future)
    AnnoncePubliee.php    ← (extension future)
    BulletinPublie.php    ← (extension future)

  Listeners/
    AuditHandler.php              ← Écoute TOUS les événements
    NotificationHandler.php       ← Écoute Paiement, Absence, Note, Annonce
    StatsCacheHandler.php         ← Écoute Eleve, Absence (invalidation cache)
    ReceiptGeneratorHandler.php   ← Écoute PaiementValide (futur)

config/
  events.php          ← Mapping EventClass → [Handler instances]
```

### 2.2 Flux d'un événement

```
1. Action métier se produit dans un contrôleur
   EleveController::store() → $id = $this->eleveModel->insert($data)

2. Controller crée et dispatche l'événement
   EventDispatcher::dispatch(new EleveCreated(
       eleveId: $id, nom: $data['nom'], prenom: $data['prenom'], ...
   ))

3. EventDispatcher itère les listeners enregistrés pour EleveCreated
   foreach (AuditHandler, StatsCacheHandler) as $handler :

4. Chaque handler reçoit l'événement et agit indépendamment
   AuditHandler::handle($event) → AuditService::logCreate(...)
   StatsCacheHandler::handle($event) → StatisticsService::clearCache()

5. Si un handler lève une exception :
   → L'exception est attrapée et loggée dans error_log()
   → Les handlers suivants continuent quand même
   → La requête HTTP n'est JAMAIS interrompue par un handler
```

### 2.3 Principe de dispatch synchrone

```
V2 : SYNCHRONE — dans la même requête HTTP
  Avantages : simple, pas de worker, pas de queue, pas de Redis
  Contraintes : les handlers doivent être rapides (< 200ms idéalement)

Pour les handlers potentiellement lents (SMTP, SMS) :
  Option A (V2) : timeout court côté EmailService/SmsService — acceptable en prod
  Option B (V3) : file d'attente légère (table `jobs` + cron PHP) pour les canaux lents
  → L'interface Listener reste identique. Seul le EventDispatcher change pour V3.

Règle V2 : un handler ne fait JAMAIS d'opération bloquante > 5 secondes.
```

---

## 3. COMPOSANTS CORE

### 3.1 `Core\Event` — Classe abstraite parente

```php
namespace Core;

abstract class Event
{
    private float $firedAt;

    public function __construct()
    {
        $this->firedAt = microtime(true);
    }

    /**
     * Nom court de l'événement — utilisé dans les logs d'audit.
     * Par défaut : nom de la classe sans le namespace.
     */
    public function getName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    /**
     * Timestamp de création de l'événement.
     */
    public function getFiredAt(): float
    {
        return $this->firedAt;
    }

    /**
     * Représentation tableau du payload — pour AuditService.
     * Chaque Event concret doit surcharger cette méthode.
     */
    abstract public function toArray(): array;
}
```

---

### 3.2 `Core\Listener` — Interface des handlers

```php
namespace Core;

interface Listener
{
    /**
     * Traite l'événement reçu.
     * Ne doit JAMAIS lancer d'exception vers l'appelant.
     * Gérer ses propres erreurs en interne (log, graceful degradation).
     */
    public function handle(Event $event): void;
}
```

---

### 3.3 `Core\EventDispatcher` — Registre et dispatch

```php
namespace Core;

class EventDispatcher
{
    /**
     * Registre : ['App\Events\EleveCreated' => [Listener, Listener, ...]]
     */
    private static array $listeners = [];

    /**
     * Enregistre un listener pour un événement.
     * Appelé dans config/events.php via Application::run().
     */
    public static function listen(string $eventClass, Listener $listener): void
    {
        self::$listeners[$eventClass][] = $listener;
    }

    /**
     * Dispatche un événement vers tous ses listeners.
     * Isolé : une exception dans un listener n'interrompt pas les suivants
     * et ne fait jamais planter la requête HTTP.
     */
    public static function dispatch(Event $event): void
    {
        $eventClass = get_class($event);

        foreach (self::$listeners[$eventClass] ?? [] as $listener) {
            try {
                $listener->handle($event);
            } catch (\Throwable $e) {
                error_log(sprintf(
                    '[EventDispatcher] %s → %s échoué : %s (dans %s:%d)',
                    $event->getName(),
                    get_class($listener),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                ));
                // Continuer vers le listener suivant
            }
        }
    }

    /**
     * Réinitialise le registre (utile dans les tests).
     */
    public static function forget(string $eventClass = null): void
    {
        if ($eventClass) {
            unset(self::$listeners[$eventClass]);
        } else {
            self::$listeners = [];
        }
    }

    /**
     * Liste les listeners enregistrés (debug/audit).
     */
    public static function getListeners(string $eventClass): array
    {
        return self::$listeners[$eventClass] ?? [];
    }
}
```

---

## 4. ELEVECREATED

### 4.1 Événement

```php
namespace App\Events;

use Core\Event;

class EleveCreated extends Event
{
    public function __construct(
        public readonly int    $eleveId,
        public readonly string $nom,
        public readonly string $prenom,
        public readonly string $matricule,
        public readonly int    $classeId,
        public readonly int    $createdById,      // users.id de l'auteur
        public readonly bool   $fromCsvImport = false,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'eleve_id'       => $this->eleveId,
            'nom'            => $this->nom,
            'prenom'         => $this->prenom,
            'matricule'      => $this->matricule,
            'classe_id'      => $this->classeId,
            'from_csv_import'=> $this->fromCsvImport,
        ];
    }
}
```

### 4.2 Quand dispatcher

```
EleveController::store()        → après $id = $this->eleveModel->insert(...)
EleveController::importCsv()    → après chaque insertion réussie (fromCsvImport: true)
```

### 4.3 Handlers enregistrés

| Handler | Action déclenchée |
|---|---|
| `AuditHandler` | `AuditService::logCreate($createdById, 'eleves', 'eleves', $eleveId, $data)` |
| `StatsCacheHandler` | `StatisticsService::clearCache()` — nb_eleves change |

### 4.4 Handlers non enregistrés (V2) — pourquoi

| Handler potentiel | Raison de l'exclusion V2 |
|---|---|
| NotificationHandler | La création d'élève n'est pas un événement notifié au parent (l'inscription précède la création du compte parent) |
| WelcomeEmailHandler | L'email de bienvenue est envoyé lors de la création du compte `users`, pas de l'élève |

---

## 5. PAIEMENTVALIDE

### 5.1 Événement

```php
namespace App\Events;

use Core\Event;

class PaiementValide extends Event
{
    public function __construct(
        public readonly int    $paiementId,
        public readonly int    $eleveId,
        public readonly float  $montant,
        public readonly string $modePaiement,    // 'especes'|'cheque'|'virement'|'mobile'
        public readonly string $fraisNom,        // nom du type de frais (peut être vide)
        public readonly int    $fraisEleveId,    // frais_eleves.id (0 si non lié)
        public readonly int    $encaisseParId,   // users.id du caissier
        public readonly string $reference = '',
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'paiement_id'    => $this->paiementId,
            'eleve_id'       => $this->eleveId,
            'montant'        => $this->montant,
            'mode_paiement'  => $this->modePaiement,
            'frais_nom'      => $this->fraisNom,
            'frais_eleve_id' => $this->fraisEleveId,
            'reference'      => $this->reference,
        ];
    }
}
```

### 5.2 Quand dispatcher

```
PaiementController::store() → après insert + recalcul statut frais_eleve
```

### 5.3 Handlers enregistrés

| Handler | Action déclenchée |
|---|---|
| `AuditHandler` | `AuditService::logCreate($encaisseParId, 'comptabilite', 'paiements', $paiementId, payload)` |
| `NotificationHandler` | `NotificationService::onPaiement($eleveId, $montant, $fraisNom)` → notifie le parent |

### 5.4 Extension future

```
ReceiptGeneratorHandler (V3)
  → PdfService::recuPaiement($paiementId)
  → sauvegarder le PDF dans storage/receipts/
  → email au parent avec le PDF en pièce jointe
```

---

## 6. NOTEAJOUTEE

### 6.1 Événement

```php
namespace App\Events;

use Core\Event;

class NoteAjoutee extends Event
{
    public function __construct(
        public readonly int    $controleId,
        public readonly int    $eleveId,
        public readonly int    $classeId,
        public readonly int    $periodeId,
        public readonly int    $matiereId,
        public readonly float  $valeur,           // note brute (ex: 14.5)
        public readonly float  $moyenneMatiere,   // moyenne recalculée pour la matière
        public readonly float  $moyenneGenerale,  // moyenne générale recalculée
        public readonly int    $saisieParId,      // users.id du professeur
        public readonly bool   $periodePubliee = false,  // true = bulletins accessibles
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'controle_id'      => $this->controleId,
            'eleve_id'         => $this->eleveId,
            'classe_id'        => $this->classeId,
            'periode_id'       => $this->periodeId,
            'matiere_id'       => $this->matiereId,
            'valeur'           => $this->valeur,
            'moyenne_matiere'  => $this->moyenneMatiere,
            'moyenne_generale' => $this->moyenneGenerale,
            'periode_publiee'  => $this->periodePubliee,
        ];
    }
}
```

### 6.2 Quand dispatcher

```
NoteController::store()   → après NoteModel::upsert() + recalcul moyennes
NoteController::update()  → idem
NoteController::import()  → après chaque note importée en lot (batch)

Note : pour l'import en lot (toute une classe), dispatcher UNE seule fois
       un événement NoteAjoutee par élève, pas pour chaque contrôle individuel.
```

### 6.3 Handlers enregistrés

| Handler | Condition d'activation | Action déclenchée |
|---|---|---|
| `AuditHandler` | Toujours | `AuditService::logCreate(saisieParId, 'notes', 'notes', controleId, payload)` |
| `NotificationHandler` | Seulement si `periodePubliee = true` | `NotificationService::onNote($eleveId, $periodeNom, $moyenneGenerale)` |

### 6.4 Règle sur la notification

```
La notification parent/élève N'EST PAS envoyée à chaque saisie de note.
Elle est envoyée uniquement quand la période est clôturée et publiée.

Deux approches possibles :
  A) NoteAjoutee contient periodePubliee = false → NotificationHandler ne fait rien
     + Séparé : événement PeriodePubliee (futur) déclenche la notification groupée

  B) NotificationHandler vérifie lui-même dans DB si la période est publiée
     → Moins propre (query dans un handler)

Choix V2 : Approche A — périodePubliee est passé dans l'événement,
           NotificationHandler vérifie `if (!$event->periodePubliee) return;`
```

---

## 7. ABSENCECREEE

### 7.1 Événement

```php
namespace App\Events;

use Core\Event;

class AbsenceCreee extends Event
{
    public const STATUTS = ['present', 'absent', 'retard', 'excuse'];

    public function __construct(
        public readonly int    $eleveId,
        public readonly string $date,       // 'YYYY-MM-DD'
        public readonly string $statut,     // 'absent' | 'retard' | 'present' | 'excuse'
        public readonly string $session,    // 'matin' | 'apres_midi' | 'journee'
        public readonly int    $classeId,
        public readonly int    $saisieParId,
        public readonly string $motif = '',
        public readonly int    $absenceId = 0,  // absences.id (0 si pointage groupé)
    ) {
        parent::__construct();
    }

    public function isAbsenceOuRetard(): bool
    {
        return in_array($this->statut, ['absent', 'retard'], true);
    }

    public function toArray(): array
    {
        return [
            'eleve_id'    => $this->eleveId,
            'date'        => $this->date,
            'statut'      => $this->statut,
            'session'     => $this->session,
            'classe_id'   => $this->classeId,
            'motif'       => $this->motif,
            'absence_id'  => $this->absenceId,
        ];
    }
}
```

### 7.2 Quand dispatcher

```
AbsenceController::savePointage()
  → pour chaque élève dans le pointage, dispatcher UNE AbsenceCreee
  → même si statut = 'present' (pour l'audit complet du pointage)

AbsenceController::store()       → pointage individuel
AbsenceController::storeRetard() → retard individuel
```

### 7.3 Handlers enregistrés

| Handler | Condition | Action déclenchée |
|---|---|---|
| `AuditHandler` | Toujours | `AuditService::logCreate(saisieParId, 'absences', 'absences', absenceId, payload)` |
| `NotificationHandler` | Si `isAbsenceOuRetard() === true` | `NotificationService::onAbsence($eleveId, $date, $statut)` |
| `StatsCacheHandler` | Toujours | `StatisticsService::clearCache()` — taux présence change |

### 7.4 Remplacement de l'existant

```php
// AVANT (AbsenceController, ligne 131-138)
try {
    $svc = new \App\Services\NotificationService();
    foreach ($statuts as $eleveId => $statut) {
        if (in_array($statut, ['absent', 'retard'], true)) {
            $svc->onAbsence((int)$eleveId, $date, $statut);
        }
    }
} catch (\Throwable) {}

// APRÈS
foreach ($statuts as $eleveId => $statut) {
    EventDispatcher::dispatch(new AbsenceCreee(
        eleveId:    (int)$eleveId,
        date:       $date,
        statut:     $statut,
        session:    $session,
        classeId:   $classeId,
        saisieParId:(int)$user['id'],
        motif:      $motifs[$eleveId] ?? '',
    ));
}
// Le try/catch global disparaît — EventDispatcher isole déjà chaque handler.
```

---

## 8. DOCUMENTGENERE

### 8.1 Événement

```php
namespace App\Events;

use Core\Event;

class DocumentGenere extends Event
{
    public const TYPES = [
        'bulletin'       => 'Bulletin de notes',
        'bulletins_classe'=> 'Bulletins de classe (lot)',
        'recu_paiement'  => 'Reçu de paiement',
        'rapport'        => 'Rapport analytique',
        'export_csv'     => 'Export CSV',
        'export_excel'   => 'Export Excel',
        'backup'         => 'Sauvegarde base de données',
    ];

    public function __construct(
        public readonly string $type,          // clé de self::TYPES
        public readonly string $format,        // 'pdf' | 'csv' | 'excel' | 'sql'
        public readonly string $filename,      // nom du fichier généré
        public readonly int    $genereParId,   // users.id
        public readonly int    $entityId = 0,  // ID de l'entité principale (eleveId, classeId...)
        public readonly int    $lignes = 0,    // nb lignes exportées (pour CSV/Excel)
        public readonly int    $taille = 0,    // taille en octets
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return [
            'type'         => $this->type,
            'format'       => $this->format,
            'filename'     => $this->filename,
            'entity_id'    => $this->entityId,
            'lignes'       => $this->lignes,
            'taille'       => $this->taille,
        ];
    }
}
```

### 8.2 Quand dispatcher

```
PdfService::download()           → après génération (type=bulletin, recu_paiement, rapport)
PdfService::save()               → après sauvegarde
ExportService::toCsv()           → avant exit (type=export_csv)
ExportService::toExcel()         → avant exit (type=export_excel)
BackupService::create()          → après création réussie (type=backup)
BulletinController::printBulletin() → layout=print (format=html — navigation navigateur)
```

### 8.3 Handlers enregistrés

| Handler | Action déclenchée |
|---|---|
| `AuditHandler` | `AuditService::log($genereParId, 'export', 'documents', null, null, null, payload)` |

### 8.4 Pourquoi logger même les exports CSV

```
La génération de documents exportant des données personnelles (élèves, notes, finances)
doit être tracée :
  - Qui a exporté quoi ?
  - À quelle heure ?
  - Combien de lignes ?

Ceci répond aux exigences de conformité RGPD/protection des données scolaires.
DocumentGenere + AuditHandler constitue le journal d'accès aux données.
```

---

## 9. HANDLERS PARTAGÉS

### 9.1 `AuditHandler` — Écoute tous les événements

```php
namespace App\Listeners;

use Core\Listener;
use Core\Event;
use App\Services\AuditService;
use App\Events\{EleveCreated, PaiementValide, NoteAjoutee, AbsenceCreee, DocumentGenere};

class AuditHandler implements Listener
{
    private AuditService $audit;

    public function __construct()
    {
        $this->audit = new AuditService();
    }

    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof EleveCreated   => $this->audit->logCreate(
                $event->createdById, 'eleves', 'eleves', $event->eleveId, $event->toArray()
            ),
            $event instanceof PaiementValide => $this->audit->logCreate(
                $event->encaisseParId, 'comptabilite', 'paiements', $event->paiementId, $event->toArray()
            ),
            $event instanceof NoteAjoutee    => $this->audit->logCreate(
                $event->saisieParId, 'notes', 'notes', $event->controleId, $event->toArray()
            ),
            $event instanceof AbsenceCreee   => $this->audit->logCreate(
                $event->saisieParId, 'absences', 'absences', $event->absenceId, $event->toArray()
            ),
            $event instanceof DocumentGenere => $this->audit->log(
                $event->genereParId, 'export', 'documents', null, null, null, $event->toArray()
            ),
            default => null,
        };
    }
}
```

---

### 9.2 `NotificationHandler` — Écoute les événements métier

```php
namespace App\Listeners;

use Core\Listener;
use Core\Event;
use App\Services\NotificationService;
use App\Events\{PaiementValide, NoteAjoutee, AbsenceCreee};
use App\Models\PeriodeModel;

class NotificationHandler implements Listener
{
    private NotificationService $notif;

    public function __construct()
    {
        $this->notif = new NotificationService();
    }

    public function handle(Event $event): void
    {
        match (true) {

            $event instanceof AbsenceCreee => $event->isAbsenceOuRetard()
                ? $this->notif->onAbsence($event->eleveId, $event->date, $event->statut)
                : null,

            $event instanceof PaiementValide =>
                $this->notif->onPaiement($event->eleveId, $event->montant, $event->fraisNom),

            $event instanceof NoteAjoutee => $event->periodePubliee
                ? $this->notif->onNote(
                    $event->eleveId,
                    (new PeriodeModel())->findById($event->periodeId)?->nom ?? "Période #{$event->periodeId}",
                    $event->moyenneGenerale
                )
                : null,

            default => null,
        };
    }
}
```

---

### 9.3 `StatsCacheHandler` — Invalide le cache de statistiques

```php
namespace App\Listeners;

use Core\Listener;
use Core\Event;
use App\Services\StatisticsService;
use App\Events\{EleveCreated, AbsenceCreee};

class StatsCacheHandler implements Listener
{
    public function handle(Event $event): void
    {
        if ($event instanceof EleveCreated || $event instanceof AbsenceCreee) {
            (new StatisticsService())->clearCache();
        }
    }
}
```

---

### 9.4 `ReceiptGeneratorHandler` — Génération reçu PDF (prévu V3)

```
namespace App\Listeners;

class ReceiptGeneratorHandler implements Listener
{
    Prévu pour V3.
    Écoute PaiementValide.
    Actions :
      1. PdfService::save('paiements/recu', [paiement...], storage/receipts/{id}.pdf)
      2. EmailService::send() au parent avec PDF en pièce jointe
      3. backup_logs → enregistrer la génération

    Non enregistré en V2 — nécessite PdfService (Dompdf) opérationnel.
    Ajouter dans config/events.php quand PdfService sera implémenté.
```

---

## 10. REGISTRATION — config/events.php

```php
<?php
// config/events.php
// Retourne le mapping Event → Listeners.
// Chargé UNE SEULE FOIS par Application::run() au démarrage.

use App\Events\{EleveCreated, PaiementValide, NoteAjoutee, AbsenceCreee, DocumentGenere};
use App\Listeners\{AuditHandler, NotificationHandler, StatsCacheHandler};

return [

    EleveCreated::class => [
        new AuditHandler(),
        new StatsCacheHandler(),
    ],

    PaiementValide::class => [
        new AuditHandler(),
        new NotificationHandler(),
        // new ReceiptGeneratorHandler(), ← décommenter en V3
    ],

    NoteAjoutee::class => [
        new AuditHandler(),
        new NotificationHandler(),
    ],

    AbsenceCreee::class => [
        new AuditHandler(),
        new NotificationHandler(),
        new StatsCacheHandler(),
    ],

    DocumentGenere::class => [
        new AuditHandler(),
    ],

];
```

**Règle :** Ajouter un effet de bord = ajouter une ligne dans ce fichier. **Ne jamais toucher les contrôleurs existants.**

---

## 11. INTÉGRATION DANS Application::run()

```php
// core/Application.php — méthode run() (extrait)

public function run(): void
{
    // 1. Routes (existant)
    require ROOT_PATH . '/config/routes.php';

    // 2. Events — NOUVEAU (charger après les routes, avant le dispatch du router)
    $events = require ROOT_PATH . '/config/events.php';
    foreach ($events as $eventClass => $listeners) {
        foreach ($listeners as $listener) {
            \Core\EventDispatcher::listen($eventClass, $listener);
        }
    }

    // 3. Dispatch de la requête (existant)
    $this->router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
}
```

**Impact sur l'autoloader :**

```php
// public/index.php — PSR-4 autoloader (à compléter pour les nouveaux namespaces)

$autoloader = [
    'Core\\'          => ROOT_PATH . '/core/',
    'App\\Controllers\\' => ROOT_PATH . '/app/Controllers/',
    'App\\Models\\'   => ROOT_PATH . '/app/Models/',
    'App\\Middleware\\'  => ROOT_PATH . '/app/Middleware/',
    'App\\Services\\'  => ROOT_PATH . '/app/Services/',
    // Ajouter :
    'App\\Events\\'   => ROOT_PATH . '/app/Events/',       // ← NOUVEAU
    'App\\Listeners\\' => ROOT_PATH . '/app/Listeners/',   // ← NOUVEAU
];
```

---

## 12. MIGRATION AVANT/APRÈS DANS LES CONTRÔLEURS

### 12.1 AbsenceController — avant/après

```php
// ══ AVANT ══════════════════════════════════════════════════════════════════════
$this->absModel->storePointage($classeId, $date, $session, $statuts, $durees, $motifs, $userId);

try {
    $svc = new \App\Services\NotificationService();
    foreach ($statuts as $eleveId => $statut) {
        if (in_array($statut, ['absent', 'retard'], true)) {
            $svc->onAbsence((int)$eleveId, $date, $statut);
        }
    }
} catch (\Throwable) {}

// ══ APRÈS ══════════════════════════════════════════════════════════════════════
use Core\EventDispatcher;
use App\Events\AbsenceCreee;

$ids = $this->absModel->storePointage($classeId, $date, $session, $statuts, $durees, $motifs, $userId);
// storePointage() retourne un tableau [eleveId => absenceId] des lignes insérées

foreach ($statuts as $eleveId => $statut) {
    EventDispatcher::dispatch(new AbsenceCreee(
        eleveId:    (int)$eleveId,
        date:       $date,
        statut:     $statut,
        session:    $session,
        classeId:   $classeId,
        saisieParId:(int)$userId,
        motif:      $motifs[$eleveId] ?? '',
        absenceId:  $ids[$eleveId] ?? 0,
    ));
}
```

---

### 12.2 PaiementController — avant/après

```php
// ══ AVANT ══════════════════════════════════════════════════════════════════════
$id = $this->paiModel->insert([...]);
$this->feModel->recalculerStatut($fraisEleveId);

try {
    (new \App\Services\NotificationService())->onPaiement($eleveId, $montant, $fraisNom);
} catch (\Throwable) {}

// ══ APRÈS ══════════════════════════════════════════════════════════════════════
use Core\EventDispatcher;
use App\Events\PaiementValide;

$id = $this->paiModel->insert([...]);
$this->feModel->recalculerStatut($fraisEleveId);

EventDispatcher::dispatch(new PaiementValide(
    paiementId:   $id,
    eleveId:      $eleveId,
    montant:      $montant,
    modePaiement: $mode,
    fraisNom:     $fraisNom,
    fraisEleveId: $fraisEleveId,
    encaisseParId:(int)$user['id'],
    reference:    $reference ?? '',
));
```

---

### 12.3 EleveController — avant/après

```php
// ══ AVANT ══════════════════════════════════════════════════════════════════════
$id = $this->eleveModel->insert($this->prepareDbData($data));
Session::flash('success', "L'élève a été créé avec succès.");
$this->redirect(BASE_URL . '/eleves/' . $id);
// (rien d'autre — pas d'audit, pas d'invalidation stats)

// ══ APRÈS ══════════════════════════════════════════════════════════════════════
use Core\EventDispatcher;
use App\Events\EleveCreated;

$id = $this->eleveModel->insert($this->prepareDbData($data));

EventDispatcher::dispatch(new EleveCreated(
    eleveId:     $id,
    nom:         $data['nom'],
    prenom:      $data['prenom'],
    matricule:   $data['matricule'],
    classeId:    (int)$data['classe_id'],
    createdById: (int)$this->currentUser()['id'],
));

Session::flash('success', "L'élève a été créé avec succès.");
$this->redirect(BASE_URL . '/eleves/' . $id);
```

---

### 12.4 NoteController — avant/après

```php
// ══ AVANT ══════════════════════════════════════════════════════════════════════
$this->noteModel->upsert($controleId, $eleveId, $valeur);
$moyenneMatiere  = $this->noteModel->calculerMoyenneMatiere($eleveId, $matiereId, $periodeId);
$moyenneGenerale = $this->noteModel->calculerMoyenneGenerale($eleveId, $periodeId);
// (rien d'autre)

// ══ APRÈS ══════════════════════════════════════════════════════════════════════
use Core\EventDispatcher;
use App\Events\NoteAjoutee;

$this->noteModel->upsert($controleId, $eleveId, $valeur);
$moyMat  = $this->noteModel->calculerMoyenneMatiere($eleveId, $matiereId, $periodeId);
$moyGen  = $this->noteModel->calculerMoyenneGenerale($eleveId, $periodeId);
$periode = $this->periodeModel->findById($periodeId);

EventDispatcher::dispatch(new NoteAjoutee(
    controleId:      $controleId,
    eleveId:         $eleveId,
    classeId:        $classeId,
    periodeId:       $periodeId,
    matiereId:       $matiereId,
    valeur:          (float)$valeur,
    moyenneMatiere:  $moyMat,
    moyenneGenerale: $moyGen,
    saisieParId:     (int)$this->currentUser()['id'],
    periodePubliee:  (bool)($periode?->publiee ?? false),
));
```

---

### 12.5 BulletinController / ExportService — DocumentGenere

```php
// Dans PdfService::download() ou ExportService::toCsv()
use Core\EventDispatcher;
use App\Events\DocumentGenere;

// Avant exit / avant envoi des headers
EventDispatcher::dispatch(new DocumentGenere(
    type:       'bulletin',        // ou 'export_csv', 'recu_paiement'...
    format:     'pdf',             // ou 'csv', 'excel'
    filename:   $filename,
    genereParId:(int)$_SESSION['_auth_user']['id'],
    entityId:   $eleveId,          // ou $classeId selon le contexte
    lignes:     $nbLignes,         // 0 pour un PDF
    taille:     strlen($pdfContent),
));
```

---

## 13. CATALOGUE COMPLET DES ÉVÉNEMENTS

### 13.1 Événements V2 (définis dans ce document)

| Événement | Module source | Handlers |
|---|---|---|
| `EleveCreated` | Élèves | AuditHandler, StatsCacheHandler |
| `PaiementValide` | Comptabilité | AuditHandler, NotificationHandler |
| `NoteAjoutee` | Notes | AuditHandler, NotificationHandler |
| `AbsenceCreee` | Absences | AuditHandler, NotificationHandler, StatsCacheHandler |
| `DocumentGenere` | Rapports, PDF, Export, Backup | AuditHandler |

### 13.2 Événements recommandés pour extensions futures

| Événement | Déclencheur | Handlers prévus |
|---|---|---|
| `UserLoggedIn` | `AuthController::login()` (succès) | AuditHandler |
| `UserLoginFailed` | `AuthController::login()` (échec) | AuditHandler (sécurité) |
| `UserLoggedOut` | `AuthController::logout()` | AuditHandler |
| `EleveModifie` | `EleveController::update()` | AuditHandler, StatsCacheHandler |
| `EleveSupprimer` | `EleveController::destroy()` | AuditHandler, StatsCacheHandler |
| `AnnoncePubliee` | `AnnonceController::store()` | AuditHandler, NotificationHandler |
| `BulletinPublie` | Clôture de période | AuditHandler, NotificationHandler (masse classe) |
| `PeriodeClôturee` | Admin clôture une période | AuditHandler, StatsCacheHandler |
| `ParametreModifie` | `ParametresController::update*()` | AuditHandler |
| `BackupCree` | `BackupService::create()` | AuditHandler |
| `ModuleToggle` | `ParametresController::toggleModule()` | AuditHandler |
| `ImportCsvTermine` | `EleveModel::importFromCsv()` | AuditHandler, StatsCacheHandler |

### 13.3 Règle d'extension

```
Ajouter un événement :
  1. Créer app/Events/MonEvenement.php (extends Core\Event)
  2. Créer ou réutiliser un handler dans app/Listeners/
  3. Ajouter l'entrée dans config/events.php
  4. Ajouter le use Core\EventDispatcher dans le contrôleur source
  5. Appeler EventDispatcher::dispatch(new MonEvenement(...))

Aucune modification de l'EventDispatcher, de l'Application, ou des autres handlers.
```

---

## 14. AUCUNE MIGRATION SQL

Le système d'événements est **100% PHP** — pas de nouvelles tables.

| Composant | Stockage |
|---|---|
| EventDispatcher | Mémoire PHP (array statique) — vit le temps de la requête |
| Event objects | Mémoire PHP — non persistés |
| Handlers | Mémoire PHP — instanciés au démarrage |
| Résultats des handlers | Persistés par les services appelés (AuditService → audit_logs, NotificationService → notification_logs) |

**Tables existantes utilisées** (définies dans d'autres étapes) :
- `audit_logs` — définie dans `SHARED_SERVICES.md` (S001)
- `notifications` — existante V1
- `notification_logs` — existante V1

**Autoloader** — seule modification : ajouter 2 namespaces dans `public/index.php` (App\Events, App\Listeners).

---

*EVENTS_V2.md — SCOLARIS | Conception complète. Aucune migration SQL. Pas d'implémentation. En attente de validation.*
