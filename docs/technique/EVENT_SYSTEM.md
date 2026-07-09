# Système d'événements — SCOLARIS V2

## 1. Principe

Règle du projet : *"Les écritures proviennent uniquement des Events. Ne jamais
écrire directement depuis les contrôleurs."* Pour les modules V2, un contrôleur ne
fait jamais de `INSERT`/`UPDATE` lui-même : il appelle un Service, qui effectue la
validation métier puis **dispatché un événement** ; un ou plusieurs **Listeners**
réagissent à cet événement (écriture réelle, notification, audit, mise à jour d'un
cache dénormalisé...). Ce découplage permet à plusieurs effets de bord
indépendants de réagir à un même fait métier sans coupler le Service qui l'émet à
chacun d'eux.

## 2. Composants (`core/`)

| Classe | Rôle |
|---|---|
| `Core\Event` | Classe abstraite de base — `getName()`, `getFiredAt()`, `toArray()` (abstrait) |
| `Core\Listener` | Contrat des écouteurs — une méthode `handle(Event $event)` |
| `Core\EventDispatcher` | Registre statique `listen(eventClass, Listener)` + `dispatch(Event)` |

## 3. Cycle de vie

```
Contrôleur → Service → new XxxEvent(...) → EventDispatcher::dispatch($event)
                                                    │
                    ┌───────────────────────────────┼───────────────────────────┐
                    ▼                                ▼                           ▼
             ListenerA::handle()             ListenerB::handle()         ListenerC::handle()
             (écriture DB réelle)            (notification)              (audit log)
```

- **Synchrone, même process** : `EventDispatcher::dispatch()` appelle tous les
  listeners enregistrés dans la même requête PHP, l'un après l'autre. Aucune file
  d'attente n'intervient à ce niveau (pour ça, voir `app/Jobs/` et
  `docs/technique/MULTI_TENANT.md` §Cache & Queue).
- **Isolation des erreurs** : une exception levée par un listener est journalisée
  (`error_log`) mais n'interrompt **jamais** les listeners suivants ni ne remonte
  au contrôleur appelant — un échec de notification ne doit jamais empêcher
  l'écriture métier principale (ou vice versa, selon l'ordre d'enregistrement).
- **Enregistrement** : `config/events.php` (≈1400 lignes) associe chaque classe
  d'événement à la liste de ses listeners, chargé une fois au bootstrap
  (`Core\Application::run()`).

## 4. Créer un événement

```php
namespace App\Modules\{Module}\Events;

final class MonEvenement extends \Core\Event
{
    public function __construct(
        public readonly int $entiteId,
        public readonly int $etablissementId,
    ) {
        parent::__construct();
    }

    public function toArray(): array
    {
        return ['entite_id' => $this->entiteId, 'etablissement_id' => $this->etablissementId];
    }
}
```

Puis dans `config/events.php` :
```php
use App\Modules\{Module}\Events\MonEvenement;
use App\Modules\{Module}\Listeners\MonHandler;
// ...
MonEvenement::class => [new MonHandler()],
```

## 5. Créer un listener

```php
namespace App\Modules\{Module}\Listeners;

final class MonHandler implements \Core\Listener
{
    public function handle(\Core\Event $event): void
    {
        // $event est garanti être une instance de l'événement écouté
        // Effectuer l'écriture réelle, l'envoi de notification, etc.
    }
}
```

## 6. Propagation du contexte tenant

Un événement dispatché **synchronement** reste dans le même process/la même
requête que son émetteur — `Core\Tenant\TenantContext` (s'il est positionné) est
donc identique pour l'événement et tous ses listeners sans mécanisme
supplémentaire. Ce n'est **plus** le cas pour un traitement **différé** (file
d'attente, `Core\Queue\QueueWorker`) : voir `docs/technique/MULTI_TENANT.md`
§"Cache & Queue" pour la façon dont le contexte est reconstitué avant l'exécution
d'une tâche différée.

## 7. Événements notables (extrait)

| Événement | Domaine | Déclenché par |
|---|---|---|
| `EleveCreated`, `EleveUpdated`, `EleveArchived` | Scolarité | Création/modification/archivage élève |
| `ClasseCreated`, `ClasseChanged`, `SchoolYearChanged` | Scolarité | Gestion des classes |
| `InscriptionCreated`, `ReinscriptionCreated` | Scolarité | Inscriptions |
| `NoteAjoutee`, `ControleUpdated` | Académique | Saisie de notes |
| `PaiementValide` | Finance | Encaissement validé |
| `AbsenceCreee` | Vie scolaire | Pointage d'absence |
| `DocumentGenere` | Documents | Génération de document |
| `ImportCsvCompleted` | Scolarité | Fin d'import en masse |
| `PlatformTenantCreated/Suspended/Archived/Restored` | Multi-Tenant | Cycle de vie établissement (portail Super-Admin) |

Liste complète : `config/events.php`.

## 8. Listeners transverses

`AuditHandler`, `NotificationHandler`, `StatsCacheHandler` (`app/Listeners/`) sont
enregistrés sur de nombreux événements pour fournir respectivement la piste
d'audit, les notifications utilisateur, et l'invalidation de caches statistiques —
un même événement métier peut donc avoir un listener "métier" (écriture) et un ou
plusieurs listeners "transverses" (effets de bord génériques).
