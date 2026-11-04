# Conduite — migrations T048 à T050, déclencheurs de protection et déploiement

La Conduite est une **note de comportement**, pas une matière d'enseignement : coefficient 1, aucune heure de cours,
absente des emplois du temps, une seule par établissement, non supprimable. Elle est identifiée par la colonne
`matieres.est_conduite` (jamais par son libellé).

## 1. Ordre obligatoire : migrations AVANT le code

Le code lit `matieres.est_conduite`. **Appliquer les migrations avant (ou en même temps que) le déploiement du code** :
sans la colonne, les pages d'évaluations, de notes, de bulletins, de matières et d'emplois du temps échouent.

| Migration | Effet | Retour arrière |
|---|---|---|
| `T048_matieres_est_conduite` | colonne `est_conduite` (NULL = matière ordinaire, 1 = Conduite), index unique `(etablissement_id, est_conduite)` (au plus une Conduite par établissement), marquage de la matière « Conduite » de chaque établissement, volume horaire à 0. S'arrête sans écrire si plusieurs matières « Conduite » existent dans un établissement ou si le coefficient n'est pas 1. | retire l'index et la colonne (refusé tant que T050 est en place) |
| `T049_permission_conduite_manage` | permission `academique.conduite.manage` pour les rôles système **admin** et **directeur** uniquement | retire les attributions puis la permission |
| `T050_matieres_conduite_declencheurs` | trois déclencheurs sur `matieres` (BEFORE INSERT / UPDATE / DELETE) | supprime les déclencheurs |

Ordre d'application : `T048` → `T049` → `T050`. **Ordre de retour arrière : `T050` → `T049` → `T048`** (annuler T048 avant T050 est refusé).

```
C:/wamp64/bin/php/php8.2.29/php.exe database/migrate.php --id=T048
C:/wamp64/bin/php/php8.2.29/php.exe database/migrate.php --id=T049
C:/wamp64/bin/php/php8.2.29/php.exe database/migrate.php --id=T050
```

Aucune suppression de donnée, aucune note, évaluation ni bulletin modifié. Faire une **sauvegarde** avant (voir `SAUVEGARDES.md`).
Les droits ne changent qu'à la prochaine connexion des intéressés (session).

## 2. Ce que protègent les déclencheurs (T050)

- `BEFORE DELETE` : suppression d'une Conduite refusée.
- `BEFORE UPDATE` : pour une Conduite, retrait ou changement du marqueur, coefficient ≠ 1, volume horaire ≠ 0 et changement
  d'établissement refusés ; pour une matière ordinaire passée en Conduite, seuls coefficient 1 et volume 0 sont acceptés. Le libellé,
  la catégorie, le responsable et la description restent modifiables.
- `BEFORE INSERT` : une Conduite ne peut être créée qu'avec coefficient 1 et volume 0.

Erreur renvoyée : SQLSTATE `45000` avec un message explicite. Une seconde Conduite dans un établissement est refusée par l'index
unique (erreur 1062).

### Limites (à connaître)

- `TRUNCATE`, `DROP TABLE`, `ALTER TABLE` et les comptes MySQL possédant `TRIGGER` ou `SUPER` **contournent ou suppriment** la protection.
- Les contrôles applicatifs restent la première ligne de défense ; la base est un dernier garde-fou.
- Un export `mysqldump` écrit un `DEFINER` ; pour restaurer sur un autre serveur, **retirer la clause `DEFINER`** (ou créer le compte).
- `migrate.php` **ne revérifie pas** une migration déjà en `success` : si un déclencheur est supprimé plus tard, seuls le contrôle
  en direct et l'outil `--check` le détectent.

## 3. Prérequis d'environnement et échec explicite de T050

T050 vérifie, avant toute création : le droit `TRIGGER`, et soit journal binaire inactif, soit `SUPER`, soit
`log_bin_trust_function_creators = 1` (sinon MySQL peut refuser la création : erreur 1419).

T050 est **tout ou rien** : si un déclencheur ne peut pas être créé, ceux déjà créés sont supprimés et la migration échoue avec :

> `T050 ÉCHEC — la protection SQL de la Conduite N'EST PAS opérationnelle : <cause>`

`migrate.php` journalise l'échec dans `migrations_log` (`failed`), poursuit les autres migrations, **retourne le code 1**, et
retente T050 au passage suivant. T050 n'est `success` que si la vérification finale (présence, moment, événement, **corps**) est valide.

