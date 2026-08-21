# Guide Administrateur — SCOLARIS V2

**Public :** comptes avec le rôle **Administrateur** (`admin`) ou **Direction**. C'est l'accès
le plus large de l'application : tous les modules opérationnels sont visibles, sous réserve
des permissions effectivement accordées à votre rôle (voir [§ 14 — Rôles et permissions](#14-rôles-et-permissions)).

> Document mis à jour le 20 août 2026, à partir de la structure réelle du menu
> (`app/Services/MenuService.php`) et des écrans en production. Les chemins entre
> parenthèses sont les URLs directes, utiles si un raccourci n'est pas encore dans le menu.

## Sommaire

1. [Connexion et tableau de bord](#1-connexion-et-tableau-de-bord)
2. [Flux de travail recommandés](#2-flux-de-travail-recommandés)
3. [Comptes et utilisateurs](#3-comptes-et-utilisateurs)
4. [Élèves](#4-élèves)
5. [Scolarité — enseignants, classes, matières](#5-scolarité--enseignants-classes-matières)
6. [Académique — évaluations, notes, bulletins, absences](#6-académique--évaluations-notes-bulletins-absences)
7. [Vie scolaire — retards, discipline, récompenses](#7-vie-scolaire--retards-discipline-récompenses)
8. [Ressources humaines](#8-ressources-humaines)
9. [Finance](#9-finance)
10. [Planning — emploi du temps, salles, plages horaires](#10-planning--emploi-du-temps-salles-plages-horaires)
11. [Rapports](#11-rapports)
12. [Paramètres de l'établissement](#12-paramètres-de-létablissement)
13. [Annonces et notifications](#13-annonces-et-notifications)
14. [Rôles et permissions](#14-rôles-et-permissions)
15. [Portail Super-Admin SaaS](#15-portail-super-admin-saas)
16. [Points de vigilance](#16-points-de-vigilance)

---

## 1. Connexion et tableau de bord

Rendez-vous sur `/login` avec votre email et votre mot de passe.

- **Plusieurs établissements** : si votre compte est rattaché à plusieurs écoles, un écran de
  sélection s'affiche après connexion. Vous pouvez ensuite changer d'établissement à tout
  moment via le sélecteur en haut de l'écran, sans vous reconnecter.
- **Mot de passe oublié** : lien « Mot de passe oublié » sur l'écran de connexion — un email
  contenant un lien de réinitialisation à usage unique est envoyé.
- **Profil** (`/profile`) : modifiez vos propres informations et votre mot de passe depuis le
  menu du compte (en haut à droite).
- **Tableau de bord** (`/dashboard`) : vue d'ensemble — effectifs, alertes récentes, raccourcis
  vers les tâches fréquentes. Le contenu s'adapte aux modules réellement actifs.

Le menu de gauche est **filtré par permission** : un écran qui n'apparaît pas chez vous est soit
désactivé pour l'établissement, soit hors de votre rôle — ce n'est pas nécessairement un bug.

---

## 2. Flux de travail recommandés

Le reste du guide décrit chaque écran indépendamment. Cette section indique dans quel **ordre**
les utiliser pour les situations où l'enchaînement compte réellement — certaines étapes ont une
vraie dépendance technique sur la précédente (ex. impossible de créer une évaluation sans
période existante), d'autres sont juste des bonnes pratiques ; le tableau le précise à chaque
fois.

### 2.1 Démarrer une nouvelle année scolaire

| # | Étape | Où | Pourquoi cet ordre |
|---|---|---|---|
| 1 | Configurer l'établissement | § 12, `/parametres/etablissement` | Nom, logo, coordonnées — repris sur tous les documents générés ensuite (bulletins, factures, reçus). |
| 2 | Créer l'année scolaire | § 12, `/parametres/annee-scolaire` | Classes, périodes et frais s'y rattachent tous ensuite. |
| 3 | Configurer les périodes | § 6.1, `/v2/academique/periodes` | **Bloquant** : le formulaire d'évaluation exige une période existante. Utilisez « Générer » pour créer tout le semestriel d'un coup plutôt qu'une par une. |
| 4 | Configurer les matières et coefficients | § 5, `/matieres/create` | **Bloquant** pour les évaluations (matière obligatoire) et pour le calcul des moyennes. |
| 5 | Créer les classes | § 5, `/classes/create` | Élèves, emploi du temps et évaluations s'y rattachent. |
| 6 | Créer ou importer les élèves | § 4, `/eleves/create` ou `/eleves/import` | Affectez-les à une classe ici, ou plus tard depuis la fiche de la classe. |
| 7 | Affecter les enseignants | § 5 (fiche) + § 8 (RH) | Matière + classe pour chaque enseignant. Non bloquant pour créer une évaluation (l'enseignant y est optionnel), mais nécessaire avant la saisie des notes en pratique. |
| 8 | Configurer les types d'évaluation | § 6.2, `/v2/academique/types-evaluations` | **Bloquant** : référentiel obligatoire, réutilisé par chaque évaluation. |
| 9 | Créer les évaluations | § 6.3 | Une par contrôle/devoir — rattachée à une période + un type + une classe + une matière. |
| 10 | Saisir les notes | § 6.4 | Grille par évaluation, élève par élève. |
| 11 | Publier puis verrouiller | § 6.3 (évaluation) / § 6.1 (période) | Publier rend les notes visibles aux familles ; verrouiller fige la période. Vérifiez tout avant de verrouiller — l'opération est volontairement difficile à annuler (§ 16). |
| 12 | Générer les bulletins | § 6.6 | Dernière étape — s'appuie sur les moyennes calculées après verrouillage de la période. |

**En parallèle, hors de cette chaîne :** la configuration financière (frais scolaires puis
facturation, § 9) et l'emploi du temps (§ 10) peuvent être préparés dès que les classes
existent (étape 5) — ils ne bloquent ni ne dépendent du cycle académique ci-dessus.

### 2.2 Mise en place d'une nouvelle année scolaire (établissement déjà en service)

Différent de « Démarrer une nouvelle année scolaire » (§ 2.1, pour un tout premier
déploiement, sans aucune donnée existante) : ceci est le rituel annuel d'un établissement déjà
en activité. La plupart des élèves et enseignants sont déjà dans le système — il s'agit de les
faire passer à l'année suivante, pas de tout recréer.

| # | Étape | Où | Pourquoi cet ordre / remarque |
|---|---|---|---|
| 1 | Vérifier que l'année sortante est bien clôturée | § 6.1 | Toutes les périodes verrouillées, bulletins générés — terminez ce cycle avant de basculer (§ 2.3). |
| 2 | Créer la nouvelle année scolaire | § 12, `/parametres/annee-scolaire` | |
| 3 | Créer les classes de la nouvelle année | § 5, `/classes/create` | **Obligatoire avant l'étape suivante** — la Réinscription ne propose comme destination que des classes déjà créées pour la nouvelle année. |
| 4 | Réinscription — passage de classe | § 5, `/reinscription` | Choisissez l'année source puis destination ; un plan de passage suggère automatiquement une classe de destination par classe source, signale les dépassements de capacité (effectif source > places restantes en destination), et permet de marquer une classe entière comme **sortante** (élèves qui quittent l'établissement, ex. fin de Terminale) plutôt que de la faire passer. Prévisualisez avant d'exécuter — l'opération déplace tous les élèves du plan en une fois, sans retour arrière automatique. |
| 5 | Désactiver les départs individuels restants | § 4 | Élèves qui partent en dehors du plan groupé (cas particuliers) — désactivez plutôt que supprimer, pour garder l'historique. |
| 6 | Inscrire les nouveaux élèves | § 4, `/eleves/create` ou `/eleves/import` | Les vrais nouveaux arrivants uniquement — les élèves déjà présents sont traités à l'étape 4. |
| 7 | Reconduire le personnel | § 8 (RH) — Contrats | Vérifiez les échéances proches (liste dédiée), renouvelez ou clôturez selon le cas ; ajustez les affectations classe/matière si des enseignants changent de poste. |
| 8 | Configurer les périodes de la nouvelle année | § 6.1 | Nouveaux semestres, nouvelles dates. |
| 9 | Mettre à jour les tarifs si nécessaire | § 9, Frais scolaires | Si rien ne change, les catégories existantes restent utilisables telles quelles — pas une étape obligatoire. |
| 10 | Emploi du temps de la nouvelle année | § 10 | Se construit sur les classes et enseignants déjà en place à ce stade. |

Une fois ces étapes faites, l'établissement est reparti pour un cycle normal (§ 2.3).

### 2.3 Cycle d'une période (répété à chaque trimestre/semestre)

Une fois l'année lancée (§ 2.1 ou § 2.2 selon le cas), seules les étapes 8 à 12 du tableau
§ 2.1 se répètent, à chaque période :

1. Vérifier que la période est **ouverte** (§ 6.1) — sinon la saisie de notes est bloquée.
2. Créer les évaluations de la période (§ 6.3).
3. Saisir puis publier les notes au fil de l'eau, évaluation par évaluation (§ 6.4).
4. Une fois tout saisi : **clôturer** puis **verrouiller** la période (§ 6.1).
5. Générer les bulletins (§ 6.6).
6. Besoin d'une correction après coup ? **Rouvrir** exceptionnellement la période plutôt que de
   modifier les bulletins à la main — sinon, passez à la période suivante.

### 2.4 Autres flux courants

- **Nouvel élève en cours d'année** : § 4 (créer la fiche) → l'affecter à sa classe. S'il arrive
  après le début d'une évaluation déjà notée, ses notes manquantes apparaissent simplement comme
  non saisies sur cette évaluation-là.
- **Nouvel employé** : workflow détaillé en § 2.7.
- **Cycle de facturation** (mise en place) : § 9 détaille l'ordre complet (frais → factures →
  paiements → caisse → comptabilité) ; pour l'encaissement au jour le jour une fois ce cycle en
  place, voir § 2.5.

### 2.5 Finance — opérations du quotidien

**Encaisser un paiement**

1. Recevez le règlement d'une famille (espèces, chèque, virement…).
2. `Finance → Paiements → Nouveau paiement` : élève, facture(s) à solder (ou paiement libre),
   montant, mode de paiement.
3. **Valider** — un reçu imprimable est généré, à remettre à la famille.
4. Montant supérieur à ce qui était dû ? Le **trop-perçu** est détecté automatiquement :
   proposez-le en remboursement ou en avoir sur une facture future.
5. En espèces, le montant alimente automatiquement la caisse du jour — aucune saisie
   supplémentaire.

**Fermer la caisse en fin de journée**

1. `Finance → Caisse du jour` — vérifiez qu'une caisse est ouverte (sinon, ouvrez-la en début de
   journée avec le fond de caisse initial).
2. Comptez physiquement les espèces présentes.
3. **Fermer** : saisissez le solde compté — le **rapprochement** le compare au solde théorique
   (paiements + mouvements manuels de la journée) et signale tout écart immédiatement.
4. Imprimez le journal de caisse pour l'archivage.

### 2.6 Vie scolaire — traiter une absence signalée par une famille

1. L'absence est déjà enregistrée côté établissement (pointage du jour ou saisie unitaire, § 6.7).
2. La famille dépose un justificatif : sur la fiche de l'absence, **Justifier** (motif +
   éventuel document).
3. Ouvrez l'absence depuis `Académique → Absences` → **Valider** ou **Refuser** la
   justification (rôle habilité) — un refus exige un motif.
4. Statut final (justifiée / non justifiée / refusée) reflété immédiatement dans les
   statistiques d'absentéisme (§ 6.7, § 11).

### 2.7 Ressources humaines — recruter un nouvel employé

| # | Étape | Où | Remarque |
|---|---|---|---|
| 1 | Créer le dossier employé | § 8, Employés | |
| 2 | Créer le contrat | § 8, Contrats | Passe en `actif` une fois signé — c'est le contrat actif qui fait qu'un employé compte comme « en poste » dans les rapports RH. |
| 3 | Affecter à un poste | § 8, Affectations (Organisation pour créer le poste s'il n'existe pas) | |
| 4 | Si enseignant : lier sa fiche « Enseignants » et l'affecter à ses matières/classes | § 5 (fiche pédagogique) + § 8, Fiches enseignants (RH) | Deux fiches distinctes et complémentaires — RH (contrat, paie) et Scolarité (classes, matières). |
| 5 | Créer son compte utilisateur si un accès à l'application est nécessaire | § 3 | |

### 2.8 Ressources humaines — traiter une demande de congé

1. L'employé (ou vous, en son nom) soumet une demande : `Congés → Nouvelle demande` — type,
   dates, motif.
2. Le système vérifie automatiquement le **solde** disponible et l'absence de
   **chevauchement** avec un congé déjà posé — une demande invalide est bloquée avant même
   d'arriver au responsable.
3. Le responsable **approuve** ou **rejette** la demande.
4. Au départ effectif de l'employé : **démarrer** le congé ; à son retour : **terminer**.
5. Besoin d'annuler après approbation ? **Annuler** plutôt que supprimer — garde la trace.

### 2.9 Finance — soumettre et payer un décaissement (dépense)

Circuit à deux niveaux de contrôle, avec un seuil qui détermine si le second niveau est
obligatoire. Par défaut, séparé entre deux rôles : le secrétariat peut **initier** une demande
mais pas la traiter ; le comptable peut la **traiter** (valider/approuver/rejeter/payer) mais
pas en créer — l'admin et la direction peuvent tout faire. Vérifiez la répartition réelle sur
votre établissement (§ 14), ce n'est qu'un défaut.

| # | Étape | Remarque |
|---|---|---|
| 1 | Si besoin, créer le fournisseur et la catégorie de dépense | `Finance → Décaissements → Fournisseurs` / `Catégories` — une fois créés, réutilisables pour toutes les dépenses suivantes. |
| 2 | Créer la demande | `Finance → Décaissements → Nouveau` : libellé, montant, date de dépense, catégorie, fournisseur, description, échéance. Statut initial : `soumis`. |
| 3 | **Valider** (niveau 1) | Contrôle que la demande est correcte. |
| 4 | **Approuver** (niveau 2) — *conditionnel* | **Obligatoire uniquement si le montant dépasse le seuil d'approbation** (50 000 par défaut, non réglable depuis l'écran Paramètres actuellement — à ajuster en base si besoin). Au-delà du seuil, **au moins un justificatif doit déjà être joint** à la demande, sinon l'approbation est refusée. |
| 5 | **Payer** | Accessible directement après *Validé* si le montant est **sous le seuil** ; sinon, le décaissement doit être passé par *Approuvé* d'abord. Indiquez le mode de paiement (et la référence si virement/chèque). Le paiement génère automatiquement l'écriture comptable ; en espèces, il impute la caisse active de la personne qui paie. |

**Justificatifs** : ajoutables à tout moment depuis la fiche du décaissement (facture
fournisseur, devis…) — indispensables avant l'étape 4 si le montant dépasse le seuil.
**Rejeter** (motif obligatoire) ou **Annuler** sont possibles selon l'état — préférez toujours
Annuler à une suppression, pour garder la trace de la décision.

### 2.10 Académique — générer et imprimer un bulletin

| # | Étape | Où | Remarque |
|---|---|---|---|
| 1 | Vérifier que les notes de la période sont saisies et **publiées** | § 6.4, § 6.1 | Seules les évaluations `publiée` ou `verrouillée` comptent dans le calcul — une évaluation encore en brouillon est ignorée, pas signalée comme manquante. |
| 2 | Repérer les trous avant d'imprimer | `Académique → Bulletins` → choisissez classe + période (`/bulletins/classe`) | Grille des moyennes par matière et par élève — une case vide = note manquante sur cette matière pour cet élève. Corrigez avant de passer à l'étape suivante. |
| 3 | Ouvrir le bulletin d'un élève | Depuis la grille, ou directement `/bulletins/{eleveId}` | **Aperçu calculé en direct** à ce stade — rien n'est encore enregistré, vous pouvez le consulter autant de fois que nécessaire sans conséquence. |
| 4 | Générer puis imprimer le document officiel | `/v2/academique/bulletins/{eleveId}/{periodeId}/imprimer` | **La première ouverture génère et fige le bulletin** (avec son QR de vérification). |
| 5 | Appréciation du chef d'établissement (optionnelle) | `.../appreciation-directeur`, réservé à la permission dédiée | S'ajoute sur le bulletin déjà généré sans toucher au reste. |

**Point de vigilance — un bulletin déjà généré ne se met pas à jour tout seul.** Une fois
l'étape 4 passée pour un élève, une correction de note ultérieure ne change plus rien à
l'impression : le document reste figé sur les valeurs du moment de sa génération. Verrouillez
la période (§ 2.3) et vérifiez la grille de l'étape 2 **avant** de lancer la première
impression de chaque élève, pas après.

Chaque bulletin imprimé porte un **QR code de vérification publique**
(`/v2/academique/bulletins/verify/{token}`) : un tiers peut confirmer son authenticité sans
compte, en le scannant.

---

## 3. Comptes et utilisateurs

**Menu :** `Rapports › Utilisateurs` (`/utilisateurs`)

| Action | Comment faire |
|---|---|
| Créer un compte | `Utilisateurs → Nouveau` : nom, email, mot de passe initial, rôle (Direction, Secrétariat, Comptable, Enseignant, Parent, Élève…). |
| Modifier | Ouvrez la fiche, éditez les champs, enregistrez. |
| Désactiver sans supprimer | Bouton **Activer/Désactiver** sur la fiche ou la liste — coupe l'accès instantanément, conserve l'historique. |
| Supprimer | Bouton **Supprimer** — à réserver aux comptes créés par erreur ; préférez la désactivation pour un départ (traçabilité). |

Un compte peut être rattaché à **plusieurs établissements**, avec un rôle différent dans chacun.

---

## 4. Élèves

**Menu :** `Élèves` (`/eleves`)

### Créer une fiche élève
`Élèves → Nouvel élève` (`/eleves/create`). Le formulaire est organisé en 3 blocs :

- **Photo** — JPG/PNG/WebP, 3 Mo max.
- **Informations personnelles** — Matricule (généré automatiquement, régénérable par le bouton
  ↻, ou saisi à la main), Nom, Prénom, Sexe, Date de naissance.
- **Coordonnées** — Téléphone, Email, Adresse.
- **Affectation** — Classe (optionnelle à la création), Parent responsable (doit déjà exister
  comme compte avec le rôle Parent).
- **Élève actif** — décochez pour un élève inscrit mais pas encore présent (n'apparaît plus
  dans les listes/statistiques tant que la case est décochée).

### Autres actions
| Action | Où |
|---|---|
| Import en masse | `Élèves → Importer` (`/eleves/import`) — fichier CSV. |
| Export | `/eleves/export/pdf` et `/eleves/export/excel` — respecte les filtres actifs de la liste. |
| Modifier / supprimer | Depuis la fiche élève (`/eleves/{id}`). |

---

## 5. Scolarité — enseignants, classes, matières

**Menu :** `Scolarité` (regroupe Enseignants, Classes, Matières, Réinscription)

### Classes
`Scolarité → Classes → Nouvelle classe` (`/classes/create`) :
**Niveau** (liste groupée par cycle), **Lettre/Nom** (ex. « A »), **Année scolaire**
(format `AAAA-AAAA`, pré-rempli), **Capacité maximale** (40 par défaut).
Depuis la fiche d'une classe : affecter/retirer un élève, affecter/retirer un enseignant.

### Matières
`Scolarité → Matières → Nouvelle matière` (`/matieres/create`) :
**Nom**, **Coefficient** (0,5 à 20 — sert au calcul de la moyenne générale),
**Volume horaire** (heures/semaine), **Enseignant responsable** (optionnel),
**Filière** (regroupe les moyennes sur le bulletin).

### Enseignants
`Scolarité → Enseignants` (`/professeurs`) : fiche CRUD classique, puis depuis la fiche,
**affecter/retirer un enseignement** (association matière + classe).

### Réinscription (passage de classe)
`Scolarité → Réinscription` (`/reinscription`) : sélectionnez la classe d'origine et la classe
de destination, **prévisualisez** la liste des élèves concernés (`/reinscription/apercu`) avant
de cliquer sur **Exécuter** — l'opération déplace tous les élèves listés en une seule fois.
Contrôlez l'aperçu avant validation : l'exécution n'a pas de retour arrière automatique.

---

## 6. Académique — évaluations, notes, bulletins, absences

**Menu :** `Académique` → Notes, Bulletins, Absences.

> Les écrans **Périodes** et **Types d'évaluation** ne sont pas dans le menu principal ; on y
> accède depuis l'intérieur du module (onglets de l'écran Évaluations) ou par URL directe
> `/v2/academique/periodes` et `/v2/academique/types-evaluations`. Configurez-les **avant**
> toute saisie de note — ce sont les briques de base.

### 6.1 Périodes scolaires (semestres)
`/v2/academique/periodes` — Créez ou générez les périodes de l'année (`Semestre 1`,
`Semestre 2`), avec dates de début/fin. Cycle de vie d'une période :

```
préparation → ouverte → clôturée → (réouverte) → verrouillée → archivée
```

- **Activer** rend la période « courante » (celle proposée par défaut ailleurs dans l'app).
- **Ouvrir** autorise la saisie de notes.
- **Clôturer** arrête la saisie normale ; **Rouvrir** l'autorise à nouveau exceptionnellement.
- **Verrouiller** fige définitivement la période (bulletins finaux) — à faire seulement quand
  tout est validé, car cela bloque toute correction ultérieure sans déverrouillage explicite.

### 6.2 Types d'évaluation
`/v2/academique/types-evaluations` — Référentiel réutilisable (Devoir, Composition, Oral…)
avec un **coefficient par défaut** et une **note maximale par défaut**, qui pré-remplissent
automatiquement le formulaire de création d'évaluation.

### 6.3 Créer et gérer une évaluation
`Académique → Notes` (`/v2/academique/evaluations`) → **Nouvelle évaluation** :
**Intitulé**, **Période scolaire**, **Type d'évaluation** (auto-remplit coefficient/barème),
**Classe**, **Matière**, **Enseignant responsable** (optionnel), **Date**, **Coefficient**,
**Note maximale**, **Description**. Une évaluation est créée **en brouillon**.

Cycle de vie : `brouillon → publiée → verrouillée` (ou `archivée`).

### 6.4 Saisir les notes
Depuis la fiche de l'évaluation → **Saisir les notes**
(`/v2/academique/evaluations/{id}/notes/saisie`) : grille un élève par ligne — **Note**,
case **Absent** (désactive la note pour cet élève), **Commentaire**. Boutons rapides
« Tout absent » / « Tout présent ». **Enregistrer** sauvegarde en brouillon (modifiable) ;
depuis l'écran de l'évaluation, **Publier** rend les notes visibles aux familles et
**Verrouiller** les fige. Import CSV disponible sur les grands effectifs
(`/v2/academique/evaluations/{id}/notes/importer`).

### 6.5 Résultats, classements et appréciations
Accessibles depuis les listes déroulantes de l'écran Évaluations, ou par URL directe :
- **Par classe** (`/v2/academique/resultats/classe`) — moyennes de chaque élève sur la période.
- **Classement** (`/v2/academique/resultats/classement`) — rangs par classe, niveau et matière.
- **Moyennes** (`/v2/academique/resultats/moyennes`) — vue consolidée par élève.
- **Appréciations par matière** (`/v2/academique/appreciations`) — saisie groupée de
  l'appréciation enseignant pour une classe/matière entière.

### 6.6 Bulletins
`Académique → Bulletins` (`/bulletins`). Depuis cet écran : grille des moyennes de toute une
classe (pour repérer les notes manquantes avant impression), bulletin individuel d'un élève et
classement. Voir § 2.10 pour la marche à suivre complète — la génération d'un bulletin fige son
contenu, un point à connaître avant de se lancer. L'**appréciation du chef d'établissement**
(réservée aux rôles Administrateur et Direction, permission `academique.bulletin.admin`)
s'ajoute via `/v2/academique/bulletins/{eleveId}/{periodeId}/appreciation-directeur`. Si vous
veniez de vous connecter avant le 21/08/2026, reconnectez-vous une fois : les permissions sont
figées en session à la connexion, une correction de configuration ne s'applique qu'à la
prochaine connexion. Chaque bulletin imprimé porte un **QR code de vérification publique** —
un tiers peut vérifier son authenticité sans compte, en scannant le code.

### 6.7 Absences
`Académique → Absences` (`/v2/vie-scolaire/absences`) — écran unique depuis le 20/08/2026
(voir § 16) :
- **Pointage journalier** (`/v2/vie-scolaire/absences/pointage`) — écran principal : choisissez
  une classe et une date, cochez le statut de chaque élève (Présent / Absent / Retard) en un
  seul envoi. Un retard bascule automatiquement dans le domaine Retards (§ 7), pas dans les
  absences.
- **Saisie unitaire** (`/v2/vie-scolaire/absences/create`) — pour signaler une absence isolée.
- **Statistiques** (`/v2/vie-scolaire/absences/statistiques`) — par classe, élèves les plus
  concernés en tête.
- Sur une absence : **Justifier** (dépôt du motif/justificatif) puis **Valider** ou **Refuser**
  la justification (rôle habilité).

---

## 7. Vie scolaire — retards, discipline, récompenses

**Menu :** `Vie scolaire` → Retards, Discipline, Récompenses.

### Retards
`/v2/vie-scolaire/retards → Nouveau retard` : élève, date/heure, cours concerné, durée. Un
**seuil d'alerte** signale les élèves cumulant trop de retards. Un retard peut être justifié
comme une absence.

### Discipline
`/v2/vie-scolaire/discipline → Nouvel incident` : élève(s) concerné(s), nature de l'incident,
gravité, description. Selon la gravité, l'incident peut déclencher une **sanction**
(`sanctionner`) — celle-ci peut se répercuter automatiquement sur le dossier de présence/
absence de l'élève (exclusion temporaire, par exemple).

### Récompenses
`/v2/vie-scolaire/recompenses → Nouvelle récompense` : valorise un comportement exemplaire ;
alimente un **classement comportemental** par élève et par classe (`/recompenses/classement`).

> **Non encore dans le menu admin**, mais fonctionnels par URL directe selon les permissions du
> compte : **Présences** (sessions de présence par cours, `/v2/vie-scolaire/presences`) et
> **Activités scolaires** (`/v2/vie-scolaire/activites`, avec liste d'attente automatique).
> L'emploi du temps, lui, est bien dans le menu depuis le 21/08/2026 — voir § 10.

---

## 8. Ressources humaines

**Menu :** `Ressources Humaines` — 10 écrans, tous sous `/v2/rh/*`.

| Écran | Ce que vous y faites |
|---|---|
| **Employés** | Dossier employé complet ; **Archiver/Restaurer** au lieu de supprimer ; changer le statut ; statistiques et export. |
| **Fiches enseignants (RH)** | Vue RH du corps enseignant : qualifications, affectation aux matières, charge horaire calculée automatiquement à partir des affectations. |
| **Organisation** | Organigramme : créez des **départements**, puis des **postes/fonctions** rattachés, avec services internes. |
| **Contrats** | Créez un contrat (type, dates, salaire…). Cycle de vie : `brouillon → actif → suspendu / résilié`, avec **renouvellement** et **avenants** tracés ; échéances proches listées séparément. Un contrat proche de sa date de fin s'auto-expire. |
| **Affectations** | Poste **principal**, **secondaire** ou **temporaire** ; **Transférer** un employé d'un service à un autre (historique conservé) ; affecter à une matière pour les enseignants. |
| **Présences RH** | Pointage (plusieurs modes de saisie) avec calcul automatique de la durée, du retard et des heures supplémentaires. Un écart peut être **régularisé** (motif obligatoire, tracé) puis **validé**. |
| **Congés** | **Nouvelle demande** : type de congé (9 types), dates, motif. Cycle : `soumis → approuvé → démarré → terminé` (ou `rejeté`/`annulé`). Le module vérifie automatiquement le **solde** de l'employé et empêche le **chevauchement** avec un congé déjà posé. |
| **Évaluations** | Créez une **campagne**, définissez des critères pondérés, puis lancez-la : chaque employé fait son **auto-évaluation**, le responsable évalue ensuite, la fiche est **validée** puis **publiée**. Un **plan de développement** peut être attaché à l'issue. |
| **Formations** | **Catalogue** de formations → créez une **session** (dates, capacité) → les employés s'**inscrivent** → session **ouverte → démarrée → terminée** ; marquez les présences, délivrez les **certifications**/compétences en fin de session. |
| **Documents RH** | Dossier documentaire (contrat, diplôme, certificat médical…) avec **date d'expiration** et **alerte** ; `Alertes expiration` liste tout ce qui expire bientôt. Chaque nouvelle version est tracée (v1, v2…) ; l'ancienne reste consultable. |

---

## 9. Finance

**Menu :** `Finance` — 9 écrans, tous sous `/v2/finance/*`.

Deux niveaux à distinguer : la **Finance scolaire** (frais, factures, paiements, caisse,
décaissements — ci-dessous) où se passe l'essentiel du travail quotidien, et la
**Comptabilité générale** (plan comptable, journal, grand livre, balance) qui est très
largement **automatique** — alimentée par les événements de la Finance scolaire, pas par une
saisie manuelle d'écritures. Le détail action par action (créer, rechercher, imprimer,
annuler…) de chaque écran est dans `docs/fonctionnel/GUIDE_COMPTABLE.md`, vérifié directement
dans le code ; ce qui suit n'est que l'ordre logique de mise en place.

### Ordre logique d'utilisation
1. **Frais scolaires** — définissez d'abord vos catégories de frais (scolarité, cantine,
   transport…) et leurs tarifs, avant de pouvoir facturer quoi que ce soit.
2. **Factures** — `Générer` en masse (toute une classe/niveau) ou créez une facture à l'unité.
   Cycle : `brouillon → émise`, puis suivi automatique selon les paiements reçus
   (`partiellement payée` / `payée` / `en retard`), jusqu'à `annulée`/`archivée`. Une facture en
   brouillon accepte lignes, remise et échéancier de paiement.
3. **Paiements** — enregistrez un règlement en le rattachant à une facture ; un reçu
   imprimable est généré. Un paiement peut être **annulé** ou **remboursé** ; un
   **trop-perçu** est détecté et se traite au choix en remboursement, en **avoir** réutilisable
   sur une facture future (c'est le seul endroit où un avoir se crée — pas depuis une facture),
   ou en annulation du trop-perçu lui-même.
4. **Caisse du jour** — **Ouvrir** une caisse en début de journée (fond de caisse initial),
   les paiements encaissés en espèces l'alimentent automatiquement, enregistrez les
   mouvements manuels si besoin, puis **Fermer** en fin de journée avec **rapprochement**
   du solde théorique vs compté. Le journal de caisse s'imprime depuis la fiche.
5. **Comptabilité** — plan comptable, journal, grand livre, balance : uniquement des vues de
   consultation, générées automatiquement à partir des paiements/décaissements/factures. Aucun
   écran de saisie d'écriture manuelle. Un **exercice comptable** se **clôture** en fin
   d'année ; une écriture erronée se corrige par **extourne** (jamais par suppression) — ce
   sont les deux seules actions disponibles à ce niveau.
6. **Décaissements** — pour les dépenses : renseignez d'abord **Fournisseurs** et
   **Catégories de dépense**, puis créez une **demande de décaissement** avec justificatif.
   Circuit détaillé en § 2.9 : `demande → validation → approbation (si > seuil) → paiement`
   (ou `rejet`/`annulation`).
7. **Impayés** et **Rapports financiers** — tableaux de bord en lecture seule ; export
   CSV/Excel/PDF depuis chaque écran.

---

## 10. Planning — emploi du temps, salles, plages horaires

**Menu :** `Planning` (`/v2/vie-scolaire/emplois-du-temps` — écran unique depuis le
21/08/2026, voir § 16).

- **Emplois du temps** (`/v2/vie-scolaire/emplois-du-temps`) : liste des grilles par classe et
  année scolaire. **Nouveau** crée une grille en **brouillon** ; **Publier** la rend visible aux
  enseignants/élèves/parents concernés — travaillez en brouillon jusqu'à ce que tout soit prêt.
  Chaque grille garde un historique de versions.
- **Ajouter un créneau** (depuis la fiche d'une grille) : classe, matière, enseignant, salle,
  jour, plage horaire. Le système **détecte les conflits** (même enseignant, même salle ou
  même classe déjà occupés sur ce créneau) et refuse l'enregistrement en listant le conflit
  précis plutôt que de l'accepter silencieusement.
- **Remplacements** (`/v2/vie-scolaire/emplois-du-temps/remplacements`) : assigne un enseignant
  remplaçant sur un créneau donné pour une date précise.
- **Salles** (`/v2/vie-scolaire/emplois-du-temps/salles`) et **Plages horaires**
  (`/v2/vie-scolaire/emplois-du-temps/plages`) : référentiels réutilisables dans la grille —
  configurez-les avant de construire un emploi du temps. Désactiver (plutôt que supprimer) une
  salle/plage encore utilisée évite de casser l'historique.
- **Vue enseignant** (`/v2/vie-scolaire/emplois-du-temps/enseignant`) : grille personnelle d'un
  enseignant, avec total d'heures sur l'année.

---

## 11. Rapports

**Menu :** `Rapports` (regroupe aussi le raccourci vers Utilisateurs, § 3).

| Écran | Contenu |
|---|---|
| Vue d'ensemble (`/rapports`) | Point d'entrée, raccourcis vers les 4 rapports ci-dessous. |
| Stats scolaires (`/rapports/scolaire`) | Effectifs, répartition par classe/niveau/filière. |
| Stats financières (`/rapports/financier`) | Vue consolidée recettes/dépenses, complémentaire au détail du module Finance. |
| Présences (`/rapports/presences`) | Taux de présence, tendances d'absentéisme. |
| Réussite (`/rapports/reussite`) | Taux de réussite, distribution des moyennes. |

Chaque rapport s'exporte en PDF (`/rapports/export/pdf`) ou Excel
(`/rapports/export/excel/{type}`).

---

## 12. Paramètres de l'établissement

**Menu :** `Paramètres` — 10 sections, toutes sous `/parametres/*`.

| Section | Ce que vous y configurez |
|---|---|
| Établissement | Identité légale : nom, logo, coordonnées — affichés sur les documents officiels. |
| Année scolaire | Année en cours, dates de début/fin. |
| Organisation académique | Structure des périodes, filières et réglages pédagogiques globaux. |
| Système de notation | Barème des notes, coefficients par défaut, mentions. |
| Finances | Devise, règles de facturation, réglages de caisse par défaut. |
| Documents | Modèles et mentions légales pour bulletins, factures, reçus. |
| Apparence | Branding visuel de l'interface (couleurs, thème, logo affiché). |
| Notifications | Canaux actifs (mail, push) et événements qui déclenchent une alerte. |
| Sécurité | Politique de mot de passe, expiration de session, restrictions d'accès. |
| Sauvegarde / Avancé | Réglages de sauvegarde applicative et options techniques avancées. |

---

## 13. Annonces et notifications

- **Annonces** (`/annonces`) : `Nouvelle annonce` — titre, contenu, cible (tout l'établissement
  ou un public spécifique). CRUD complet.
- **Notifications** (`/notifications`) : votre propre fil, avec préférences par canal
  (`/notifications/preferences`) et « Tout marquer comme lu ».
- **Historique admin** (`/admin/notifications`, hors menu — URL directe) : consulte toutes les
  notifications envoyées dans l'établissement, permet d'envoyer un test et de purger les
  anciennes.

---

## 14. Rôles et permissions

Chaque module découpe ses écrans en permissions fines (consulter, créer, modifier, archiver,
publier…), assignées par rôle. Concrètement :

- Ce que vous voyez dans le menu **dépend de vos permissions**, pas seulement de votre rôle
  affiché — deux comptes « Administrateur » peuvent avoir des accès légèrement différents si
  leurs permissions ont été personnalisées.
- Un écran qui répond « accès refusé » n'est pas forcément un bug : vérifiez d'abord que le
  compte a bien la permission attendue pour cette action, plutôt que de contacter le support en
  premier réflexe.

Le détail technique (liste des permissions, tables, résolution par rôle) est dans
`docs/technique/RBAC.md` — utile si vous configurez vous-même des rôles personnalisés, pas pour
l'usage courant.

---

## 15. Portail Super-Admin SaaS

`/platform/*` — **indépendant** des établissements, avec sa propre connexion (`/platform/login`).
Réservé aux comptes disposant en plus d'une entrée dans la table des opérateurs plateforme —
ce n'est pas automatique pour tout Administrateur d'établissement.

- **Tableau de bord** — vue consolidée sur tous les établissements hébergés.
- **Établissements** — créer, activer, suspendre, archiver, restaurer, supprimer, assigner un
  plan.
- **Plans** — définir les offres d'abonnement (quotas, fonctionnalités).
- **Domaines personnalisés, Quotas, Monitoring** — par établissement, réservés aux opérateurs
  plateforme.
- **Sauvegardes** (niveau `super_admin` uniquement) — sauvegarde globale, par établissement ou
  différentielle ; vérification d'intégrité ; restauration.

---

## 16. Points de vigilance

- **Suppression logique.** La plupart des suppressions dans l'application sont réversibles
  (archivage), pas immédiates — préférez toujours *Archiver* à *Supprimer* quand le choix
  existe, pour garder l'historique.
- **Absences et Planning : un seul système désormais.** Jusqu'au 20-21/08/2026, ces deux
  écrans avaient chacun une ancienne version qui restait accessible en parallèle de la
  nouvelle, sans lien entre les deux — au risque de saisir au mauvais endroit. Ce n'est plus le
  cas : chaque menu ne pointe plus que vers une seule version, à jour. Le détail technique de
  cette convergence (tables concernées, corrections apportées) est consigné dans
  `CHANGELOG.md`, pas utile pour l'usage quotidien.
- **Modules livrés mais désactivés**, invisibles dans toute l'interface : Scolarité V2,
  Documents (GED transversale), Communication, Bibliothèque, Inventaire, Rapports & BI V2,
  Portails dédiés par rôle. Ils sont prêts et audités ; leur activation est un choix produit
  différé, pas un problème technique — si l'un d'eux vous intéresse, c'est une simple demande à
  formuler, pas un développement à attendre.
- **Verrouillage académique.** Verrouiller une période ou une évaluation est volontairement
  difficile à annuler — assurez-vous que toutes les notes sont saisies et vérifiées avant de
  verrouiller, en particulier juste avant l'édition des bulletins.
- **Clôture de caisse et d'exercice comptable** sont également irréversibles en usage normal
  (une réouverture existe mais reste une opération exceptionnelle, tracée) — vérifiez le
  rapprochement avant de valider une fermeture de caisse, et le solde des comptes avant de
  clôturer un exercice.

---

*Voir aussi : `docs/fonctionnel/GUIDE_RH.md`, `docs/fonctionnel/GUIDE_COMPTABLE.md`,
`docs/fonctionnel/GUIDE_DIRECTION.md` pour les vues centrées sur un métier plutôt que sur le
rôle Administrateur. Pour le détail technique (architecture, base de données, permissions,
historique des migrations) : `docs/technique/` et `CHANGELOG.md` — ce guide reste volontairement
centré sur l'usage, pas sur l'implémentation.*
