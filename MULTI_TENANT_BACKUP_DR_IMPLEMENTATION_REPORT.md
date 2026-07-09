# Phase 14.11 — Sauvegardes & Reprise après incident — Rapport d'implémentation

## 1. Contexte & périmètre

Blueprint §25.1, ligne Phase 14.11 : *"Sauvegardes Automatiques — Backup quotidien +
export tenant — Livrable : TenantBackupJob, UI téléchargement backup — Validation :
Backup créé → restauré sur DB test → données intactes."* Le détail de la stratégie
vit au §19 (schéma `platform_backups` §19.4, stratégie 3 niveaux §19.1, isolation du
dump §19.2, restauration §19.3) — repris ici quasi à l'identique.

Cette phase ne bute sur aucune limite d'infrastructure : contrairement à Redis/S3/
Supervisord (Phases 14.8/14.9), tout ce qui est demandé ici (dump, chiffrement,
restauration, vérification) est réalisable en PHP pur + MySQL, sans dépendance
externe. Décision de conception centrale : le format de sauvegarde est un **format
structuré maison** (tableau PHP → JSON), pas un dump SQL texte brut — c'est le même
code qui écrit et relit ce format, ce qui élimine toute la classe de bugs liée au
découpage fragile d'instructions SQL sur des `;` (un `;` peut apparaître dans une
valeur de colonne et casser un parseur SQL naïf).

## 2. Composants créés

