# Sauvegardes — Guide opérationnel

Référence technique : `docs/technique/MULTI_TENANT.md` §8,
`MULTI_TENANT_BACKUP_DR_IMPLEMENTATION_REPORT.md`. Ce document couvre l'usage
opérationnel.

## 1. Types de sauvegarde

| Type | Contenu | Rétention par défaut | Déclenchement |
|---|---|---|---|
| Globale (`global`/`manual`) | Schéma + données de toutes les tables | 30 jours | Portail ou CLI |
| Pré-migration (`pre_migration`) | Identique à globale | 7 jours | Manuel (non automatisé avant `migrate.php` — voir §5) |
| Par établissement (`tenant`) | Données uniquement, tables tenant-scopées | 28 jours | Portail ou CLI |
| Différentielle (`differential`) | Lignes créées depuis une date donnée | 14 jours | Portail ou CLI |

Chiffrement **AES-256-CBC activé par défaut**, clé dérivée de `APP_KEY`. Stockage
via `Core\Storage` (local aujourd'hui, S3-compatible dès qu'un adaptateur réel sera
implémenté).

## 2. Depuis le portail Super-Admin

`/platform/backups` (réservé au niveau opérateur `super_admin` — voir
`docs/technique/RBAC.md` §6) :

1. **Sauvegarde globale** : bouton "Lancer maintenant".
2. **Sauvegarde par établissement** : sélectionner l'établissement, "Sauvegarder ce tenant".
3. **Sauvegarde différentielle** : établissement (ou toute la plateforme) + nombre
   de jours en arrière.

Chaque sauvegarde apparaît dans l'historique avec son statut, sa taille, et si
elle est chiffrée.

## 3. Vérifier l'intégrité d'une sauvegarde

Bouton "Vérifier" sur une sauvegarde réussie : recalcule le checksum SHA-256 du
fichier stocké et le compare à celui enregistré à la création — détecte toute
altération.

Pour une sauvegarde **globale**, un second niveau de vérification est disponible :
"Vérifier par restauration" restaure réellement schéma + données dans une base
MySQL **temporaire, créée puis immédiatement supprimée** — ne touche jamais la
base de production. C'est la validation la plus rigoureuse disponible.

## 4. Planification automatique

**Aucun scheduler n'est actif par défaut** dans cet environnement (pas de
Supervisord/cron intégré à l'application — voir `docs/technique/MULTI_TENANT.md`
§6). Pour automatiser une sauvegarde quotidienne :

```bash
# Pousser une tâche de sauvegarde globale dans la file
php -r "
require 'core/Backup/...';
// Voir app/Jobs/PlatformBackupJob.php pour le format du payload attendu
"
# Puis, périodiquement (cron système, ex. tous les jours à 02h00) :
php database/queue-worker.php --limit=10
```

Recommandation : configurer une tâche planifiée système (cron Linux ou
Planificateur de tâches Windows) qui pousse une tâche `PlatformBackupJob` (type
`global`) puis appelle `queue-worker.php` — voir
`MULTI_TENANT_CACHE_QUEUE_IMPLEMENTATION_REPORT.md` pour le détail du mécanisme de
file d'attente.

## 5. Sauvegarde avant migration

Le blueprint prévoit une sauvegarde automatique de type `pre_migration` avant toute
migration SQL. **Non câblée automatiquement** dans `database/migrate.php` à ce
jour (voir `MULTI_TENANT_BACKUP_DR_IMPLEMENTATION_REPORT.md` §10). En attendant,
déclenchez manuellement une sauvegarde globale avant toute campagne de migration en
production.

## 6. Téléchargement

Bouton "Télécharger" — fichier chiffré brut (`.bak`), à conserver hors site pour
une reprise après sinistre complet (voir `docs/deploiement/RESTAURATION.md`).

## 7. Documents associés

`docs/deploiement/RESTAURATION.md`, `docs/technique/MULTI_TENANT.md` §8.
