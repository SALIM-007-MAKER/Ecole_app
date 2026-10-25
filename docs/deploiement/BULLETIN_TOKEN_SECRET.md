# BULLETIN_TOKEN_SECRET — secret des QR codes de bulletins

`BULLETIN_TOKEN_SECRET` protège les jetons de vérification imprimés (QR code) sur les bulletins officiels.
Sans ce secret, **aucun jeton ne peut être émis ni vérifié** : l'application refuse explicitement plutôt que
d'utiliser une valeur par défaut. Il n'existe aucun repli vers `APP_KEY` ni vers un autre secret.

## 1. Générer le secret (une seule fois par environnement)

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Le résultat est une chaîne de 64 caractères hexadécimaux (256 bits). Règles appliquées au démarrage :

| Règle | Détail |
|---|---|
| Longueur | 64 caractères hexadécimaux au minimum, nombre pair |
| Contenu | uniquement `0-9` et `a-f` (majuscules acceptées) ; ni espace ni retour à la ligne |
| Entropie | au moins 12 caractères distincts ; les motifs répétés (`abab…`, `0123…` répété) sont refusés |
| Indépendance | doit être **différent** de `APP_KEY`, `JWT_SECRET`, `STORAGE_SIGNING_KEY`, `VAPID_PRIVATE_KEY` |

Utilisez **une valeur différente par environnement** (développement, test, production).

## 2. Où le définir

| Environnement | Emplacement |
|---|---|
| Poste local / WAMP | fichier `.env` à la racine (ignoré par Git) : `BULLETIN_TOKEN_SECRET=<valeur>` |
| Docker Compose | variable d'environnement de l'hôte ou fichier `.env` lu par Compose ; `docker-compose.yml` exige la variable (pas de valeur par défaut) |
| Railway / hébergeur | onglet **Variables** du service : `BULLETIN_TOKEN_SECRET` |

Ne jamais le placer : dans le dépôt Git, dans la base de données, dans un journal, dans un ticket ou un message.
`.env.example` contient uniquement le nom de la variable, sans valeur.

## 3. Conserver une copie

**Perdre le secret invalide tous les QR officiels déjà imprimés** (les jetons ne sont plus recalculables). Conservez-le
dans un gestionnaire de mots de passe, avec les autres secrets de production. Ne le stockez pas dans le même
fichier de sauvegarde que la base de données.

## 4. Vérifier la configuration

```bash
php -r "require 'app/Modules/Academique/Services/BulletinTokenException.php'; require 'app/Modules/Academique/Services/BulletinTokenService.php'; \
        \$ok = App\Modules\Academique\Services\BulletinTokenService::isConfigured(); echo \$ok ? 'secret valide' : 'secret absent ou invalide', PHP_EOL;"
```

En cas de refus, le message indique la règle non respectée (jamais la valeur fournie).

## 5. Ce que signe le jeton

`jeton = HMAC-SHA256(hex2bin(secret), "bulletin|v2|{établissement}|{élève}|{période}")`, en hexadécimal minuscule
(64 caractères). Le même bulletin obtient toujours le même jeton ; le statut, les notes et les noms ne sont pas signés.
Détails dans l'en-tête de `app/Modules/Academique/Services/BulletinTokenService.php`.

## 6. Changer le secret

Changer la valeur invalide **tous** les jetons existants. Ne le faites que lors d'une rotation planifiée
(procédure dédiée, avec réimpression des bulletins officiels concernés) — jamais pour « tester ».