| Fichier | Rôle |
|---|---|
| `database/migrations/T018_backup_dr_infrastructure.php` | `platform_backups` (schéma **exact** du blueprint §19.4, `type` étendu additivement avec `'differential'`), `platform_restores` (journalisation des restaurations, absente du blueprint mais nécessaire à "traçabilité des opérations") |
| `core/Backup/DatabaseDumper.php` | `dumpGlobal()` (schéma+données, toutes tables), `dumpTenant()` (données uniquement, tables tenant-scopées découvertes dynamiquement), `dumpDifferential()` (filtre par date) |
| `core/Backup/DatabaseRestorer.php` | `restoreGlobalToScratch()` (vers une base neuve), `restoreTenantLive()` (upsert non destructif vers la base en cours d'exécution — exact du blueprint §19.3) |
| `core/Backup/BackupEncryption.php` | AES-256-CBC, clé dérivée de `APP_KEY` (même convention que Storage/API) |
| `core/Backup/BackupService.php` | Orchestration complète : création, ledger, intégrité, restauration, historique, statistiques, purge |
| `app/Jobs/PlatformBackupJob.php` | Tâche de file d'attente (réutilise la Phase 14.9) pour les sauvegardes planifiées |
| `app/Controllers/PlatformBackupController.php` | 9 actions, **exclusivement niveau `super_admin`** |
| `app/Views/platform/backups/index.php` | Stats, déclenchement, historiques sauvegardes/restaurations |
| `tests/Unit/BackupDrTest.php` | 43 assertions (voir §8) |

## 3. Composants modifiés

| Fichier | Modification |
|---|---|
| `config/routes.php` | 9 routes `/platform/backups*` |
| `app/Views/layouts/platform.php` | Lien "Sauvegardes" dans la nav, visible uniquement aux opérateurs `super_admin` |

Aucun fichier V1 déplacé, aucune route existante modifiée. `config/permissions.php`
non touché (même discipline d'isolation RBAC que la Phase 14.10 — ce portail n'a
aucun lien avec le RBAC établissement).

## 4. Stratégie de sauvegarde

Conforme au blueprint §19.1 (3 niveaux), plus la différentielle explicitement
demandée par cette phase :

| Niveau | Type | Contenu | Rétention par défaut |
|---|---|---|---|
| 1 — Globale | `global`/`manual` | Schéma + données de **toutes** les tables | 30 jours |
| 2 — Par tenant | `tenant` | Données uniquement, tables tenant-scopées + la ligne `etablissements` | 28 jours |
| 3 — Pré-migration | `pre_migration` | Identique à globale, déclenchement prévu mais non câblé (voir §9) | 7 jours |
| Différentielle | `differential` | Lignes créées depuis une date donnée (`created_at >=`), plateforme entière ou un seul tenant | 14 jours |

**Isolation du dump tenant (§19.2)** : tables tenant-scopées découvertes
dynamiquement via `INFORMATION_SCHEMA.COLUMNS` (colonne `etablissement_id`) — reste
exact si un futur module ajoute une table tenant-scopée sans modifier ce fichier.
`users` est filtré via `user_etablissements` (appartenance réelle multi-établissement)
plutôt que la colonne `users.etablissement_id`, recommandation explicite du blueprint.

**Chiffrement** : AES-256-CBC, activé par défaut (`encrypt: true`), désactivable
explicitement. IV aléatoire par sauvegarde, clé dérivée de `APP_KEY` (jamais stockée
en clair dans le ledger).

**Stockage** : réutilise `Core\Storage` (Phase 14.8) — compatible local/S3 par
construction, sans code supplémentaire (le jour où `STORAGE_DRIVER=s3` sera
opérationnel, les sauvegardes en bénéficieront automatiquement).

**Planification** : `PlatformBackupJob` réutilise la file d'attente de la Phase 14.9
— pousser une tâche puis exécuter `database/queue-worker.php`. Aucun scheduler
automatique quotidien (pas de Supervisord/cron dans cet environnement, déjà
documenté en 14.9) — voir §9.

## 5. Stratégie de restauration

Deux mécanismes distincts, correspondant à deux usages réels différents décrits par
le blueprint — **jamais** un remplacement en place de la base de production :

- **`restoreGlobalToScratch()`** : reconstruit schéma + données dans une base
  **toujours neuve**, créée puis détruite automatiquement (blueprint §19.3 :
  "Restaurer dump MySQL complet → **nouvelle** instance DB"). C'est le mécanisme de
  "vérification d'intégrité avant restauration" de cette phase : la vérification EST
  une restauration réelle, complète, dans un environnement jetable — pas une
  simulation. Ne touche jamais la base appelante.

- **`restoreTenantLive()`** : "Exécuter les INSERTs avec `ON DUPLICATE KEY UPDATE`"
  (citation exacte du blueprint §19.3) — une **fusion** (upsert) des lignes du tenant
  dans la base en cours d'exécution, jamais un DELETE préalable. Garde d'isolation
  stricte : chaque ligne restaurée est revérifiée contre l'`etablissement_id` demandé
  **même si le contenu du dump avait été altéré** — testé explicitement en injectant
  une ligne falsifiée portant l'identifiant d'un autre tenant dans un dump légitime
  (§8.4).

**Confirmation obligatoire** : `restoreTenantLive()` (niveau service) exige que
l'opérateur saisisse le **slug exact** de l'établissement cible — filet de sécurité
contre un clic accidentel sur une action qui modifie des données réelles, sans pour
autant bloquer l'opération légitime (le mécanisme sous-jacent, upsert, n'est de toute
façon jamais destructif pour les autres tenants).

## 6. Plan de reprise après incident (Disaster Recovery)

- **Reprise totale** : `restoreGlobalToScratch()` vers une nouvelle instance —
  exactement le scénario "sinistre total" du blueprint §19.3. Testé de bout en bout
  (création réelle d'une base MySQL, restauration complète, vérification des
  comptages de lignes, suppression) — voir §8.5.
- **Vérification de cohérence** : `verifyIntegrity()` recalcule le SHA-256 du
  contenu stocké et le compare au checksum enregistré au moment de la création — 
  détecte toute altération du fichier de sauvegarde (testé en modifiant
  volontairement le contenu stocké sur disque, §8.3).
- **Gestion des erreurs critiques** : toute exception pendant une création/
  restauration est capturée, journalisée (`statut='failed'`, `error_message`), et
  ne laisse jamais le ledger dans un état ambigu (`pending`/`running` orphelin).
