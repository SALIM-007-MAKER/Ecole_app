# Lot 3 — Étape 3 : isolation de la Vie scolaire et des fiches V1 (inventaire par table)

Contexte d'établissement : `TenantContext`, positionné pour toute requête authentifiée par `SessionTenantGuard` (étape 1).
Trois niveaux de protection, du plus large au plus fin :

1. **Garde de frontière `Core\Tenant\ResourceGuard`** (appelé par le routeur avant tout contrôleur) : toute ressource désignée par un
   paramètre de route, un champ de formulaire (`eleve_id`, `classe_id`, `salle_id`, `categorie_id`…) ou une clé de tableau de saisie
   (`presences[eleveId]`) doit appartenir à l'établissement courant ; sinon **404** (jamais 403 : pas d'oracle d'existence) et journal de sécurité.
   Ressource inexistante : le contrôleur répond « introuvable ». Erreur de résolution : refus (fail-closed).
2. **Dépôts** : listes, statistiques, exports et classements filtrés à la source (`TenantScope`).
3. **Modèles** : référentiels propres (`Core\Tenant\ReferentielPropre`) et fiches V1 (`tenantScoped`).

## Propriétaire fonctionnel et règle de rattachement

| Table | Propriétaire | Rattachement | Migration |
|---|---|---|---|
| `eleves`, `classes`, `matieres`, `professeurs`, `enseignements` | établissement (colonne existante) | colonne | — |
| `vs_absences`, `vs_retards`, `vs_dossiers_discipline`, `vs_recompenses`, `vs_emplois_du_temps`, `vs_edt_creneaux`, `vs_appels` | établissement de la **classe** | `classe_id → classes.etablissement_id` | — |
| `vs_justifications_*`, `vs_incidents_discipline`, `vs_sanctions_discipline`, `vs_appels_discipline`, `vs_edt_remplacements`, `vs_edt_versions` | via la ressource parente | absence / retard / dossier / créneau / EDT | — |
| `vs_activites` (+ inscriptions, classes, responsables, historique) | établissement de l'**utilisateur créateur** (`cree_par`) | `users.etablissement_id` | — |
| `vs_motifs_absence`, `vs_discipline_categories`, `vs_recompense_categories`, `vs_activite_categories` | établissement — **catalogues** livrés par l'application | usage ; copies du catalogue par établissement | **T054** |
| `vs_edt_salles`, `vs_edt_plages_horaires` | établissement — **données propres** (aucun catalogue) | usage (créneaux) ; sinon arbitrage explicite | **T055** |

## Données réelles actuelles (un seul établissement)

- Catalogues : 7 motifs, 10 catégories de discipline, 8 de récompenses, 8 d'activités. L'usage ne démontre rien pour la plupart (peu ou pas de lignes) :
  ce sont des **gabarits**, dont l'établissement 1 reçoit sa copie. Le seul usage existant (1 incident, catégorie `INCIVILITE`) référence un **dossier qui n'existe pas**
  (donnée orpheline) : signalé `[AMBIGU]`, non rattaché.
- Salles (8) et plages horaires (11) : **aucun créneau** ⇒ propriété non démontrée ⇒ restent à `NULL` (invisibles) après la migration, jusqu'à arbitrage explicite :
  `php database/tools/lot3_arbitrage.php` (liste, lecture seule) puis `--appliquer --etab=ID --confirmer`. Ne jamais lancer T055 sans prévoir cet arbitrage,
  sinon l'établissement ne verra plus ses salles et plages (les créneaux existants étant au nombre de 0, aucune donnée n'est perdue).

## Structure, compatibilité, retour arrière

- `etablissement_id INT UNSIGNED NULL` + index + clé étrangère `ON DELETE RESTRICT` ; unicités `(etablissement_id, code)`.
- **Avant migration** : le code détecte la colonne ; les six référentiels restent partagés (aucune page cassée — testé). Les protections par classe s'appliquent immédiatement.
- **Retour arrière** T055 → T054, vérifié sur copie : schéma et données identiques. T054 refuse si un code référencé existe en plusieurs exemplaires.
- **Aucune migration appliquée à la base réelle.**

## Corrigé au passage (bloquait la vérification)

- Le formulaire d'activité interrogeait des tables inexistantes (`user_roles`, `roles`) : erreur fatale à l'ouverture de « Nouvelle activité ». La liste d'enseignants lit désormais `users.role`, filtrée par établissement.

## Limites et constats hors périmètre

- Module Scolarité (désactivé) et pages publiques non traités ; sans contexte (CLI, tests hors HTTP) aucun filtre n'est ajouté (comportement historique).
- Le module Présences s'appuie sur les tables `vs_appels` / `vs_presences` **absentes de la base réelle** (migration 002 jamais appliquée) : ses routes échouent déjà ;
  elles sont désormais refusées (404) par le garde plutôt qu'en erreur serveur.
- Les listes d'enseignants de la Vie scolaire reposent sur `users.role` ; un enseignant rattaché à plusieurs établissements apparaît via `user_etablissements`.
