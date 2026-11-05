# Double authentification (2FA) des opérateurs

Dernière brique avant la mise en production commerciale du Super-Admin SaaS (migration **T072**). Elle protège la connexion au portail `/platform/*` : le mot de passe seul ne suffit plus pour un opérateur qui l'a activée.

- Activation / gestion : `/platform/compte/2fa` (menu « Sécurité (2FA) », tous les niveaux d'opérateur).
- Caractère obligatoire : paramètre **« Double authentification des opérateurs »** de `/platform/parametres` (super_admin), ou `PLATFORM_REQUIRE_2FA=1` dans le `.env`. Défaut : **facultative** (aucun opérateur n'est bloqué à la mise à jour).

## Principe

TOTP standard (RFC 6238) : code à 6 chiffres, renouvelé toutes les 30 s, SHA-1. Compatible Google Authenticator, Microsoft Authenticator, Aegis, FreeOTP, 1Password… Implémenté sans dépendance externe ; l'algorithme est vérifié sur les vecteurs officiels de la RFC.

## Connexion en deux temps

1. `/platform/login` : e-mail + mot de passe. Si la 2FA est active, l'opérateur **n'est pas connecté** : seul un état « mot de passe validé » (5 minutes, 5 essais) est conservé en session.
2. `/platform/login/2fa` : code à 6 chiffres **ou** code de secours. Succès = session ouverte (nouvel identifiant de session). 5 échecs ou 5 minutes écoulées = le mot de passe est à ressaisir.

Tant que le second facteur n'est pas validé, **aucune page du portail n'est accessible** (le tableau de bord renvoie vers la connexion).

## Activation

1. Menu « Sécurité (2FA) » : un QR code (et la clé à saisir à la main) est affiché.
2. Scanner dans l'application d'authentification, saisir le premier code pour **confirmer** : la 2FA n'est activée que si le code est bon.
3. **10 codes de secours** sont affichés **une seule fois** : à noter ou imprimer.

## Sécurité

| Sujet | Règle |
|---|---|
| Secret | 160 bits, **chiffré au repos** (sodium secretbox, clé dérivée de `APP_KEY`), jamais écrit dans un journal ni dans l'audit |
| Rejeu | un code déjà accepté, ou plus ancien, est refusé (mise à jour atomique du dernier pas) ; tolérance d'horloge ±1 pas (30 s) |
| Codes de secours | à usage unique, stockés **hachés** (HMAC-SHA256) ; saisie tolérante (minuscules, sans tiret) |
| Limitation | 5 échecs / 15 min par opérateur **et** adresse IP, 20 par IP, 15 par opérateur ; le blocage s'applique même avec le bon code |
| Audit global | `operator.login.2fa_required`, `operator.2fa.failed`, `operator.2fa.throttled`, `operator.2fa.recovery_used`, `operator.2fa_enabled`, `operator.2fa_recovery_regenerated`, `operator.2fa_disabled`, `operator.2fa_reset` ; connexion réussie avec la méthode utilisée |
| Atomicité | activation, régénération, désactivation et réinitialisation sont des transactions avec audit **strict** : audit impossible = rien de modifié |
| `APP_KEY` | indispensable (16 caractères minimum). **Ne pas la changer** après activation : les secrets deviendraient illisibles (voir ci-dessous) |

## Gestion

- **Régénérer les codes de secours** : exige un code TOTP valide ; les anciens codes cessent de fonctionner.
- **Désactiver sa 2FA** : mot de passe **et** code TOTP exigés. **Refusé** si la 2FA est obligatoire.
- **Rendre la 2FA obligatoire** : n'est accepté que si l'opérateur qui décide a lui-même activé la sienne (pas d'auto-blocage). Ensuite, tout opérateur sans 2FA est conduit à `/platform/compte/2fa` à sa connexion ; seules cette page, le changement de mot de passe et la déconnexion restent ouverts. Le changement de mot de passe (provisoire) passe avant l'activation.
- Une session déjà ouverte au moment où la 2FA devient obligatoire n'est pas interrompue ; l'exigence s'applique à la **prochaine connexion**.

## Téléphone perdu

Aucun contournement par l'interface. Dans l'ordre :

1. L'opérateur utilise un **code de secours** (puis régénère ses codes et réenregistre son application).
2. Sans code de secours : l'accès serveur retire la 2FA du compte :

```
php database/create-operator.php --reinitialiser-2fa --email=moi@exemple.tld
```

(Docker : `docker compose exec app php database/create-operator.php --reinitialiser-2fa --email=...`.) L'opérateur se reconnecte avec son seul mot de passe et réactive la 2FA ; si elle est obligatoire, il y est conduit. L'opération est inscrite au journal d'audit global (acteur « cli »).

Si `APP_KEY` a été changée par erreur : les secrets chiffrés **et** les codes de secours (hachés avec une clé dérivée de `APP_KEY`) deviennent inutilisables, la connexion avec 2FA échoue pour tous. Rétablir l'ancienne `APP_KEY` si possible ; sinon, la réinitialisation par script ci-dessus est la seule voie (elle ne dépend pas de la clé), puis chaque opérateur réactive sa 2FA.

## Migration T072

Additive et idempotente : `platform_operators` + `totp_secret`, `totp_enabled_at`, `totp_last_step` ; table `platform_operator_recovery_codes`. Aucune ligne modifiée : personne n'a la 2FA après application. Avant son application, le portail se comporte exactement comme avant (la page « Sécurité » l'indique). Retour arrière : retire la table et les colonnes (chaque opérateur devra se réinscrire).

**Ordre de mise en production recommandé** : appliquer T072 → chaque super_admin active sa 2FA → rendre la 2FA obligatoire dans les paramètres → vérifier qu'au moins deux super_admin ont chacun sa 2FA et ses codes de secours.

## Non couvert

« Appareil de confiance » (la 2FA est demandée à chaque connexion), 2FA des comptes d'établissement (hors périmètre : portail plateforme uniquement).

## Passkeys

Les passkeys (WebAuthn : clés de sécurité, Windows Hello, téléphone) s'ajoutent à la 2FA par application comme second facteur possible : voir `PASSKEYS.md` (migration T073).