- **Reprise progressive** : la restauration tenant (upsert, isolée par
  établissement) permet de restaurer les établissements un par un, sans interrompre
  le service des autres — c'est la conséquence directe du modèle "Shared Database,
  Shared Schema" combiné à l'upsert non destructif : aucune coupure de service
  globale n'est nécessaire pour restaurer un seul tenant.

## 7. API internes

Toutes les routes `/platform/backups*` sont gardées par
`requirePlatformLevel('super_admin')` **exclusivement** (pas `admin`, contrairement
au reste du portail Platform) — exigence explicite de cette phase.

| Route | Description |
|---|---|
| `GET /platform/backups/api` | JSON : statistiques, historique sauvegardes, historique restaurations |
| `POST /platform/backups/global` \| `/tenant` \| `/differentiel` | Lancement des sauvegardes |
| `POST /platform/backups/{id}/verifier` | Vérification d'intégrité (checksum) |
| `POST /platform/backups/{id}/restaurer-verif` | Restauration de vérification (base jetable) |
| `POST /platform/backups/{id}/restaurer-tenant` | Restauration tenant en direct (upsert + confirmation) |
| `GET /platform/backups/{id}/telecharger` | Téléchargement sécurisé (jamais d'URL publique) |

## 8. Tests réalisés

`tests/Unit/BackupDrTest.php` — 43/43 assertions, avec **2 établissements de test à
jeux de données différents** (1 classe pour A, 2 classes pour B), transaction PDO
annulée en fin de script + nettoyage explicite des fichiers de sauvegarde physiques :

1. **`DatabaseDumper`** (14 assertions) : découverte dynamique des tables
   tenant-scopées, `dumpTenant(A)` contient EXACTEMENT les données de A et RIEN de B
   (vérifié dans les deux sens), `dumpGlobal()` inclut le schéma complet, `dumpDifferential()`
   filtre correctement par date (vide si `since` dans le futur, non vide si récent).
2. **`BackupEncryption`** (3 assertions) : round-trip chiffrement/déchiffrement, une
   charge utile altérée d'un seul octet est détectée et rejetée.
3. **`BackupService` — création & intégrité** (10 assertions) : ledger
   `platform_backups` correctement peuplé (statut, type, checksum, durée), deux
   tenants différents produisent des checksums différents, `verifyIntegrity()`
   détecte une altération du fichier stocké sur disque puis redevient vraie une fois
   restauré.
4. **Restauration tenant en direct — upsert non destructif** (5 assertions) :
   modifier une donnée après sauvegarde puis restaurer ramène bien la valeur
   sauvegardée, **zéro effet sur l'autre tenant**, et — point de sécurité
   central — une ligne falsifiée portant l'`etablissement_id` d'un AUTRE tenant dans
   un dump par ailleurs légitime est filtrée et **jamais insérée**.
5. **Restauration globale de vérification** (5 assertions) : une base MySQL réelle
   est créée, le schéma + les données y sont intégralement restaurés (>40 tables),
   puis la base est supprimée — vérifié via `INFORMATION_SCHEMA.SCHEMATA` qu'elle
   n'existe plus, et que la base appelante est totalement inchangée.
6. **Journalisation & confirmation** (6 assertions) : une confirmation textuelle
   incorrecte est rejetée AVANT tout accès aux données, chaque tentative
   (réussie ou non) est journalisée dans `platform_restores` avec le bon type/statut/
   opérateur.

**Non-régression** : intégralité des tests des Phases 14.2 → 14.10 ré-exécutée,
tous verts sans modification. Suite historique `security_tests.php` (48/48) et
`functional_tests.php` (109/109) également ré-exécutées.

Total cumulé (Phases 14.2 → 14.11) : **470/470 tests, tous verts**
(313 tests Multi-Tenant + 157 suite historique).

**Vérification HTTP live** (`curl`) : `GET /platform/backups` sans session opérateur
→ 302 vers `/platform/login` (garde d'accès fonctionnelle en conditions réelles).

## 9. Anomalie détectée / correction appliquée

**Bug réel, sérieux, trouvé pendant les tests** : `DatabaseDumper::tenantScopedTables()`
découvrait dynamiquement les tables tenant-scopées via la présence d'une colonne
`etablissement_id` — mais `platform_backups` possède **elle-même** cette colonne
(légitimement, per blueprint §19.4 : `NULL` = sauvegarde globale). Conséquence en
cascade : une sauvegarde tenant capturait **sa propre ligne** `platform_backups`,
encore en cours d'écriture au moment du dump (`storage_path` pas encore finalisé,
`checksum` encore `NULL`) ; en restaurant cette sauvegarde plus tard (upsert), la
ligne réelle et correctement finalisée du ledger était **écrasée** par cet état
incomplet — rendant la sauvegarde elle-même illisible (`storage_path` vide) après
sa propre restauration. Corrigé en excluant explicitement les tables `platform_*`
(métadonnées système de la plateforme, jamais des données métier d'un établissement)
de la découverte dynamique. Détecté par `tests/Unit/BackupDrTest.php` section 6
(assertion "la ligne platform_backups de A reste intacte"), corrigé, re-testé
(43/43). Ce bug aurait pu, en production, rendre silencieusement inutilisable
n'importe quelle sauvegarde tenant après une restauration ultérieure de ce même
tenant — trouvé avant toute mise en production grâce au test de bout en bout
create→modify→restore→verify.

## 10. Hors périmètre

- **Planification automatique quotidienne** (blueprint §19.1 : "02h00 UTC") :
  `PlatformBackupJob` est réel et testé au niveau infrastructure (Phase 14.9), mais
  aucun démon/cron ne l'appelle automatiquement — même limite déjà documentée
  (Windows/WAMP local, pas de Supervisord). Un déclenchement manuel (portail) ou
  planifié via `schtasks`/CI externe reste possible dès aujourd'hui.
- **Snapshot automatique avant migration** (`pre_migration`, Niveau 3 du blueprint) :
  le type existe dans le ledger et `createGlobal(..., 'pre_migration')` fonctionne,
  mais n'est pas câblé automatiquement dans `database/migrate.php` — l'intégrer
  changerait le comportement d'un outil déjà utilisé à chaque phase depuis 14.2 ;
  risque jugé disproportionné par rapport au bénéfice pour cette phase.
- **Interface tenant "Télécharger ma sauvegarde"** (blueprint §19.1, Niveau 2) : la
  demande explicite de cette phase restreint les endpoints au rôle Super Admin
  uniquement ("Ces endpoints doivent être réservés au rôle SaaS Super Admin") — une
  UI self-service côté établissement est une extension distincte, non construite ici.
- **Restauration point-in-time via binlog MySQL** (plan Enterprise, blueprint §19.3) :
  nécessite une configuration serveur MySQL (binlog activé, rétention) hors de
  portée applicative dans cet environnement de développement.
- **Export ZIP chiffré par clé de plan** (blueprint §19.1 : "clé dérivée du plan") :
  le chiffrement implémenté utilise une clé unique dérivée de `APP_KEY` pour toute la
  plateforme, pas une clé par plan tarifaire — simplification déraisonnable à lever
  seule dans cette phase sans le système de gestion de clés qu'elle impliquerait.

## 11. Décision finale

**GO WITH FIXES** *(fixes = les 5 points hors périmètre du §10, aucun ne bloquant
l'usage réel du sous-système)*.

Sauvegarde (globale/tenant/différentielle, chiffrée, checksummée), restauration
(vérification par base jetable réelle + restauration tenant upsert non destructive
avec confirmation), journalisation complète, et endpoints strictement réservés au
rôle Super Admin sont implémentés et testés de bout en bout avec 2 établissements à
jeux de données distincts. 470/470 tests verts, zéro régression, 1 anomalie sérieuse
trouvée et corrigée (auto-corruption d'une sauvegarde tenant via sa propre ligne de
ledger). Ne pas commencer la revue finale du Multi-Tenant tant que cette phase n'est
pas validée.
