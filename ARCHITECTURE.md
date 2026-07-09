# Architecture — SCOLARIS V2.0.0 / EduNova

Document de référence de release (Phase 16.0). Détail complet :
`docs/technique/ARCHITECTURE_GLOBALE.md`, `docs/technique/ARCHITECTURE_MODULES.md`.

## 1. Résumé

SCOLARIS V2.0.0 (EduNova) est une plateforme de gestion scolaire multi-
établissements (SaaS) en **PHP 8.2 pur, sans dépendance Composer** — autoloading,
routage, ORM minimal, événements, tests : tout est écrit à la main. Rendu
serveur (PHP + Tailwind CSS CDN + Lucide Icons) et API REST dédiée (JWT + clés
API).

## 2. Schéma global

```
Navigateur / Client API
        │ HTTP
        ▼
public/index.php — autoloader PSR-4 maison, point d'entrée unique
        │
        ▼
Core\Application::run() — bootstrap, sessions, routage, événements
        │
        ├──► Core\Router ──► Contrôleurs V1 (app/Controllers/)
        │                 └► Modules V2 (app/Modules/*, si enabled=true)
        │
        ▼
Core\Model / Repositories / Services ──► Core\EventDispatcher ──► Listeners
        │
        ▼
MySQL 8.4 (PDO) — Shared Database, Shared Schema (isolation par etablissement_id)
```

## 3. Principes architecturaux figés en V2.0.0

1. **Zéro dépendance tierce.** Choix délibéré et constant depuis l'origine.
2. **Coexistence V1/V2.** Contrôleurs plats (`app/Controllers/`) et modules
   structurés (`app/Modules/{Nom}/`) coexistent durablement — un module V2 ne
   remplace jamais son équivalent V1 par suppression.
3. **Multi-tenant Shared Database, Shared Schema.** Isolation par colonne
   `etablissement_id`, filtrée systématiquement.
4. **Écritures pilotées par événements** pour les domaines V2 : Contrôleur →
   Service → Event → Listener, jamais d'écriture directe depuis un contrôleur.
5. **Sécurité par défaut** : CSRF systématique, RBAC à plusieurs paliers,
   suppression toujours logique (`deleted_at`), jamais de `DROP`/`DELETE`
   physique sur des données utilisateur.

## 4. Modules — état à la release

| Module | Statut V2.0.0 |
|---|---|
| Core | ✅ Figé, opérationnel |
| Scolarité, Académique | ✅ Figé, opérationnel (socle V1 actif) |
| Multi-Tenant (infrastructure SaaS complète) | ✅ Figé, testé (score 8.4/10) |
| API Platform | ✅ Figé, opérationnel (corrigé Phase 15.1) |
| Finance, Vie scolaire, RH | ⚠️ Figé — routage/code présents, **données non appliquées** |
| Documents, Communication, Bibliothèque, Inventaire, Rapports & BI | ⚠️ Figé — désactivés, **jamais exécutés contre une base réelle** |
| Portails | ⚠️ Figé — désactivé, dépend des modules ci-dessus |

Détail des limitations et plan de résolution :
`SCOLARIS_V2_FINAL_RELEASE_REPORT.md` §7/§9, `ROADMAP_V2.1.md`.

## 5. Gel de l'architecture (Phase 16.0)

Les 14 modules listés ci-dessus sont **déclarés figés** à compter de cette
version : aucune modification ne doit leur être apportée en dehors d'un nouveau
cycle de développement (voir `SCOLARIS_V2_FINAL_RELEASE_REPORT.md` §2). Les
corrections de bugs critiques restent possibles via un correctif de version
(2.0.x) sans constituer un nouveau cycle.

## 6. Documents associés

`docs/technique/` (8 documents détaillés), `INSTALLATION.md`, `DEPLOYMENT.md`,
`API_DOCUMENTATION.md`.
