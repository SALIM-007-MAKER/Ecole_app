# Lot 3 — Étape 2 : isolation du module Académique (inventaire par table)

Contexte d'établissement : `TenantContext`, positionné pour toute requête authentifiée par `SessionTenantGuard` (étape 1).
Filtrage : `Core\Tenant\TenantScope` (jointure sur la classe pour les tables sans colonne, colonne directe pour les référentiels).
Sans contexte (scripts CLI, tests hors HTTP, pages publiques) aucun filtre n'est ajouté : comportement historique.

## Propriétaire fonctionnel et règle de rattachement

| Table | Propriétaire | Rattachement | Mécanisme | Migration |
|---|---|---|---|---|
| `evaluations` | établissement de sa **classe** | `classe_id → classes.etablissement_id` | jointure (`ownershipRestriction`, `TenantScope::classe`) | aucune |
| `notes_v2` | établissement de la classe de son **évaluation** | `evaluation_id → evaluations.classe_id → classes` | jointure | aucune |
| `notes_historique` | idem, via la note | `note_id → notes_v2 → …` | jointure (`restrictionNote`) | aucune |
| `bulletins_v2` | établissement de sa **classe** (et de l'élève) | `classe_id`, `eleve_id → eleves.etablissement_id` | jointure | aucune |
| `bulletin_tokens_retired` | via le bulletin | lecture publique par empreinte (vérification d'authenticité) : **volontairement non filtrée** | — | aucune |
| `appreciations_matiere` | colonne `etablissement_id` existante | + l'élève doit appartenir à la classe soumise | colonne + contrôle d'élève | aucune |
| `inscriptions` | établissement de l'élève / de la classe | module **Scolarité désactivé** (non servi en HTTP) ; l'Académique ne l'interroge qu'avec des identifiants déjà validés | — | aucune (limite documentée) |
| `periodes_scolaires` | **établissement** | classes des évaluations et des bulletins qui la référencent (un seul établissement ⇒ rattachée) | colonne `etablissement_id` | **T051** |
| `types_evaluations` | **établissement** | usage par les classes des évaluations ; types **système** = catalogue copié par établissement | colonne `etablissement_id` | **T052** |
| `periodes_scolaires_config` | **établissement** (gabarit copié) | gabarit sans propriétaire, une copie par établissement | colonne `etablissement_id` | **T053** |

## Données réelles actuelles (base `ecole_app`, un seul établissement)

- `periodes_scolaires` : 4 lignes, toutes utilisées par des évaluations ET des bulletins de l'établissement 1 ⇒ **rattachement démontré**, 0 ambiguïté.
- `types_evaluations` : 5 types système. `devoir` et `examen` sont utilisés (⇒ établissement 1). `controle`, `tp`, `oral` ne sont utilisés par aucune évaluation :
  ce sont des **gabarits système** (catalogue sans propriétaire) ; l'établissement 1 reçoit sa propre copie de chacun (ce n'est pas une attribution de propriété).
  Résultat attendu : 10 lignes (5 gabarits + 5 types de l'établissement 1), 0 ambiguïté.
- `periodes_scolaires_config` : 2 lignes sans propriétaire ⇒ gabarit conservé + copie pour l'établissement 1.

## Ambiguïtés : traitement

Une ligne dont le propriétaire n'est pas démontré (période sans usage, période ou type utilisé par plusieurs établissements, type non système sans usage)
reste à `NULL`, est signalée par `[AMBIGU]` dans la sortie de la migration et est **invisible de tous** jusqu'à arbitrage. Aucune valeur par défaut vers l'établissement 1.

## Structure, index, compatibilité, retour arrière

- Colonne `etablissement_id INT UNSIGNED NULL`, index, clé étrangère `ON DELETE RESTRICT` vers `etablissements`.
- Unicités : périodes `(etablissement_id, annee_scolaire, type_periode, numero)` ; types `(etablissement_id, code)` ; configuration `(etablissement_id, numero)`.
- **Compatibilité avant migration** : le code détecte la colonne (`TenantScope::colonneEtablissement`) ; sans elle, ces trois référentiels restent partagés
  (comportement actuel). Toutes les autres protections (jointure par classe) s'appliquent immédiatement.
- **Retour arrière** : T053 → T052 → T051, vérifié sur copie (schéma et données **identiques** à l'état initial). T052 refuse si des types référencés existent
  en plusieurs exemplaires. Les `UPDATE` de rattachement conservent `updated_at`.
- **Aucune migration appliquée à la base réelle** à ce stade.

## Points d'accès corrigés (lecture et écriture, identifiant et liste)

Évaluations (liste, fiche, modification, publier, verrouiller, déverrouiller, archiver), notes (liste, fiche, saisie, import, modification, suppression, publication,
verrouillage, historique), bulletins (impression, appréciation du directeur, régénération, publication, archivage), appréciations de matière, résultats
(classe, classement, moyennes — classe et période de l'URL ignorées si étrangères), périodes (liste, fiche, modification, activation, clôture, verrouillage,
archivage, génération), types d'évaluation, configuration des périodes, analytique. Les identifiants soumis par les formulaires (classe, matière, enseignant,
période, type, élève) doivent appartenir à l'établissement courant.

## Limites et constats hors périmètre

- Vie scolaire (étape 3) et module Scolarité (désactivé) non traités ici.
- Pages publiques et scripts sans contexte : pas de filtre (historique).
- Constats **non corrigés**, antérieurs au lot : la constante inexistante `ENT_JS` provoque une erreur fatale dans 9 vues (liste des périodes avec le bouton
  « Archiver », évaluations, types d'évaluation, etc.) ; la page de gestion des types d'évaluation est inaccessible à l'administrateur (permission absente du palier actif).
- Corrigé au passage (bloquait les tests) : `PeriodeScolaireFiltersDTO` lisait une clé `per_page` absente et obtenait une liste vide.
