# Jeu de données de référence des tests

`ecole_app_reference.sql` est un export complet (schéma, données, clés étrangères, déclencheurs) d'un état de démonstration de l'application.
Les tests le chargent dans une base temporaire (`DbCopy::create()`), au lieu de copier la base de développement : on peut donc **vider ou modifier
librement la base vivante** sans casser la suite de tests.

## Contenu et confidentialité
- Données de démonstration uniquement (23 élèves, 36 bulletins, notes, factures, absences, notifications, 16 comptes).
- **Anonymisé** : noms et prénoms remplacés par `Nom01` / `Prenom01` (y compris dans les bulletins, notifications et journaux d'audit),
  téléphones, adresses et dates de naissance remplacés. Les tests qui cherchent un nom utilisent ces pseudonymes (ex. `Nom08`).
  L'outil `Anonymiseur.php` lit les noms dans la base elle-même (aucun nom réel dans le dépôt) ; à lancer sur toute copie issue de vraies données.
- Tous les comptes partagent le **même hachage de mot de passe de test** (mot de passe de test : `Test-1234!`), sans rapport avec les mots de passe réels.
- Aucun secret (`bulletin_secret` neutralisé), aucune adresse e-mail ni téléphone réels. `JeuDeReferenceTest` vérifie ces points.

## Quand le mettre à jour
Après l'ajout d'une migration : `JeuDeReferenceTest` échoue tant que le jeu ne contient pas toutes les migrations du dépôt.

    php tests/Support/fixtures/mettre_a_jour.php

Le script applique les migrations en attente sur une copie jetable, exporte, neutralise puis remplace le fichier. Il ne lit ni n'écrit la base vivante.

## Retrouver l'ancien comportement
`DBCOPY_SOURCE=live php tests/Unit/<Test>.php` copie la base vivante (utile pour essayer une migration sur des données réelles).