## 4. Vérification : `database/tools/conduite_protection.php --check`

```
C:/wamp64/bin/php/php8.2.29/php.exe database/tools/conduite_protection.php --check
```

Contrôle de **métadonnées uniquement** (aucune écriture, aucune tentative de suppression) : nom, moment, événement, table et corps
normalisé des trois déclencheurs. Code retour **0** = opérationnelle ; **1** = absente, incomplète ou modifiée (un déclencheur au
corps vide est détecté) ; 2 = erreur de connexion.

**Un déploiement est considéré échoué si `migrate.php` ou `conduite_protection.php --check` retourne autre chose que 0.**
Le dépôt n'a aucun automatisme de déploiement : ce blocage repose sur cette procédure et sur ces codes de retour.

L'état est aussi affiché **en direct** (jamais stocké) sur la fiche de la matière Conduite — « Protection SQL : opérationnelle / NON
opérationnelle » avec un bandeau d'alerte pour les profils `academique.conduite.manage` — et un message critique est journalisé.

## 5. Mode strict (`CONDUITE_PROTECTION_REQUIRED`)

Quand la protection SQL n'est pas opérationnelle, le mode strict **bloque la régénération en vue de la publication et la
publication** des bulletins (les brouillons restent possibles).

- **Activé par défaut en production** (`APP_ENV` absent ou `production`).
- Désactivable explicitement : `CONDUITE_PROTECTION_REQUIRED=0` (par exemple en développement).
- Activable explicitement hors production : `CONDUITE_PROTECTION_REQUIRED=1`.

## 6. Publication des bulletins : ce que le code contrôle

La publication s'exécute dans **une transaction `REPEATABLE READ`** (isolation fixée puis **vérifiée**, attente de verrou limitée à
5 s, restaurée ensuite) qui verrouille, dans l'ordre, évaluations de Conduite → élèves actifs → notes → bulletin, puis contrôle :

1. **Complétude stricte** : chaque élève actif a une note valide (0 compris, pas d'« absent ») dans chaque évaluation de Conduite
   active (publiée ou verrouillée) de la classe et de la période ; au moins une évaluation active doit exister.
2. **Format de l'instantané** (`snapshot_version`) à jour ; sinon « régénérer d'abord ».
3. **Fraîcheur réelle** : la ligne Conduite de l'instantané est comparée champ par champ à celle recalculée depuis les notes
   actuelles. `conduite_complete` n'est qu'une indication d'affichage, jamais une preuve.
4. Bascule `WHERE statut = 'brouillon'` avec vérification du nombre de lignes modifiées.

En cas d'interblocage (1213) ou d'attente dépassée (1205) : **une seule** nouvelle tentative, avec une transaction neuve et tous les
contrôles refaits. Au-delà : message « réessayez ». Une saisie de note peut attendre jusqu'à 5 s pendant une publication.

La **régénération en vue de la publication** est une action explicite (`POST /v2/academique/bulletins/{eleveId}/{periodeId}/regenerer` :
authentification, CSRF, droit de génération, même établissement, statut brouillon) ; elle ne publie jamais. Les **brouillons
existants** (ancien format) doivent tous être régénérés explicitement avant publication — jamais automatiquement.

Outils en lecture seule : `database/tools/conduite_bulletins_concernes.php` (bulletins à régénérer) et
`database/tools/conduite_doublons.php` (évaluations de Conduite en doublon par classe et période).

## 7. Procédures

**Installation** : sauvegarde → `migrate.php` (T048, T049, T050) → `conduite_protection.php --check` (code 0) → déploiement du code
→ reconnexion des administrateurs et directeurs (nouvelle permission).

**Retour arrière** : `database/migrate.php` ne sait pas annuler ; appeler les fonctions `rollback` dans l'ordre T050 → T049 → T048
(chaque fichier de `database/migrations/` expose `rollback`). Restaurer d'abord le code précédent, car il ne lit pas `est_conduite`.

**Restauration d'une sauvegarde** : si l'export contient les déclencheurs, retirer la clause `DEFINER` (§ 2) puis relancer
`conduite_protection.php --check`.

**Test facultatif (hors exécution par défaut)** : le comportement de T050 avec un compte MySQL restreint et le journal binaire actif
(erreur 1419) doit être vérifié dans un conteneur MySQL **éphémère et isolé**, jamais sur un serveur réel ou partagé.
