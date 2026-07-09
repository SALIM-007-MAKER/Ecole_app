# Standards de code — SCOLARIS V2

## 1. Principe fondateur : zéro dépendance tierce

Ce projet ne possède **ni `composer.json` ni `vendor/`**. Tout — autoloading,
routage, accès base de données, tests, génération PDF, gestion d'événements — est
écrit à la main en PHP pur. C'est un choix délibéré, constant depuis l'origine du
projet à travers des dizaines de phases de développement, pas un oubli.
**Implication directe** : n'introduisez jamais une dépendance Composer sans
discussion préalable — cela romprait la cohérence architecturale entière du
projet (voir `docs/deploiement/DOCKER.md` §1 pour l'impact déploiement).

## 2. Style PHP

- **PHP 8.2**, `declare(strict_types=1)` sur tout nouveau fichier `core/` et
  `app/Modules/*` (les fichiers V1 plus anciens n'en ont pas systématiquement —
  ne pas l'ajouter rétroactivement sans raison, cela peut changer un comportement
  de coercition de type existant).
- Typage des propriétés/paramètres/retours partout où c'est raisonnable.
- `final class` par défaut pour les services/DTO qui ne sont pas conçus pour être
  étendus.
- Propriétés promues en constructeur (`public function __construct(private
  readonly Foo $foo)`) pour les DTO et services immuables.
- Noms de classes en `PascalCase`, méthodes/propriétés en `camelCase`, constantes
  en `SCREAMING_SNAKE_CASE`.

## 3. Commentaires et documentation en ligne

- Docblocks **uniquement** quand ils apportent une information que le code seul ne
  donne pas (contrainte cachée, invariant, contournement d'un bug précis,
  comportement surprenant). Ne jamais documenter ce qu'un nom bien choisi dit déjà.
- Pas de blocs de commentaires multi-paragraphes en tête de méthode "ce que fait
  cette méthode" — le nom de la méthode et son corps doivent suffire.
- Quand une décision de conception n'est pas évidente (ex. pourquoi une
  restauration tenant utilise un upsert plutôt qu'un delete+insert), un
  commentaire court expliquant le **pourquoi** est bienvenu — voir
  `core/Backup/DatabaseRestorer.php` pour un exemple représentatif du niveau de
  détail attendu.

## 4. Sécurité — non négociable

- Aucune requête SQL construite par concaténation de valeur utilisateur —
  toujours des requêtes préparées (`PDO::prepare()` + paramètres liés).
- CSRF systématique sur toute action `POST` (`Core\Controller::verifyCsrf()`).
- Suppression **toujours logique** (`deleted_at`) — jamais de `DROP TABLE` ni de
  `DELETE` physique sur des données créées par un utilisateur.
- Toute vérification de permission passe par `Core\Controller::requirePermission()`/
  `can()` — jamais une condition ad hoc sur `$user['role']`.
- Aucun secret en dur dans le code — toujours via `.env`/`config/*.php`.

## 5. Isolation multi-tenant dans le nouveau code

Toute nouvelle table portant des données propres à un établissement **doit**
avoir une colonne `etablissement_id` indexée. Tout nouveau Repository/Service
manipulant une telle table doit filtrer explicitement dessus — voir
`docs/technique/MULTI_TENANT.md` et le patron `Core\Tenant\TenantCache`/
`TenantQuotaService` pour l'exemple de "aucune méthode n'accepte de clé sans
établissement explicite".

## 6. Tests

Voir `docs/developpeur/TESTS.md` — format manuel (pas de framework), rollback
systématique des données de test.

## 7. Ce qu'il ne faut jamais faire

- Introduire une dépendance Composer sans discussion (§1).
- Écrire directement en base depuis un contrôleur V2 — passer par
  Service → Event → Listener (voir `docs/technique/EVENT_SYSTEM.md`).
- Dupliquer une table/un préfixe déjà utilisé par un autre module.
- Supprimer un contrôleur/route V1 au prétexte qu'un module V2 équivalent existe —
  les deux doivent coexister (voir `docs/technique/ARCHITECTURE_GLOBALE.md` §2).
- Modifier `core/Router.php` sans re-tester les trois conventions de handler
  existantes (nom brut V1, chemin partiel V2, nom pleinement qualifié
  `Controller::class` — voir l'historique du bug corrigé en `RELEASE_CANDIDATE_RC1_REPORT.md` §2.1).
