# Paramètres globaux de la plateforme

Étape 6 du Super-Admin SaaS (migration **T071**). Page `/platform/parametres` (menu « Paramètres »), **super_admin uniquement** (lecture et écriture : ce sont des réglages commerciaux).

## Priorité d'une valeur

1. **Surcharge enregistrée** dans `platform_settings` (modifiée depuis la page) ;
2. **variable d'environnement** (`.env`), si elle existe et est valide ;
3. **valeur par défaut** du code.

Une valeur **vide** enregistrée supprime la surcharge (retour au `.env`, puis au défaut). Une valeur invalide dans le `.env` est ignorée (retour au défaut). L'écran indique la source de chaque valeur (surcharge / `.env` / défaut) et le dernier auteur.

## Réglages

| Clé | Libellé | Règle | `.env` | Défaut |
|---|---|---|---|---|
| `devise_reference` | Devise de référence | 3 lettres (passées en majuscules) | `PLATFORM_DEFAULT_CURRENCY` | `XOF` |
| `fuseau` | Fuseau horaire | identifiant IANA connu (`Africa/Niamey`…) | `PLATFORM_TIMEZONE` | `Africa/Niamey` |
| `essai_defaut_jours` | Durée d'essai par défaut | entier 0–365 | — | `0` |
| `grace_defaut_jours` | Jours de grâce par défaut | entier 0–90 | — | `0` |
| `paiement_instructions` | Coordonnées de paiement | texte ≤ 500, sans caractère de contrôle | — | vide |

### Effets

- **Devise, essai, grâce** : valeurs *proposées* à la création d'un plan (formulaire et valeurs de départ). Un champ saisi explicitement n'est jamais écrasé ; **les plans et abonnements existants ne changent pas**. Aucun montant n'est converti.
- **Fuseau** : définit le « jour » des abonnements (bascule à minuit local, `date_fin` inclusive) et la date « aujourd'hui » des paiements. Appliqué immédiatement. Les dates déjà enregistrées ne sont pas modifiées : changer de fuseau décale seulement les prochaines bascules.
- **Coordonnées de paiement** : affichées en rappel sur la fiche d'un établissement (section Paiements) et en pied de reçu. Texte libre échappé à l'affichage. **Ne jamais y mettre de mot de passe ni de secret** : les secrets restent dans le `.env`.

## Intégrité et audit

- Validation complète avant toute écriture : **toutes** les erreurs sont renvoyées ensemble ; si l'une existe, **rien** n'est écrit (pas même les champs valides).
- Une transaction contient les écritures **et** l'audit global strict `settings.updated` (uniquement les clés modifiées, ancienne et nouvelle valeur). Audit impossible = aucune modification. Aucun changement réel = ni écriture ni entrée de journal.
- Lecture sans erreur ni coût particulier si la table n'existe pas encore ou si aucune connexion n'est ouverte (repli sur le `.env`). Le contenu de la table est lu une fois par processus.

## Migration T071

Additive et idempotente : crée `platform_settings` (`cle`, `valeur`, `updated_by`, `updated_at`). **Aucune ligne n'est insérée** : tant qu'un super_admin ne modifie rien, le comportement est exactement celui d'avant. Retour arrière : supprime la table (les surcharges sont perdues, retour aux valeurs du `.env`).

Après application : régénérer le jeu de référence des tests (`php tests/Support/fixtures/mettre_a_jour.php`) — déjà fait dans ce dépôt.

## Non couvert

Paramètres propres à un établissement (ils restent dans les paramètres de l'école), secrets et identifiants SMTP (`.env`), nom ou logo de la plateforme, 2FA des opérateurs.
