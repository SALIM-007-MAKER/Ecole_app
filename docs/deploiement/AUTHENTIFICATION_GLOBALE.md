# Authentification globale (connexion commune `/login`) — Phase 1

Les utilisateurs de **tous** les établissements se connectent par la même adresse `/login`. Le portail des opérateurs de plateforme (`/platform/login`) reste **indépendant** et inchangé.

## Principe

Avant l'authentification, l'établissement de l'utilisateur n'est pas connu. `UserModel::findByEmail()` est limité au tenant par défaut (établissement 1) et ne peut donc pas servir à la connexion. `App\Services\GlobalAuthService` interroge `users` directement (PDO, hors `Core\Model`) : **les autres requêtes de `UserModel` restent limitées à leur établissement**. L'e-mail est unique dans toute la plateforme.

### Séquence

1. CSRF, format, **limitation de débit** (inchangée : 5 par compte et adresse, 20 par compte, 30 par adresse).
2. `authenticate()` : e-mail inconnu et mot de passe faux donnent **la même réponse** (« Email ou mot de passe incorrect », même redirection ; un hachage factice égalise le temps de réponse).
3. Compte désactivé : message habituel, uniquement après un mot de passe correct.
4. `classer()` : établissements **accessibles** ou **bloqués avec la raison** (suspendu, résilié, archivé, supprimé, abonnement sans accès — mêmes règles que `SessionTenantGuard` —, appartenance sans rôle propre).
5. Aucun accessible : message **précis** (identifiants déjà vérifiés). Un accessible : ouverture directe. Plusieurs : **aucune session scolaire** (`_auth_user` absent, aucun contexte d'établissement) ; seul `_pending_auth_user_id` + `_pending_auth_at` est posé.
6. Choix (`POST /choisir-etablissement`) : l'appartenance **et** l'état de l'établissement sont **revérifiés côté serveur**. L'état d'attente **expire après 10 minutes** (sans horodatage : considéré expiré).
7. `completeLogin()` ouvre la session (identifiant de session régénéré).

## Règles d'appartenance et de rôle

| Situation | Comportement |
|---|---|
| Appartenance active avec un rôle dans `user_roles_etab` | rôle de CET établissement |
| Plusieurs établissements, rôles différents | rôle de l'établissement sélectionné, jamais de cumul entre établissements |
| **Aucune ligne** `user_etablissements` (ni active ni quittée) | repli historique : `users.etablissement_id` et `users.role` |
| Appartenance active **sans rôle propre** | **refus**, journalisé (`auth.role_missing`) — jamais de repli sur `users.role` |
| Appartenance quittée (`left_at`) | aucun accès, **pas** de repli |

- **Rôles multiples dans un établissement** : rôle affiché selon la priorité fixe `admin` > `directeur` > `comptable` > `secretaire` > `enseignant` > `parent` > `eleve` (codes inconnus ensuite, par ordre alphabétique) ; l'ordre des lignes en base n'a aucun effet. Les permissions sont la **réunion** de celles de tous ses rôles. Chaque cas est journalisé (`auth.multiple_roles`). Aucun rôle n'est ajouté ni retiré automatiquement.
- **Permissions RBAC V2** : `rbac_user_roles` est global (sans établissement). Hors compte historique, `UserModel::getPermissions(..., $strictTenant = true)` **ignore** ce palier : aucune permission globale n'est héritée dans un autre établissement. Le paramètre est faux par défaut : les autres appelants gardent leur comportement.

## Rafraîchissement de session

`Application::rafraichirDroitsSession()` (toutes les 60 s) utilise la **même résolution** : un rôle modifié est pris en compte ; un rôle **retiré** (ou jamais attribué) invalide la session au rafraîchissement suivant (`SESSION_ROLE_REVOKED`, retour à `/login`). Une panne de lecture ne déconnecte personne.

## Journal global (`platform_audit_logs`)

Événements : `auth.global_login_success`, `auth.global_login_failure`, `auth.login_denied`, `auth.tenant_selected`, `auth.tenant_switch`, `auth.role_missing`, `auth.multiple_roles`. L'identifiant de l'utilisateur est dans `entity_id` (`entity_type = 'user'`) ; `operator_id` reste réservé aux opérateurs. Aucun mot de passe, hachage ni jeton n'est écrit. Les échecs sont limités à 30 lignes par adresse et par fenêtre. Une panne du journal ne bloque pas la connexion (fichier de secours du journal global).

### Migration T068

Étend l'ENUM `actor_type` avec `'school_user'` (extension pure : aucune ligne modifiée, défaut inchangé, journal toujours en ajout seul — déclencheurs vérifiés). **Avant T068**, la connexion fonctionne et les événements sont écrits avec l'acteur `anonymous`. Retour arrière : refusé tant que des lignes utilisent `school_user` (le journal ne se réécrit pas).

> **T068 n'est PAS appliquée à la base WAMP** à ce stade. Avant application : sauvegarde vérifiée (`php database/backup.php --verify-restore`), puis `php database/migrate.php --id=T068`, et autorisation explicite.

## Contrôle préalable au déploiement

Une appartenance active **sans** ligne `user_roles_etab` est désormais refusée à la connexion. Requête de contrôle (lecture seule) :

```sql
SELECT ue.user_id, ue.etablissement_id FROM user_etablissements ue
 WHERE ue.left_at IS NULL
   AND NOT EXISTS (SELECT 1 FROM user_roles_etab ur WHERE ur.user_id = ue.user_id AND ur.etablissement_id = ue.etablissement_id);
```

Sur la base de développement WAMP : 0 ligne.

## Limites connues (phases suivantes)

- `switchSchool` et le profil restent limités à l'établissement courant (`UserModel::findById`) : **Phase 2**.
- Mot de passe oublié / réinitialisation : recherche encore limitée au tenant par défaut : **Phase 3**.
- La page de choix reçoit la liste des établissements bloqués (`bloques`) mais ne les affiche pas encore (vue `auth/choose-school` hors périmètre de la Phase 1).
- Création du premier administrateur et invitations : Phases 4 et 5.

## Tests

`tests/Unit/AuthGlobaleTest.php` (copies jetables, HTTP complet avec session persistante : `tests/Support/auth_request.php`).

---

# Phase 2 — changement d'établissement et compte propre

## Changement d'établissement (`POST /auth/switch-school`)

Pour un utilisateur déjà connecté et membre de plusieurs établissements. Le serveur décide ; le client ne choisit jamais librement :

1. authentification, CSRF ; même établissement : aucune action ;
2. l'utilisateur est relu **par son identifiant de session, tous établissements confondus** (`GlobalAuthService::lookupById`) — `UserModel::findById` est limité à l'établissement courant et échouait pour un membre rattaché à un autre établissement ;
3. `classer()` : appartenance active, état de l'établissement (suspendu, résilié, archivé, abonnement sans accès) et rôle propre sont revérifiés. Établissement bloqué : **message précis**, établissement courant **conservé**, événement `auth.login_denied` (`etape = bascule`) ; sans appartenance : refus, `SWITCH_SCHOOL_REJECTED` ;
4. `completeLogin(..., 'auth.tenant_switch')` : rôle propre à l'établissement visé, permissions recalculées (mode strict, aucun cumul), identifiant de session régénéré ;
5. **purge** de ce qui appartient à l'établissement quitté : `_auth_refreshed_at`, `__etab_slug`, `bi_config_direction`, `old_input`, `errors` ;
6. audit `auth.tenant_switch` avec `de` (établissement quitté) et `vers` ; message « Vous êtes maintenant connecté à « … » ».

La liste de bascule de la session (`available_etablissements`) ne contient que les établissements **accessibles** (au moins deux pour s'afficher).

## Compte propre : profil, photo, mot de passe

Nom, e-mail, mot de passe et photo sont des données de la **personne**. `GlobalAuthService::monCompte()`, `mettreAJourMonProfil()` (liste blanche : nom, prénom, e-mail, téléphone, photo — jamais le rôle, l'établissement ni l'état) et `changerMonMotDePasse()` agissent **uniquement sur l'identifiant de la session** ; un identifiant ou un champ forcé dans la requête est ignoré. Avant la Phase 2, ces opérations passaient par `UserModel`, limité à l'établissement courant : pour un membre rattaché à un autre établissement, la page de profil se rendait vide et les mises à jour étaient **sans effet avec un message de succès**. `UserModel::findById` / `update` / `updatePassword` ne changent pas : les écrans d'administration continuent de les utiliser.

## RBAC V2

`UserModel::getPermissions` n'est appelé que par `GlobalAuthService`. Hors compte historique sans appartenance, le mode strict ignore `rbac_user_roles` (global) : aucune permission n'est héritée d'un établissement à l'autre, y compris après une bascule. Aucun code applicatif n'écrit dans `rbac_user_roles`.

## Limites (phases suivantes)

- Un changement de mot de passe n'invalide pas les **autres sessions** ouvertes (aucun registre de sessions) : Phase 3, avec la réinitialisation.
- `UtilisateurController` liste et gère les utilisateurs par `users.etablissement_id` : un membre rattaché à un autre établissement n'y apparaît pas. Phase 4 (gestion des administrateurs).
- Le changement d'identifiant de session ne peut pas être observé par les tests en ligne de commande (chaque requête est un processus distinct) ; il est garanti par `Session::regenerate()` dans `completeLogin`.

## Tests

`tests/Unit/AuthSessionTest.php` (48 vérifications) : bascule valide et refusée, utilisateur dont l'établissement d'origine est l'établissement 2, purge et données visibles, liste de bascule, profil/photo/mot de passe inter-établissements, absence d'élévation de privilèges, RBAC. Le banc d'essai `tests/Support/auth_request.php` relit désormais la session persistée **avant** `Session::start()` (l'expiration par inactivité est donc réellement évaluée).

---

# Phase 3 — mot de passe oublié et réinitialisation globale

## Demande (`POST /forgot-password`)

- **Recherche globale** (`GlobalAuthService::lookupByEmail`) : un utilisateur de n'importe quel établissement reçoit son e-mail (avant : `UserModel::findByEmail`, limité à l'établissement par défaut, aucun e-mail et aucune erreur pour les autres).
- Réponse **identique** que le compte existe, soit inconnu ou désactivé, ou que la demande soit limitée (même message, même redirection) ; l'envoi se fait **après** la réponse. Limitation inchangée : 3 par adresse et IP, 5 par adresse, 10 par IP.
- Le jeton (256 bits) est stocké **haché** (SHA-256) dans `password_resets`, lié à l'adresse enregistrée, valable **1 heure** ; une nouvelle demande invalide le lien précédent.
- **Aucun lien ni jeton dans un journal.** L'ancien `error_log` du lien (mode debug) est supprimé : un échec d'envoi est seulement journalisé (`auth.password_reset_mail_failed`, sans jeton ni adresse) et l'utilisateur peut refaire une demande.

