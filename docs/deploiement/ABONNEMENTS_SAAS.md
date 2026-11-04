# Abonnements SaaS — établissement ↔ plan

Étape 4 du Super-Admin SaaS (migration **T067**). Fiche établissement : `/platform/etablissements/{id}` (section « Abonnement »). Lecture : tout opérateur. Écriture : **`super_admin` uniquement** — souscrire, renouveler, annuler, prolonger l'essai, **assigner/changer un plan** et **rattacher un plan à la création d'un établissement**. Un `admin` peut toujours créer un établissement (sans plan), le suspendre, l'activer ou l'archiver ; le formulaire de création ne lui propose pas de plan et un `plan_id` forcé est refusé.

## Modèle

`platform_subscriptions` : une ligne par période, historique conservé. **Les conditions commerciales (prix, devise, durée d'essai, jours de grâce) sont figées dans la ligne** : modifier un plan ne change jamais un abonnement existant.

| Statut | Sens |
|---|---|
| `trial` | essai (cycle `trial`) |
| `active` | période payante en cours (cycle `monthly`, `yearly` ; `legacy` = repris de l'existant, cycle et prix inconnus) |
| `grace` | `date_fin` dépassée, accès encore maintenu pendant `jours_grace_appliques` |
| `expired` | plus d'accès |
| `cancelled` | annulé : accès jusqu'à `date_fin` incluse, pas de renouvellement |
| `superseded` | remplacé par une période plus récente (renouvellement, changement de plan) |

Un seul abonnement « courant » (`trial`, `active`, `grace`, `cancelled`) par établissement, garanti par un index unique en base.

`etablissements.statut` reste le verrou d'accès lu par le reste de l'application. `plan_id`, `plan_expires_at` (dernier jour d'accès = `date_fin` + grâce) et `trial_ends_at` sont un **miroir** écrit uniquement par le service d'abonnement.

## Dates et fuseau

- Fuseau : `PLATFORM_TIMEZONE` (défaut `Africa/Niamey`). Tout est calculé en **jours calendaires locaux**.
- `date_fin` est **inclusive**. La bascule se fait à **minuit local, le lendemain de `date_fin`** (grâce : le lendemain du dernier jour de grâce).
- Mensuel : début + 1 mois − 1 jour ; annuel : début + 12 mois − 1 jour ; le jour est ramené à la fin du mois si besoin (31 janvier + 1 mois = 28/29 février).
- Essai : `duree_essai_jours` du plan, le jour de départ compte (14 jours du 10 au 23). Pas de grâce pour un essai.
- Plan sans essai (durée 0) : l'abonnement démarre **mensuel et actif** à la création.

## Règles

- **Un seul essai par établissement.** Prolongation exceptionnelle (`super_admin`) : 1 à 90 jours, motif obligatoire, auditée ; valable aussi sur un essai expiré (levée de la suspension automatique).
- **Changement de plan** : immédiat, sans prorata ni remboursement ; même échéance, prix/devise/grâce/quotas du nouveau plan.
- **Renouvellement** : manuel. La période suivante commence **le lendemain de `date_fin`** (continuité, y compris pendant la grâce). Par défaut conditions actuelles du plan ; case « conserver les conditions précédentes » possible (même cycle). Un plan archivé impose de conserver les conditions. Abonnement sans échéance : la période démarre aujourd'hui. Après expiration : « Souscrire / réactiver » (période depuis aujourd'hui).
- **Annulation** : motif obligatoire ; accès maintenu jusqu'à `date_fin` ; à l'échéance l'établissement est suspendu automatiquement.
- **Devise** : aucune conversion. Toute souscription **payante** à un plan en `DZD` est refusée (corriger devise et prix du plan d'abord). Un essai sur un plan DZD avec essai est toléré (aucun paiement) mais sa conversion est refusée.
- **`auto_renew`** : colonne présente, **sans effet** avant l'étape paiements.
- Établissement déjà actif sans abonnement auquel on assigne un plan : abonnement **sans échéance** (aucune date inventée).

## Suspension automatique ≠ suspension manuelle

`etablissements.suspension_origine` : `subscription` (automatique), `manual` (opérateur), `NULL` (historique : traité comme manuel).

| Événement | Effet |
|---|---|
| Accès cessé (expiré, annulation échue) | si l'établissement est `trial`/`active` : `suspended`, `suspension_origine = subscription`, motif « Abonnement expiré » |
| Souscription / renouvellement / prolongation d'essai | lève **uniquement** une suspension `subscription` (statut, origine, motif et date effacés) |
| Suspension par un opérateur | `suspension_origine = manual`, motif de l'opérateur ; si l'établissement était suspendu automatiquement, l'ancien motif reste dans l'audit (`avant`) |
| Établissement suspendu manuellement ou à origine `NULL` | jamais modifié ni levé par un abonnement (l'audit indique « suspension conservée ») |
| Établissement `archived` / `cancelled` | jamais touché par l'automatisme |
| Activation / restauration manuelle sans accès d'abonnement | **refusée** (renouveler, souscrire ou prolonger l'essai d'abord) |

## Échéances

`php database/subscriptions-tick.php [--dry-run]` — à planifier **chaque jour peu après minuit** (heure de Niamey). Applique actif → grâce → expiré, essai → expiré, annulation échue, avec suspension automatique. Idempotent ; chaque abonnement a sa transaction ; code de sortie 1 si une transition a échoué. Acteur d'audit : `system`.

**Rattrapage** : le script traite aussi la dernière période de chaque établissement lorsqu'elle est déjà `expired` alors que l'établissement est resté `trial`/`active` (essai échu repris par T067, script arrêté longtemps…) : suspension automatique, une seule fois, auditée avec `rattrapage = true`. Une ancienne période expirée d'un établissement réabonné n'a aucun effet. Une suspension existante (manuelle ou historique) n'est jamais réécrite.

Le garde de session (`SessionTenantGuard`) calcule aussi l'état par les dates (lecture seule) : un abonnement échu est bloqué même si le script n'a pas encore tourné. Aucun blocage en cas de panne technique, d'établissement sans abonnement ou de base non migrée.

**Avant la mise en route sur une installation existante**, lancer `--dry-run` : les essais déjà échus (`trial_ends_at` dans le passé) sont repris tels quels par T067 et seront bloqués/suspendus. Requête de contrôle (lecture seule) :

```sql
SELECT id, nom, statut, trial_ends_at, plan_expires_at FROM etablissements
 WHERE deleted_at IS NULL AND plan_id IS NOT NULL
   AND ((statut = 'trial' AND trial_ends_at < CURDATE()) OR (statut <> 'trial' AND plan_expires_at < CURDATE()));
```

## Atomicité et audit

Chaque opération = une transaction (point de sauvegarde si l'appelant en a une) contenant les écritures **et** la trace d'audit globale en mode strict : audit impossible → opération annulée. Erreur technique : message générique avec référence, détail dans `storage/logs`.

Actions du journal global : `subscription.created`, `.renewed`, `.plan_changed`, `.cancelled`, `.trial_extended`, `.grace_started`, `.expired`, `.cancel_effective` (+ `tenant.plan_assigned` conservé).

## Migration T067

Additive et idempotente : `etablissements.suspension_origine` (NULL partout), table `platform_subscriptions`, reprise de l'existant **sans écrire dans `etablissements`** (trial → `trial`, cancelled → `cancelled`, autres → `active`, échéance = date actuelle ou sans échéance, cycle `legacy`, prix inconnu, grâce 0). Établissements supprimés ou sans plan : aucun abonnement. Retour arrière : supprime `platform_subscriptions` (historique perdu : sauvegarde préalable) ; la colonne est conservée. Avant son application le code retombe sur le comportement historique.

Après application : régénérer le jeu de référence des tests (`php tests/Support/fixtures/mettre_a_jour.php`) si d'autres migrations sont ajoutées.

## Bandeau de période de grâce (côté école)

Tant que l'abonnement est en grâce, les utilisateurs de rôle `admin`, `directeur` ou `super_admin` de l'établissement voient en haut de page un bandeau ambre (« Abonnement échu : période de grâce ») avec la date de fin d'abonnement, le dernier jour d'accès (inclus) et le nombre de jours restants. Les autres rôles ne le voient pas ; aucun montant n'est affiché. Le bandeau disparaît au renouvellement et n'existe plus une fois la grâce écoulée (l'accès est alors refusé). Lecture seule, sans effet en cas de panne ou avant T067.

## Non couvert (étapes suivantes)

Paiements et revenus : voir `PAIEMENTS_SAAS.md` (étape 5, fait). Non couvert ici : notifications d'échéance, surcharge de quotas par établissement, activation réelle des `modules_inclus`, 2FA.
