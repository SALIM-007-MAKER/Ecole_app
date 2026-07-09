# Phase 14.7 — Custom Domains — Rapport d'implémentation

## 1. Contexte & périmètre réellement buildable

Cette application tourne en local sur Apache/WAMP : pas de nom de domaine public, pas de
reverse-proxy Nginx/Caddy, pas de worker cron/queue (prévu Phase 14.9). Le blueprint
(§11) demande des composants qui relèvent en partie de l'infrastructure serveur
(émission SSL ACME, reconfiguration Nginx dynamique, vérification DNS planifiée).

Décision de périmètre (annoncée à l'utilisateur avant implémentation) : construire et
tester rigoureusement tout ce qui est du ressort du code applicatif — gestion des
domaines (CRUD), génération de token de vérification, challenge DNS TXT (avec lookup
injectable pour les tests), durcissement anti Host-Header — et documenter honnêtement
ce qui reste hors de portée dans cet environnement.

## 2. Composants créés

| Fichier | Rôle |
|---|---|
| `core/Tenant/DomainVerificationService.php` | CRUD domaines, génération de token, vérification DNS TXT (lookup DNS injectable), isolation stricte par `findOwned()` |
| `app/Controllers/DomainController.php` | Actions `index/store/verify/toggle/destroy`, permission-gated, toujours scopé à `Session::getUser()['etablissement_id']` |
| `app/Views/settings/domains.php` | Liste des domaines + statut + instructions TXT + formulaire d'ajout |
| `database/migrations/T011_domains_verification_token.php` | Ajoute `verification_token VARCHAR(64)` à `etablissement_domains` |
| `database/migrations/T012_domains_permissions.php` | Seed `domains.view` / `domains.manage` dans `etab_permissions` + `etab_role_permissions` (admin, directeur) |
| `tests/Unit/DomainTenantTest.php` | 40 assertions (voir §6) |

## 3. Composants modifiés

| Fichier | Modification |
|---|---|
| `core/Tenant/TenantResolver.php` | `currentHost()` : rejette tout Host malformé/trop long avant résolution (accepte `localhost`/IP en dev + hôtes RFC 1123) — défense en profondeur, sans changement de comportement pour un Host valide |
| `config/permissions.php` | Ajout `domains.view`, `domains.manage` aux rôles `admin` et `directeur` |
| `config/routes.php` | 5 routes `/parametres/domaines*` |

Aucun fichier V1 déplacé, aucun contrôleur V1 supprimé, aucune route existante modifiée.

## 4. Configuration des domaines

`etablissement_domains` (existant depuis la Phase 14.2, T004) :
`id, etablissement_id, domain (UNIQUE globale), type ENUM('custom','subdomain'), verification_token (NEW T011), verified, ssl_status ENUM('pending','issued','error'), ssl_expires_at, verified_at, created_at`.

Flux d'ajout : `addDomain()` valide le format (RFC 1123 stricte), l'unicité globale (un
domaine ne peut appartenir qu'à un seul établissement), la limite de 10 domaines par
établissement, génère un token aléatoire (`random_bytes(16)` → 32 hex), insère avec
`verified=0`. L'administrateur configure alors le TXT `_edunova-verify.{domaine}` =
`edunova-verify={token}` chez son registrar, puis déclenche `verify()`.

## 5. Adaptations TenantResolver

`TenantRepository::findByDomain()` (Phase 14.2, non modifié) implémentait déjà la
résolution tenant-par-domaine (`WHERE domain = :domain AND verified = 1`) — confirmé
réutilisé tel quel et vérifié par test (§6.5). Le seul changement apporté au
`TenantResolver` est le durcissement de `currentHost()` contre les Host headers
malformés (voir §3), sans toucher à l'ordre de résolution (session → sous-domaine →
domaine personnalisé → chemin → header) ni à aucun comportement testé en Phase 14.2.

**Non modifié / non nécessaire** : `TenantMiddleware` reste non activé dans le pipeline
(cohérent avec toutes les phases précédentes) — la résolution par domaine personnalisé
est donc fonctionnelle et testée au niveau composant, mais pas encore branchée sur le
trafic réel tant que la Phase où le middleware est activé n'a pas eu lieu.

## 6. Tests réalisés

`tests/Unit/DomainTenantTest.php` — 40/40 assertions, transaction PDO annulée en fin
de script (zéro pollution) :

1. **`isValidHostname()`** (10 assertions) : formats valides (domaine simple,
   sous-domaine, TLD court) et rejetés (vide, tiret en tête/fin de label, sans TLD,
   underscore, > 253 caractères, espace).
2. **`addDomain()`** (11 assertions) : normalisation en minuscules, génération de
   token, nom/valeur TXT corrects, format invalide rejeté, **unicité globale**
   (un domaine déjà pris par etabA est refusé pour etabB), **limite de 10 domaines**
   par établissement appliquée.
3. **`verify()`** (6 assertions) : succès avec lookup DNS injecté renvoyant le bon TXT,
   échec si aucun enregistrement ne correspond, **idempotence** (déjà vérifié → true
   sans re-vérifier), **isolation stricte** — etabB ne peut pas vérifier un domaine
   appartenant à etabA (le domaine reste non vérifié après la tentative).
4. **`toggleActive()` / `delete()`** (6 assertions) : isolation stricte inter-tenant
   (etabB ne peut ni désactiver ni supprimer un domaine de etabA — vérifié en base
   après la tentative), le propriétaire légitime peut agir normalement.
5. **Résolution multi-tenant par domaine** (7 assertions) : `TenantRepository::findByDomain()`
   résout correctement 2 domaines vérifiés appartenant à 2 établissements de test
   distincts, un domaine non vérifié ne résout vers aucun établissement (anti-usurpation),
   un domaine inconnu ne résout vers rien.

**Non-régression** : `tests/Unit/TenantResolverTest.php` ré-exécuté après le
durcissement de `currentHost()` — toujours **29/29** assertions passées.

Total cumulé des tests Multi-Tenant (Phases 14.2 → 14.7) :
TenantResolverTest (29) + TenantIsolationTest (24) + RbacTenantTest (18) +
BrandingTenantTest (18) + MultiTenantUserTest (15) + DomainTenantTest (40) = **144**,
plus la suite historique `security_tests.php` (48) + `functional_tests.php` (109) =
**301/301 tests, tous verts**.

## 7. API / Webhooks / Portails

Revue (sans modification, cohérent avec l'approche des Phases 14.4/14.5) : les modules
API Platform (Phase 13.x) et Portails (Phase 12.x) résolvent aujourd'hui l'établissement
depuis la session/l'utilisateur authentifié, pas depuis le nom d'hôte de la requête —
ils ne sont donc pas affectés par la résolution par domaine personnalisé tant que
`TenantMiddleware` n'est pas activé. Aucune adaptation nécessaire à ce stade ; le jour
où le middleware est branché, API et Portails hériteront automatiquement du tenant
résolu par domaine sans changement de code (ils lisent déjà `TenantContext`).

## 8. Anomalies détectées / corrections appliquées

Aucune anomalie détectée dans le code applicatif existant lors de cette phase — le
sous-système est neuf et construit avec l'isolation (`findOwned()`) dès le départ.

## 9. Hors périmètre (infrastructure serveur, non applicable en local)

- Émission / renouvellement automatique de certificats SSL (ACME / Let's Encrypt)
- Reconfiguration dynamique Nginx/Caddy pour servir un nouveau domaine
- Planification récurrente de la re-vérification DNS (nécessite un worker cron/queue —
  Phase 14.9)
- Test réseau réel d'un enregistrement DNS TXT (le lookup est injecté dans les tests ;
  `dns_get_record()` réel n'a pas de domaine public à interroger dans cet environnement)

Ces éléments sont documentés ici plutôt que simulés, pour ne pas laisser croire à une
capacité de production qui n'existe pas encore dans cet environnement.

## 10. Décision finale

**GO WITH FIXES** *(fixes = les 4 points hors périmètre du §9, qui ne bloquent pas
l'usage applicatif du sous-système mais devront être adressés avant un déploiement
public réel)*.

Le CRUD des domaines, la génération/vérification de token, l'isolation stricte
inter-tenant et la résolution par domaine sont implémentés et testés (301/301 tests
verts, zéro régression). Ne pas commencer la phase suivante tant que cette phase n'est
pas validée.
