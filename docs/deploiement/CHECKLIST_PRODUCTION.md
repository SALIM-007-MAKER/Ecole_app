# Checklist de mise en production

Document de préparation : **rien n'a été déployé**. Les cases sont à cocher **sur le serveur de production**, avec la preuve indiquée. Modèle de configuration : `.env.production.example` (aucun secret).

Légende : **[Déjà vérifié]** = constaté dans le dépôt ou sur WAMP, avec la preuve notée ; **[À faire]** = à exécuter et vérifier sur le serveur cible. Ne cocher une étape qu'avec sa preuve.

## 0. État connu au 5 novembre 2026 (poste WAMP)

| Point | État | Preuve |
|---|---|---|
| Code poussé sur `origin/main` | [Déjà vérifié] dernier commit `0b72ddf` | `git status -sb` : main à jour avec origin |
| Migrations T064 → T073 appliquées à la base WAMP | [Déjà vérifié] | `migrations_log` : T073 en dernier, statut `success` |
| Tests unitaires (70 fichiers) | [Déjà vérifié] 0 échec | exécution du 5/11 ; `JeuDeReferenceTest` corrigé puis relancé : 18 OK |
| Tests E2E publics (Playwright 1.64.1) | [Déjà vérifié] **14 sur 14 passent** (après correction de `APP_BASE_URL`) | exécution du 5/11 (§ 9) ; le 15ᵉ test n'a pas été lancé (il écrit dans le journal d'audit) |
| Alerte Dependabot n°1 (Playwright) | [Déjà vérifié] corrigée | `npm audit` : 0 vulnérabilité |
| Recette des passkeys avec un vrai appareil | **[À faire]** | non réalisée : les tests utilisent un appareil simulé |
| SMTP configuré | **[À faire]** | `MAIL_USERNAME` vide sur WAMP |
| `APP_ENV=production`, `APP_DEBUG=false` | **[À faire]** | WAMP est en `development` / `true` |

## 1. Avant de toucher au serveur

- [ ] Choisir et réserver le **nom de domaine** définitif. Les passkeys sont rattachées au domaine : le changer plus tard les rend inutilisables.
- [ ] Un **certificat HTTPS** valide est disponible. Sans HTTPS : pas de passkeys, cookies de session non sécurisés.
- [ ] Une **sauvegarde de la base actuelle** est faite et **vérifiée par restauration** : `php database/backup.php --verify-restore`. Preuve attendue : « Restauration de contrôle : N/N tables, lignes identiques ».
- [ ] La sauvegarde est copiée **hors du serveur** (un dump en clair, non chiffré : le protéger).
- [ ] Une fenêtre de maintenance est annoncée aux utilisateurs.

## 2. Configuration (`.env`)

Partir de `.env.production.example`. Ne jamais copier le `.env` de WAMP tel quel.

- [ ] `APP_ENV=production` et `APP_DEBUG=false`.
- [ ] `APP_URL=https://<domaine>` (HTTPS, sans slash final).
- [ ] Trois secrets **distincts**, générés chacun par `php -r "echo bin2hex(random_bytes(32));"` : `APP_KEY`, `BULLETIN_TOKEN_SECRET`, `JWT_SECRET` (et `STORAGE_SIGNING_KEY`).
- [ ] **Sauvegarder `APP_KEY` dans un coffre-fort de mots de passe, séparé du serveur** : la perdre ou la changer rend illisibles tous les secrets 2FA et tous les codes de secours.
- [ ] Utilisateur MySQL **dédié**, limité à la base de l'application, pas `root`. Mot de passe fort.
- [ ] `TRUSTED_PROXIES` : vide si le serveur est exposé directement ; sinon l'adresse exacte du reverse proxy.
- [ ] `CORS_ORIGINS` limité aux domaines réels (aucun `localhost`).
- [ ] SMTP : `MAIL_USERNAME`, `MAIL_PASSWORD` (mot de passe d'application), `MAIL_FROM_EMAIL` identique à `MAIL_USERNAME` (voir `EMAIL_GMAIL.md`).
- [ ] `BULLETIN_PUBLICATION_ENABLED=false` jusqu'à la revue des droits de publication.
- [ ] Droits du fichier `.env` : lecture par le seul compte du serveur web. Le fichier n'est **pas** dans le dossier public.
- [ ] Le fichier `.env` n'est pas accessible par le web : `curl -I https://<domaine>/.env` doit répondre 403 ou 404.

## 3. Base de données

- [ ] Appliquer les migrations : `php database/migrate.php`. **Attention : ce script applique immédiatement les migrations en attente, il n'a ni option d'aide ni mode simulation.** Toujours faire la sauvegarde (§ 1) avant.
- [ ] Preuve : la dernière ligne de `migrations_log` est `T073` (ou plus récente), statut `success`.
- [ ] Les déclencheurs d'audit sont présents (journal global et journaux d'école en ajout seul) : `JeuDeReferenceTest` le contrôle sur la copie ; sur le serveur, `SHOW TRIGGERS` doit lister `trg_platform_audit_no_*`, `trg_audit_logs_no_*`, `trg_platform_payments_*`.

## 4. Opérateurs de la plateforme (Super-Admin)

Sur WAMP : opérateurs 1 et 37 inactifs, opérateur 38 super_admin actif sans 2FA.

