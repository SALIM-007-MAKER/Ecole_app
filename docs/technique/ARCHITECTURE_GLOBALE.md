# Architecture Globale — SCOLARIS V2 / EduNova

## 1. Vue d'ensemble

SCOLARIS V2 (nom commercial : **EduNova**) est une application de gestion scolaire
multi-établissements (SaaS) écrite en **PHP 8.2** pur, sans framework tiers, avec un
front-end serveur (rendu PHP + Tailwind CSS CDN + Lucide Icons) et une API REST
dédiée aux intégrations externes.

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Navigateur / Client API                      │
└───────────────────────────────┬───────────────────────────────────────┘
                                 │ HTTP
┌───────────────────────────────▼───────────────────────────────────────┐
│  public/index.php  — point d'entrée unique, autoloader PSR-4 maison    │
│  Core\Application::run() — bootstrap, sessions, routage, événements    │
└───────────────────────────────┬───────────────────────────────────────┘
                                 │
        ┌────────────────────────┼─────────────────────────┐
        ▼                        ▼                          ▼
┌───────────────┐      ┌──────────────────┐       ┌──────────────────┐
│  Core\Router    │      │  Contrôleurs V1   │       │  Modules V2       │
│  (config/routes │      │  app/Controllers/ │       │  app/Modules/*    │
│  .php + routes  │      │                   │       │  (Finance, RH,    │
│  par module)    │      │                   │       │  API, Multi-Tenant│
└───────────────┘      └────────┬──────────┘       │  infra, etc.)     │
                                 │                    └────────┬──────────┘
                                 ▼                             ▼
                     ┌─────────────────────────────────────────────┐
                     │   Core\Model / Repositories / Services        │
                     │   Core\EventDispatcher → Listeners            │
                     └───────────────────┬─────────────────────────┘
                                          ▼
                              ┌───────────────────────┐
                              │   MySQL 8.4 (PDO)      │
                              │   Shared Database,     │
                              │   Shared Schema         │
                              └───────────────────────┘
```

## 2. Principes architecturaux

1. **Aucune dépendance tierce gérée par Composer.** Il n'existe ni `composer.json`
   ni `vendor/` dans ce projet : autoloading, routage, ORM minimal, tests, tout est
   écrit à la main. C'est un choix délibéré et constant depuis l'origine du projet,
   pas un oubli — voir `docs/developpeur/STANDARDS_CODE.md` pour les implications.
2. **Coexistence V1 / V2.** L'application a été développée par strates successives :
   un socle "V1" (contrôleurs plats sous `app/Controllers/`, modèles sous
   `app/Models/`) reste actif et sert la quasi-totalité du trafic réel aujourd'hui
   (élèves, classes, notes, absences, authentification, branding, domaines,
   stockage, cache, portail Super-Admin, sauvegardes). Des "modules V2"
   (`app/Modules/{Nom}/`) plus récents, structurés en domaines métier avec
   Repository/Service/Controller/Event, s'activent individuellement via
   `config/modules.php`. Voir `docs/technique/ARCHITECTURE_MODULES.md`.
3. **Multi-tenant "Shared Database, Shared Schema".** Un seul schéma MySQL sert
   tous les établissements ; l'isolation se fait par une colonne
   `etablissement_id` filtrée systématiquement, jamais par une base ou un schéma
   séparé par client. Voir `docs/technique/MULTI_TENANT.md`.
4. **Écritures pilotées par événements.** La règle du projet est : *"Les écritures
   proviennent uniquement des Events. Ne jamais écrire directement depuis les
   contrôleurs"* pour les domaines métier V2 — un contrôleur appelle un Service, qui
   dispatché un Event, dont un Listener effectue l'écriture réelle. Voir
   `docs/technique/EVENT_SYSTEM.md`.
5. **Sécurité par défaut.** CSRF systématique sur les actions d'état, RBAC à
   plusieurs paliers (voir `docs/technique/RBAC.md`), suppression **toujours**
   logique (`deleted_at`), jamais de `DROP TABLE`/`DELETE` physique sur des données
   utilisateur.

## 3. Arborescence du projet

```
ecole_app/
├── core/                    Framework interne (Router, Model, Session, Controller,
│                            EventDispatcher, Cache, Queue, Storage, Backup, Platform, Tenant)
├── app/
│   ├── Controllers/         Contrôleurs V1 (un fichier = un domaine fonctionnel)
│   ├── Models/               Modèles V1 (accès direct PDO via Core\Model)
│   ├── Services/             Services transverses V1 (Email, SMS, Upload, Menu, Audit)
│   ├── Events/ Listeners/    Système d'événements V1
│   ├── Jobs/                 Tâches de file d'attente (Core\Queue)
│   ├── Shared/                Services partagés utilisés par l'API Platform (Analytics, Api/*)
│   ├── Modules/              Modules V2 (un dossier = un domaine métier complet)
│   │   ├── Scolarite/ Academique/ Finance/ VieScolaire/ RH/
│   │   ├── Documents/ Communication/ Bibliotheque/ Inventaire/ Rapports/
│   │   ├── Portals/ Api/
│   └── Views/                Vues PHP (layouts + pages), rendues par Core\View
├── config/                   Un fichier par domaine de configuration
├── database/
│   ├── migrations/            Migrations séquentielles (T0xx pour Multi-Tenant)
│   ├── migrate.php            Runner de migrations (idempotent)
│   └── queue-worker.php       Runner CLI de la file d'attente
├── storage/                   Fichiers hors webroot (cache, sauvegardes, uploads tenant)
├── public/                    Webroot — point d'entrée unique (index.php) + assets statiques
├── tests/                     Tests (voir docs/developpeur/TESTS.md)
└── docs/                      Cette documentation
```

## 4. Cycle de vie d'une requête HTTP

1. Apache route toute requête vers `public/index.php` (réécriture d'URL).
2. L'autoloader PSR-4 maison est enregistré (`spl_autoload_register`), mappant
   `Core\`, `App\Controllers\`, `App\Models\`, `App\Middleware\`, `App\Services\`,
   `App\Events\`, `App\Listeners\`, `App\Modules\`, `App\Jobs\`, `App\Shared\` vers
   leurs répertoires respectifs.
3. `Core\Application::getInstance()->run()` :
   - `bootstrap()` : fuseau horaire, gestion des erreurs (`display_errors`/
     `set_exception_handler` selon `config('app.debug')`), démarrage de session.
   - Charge `config/routes.php` (routes V1) puis, pour chaque module dont
     `enabled=true` dans `config/modules.php`, le fichier `routes.php` de ce
     module.
   - Enregistre les listeners d'événements (`config/events.php`).
   - `Core\Router::dispatch()` fait correspondre méthode+chemin à un handler,
     instancie le contrôleur, appelle la méthode.
4. Le contrôleur authentifie/autorise (`Core\Session`, RBAC), délègue à un modèle/
   service, puis rend une vue (`Core\View::render()`) ou une réponse JSON
   (`Core\View::json()`).

## 5. Points d'entrée alternatifs

| Point d'entrée | Usage |
|---|---|
| `public/index.php` | Toutes les requêtes web + API REST + portail Super-Admin |
| `database/migrate.php` | Migrations de schéma (CLI) |
| `database/queue-worker.php` | Traitement de la file d'attente (CLI, invocation manuelle/planifiée) |

## 6. Documents associés

- `docs/technique/ARCHITECTURE_MODULES.md` — détail de chaque module V2
- `docs/technique/BASE_DE_DONNEES.md` — schéma, conventions, migrations
- `docs/technique/API_REST.md` — API Platform (JWT, clés API, ressources)
- `docs/technique/EVENT_SYSTEM.md` — événements et écoute
- `docs/technique/RBAC.md` — rôles, permissions, résolution
- `docs/technique/MULTI_TENANT.md` — isolation multi-établissements
- `docs/technique/PORTAILS.md` — framework de portails par profil
- `RELEASE_CANDIDATE_RC1_REPORT.md` — état de préparation production détaillé et vérifié empiriquement (à lire avant tout déploiement)
