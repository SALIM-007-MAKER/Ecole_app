# Programme pédagogique par classe — règles (T075 + T076)

## Matières obligatoires et facultatives (`classe_matieres.obligatoire`)

Réglage **par matière et par classe** (1 = obligatoire, défaut ; 0 = facultative).

| Élément | Matière obligatoire | Matière facultative |
|---|---|---|
| Moyenne **de la matière** (bulletin, relevés) | calculée et affichée | calculée et affichée (identique) |
| Moyenne **générale** de la période | pèse son coefficient | **poids 0** : ignorée |
| **Classement** (classe, niveau) | idem moyenne générale | idem : ignorée |
| Coefficient affiché (fiche programme, enseignements, analyse par matière) | coefficient déclaré | coefficient **déclaré** (non faussé) |
| Bulletin (ligne de la matière) | coefficient affiché | coefficient affiché **0** |

Mise en œuvre : `ProgrammeSql::coefficient()` (calculs) renvoie 0 pour une facultative ; `ProgrammeSql::coefficientDeclare()` (affichage) renvoie la valeur saisie.
Si toutes les matières comptées ont un poids 0, la moyenne est « vide » (pas de division par zéro).

Résultats historiques : **aucun recalcul automatique**. Les bulletins enregistrés, moyennes en base, notes et évaluations ne sont jamais modifiés ; seuls les aperçus/calculs en direct reflètent le nouveau réglage.
Un programme est en lecture seule dès qu'un bulletin publié ou archivé existe pour la classe. Si des notes existent, changer le coefficient ou le caractère obligatoire exige une confirmation explicite.

## Matières actives / inactives (`matieres.actif`, T076)
Désactivée : non proposée pour de **nouveaux** enseignements, lignes de programme, évaluations, reprises ; l'existant (affectations, notes, bulletins) est conservé. Réactivable. Une matière déjà utilisée ne peut pas être supprimée (la désactiver). La Conduite ne se désactive pas.

## Nom d'affichage (`matieres.nom_affichage`, T076)
Propre à l'établissement (les matières le sont). `nom` reste la référence (unicité, identifiants). Appliqué : bulletins, classement, évaluations, notes, analyses académiques, enseignements, programme, listes d'affectation. **Non encore appliqué** : portails, RH, emplois du temps, API, exports Rapports/BI (ils affichent le nom de référence).

## Reprise à la création d'une classe
Choix facultatif de la classe source (même niveau **et** même série, programme non vide), confirmation obligatoire, tout vérifié avant la création ; jamais d'écrasement.
