# Passkeys (WebAuthn) des opérateurs

Une **passkey** (clé de sécurité USB/NFC, empreinte ou visage, Windows Hello, téléphone) peut remplacer le code à 6 chiffres comme second facteur à la connexion. Elle **s'ajoute** à la double authentification par application (voir `DOUBLE_AUTHENTIFICATION.md`), elle ne la remplace pas.

## Principe

- Une passkey ne peut être enregistrée que si la 2FA par application est déjà active. Le code TOTP et les codes de secours restent donc toujours disponibles en cas de perte de l'appareil.
- À la connexion, après le mot de passe, la page « Vérification en deux étapes » propose le bouton **Utiliser ma passkey** si l'opérateur en a au moins une. Le code à 6 chiffres reste affiché en dessous.
- Désactiver ou réinitialiser la 2FA retire aussi toutes les passkeys du compte.
- Jusqu'à 10 passkeys par opérateur, chacune avec un nom (« YubiKey », « Téléphone »…), renommable.

## Enregistrer une passkey

Menu Sécurité → Double authentification → bloc **Passkeys** : saisir un nom et son mot de passe, puis valider sur l'appareil. L'opérateur reçoit un e-mail d'alerte (si le SMTP est configuré). L'ajout et la suppression sont inscrits au journal d'audit global ; **ni la clé ni l'identifiant du credential n'y figurent**.

La suppression et l'ajout exigent le mot de passe. Les erreurs de mot de passe partagent le compteur de la page 2FA (5 échecs / 15 min par opérateur et IP).

## Ce qui est vérifié

| Contrôle | Détail |
|---|---|
| Défi | 32 octets aléatoires, lié à l'opérateur et à l'usage (création ou connexion), valable 2 minutes, **détruit après un seul usage** |
| Origine et domaine | origine exacte et RP ID, issus de la configuration (jamais de l'en-tête `Host`) |
| Présence | l'utilisateur doit avoir validé sur l'appareil |
| Signature | ES256 (ECDSA P-256) ou RS256, sur `authData` et l'empreinte de `clientDataJSON` |
| Compteur | doit progresser ; un compteur qui recule signale un clone possible et la connexion est refusée (journal de sécurité). Les appareils qui renvoient toujours 0 sont acceptés tant que le compteur enregistré est 0 |
| Limitation | mêmes limites d'essais que le code à 6 chiffres (5 par opérateur et IP, 20 par IP, 15 par opérateur) |

Seule la clé **publique** est conservée en base. Les attestations des fabricants ne sont pas vérifiées (modèle « none ») : elles servent à connaître le modèle d'appareil, pas à authentifier l'opérateur.

## Configuration

WebAuthn **ne fonctionne qu'en HTTPS** (ou sur `localhost`). Le domaine et l'origine sont déduits de `APP_URL`. Si le portail est servi sous un autre domaine que `APP_URL`, ou si l'on veut un RP ID plus large (sous-domaines), les définir dans le `.env` :

```
PLATFORM_WEBAUTHN_ORIGIN=https://plateforme.exemple.org
PLATFORM_WEBAUTHN_RP_ID=exemple.org
```

**Attention : changer le domaine ou le RP ID après l'enregistrement des passkeys les rend inutilisables** (le navigateur les rattache au domaine). Les opérateurs se connectent alors avec leur code TOTP et en ré-enregistrent.

## Migration T073

Table `platform_operator_passkeys` (clé publique, algorithme, compteur, nom, dates). Additive, idempotente, réversible (`php database/migrate.php`). Sans la migration, le portail se comporte exactement comme avant : aucun bouton, aucune route active.

## Téléphone ou clé perdue

1. L'opérateur se connecte avec son code TOTP ou un code de secours, puis supprime la passkey perdue (Sécurité → Passkeys).
2. Si l'opérateur n'a plus aucun second facteur : un autre super_admin ou l'accès serveur réinitialise la 2FA (`database/create-operator.php --reinitialiser-2fa`), ce qui retire aussi ses passkeys.

## Non couvert

- Connexion **sans mot de passe** (passkeys « découvrables ») : le mot de passe reste exigé en premier.
- Vérification des attestations et liste des modèles d'appareils autorisés.
- Algorithmes autres que ES256 et RS256 (EdDSA).
- Test avec un vrai navigateur et un vrai appareil : les tests automatiques utilisent un appareil simulé avec de vraies clés et de vraies signatures ; la première utilisation réelle doit être faite par un opérateur (voir la procédure de recette ci-dessous).

## Recette manuelle (à faire une fois, sur localhost ou en HTTPS)

1. Appliquer la migration T073, se connecter en super_admin avec la 2FA active.
2. Sécurité → Passkeys → ajouter une passkey (Windows Hello ou clé USB).
3. Se déconnecter, se reconnecter : mot de passe, puis « Utiliser ma passkey ».
4. Vérifier l'entrée `operator.passkey_added` dans le journal d'audit et, si le SMTP est configuré, l'e-mail d'alerte.
5. Supprimer la passkey avec son mot de passe.
