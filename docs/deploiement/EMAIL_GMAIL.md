# Envoi d'e-mails réels avec Gmail

L'application envoie ses e-mails (réinitialisation du mot de passe, notifications) par **SMTP**, sans bibliothèque externe
(`app/Services/SmtpMailer.php`, utilisé par `EmailService` quand `MAIL_DRIVER=smtp`).

## 1. Préparer le compte Gmail (une seule fois)

Google refuse le mot de passe habituel du compte pour une application. Il faut un **mot de passe d'application** :

1. Activer la **validation en 2 étapes** sur le compte Google (https://myaccount.google.com/security).
2. Ouvrir https://myaccount.google.com/apppasswords, créer un mot de passe d'application (nom libre, ex. « Ecole App »).
3. Google affiche **16 caractères** (avec ou sans espaces). Les copier : ils ne seront plus affichés.

## 2. Configurer `.env`

```
MAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=votre.adresse@gmail.com
MAIL_PASSWORD=xxxxxxxxxxxxxxxx
MAIL_FROM_EMAIL=votre.adresse@gmail.com
MAIL_FROM_NAME="Ecole App"
```

- `MAIL_FROM_EMAIL` doit être **la même adresse** que `MAIL_USERNAME` : Gmail remplace sinon l'expéditeur.
- Alternative SSL : `MAIL_PORT=465` et `MAIL_ENCRYPTION=ssl`.
- Le mot de passe d'application se saisit **uniquement dans `.env`** (fichier non versionné) ou dans une variable d'environnement du
  serveur. Ne le communiquez jamais dans un message, un ticket ou un dépôt. Il est révocable à tout moment depuis la même page Google.
- Les modifications de `.env` sont prises en compte à la requête suivante (pas de redémarrage).
- Variables optionnelles : `MAIL_REPLY_TO`, `MAIL_TIMEOUT` (secondes, 15 par défaut).

## 3. Vérifier

1. Ouvrir `/forgot-password`, saisir l'e-mail d'un compte **existant et actif** de la table `users`.
2. Le message « Si cet email est enregistré… » s'affiche (toujours le même, que l'adresse existe ou non).
3. Le mail « Réinitialisation de votre mot de passe » arrive (vérifier aussi les courriers indésirables). Le lien est valable **1 heure**
   et ne sert qu'une fois.

Si rien n'arrive : consulter `storage/logs/app-AAAA-MM-JJ.log`, ligne `[MAIL] Envoi SMTP échoué…`. Elle donne la cause sans jamais
contenir le mot de passe :

| Message | Cause probable |
|---|---|
| `SMTP non configuré : MAIL_USERNAME / MAIL_PASSWORD sont vides` | `.env` incomplet |
| `refusé l'étape « authentification » (code 535…)` | mot de passe d'application erroné, ou validation en 2 étapes non activée |
| `Connexion au serveur SMTP impossible` | pas d'accès Internet, pare-feu ou antivirus bloquant le port 587/465 |
| `Échec de la négociation TLS` | horloge du poste incorrecte, ou antivirus qui intercepte le chiffrement |
| `Adresse destinataire invalide` | e-mail du compte mal saisi en base |

En développement (`APP_DEBUG=true`), si l'envoi échoue, le lien de réinitialisation est écrit dans le journal PHP
(`c:/wamp64/logs/php_error.log`) pour ne pas bloquer le travail. Il n'est **jamais** affiché à l'écran.

## Sécurité et limites

- Le certificat du serveur SMTP est toujours vérifié ; les identifiants ne partent jamais en clair (`MAIL_ENCRYPTION=none` n'est
  accepté que vers `localhost`).
- Le lien envoyé est construit à partir de `APP_URL` (jamais de l'en-tête `Host` de la requête). **`APP_URL` doit donc être l'adresse
  publique réelle** du site en production (https).
- Les demandes de réinitialisation sont limitées : 3 par adresse et par IP, 5 par adresse, 10 par IP, sur 15 minutes. Au-delà, la page
  répond pareil mais n'envoie rien.
- Compte Gmail gratuit : environ 500 e-mails par jour. Au-delà, utiliser un service d'envoi transactionnel (même configuration SMTP).
- **Temps de réponse** : pour que la durée de la page ne révèle pas si une adresse existe, la réponse (toujours identique : même message, mêmes en-têtes) est terminée
  et libérée **avant** l'envoi du mail ; l'envoi s'exécute ensuite dans la même requête PHP, sans file de tâches ni worker. Le jeton est déjà enregistré, le
  lien est valide à l'arrivée du mail. Si le serveur web n'envoie pas la réponse tout de suite (compression ou tampon intermédiaire), la page peut
  attendre la fin de l'envoi : l'envoi fonctionne quand même, seule la protection contre la mesure du temps est alors réduite. Une différence de quelques
  millisecondes (écriture du jeton en base) subsiste ; les limites de demandes ci-dessus empêchent de l'exploiter en masse.
- Un échec d'envoi n'est jamais montré à l'utilisateur (message identique) : il est écrit dans `storage/logs/app-AAAA-MM-JJ.log`.