- [ ] Garder les comptes 1 et 37 **inactifs** (ne pas les réactiver).
- [ ] Créer un **second super_admin** : `php database/create-operator.php --email=... --nom="..." --niveau=super_admin`. Deux super_admin au minimum : si l'un perd son accès, l'autre peut réinitialiser sa 2FA.
- [ ] Chaque super_admin active sa **2FA** (menu Sécurité), **imprime ses 10 codes de secours** et les range hors ligne.
- [ ] Chaque super_admin ajoute au moins **une passkey** en plus (recette, § 8).
- [ ] Seulement ensuite : rendre la 2FA obligatoire (`/platform/parametres`, ou `PLATFORM_REQUIRE_2FA=1`). **Ne pas le faire avant** : un opérateur sans 2FA serait bloqué.
- [ ] Connaître la procédure d'urgence : `php database/create-operator.php --reinitialiser-2fa --email=...` (accès serveur requis).

## 5. Serveur web et sécurité

- [ ] Seul le dossier `public/` est exposé par le serveur web.
- [ ] HTTPS forcé (redirection HTTP → HTTPS). L'application envoie `Strict-Transport-Security` en HTTPS (`public/index.php`).
- [ ] Les dossiers `storage/`, `database/`, `tests/`, `docs/`, `vendor/` ne sont pas servis par le web.
- [ ] `GET /health` répond `{"status":"healthy","database":"ok"}` (déjà le cas sur WAMP).
- [ ] Aucune erreur PHP affichée : provoquer une URL inexistante et vérifier une réponse propre, sans trace.
- [ ] En-tête `X-Powered-By` sans version de PHP (contrôlé par le test E2E « la version de PHP n'est pas exposée », qui passe).
- [ ] Le test « POST de connexion sans jeton CSRF refusé » passe (contrôle E2E, passe).

## 6. Tâches planifiées

- [ ] Sauvegarde quotidienne avec contrôle : `php database/backup.php --verify-restore` (sous Windows : `schtasks`, voir `SAUVEGARDES.md`). Preuve : un fichier `auto_<base>_AAAAMMJJ_HHMMSS.sql` apparaît chaque jour.
- [ ] Échéances d'abonnement, chaque jour peu après minuit : `php database/subscriptions-tick.php` (essai : `--dry-run`).
- [ ] File de tâches si utilisée : `php database/queue-worker.php`.
- [ ] Un **test de restauration** complet a été fait au moins une fois (`RESTAURATION.md`).

## 7. E-mails et alertes

- [ ] Envoi réel validé : l'opérateur reçoit l'alerte lors d'une connexion de test (le bandeau « alertes inactives » de la page 2FA disparaît).
- [ ] Une alerte arrive à l'ajout d'une passkey et à un changement de mot de passe.

## 8. Recette fonctionnelle sur le serveur

- [ ] Connexion d'un établissement de test (compte de test, pas de données réelles).
- [ ] Connexion opérateur : mot de passe → code TOTP → tableau de bord.
- [ ] **Passkeys** : ajouter une passkey (Windows Hello / clé USB), se déconnecter, se reconnecter avec elle, vérifier l'entrée `operator.passkey_added` dans le journal d'audit, puis la supprimer. Étapes détaillées dans `PASSKEYS.md`.
- [ ] Création d'un établissement et d'un abonnement de test, enregistrement d'un paiement de test, export CSV du journal d'audit.
- [ ] Les journaux d'école sont consultables en lecture seule par le super_admin.

## 9. Tests E2E (rapport du 5 novembre 2026, WAMP, Playwright 1.64.1)

Commande : `cd tests/e2e && npx playwright test --project=public`. Sans compte de test (`E2E_EMAIL` / `E2E_PASSWORD`), seul le projet « public » tourne.

- 14 des 15 tests lancés : **14 passent** (13 au premier essai, puis 14 après la correction ci-dessous).
- Le test « identifiants invalides : pas de session ouverte » n'a **pas** été lancé : il soumet une fausse connexion, qui écrit `auth.global_login_failure` dans le journal d'audit en ajout seul de la base visée. À lancer uniquement contre une base de test.
- **Échec corrigé** : « la page de connexion s'affiche sans erreur ». Erreur console : « A bad HTTP response code (404) was received when fetching the script ». Cause : `app/Views/layouts/auth.php` charge `app.js` sans définir `window.APP_BASE_URL` (défini seulement dans `main.php` et `platform.php`). Le service worker est alors demandé à `http://localhost/sw.js` (404) au lieu de `http://localhost/ecole_app/sw.js` (200). `platform-auth.php` ne définit pas non plus cette variable. **Correction appliquée** (preuve : le HTML servi contient la définition, et les 14 tests passent) : ajout de `<script>window.APP_BASE_URL = '<?= BASE_URL ?>';</script>` avant `app.js` dans ces deux layouts. En production à la racine d'un domaine, l'URL `/sw.js` est correcte et ce défaut disparaît ; il reste à corriger pour tout déploiement dans un sous-dossier.
- La mise à jour de Playwright a imposé de télécharger un Chromium plus récent : `npx playwright install chromium`.

## 10. Retour arrière

- [ ] Sauvegarde de la base d'avant la mise en production disponible et testée.
- [ ] Version précédente du code identifiable (`git log`, étiquette ou commit noté).
- [ ] Procédure : `MISE_A_JOUR.md` § 5 et 5 bis (retour à une version précédente, code et base) ; `RESTAURATION.md`.
- [ ] Pour retirer les passkeys seules : retour arrière de T073 (supprime la table, chaque opérateur garde son TOTP et ses codes de secours).

## 11. Après la mise en production (premières 48 h)

- [ ] Surveiller `storage/logs` (erreurs, `LOGIN_THROTTLED`, `PLATFORM_PASSKEY_*`) et le journal d'audit global.
- [ ] Contrôler que la première sauvegarde planifiée s'est bien produite.
- [ ] Noter la date et le numéro de commit déployés.
