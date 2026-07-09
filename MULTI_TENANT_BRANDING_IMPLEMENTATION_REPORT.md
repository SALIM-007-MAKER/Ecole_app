# MULTI_TENANT_BRANDING_IMPLEMENTATION_REPORT.md
## Phase 14.5 — Branding Multi-Tenant

> **Statut :** ✅ IMPLÉMENTÉ — décision : **GO WITH FIXES** (voir §9)
> **Date :** 2026-07-08
> **Périmètre :** Personnalisation de l'identité visuelle par établissement. Aucune modification de la logique métier.
> **Référence :** `MULTI_TENANT_V2_BLUEPRINT.md` §9 (Paramètres), §10 (Branding), §12 (Stockage), §25.1 Phase 14.5

---

## 1. Résumé exécutif

Chaque établissement peut désormais personnaliser nom, logo, favicon, image
de connexion, couleurs primaire/secondaire, police, thème, informations de
contact, pied de page et affichage des fils d'Ariane — via une interface
admin dédiée (`/parametres/branding`), sans jamais impacter les autres
tenants. Les valeurs par défaut reproduisent **exactement** ce qui était
codé en dur avant cette phase (EduNova, violet #7c3aed, police Inter) :
**zéro régression visuelle** pour l'établissement existant.

L'écran de connexion (page publique, sans session utilisateur) résout son
branding via une invocation locale et en lecture seule de `TenantResolver`
(déjà construit et testé en Phase 14.2), sans jamais activer de middleware
global — même discipline de non-régression que les phases précédentes.

**246/246 assertions passées** (18 nouvelles pour cette phase + toutes les
suites précédentes), plus vérification navigateur réelle : connexion,
tableau de bord, modification effective d'une couleur avec application
immédiate, puis restauration.

---

## 2. Composants créés

| Fichier | Rôle |
|---|---|
| `core/Tenant/BrandingData.php` | DTO immuable (16 champs) + `defaults()` reproduisant les valeurs historiques codées en dur |
| `core/Tenant/BrandingService.php` | `get(etabId)` / `save(etabId, fields)` / `forCurrentRequest()` (résolution pré-auth) — cache mémoire isolé par tenant |
| `core/Tenant/TenantStorageService.php` | Upload/suppression sécurisés (validation MIME réelle, 2 Mo max), isolation stricte des chemins par `etablissement_id` |
| `app/Controllers/SettingsController.php` | `showBranding()` / `updateBranding()` — cible TOUJOURS l'établissement de la session courante, jamais un id fourni par la requête |
| `app/Views/settings/branding.php` | Formulaire admin (identité, couleurs, images, contact, affichage) |
| `database/migrations/T008_branding_seed.php` | Seed `etablissement_branding` + `etab_settings` (section branding) pour l'établissement existant |
| `database/migrations/T009_branding_permissions.php` | Permissions `branding.view` / `branding.update` |
| `tests/Unit/BrandingTenantTest.php` | 18 assertions : défauts, isolation 2 tenants, cache, validation upload |

## 3. Composants modifiés

| Fichier | Changement |
|---|---|
| `app/Views/layouts/main.php` | Title, favicon, `apple-mobile-web-app-title`, logo sidebar/header, nom affiché, variables CSS `--violet`/`--violet-hover`/`--violet-light`/`--brand-secondary`, police Google Fonts dynamique — tout résolu via `BrandingService::get()` depuis `Session::getUser()['etablissement_id']` |
| `app/Views/layouts/auth.php` | Idem, résolu via `BrandingService::forCurrentRequest()` (pas de session à ce stade) |
| `app/Views/auth/login.php` | Logo et message de bienvenue dynamiques (remplace le calcul local précédent) |
| `app/Controllers/AuthController.php` | `Session::setUser()` stocke désormais `etablissement_id` (nécessaire pour que les pages authentifiées résolvent leur branding sans middleware) |
| `app/Services/MenuService.php` | Nouvel item de menu "Branding" (icône `palette`), visible admin/directeur via `branding.view` |
| `config/permissions.php` | +2 permissions (`branding.view`, `branding.update`) pour admin et directeur |
| `config/routes.php` | +2 routes (`GET`/`POST /parametres/branding`) |

**Aucune vue métier, aucun contrôleur métier, aucun modèle existant modifié** en dehors des points d'intégration ci-dessus.

---

## 4. Ressources gérées (Stockage)

Convention adaptée de §10.3/§12.1 du blueprint à l'architecture déjà
établie dans cette application (voir `ProfesseurController::handlePhoto`)
— les logos doivent être servis directement par URL, contrairement à
`storage/` qui est hors du webroot :

```
public/uploads/branding/{etablissement_id}/{context}_{uniqid}.{ext}
```

- **Logo, favicon, image de connexion** : upload validé par `mime_content_type()`
  réel (pas seulement l'extension — testé avec un fichier PHP déguisé en
  `.png`, rejeté), 2 Mo max, formats PNG/JPEG/WEBP/SVG/ICO.
- **Isolation** : chaque fichier vit sous le dossier de SON établissement ;
  `TenantStorageService::delete()` refuse silencieusement de supprimer un
  fichier hors du dossier du tenant demandé (testé).
- **Extensibilité S3** : `TenantStorageService` expose une interface
  `put()/delete()` conforme à §12.1 (`StorageInterface`) — un futur
  adaptateur S3 (Phase 14.8) remplace l'implémentation sans toucher aux
  appelants (`BrandingService`, `SettingsController`).

---

## 5. Cache

Mémoïsation statique en mémoire PHP, clé = `etablissement_id`, portée à la
requête courante (pas de cache inter-requêtes — Redis prévu Phase 14.9,
explicitement hors périmètre ici). `save()` invalide immédiatement l'entrée
concernée. Vérifié par test : 3 lectures alternées A/B ne se mélangent
jamais, et une modification de B laisse le cache de A intact.

**Architecture extensible respectée** : les champs "étendus" (police,
thème, image de connexion, contact, pied de page, affichage) vivent dans
`etab_settings` (clé/valeur par section) plutôt que dans des colonnes
fixes — toute future personnalisation s'ajoute par une ligne de donnée,
sans migration de schéma.

---

## 6. Intégration — état par composant demandé

| Composant | État |
|---|---|
| Écran de connexion | ✅ Intégré (logo, couleurs, message de bienvenue, police, pied de page) |
| Tableaux de bord / portails | ✅ Intégré (layout `main.php` partagé par toutes les pages authentifiées) |
| Documents générés | ❌ Non traité — voir §9 |
| Rapports PDF | ❌ Non traité — voir §9 |
| Emails | ❌ Non traité — voir §9 |
| Notifications | ❌ Non traité — voir §9 |

---

## 7. Tests réalisés

| Suite | Résultat | Portée |
|---|---|---|
| `tests/Unit/BrandingTenantTest.php` (nouveau) | **18/18 PASS** | Défauts (non-régression), isolation 2 tenants réels, cache, validation upload |
| `tests/Unit/RbacTenantTest.php` (14.4) | **18/18 PASS** | Non-régression RBAC |
| `tests/Unit/TenantIsolationTest.php` (14.3) | **24/24 PASS** | Non-régression données |
| `tests/Unit/TenantResolverTest.php` (14.2) | **29/29 PASS** | Non-régression fondation |
| `tests/security_tests.php` (pré-existant) | **48/48 PASS** | Régression sécurité globale |
| `tests/functional_tests.php` (pré-existant) | **109/109 PASS** | Régression fonctionnelle globale |
| **Total** | **246/246 PASS** | |
| Navigateur réel | ✅ | Login pré-auth (`--violet` = valeur DB, message de bienvenue DB) ; dashboard (title, logo, nom) ; page `/parametres/branding` accessible (200) ; **changement réel de couleur primaire → répercuté immédiatement sur le dashboard → restauré**, 0 erreur 5xx |

**Performances** : vérifiées fonctionnellement et par revue de code (mémoïsation statique empêchant toute lecture DB dupliquée dans une même requête — ex. `login.php` et `auth.php` appellent chacun `forCurrentRequest()` mais ne déclenchent qu'une seule requête SQL grâce au cache). Pas d'instrumentation formelle de comptage de requêtes (jugée disproportionnée à ce stade ; pertinente lors du passage à Redis, Phase 14.9).

---

## 8. Problèmes rencontrés et corrections

**PROBLÈME — Portée des variables entre vue et layout.** Le système de
rendu (`Core\View::render()`) capture le contenu de la vue (`login.php`)
AVANT d'inclure le layout (`auth.php`) : une variable `$branding` calculée
dans le layout n'est donc pas disponible dans la vue déjà rendue.
**Correction :** extraction de la résolution dans une méthode centralisée
et idempotente, `BrandingService::forCurrentRequest()`, appelée
indépendamment par les deux fichiers — même algorithme, aucune duplication
de logique, aucune fuite possible entre les deux points d'appel (vérifié :
les deux résolvent au même établissement dans la même requête).

**PROBLÈME — Permissions manquantes.** `branding.view` / `branding.update`
n'existaient pas dans `config/permissions.php`. Ajoutées pour `admin` et
`directeur` (rôle propriétaire de son établissement selon §8.1 du
blueprint), propagées à `etab_permissions`/`etab_role_permissions` via
T009 — suite RBAC (Phase 14.4) toujours verte après ajout.

Aucune régression détectée sur les fonctionnalités existantes.

---

## 9. Dette technique / périmètre non traité (décision transparente)

| Élément | Raison de l'exclusion |
|---|---|
| Documents générés, rapports PDF, emails, notifications | Surface de modification trop large et adjacente à de la logique métier existante (templates de bulletins, `EmailService`, `SmsService`) pour ce périmètre "sécurité/branding uniquement" ; `BrandingService::get()` est prêt à être consommé par ces générateurs, câblage différé |
| `theme_mode` (clair/sombre) | Champ stocké et modifiable dans l'interface admin, mais **aucun rendu visuel sombre** — le design system CSS actuel n'a pas de règles dark mode ; implémenter le thème sombre est un chantier CSS à part entière, hors "adaptation du branding" |
| `app/Views/home/index.php` (page publique) | Landing page marketing, volontairement laissée statique (analogue à une page d'accueil SaaS avant sélection d'établissement) |
| `public/manifest.json` | Fichier JSON statique (PWA) — le rendre dynamique par tenant nécessiterait une route dédiée, non requis par les critères de validation du blueprint pour cette phase |
| `app/Views/errors/403.php`, `404.php`, `500.php` | Pages d'erreur historiquement brandées en dur (session antérieure) — laissées telles quelles, risque/bénéfice jugé négligeable |

Aucun de ces points ne constitue une fuite de données inter-tenant ni une
régression — ce sont des zones volontairement non couvertes, documentées
pour décision ultérieure.

---

## 10. Décision finale : **GO WITH FIXES**

**GO** sur le périmètre livré : personnalisation complète et isolée
(identité, couleurs, images, contact, pied de page, affichage), interface
admin fonctionnelle, upload sécurisé, cache isolé par tenant, intégration
réelle écran de connexion + zone applicative, zéro régression.

**WITH FIXES** — les 5 éléments du §9 restent à statuer : aucun ne bloque
la validité du travail livré, mais ils doivent être explicitement acceptés
comme hors périmètre (ou planifiés) avant de considérer le branding
"complet" au sens large du blueprint.

---

## 11. Compatibilité V2

**100% compatible.** Établissement existant : rendu visuel identique
(vérifié via les valeurs par défaut = valeurs historiques). Aucune route,
vue ou contrôleur métier existant modifié. Nouvelle table `etab_settings`
et `etablissement_branding` déjà créées en Phase 14.2, seulement peuplées
ici — aucun changement de schéma.