## Réinitialisation (`POST /reset-password`)

1. essais de lien invalides limités (10 par adresse IP) ;
2. contrôle de validité **sans consommer** le jeton (un mot de passe refusé peut être corrigé) ;
3. **politique de la personne** (`GlobalAuthService::politiqueMotDePasse`) : administrateur dans au moins un de ses établissements (ou compte historique de rôle admin) = 14 caractères et 3 familles (`PlatformPasswordPolicy`), sans son e-mail ni son nom ; sinon le **plus élevé** des réglages `password_min_length` de ses établissements (8 par défaut). La même politique s'applique au changement depuis le profil ;
4. consommation **atomique** : une seule requête conditionnelle (`used = 0 AND expires_at > NOW()`, une ligne affectée exigée) dans la même transaction que le changement du mot de passe ; les autres jetons de l'adresse sont supprimés. Un échec de la mise à jour ne consomme pas le jeton.

Événements : `auth.password_reset_requested`, `auth.password_reset`, `auth.password_reset_failed` (lien invalide / expiré / déjà utilisé / mot de passe refusé), `auth.password_changed` (profil), `auth.password_reset_mail_failed`.

## Sessions ouvertes (migration T069)

`users.password_changed_at` (additive, NULL). Chaque session mémorise la date du mot de passe à la connexion (`pwd_at`) ; `Application::rafraichirDroitsSession` (toutes les 60 s) invalide toute session dont la date est antérieure : **une session ouverte avant une réinitialisation ou un changement est invalidée au plus 60 s plus tard**. La session qui change son mot de passe depuis le profil reste valide (date renouvelée) ; les liens de réinitialisation en attente sont révoqués. Avant T069, le code fonctionne sans cette invalidation (colonne détectée).

> **T068 et T069 ne sont PAS appliquées à la base WAMP** à ce stade. Avant application : sauvegarde vérifiée (`php database/backup.php --verify-restore`), puis `php database/migrate.php --id=T068`, `--id=T069`, et autorisation explicite.

## Limites

- Délai d'invalidation des sessions : jusqu'à 60 s (intervalle de rafraîchissement des droits).
- Les colonnes héritées `users.reset_token` / `reset_token_expires` ne sont ni utilisées ni modifiées.
- Une adresse modifiée entre la demande et l'utilisation du lien : le lien devient inutilisable (le jeton est lié à l'adresse).
- La création du premier administrateur et les invitations (Phases 4 et 5) réutiliseront ces mécanismes avec une durée de 72 h.

## Tests

`tests/Unit/AuthRecuperationTest.php` (71 vérifications) avec un faux serveur SMTP local : aucun e-mail réel n'est possible depuis le banc d'essai (`tests/Support/auth_request.php` remplace la configuration SMTP du fichier `.env`).
