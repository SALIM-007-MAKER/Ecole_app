# Alertes de sécurité par e-mail (opérateurs de la plateforme)

Un opérateur est prévenu par e-mail quand quelque chose touche la sécurité de **son** compte : si ce n'est pas lui, il le sait tout de suite.

## Prérequis

L'envoi utilise la configuration SMTP existante (`docs/deploiement/EMAIL_GMAIL.md`). **Tant que `MAIL_DRIVER=smtp` et `MAIL_USERNAME` ne sont pas renseignés, aucune alerte n'est envoyée** : rien n'est tenté, aucune erreur n'est journalisée. La page « Sécurité (2FA) » (`/platform/compte/2fa`) l'indique par un bandeau.

## Ce qui déclenche une alerte

| Événement | Quand | Désactivable |
|---|---|---|
| Nouvelle connexion | Connexion réussie (après le second facteur s'il y en a un) | **Oui** : réglage « Alerte e-mail à chaque connexion » de `/platform/parametres`, ou `PLATFORM_NOTIFY_LOGIN=0` |
| Code de secours utilisé | Connexion avec un code de secours | Non |
| Mot de passe modifié | Changement par l'opérateur lui-même | Non |
| Mot de passe réinitialisé | Réinitialisation par un administrateur (`create-operator.php`) | Non |
| 2FA activée / désactivée | Par l'opérateur | Non |
| 2FA réinitialisée | Retirée par un administrateur (téléphone perdu) | Non |
| Codes de secours régénérés | Par l'opérateur | Non |

Aucune alerte n'est envoyée pour une tentative **refusée** (mauvais mot de passe, mauvais code, opération annulée) : un attaquant ne doit pas pouvoir inonder la boîte d'un opérateur.

## Contenu du message

Ce qui s'est passé, la date (fuseau de la plateforme), l'adresse IP, le navigateur, la méthode du second facteur le cas échéant, et la conduite à tenir (changer son mot de passe, prévenir un autre super administrateur).

**Jamais** de mot de passe, de secret, de code de secours ni de **lien** : un message de sécurité qui contient un lien est un message qu'on apprend à cliquer. En ligne de commande (`create-operator.php`), l'origine indiquée est « ligne de commande (accès serveur) ».

## Garanties

- **Jamais bloquant** : l'alerte part après l'opération, qui a déjà eu lieu. Un serveur SMTP en panne n'empêche ni la connexion ni l'activation de la 2FA.
- **Après la réponse** : sur le web, l'envoi se fait une fois la réponse partie vers le navigateur (SMTP = plusieurs secondes).
- **Après validation** : pour les opérations en base (2FA, mot de passe), l'alerte n'est envoyée qu'une fois la transaction validée ; une opération annulée ne produit aucune alerte.
- **Échecs visibles** : un envoi qui échoue est inscrit au journal technique et au journal d'audit global (`operator.notification.failed` : événement et opérateur, **sans adresse ni détail technique**). À surveiller : si les alertes ne partent plus, c'est là qu'on le voit.
- Un opérateur **sans adresse e-mail** ou **désactivé** n'est pas alerté (aucune erreur).

## Limites

- L'alerte est envoyée à l'adresse enregistrée du compte : si le compte de messagerie de l'opérateur est lui-même compromis, l'alerte est lue par l'attaquant.
- Pas d'alerte sur les échecs de connexion ni sur un verrouillage (voir ci-dessus), ni de récapitulatif périodique.
- Pas de notification aux **établissements** : uniquement les opérateurs de la plateforme.

## Tests

`php tests/Unit/PlatformSecurityNotifierTest.php` (45 contrôles) : disponibilité, mot de passe, 2FA, robustesse (envoi refusé, exception, sans adresse, désactivé, transaction annulée), contenu (aucun secret ni lien, en-têtes neutralisés), parcours HTTP (réglage, code de secours, échecs sans alerte). Aucun e-mail réel n'est envoyé.
