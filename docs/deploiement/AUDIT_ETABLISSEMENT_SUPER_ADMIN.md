# Consultation des journaux d'établissement par le Super-Admin

Dernier point du plan Super-Admin SaaS. Le Super-Admin peut lire le journal d'audit (`audit_logs`, voir `AUDIT_ETABLISSEMENT.md`) de **n'importe quel établissement**, en lecture seule, et **sa lecture est elle-même tracée**.

- Accès : fiche d'un établissement (`/platform/etablissements/{id}`) → bouton « Journal d'audit », ou directement `/platform/etablissements/{id}/audit`.
- Réservé au niveau **super_admin**. Les niveaux admin et support reçoivent un 403 (refus inscrit au journal global) ; un compte d'établissement n'a aucun accès au portail.

## Ce que l'on peut faire

| Page | Contenu |
|---|---|
| `/platform/etablissements/{id}/audit` | Liste paginée (50 par page) avec les mêmes filtres que côté école : recherche, dates, utilisateur, module, action, résultat |
| `/platform/etablissements/{id}/audit/{événement}` | Détail d'un événement (ancienne et nouvelle valeur) |
| `/platform/etablissements/{id}/audit/export` | Export CSV (BOM UTF-8, séparateur `;`, formules neutralisées), 50 000 lignes au plus, mêmes filtres |

Aucune route de modification ni de suppression n'existe (GET uniquement ; POST, PUT et DELETE répondent 404).

## Isolation

- L'établissement lu est celui de l'URL, vérifié existant (sinon 404). Toutes les requêtes filtrent dessus : `AuditService` exige un identifiant explicite.
- Un événement d'un autre établissement demandé via l'URL de celui-ci répond **404**, indiscernable d'un identifiant inexistant.
- Le contexte tenant n'est pas utilisé : le Super-Admin n'appartient à aucun établissement.

## Traçabilité de la lecture

Chaque consultation est inscrite au **journal d'audit global** (`/platform/audit`) **avant** que les données soient servies :

| Action | Déclencheur |
|---|---|
| `etablissement.audit.viewed` | Affichage de la liste (filtres non vides et page conservés) |
| `etablissement.audit.event_viewed` | Affichage du détail (identifiant de l'événement) |
| `etablissement.audit.exported` | Export CSV (filtres conservés) |

Chaque ligne porte l'opérateur, son niveau, l'établissement consulté, l'adresse IP et le navigateur. **Le contenu consulté n'est jamais recopié** dans le journal global.

L'écriture est en mode **strict** : si la trace ne peut pas être écrite, **rien n'est affiché** (503) et l'incident est consigné dans le journal technique. Contrairement aux autres opérations de la plateforme, il n'y a donc pas de repli sur le fichier de secours : lire les données d'une école sans trace n'est pas acceptable.

## Limites

- Les établissements sans migration T065 (colonne `etablissement_id`) affichent « journal indisponible ».
- Les noms des élèves et des parents ne sont jamais présents dans ce journal (règle de l'étape 2) ; le Super-Admin voit les noms du personnel.
- Pas de notification à l'école lors d'une consultation : la trace est côté plateforme uniquement.

## Tests

`php tests/Unit/PlatformEtablissementAuditTest.php` : accès par niveau, isolation entre deux établissements, filtres, pagination, traçabilité (liste, détail, export), export CSV, lecture seule, panne du journal global.
