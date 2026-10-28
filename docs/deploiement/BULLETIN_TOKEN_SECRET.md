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

## 7. Variables associées

| Variable | Défaut | Rôle |
|---|---|---|
| `BULLETIN_PUBLICATION_ENABLED` | `false` (fermé) | Interrupteur de la publication / archivage des bulletins. Tant qu'il n'est pas à `true` (ou `1`, `yes`, `on`), personne ne peut publier, quels que soient ses droits. Ne l'ouvrir qu'après la revue des droits RBAC. |
| `TRUSTED_PROXIES` | vide | Adresses ou blocs CIDR (séparés par des virgules) des proxys autorisés à fournir `X-Forwarded-For`. **Vide : l'en-tête est ignoré.** Les jokers (`*`, `0.0.0.0/0`, `::/0`) sont refusés. |

La page publique de vérification est limitée à **30 requêtes par minute et par adresse IP**. Derrière un proxy (Railway, Nginx…),
renseignez `TRUSTED_PROXIES` avec l'adresse du proxy, sinon toutes les requêtes semblent venir du proxy et partagent un seul compteur.

## 8. Remarques d'exploitation

- Le lecteur de `.env` du projet **ne gère pas les commentaires en fin de ligne** : écrivez `BULLETIN_TOKEN_SECRET=<valeur>` sans rien après,
  et placez les commentaires sur leurs propres lignes (commençant par `#`).
- Sans secret valide, les **aperçus** (espaces parent et élève) continuent de fonctionner, mais l'enregistrement d'un bulletin
  et la vérification publique refusent explicitement. L'impression d'un **brouillon** reste possible (filigrane « BROUILLON », sans QR).
- Un bulletin n'a un QR officiel qu'une fois **publié** ; l'URL du QR est construite à l'impression à partir de `APP_URL`
  (en production : HTTPS obligatoire, hôte non local).
- Les jetons n'apparaissent jamais dans les journaux ni dans l'audit : seule une empreinte courte (`jeton_empreinte`) est consignée.

## Publier les bulletins avec leur QR de vérification (parents)

Un QR n'est imprimé que sur un bulletin **publié** : la page publique de vérification n'accepte que les bulletins émis. Procédure, dans l'ordre :

1. **Sauvegarde vérifiée** : `php database/backup.php --verify-restore`.
2. Poser `BULLETIN_TOKEN_SECRET` dans le `.env` (voir plus haut). `APP_URL` doit être une adresse absolue ; hors développement, en `https://` avec un nom de domaine réel.
3. **Rotation des jetons v1 → v2** (une seule fois, si des bulletins portent encore l'ancien jeton) :
   `php database/tools/bulletin_tokens.php --check`, puis `--simulate` (lecture seule, affiche la somme de contrôle), puis
   `--apply --confirm=<somme> --authorized-by="<nom>"`. Les anciens QR (empreintes SHA-256 conservées dans `bulletin_tokens_retired`) cessent d'être valides.
4. Ouvrir la publication : `BULLETIN_PUBLICATION_ENABLED=1`.
5. Sur la page d'une classe (`/bulletins/classe?classe_id=…&periode_id=…`), bouton **« Publier avec QR »** : régénère puis publie tous les bulletins de la classe.
   Un bulletin en échec (Conduite incomplète, jeton hérité…) est nommé dans le bilan et n'empêche pas les autres ; un second clic ne touche jamais aux bulletins déjà publiés.

Points d'attention : la page de vérification est limitée à 30 requêtes par minute et par adresse IP — à relever si de nombreux parents scannent depuis la même connexion
(Wi-Fi de l'école). L'impression est une page HTML (pas de génération PDF côté serveur).
