# Module Scolarité V2

**Phase :** 1.1 — Structure  
**Statut :** Structure créée, fonctionnalités non implémentées  
**Activé :** Non (`config/modules.php` → `enabled: false`)

---

## Responsabilités

Ce module couvre la gestion complète de la scolarité :

| Sous-domaine | Responsabilité |
|---|---|
| **Élèves** | Dossier élève, données personnelles, photo, statut (actif/archivé/transféré) |
| **Classes** | Création/édition, effectifs, capacité, niveau, année scolaire |
| **Inscriptions** | Cycle de vie d'une inscription (en_attente → validée/rejetée), historique |
| **Familles** | Représentants légaux, lien famille↔élèves, contacts d'urgence |
| **Matières** | Catalogue, coefficients, niveaux associés, activation/désactivation |

---

## Architecture

```
app/Modules/Scolarite/
├── Controllers/          Couche HTTP — valide les entrées, délègue aux Services
│   ├── EleveController.php
│   ├── ClasseController.php
│   ├── InscriptionController.php
│   ├── FamilleController.php
│   └── MatiereController.php
│
├── Models/               Couche DB — opérations CRUD via Core\Model
│   ├── EleveModel.php
│   ├── ClasseModel.php
│   ├── InscriptionModel.php   ← table à créer (Phase 1.2)
│   ├── FamilleModel.php       ← table à créer (Phase 1.2)
│   └── MatiereModel.php
│
├── Services/             Logique métier — règles, orchestration, events
│   ├── InscriptionService.php    Cycle de vie des inscriptions
│   └── AffectationService.php    Affectation élèves/enseignants aux classes
│
├── Repositories/         Requêtes SQL complexes (jointures multi-tables)
│   ├── EleveRepository.php       Pagination, recherche, dossier complet
│   ├── ClasseRepository.php      Effectifs, disponibilités, emplois du temps
│   └── InscriptionRepository.php Historique, comptages, filtres
│
├── Policies/             Autorisations contextuelles (au-delà des permissions globales)
│   ├── ElevePolicy.php           Un parent ne voit que ses enfants
│   └── ClassePolicy.php          Un enseignant ne voit que ses classes
│
├── Events/               Événements du module (étendent Core\Event)
│   ├── EleveInscrit.php
│   ├── InscriptionValidee.php
│   └── ClasseAffectee.php
│
├── Listeners/            Handlers des événements du module
│   └── InscriptionHandler.php    Audit + notifications
│
├── Views/                Vues PHP par sous-domaine (stubs — Phase 1.1)
│   ├── eleves/           index, show, form
│   ├── classes/          index, show, form
│   ├── inscriptions/     index, form
│   ├── familles/         index, form
│   └── matieres/         index, form
│
├── routes.php            Routes /v2/scolarite/* (chargées uniquement si enabled)
├── module.json           Manifeste du module (métadonnées, dépendances, permissions)
└── README.md             Ce fichier
```

---

## Coexistence V1 / V2

Ce module **ne remplace pas** les contrôleurs V1. Les deux coexistent :

| Fonctionnalité | V1 (actif) | V2 (inactif) |
|---|---|---|
| Liste élèves | `GET /eleves` → `app/Controllers/EleveController` | `GET /v2/scolarite/eleves` → `Modules/Scolarite/Controllers/EleveController` |
| Classes | `GET /classes` → `app/Controllers/ClasseController` | `GET /v2/scolarite/classes` → idem |
| Matières | `GET /matieres` → `app/Controllers/MatiereController` | `GET /v2/scolarite/matieres` → idem |

La migration V1 → V2 se fait sous-domaine par sous-domaine, sans coupure.

---

## Activation

Pour activer le module (quand Phase 1.2 sera implémentée) :

```php
// config/modules.php
'scolarite' => [
    'enabled' => true,   // ← passer à true
    ...
],
```

L'`Application` chargera alors automatiquement `routes.php` et les listeners.

---

## Migrations nécessaires avant activation (Phase 1.2)

| Migration | Table | Description |
|---|---|---|
| SC001 | `inscriptions` | Cycle de vie inscription (statut, annee_scolaire_id, classe_id) |
| SC002 | `familles` | Entité famille (nom, adresse, contacts) |
| SC003 | `familles_eleves` | Relation N-N famille↔élèves avec lien de parenté |
| SC004 | `eleves` (ALTER) | Ajouter colonne `statut` (actif\|archive\|transfere) |

---

## Enregistrement des événements du module (à faire lors de l'activation)

Ajouter dans `config/events.php` :

```php
use App\Modules\Scolarite\Events\EleveInscrit;
use App\Modules\Scolarite\Events\InscriptionValidee;
use App\Modules\Scolarite\Events\ClasseAffectee;
use App\Modules\Scolarite\Listeners\InscriptionHandler;

EleveInscrit::class      => [new InscriptionHandler()],
InscriptionValidee::class => [new InscriptionHandler()],
ClasseAffectee::class    => [new InscriptionHandler()],
```
