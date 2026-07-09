# Restauration — Guide opérationnel

Référence technique : `docs/technique/MULTI_TENANT.md` §8,
`MULTI_TENANT_BACKUP_DR_IMPLEMENTATION_REPORT.md`. **Lisez ce document en entier
avant de restaurer quoi que ce soit** — les deux mécanismes disponibles ont des
comportements très différents.

## 1. Les deux mécanismes de restauration — bien comprendre la différence

| | Restauration **globale** de vérification | Restauration **tenant** en direct |
|---|---|---|
| Cible | Base **temporaire**, créée puis supprimée automatiquement | Base **de production**, en cours d'exécution |
| Effet | **Aucun** sur les données réelles — vérification pure | **Fusionne** (upsert) les données du tenant restauré dans la base réelle |
| Destructif ? | Non, jamais | Non — `INSERT ... ON DUPLICATE KEY UPDATE`, jamais de suppression préalable |
| Confirmation requise | Aucune | **Oui** — saisir le slug exact de l'établissement |
| Cas d'usage | Vérifier qu'une sauvegarde globale est exploitable, tester un scénario de reprise après sinistre | Récupérer les données d'UN établissement après une erreur/perte localisée |

**Il n'existe volontairement aucun mécanisme "restaurer une sauvegarde globale en
écrasant la base de production en place".** La procédure de reprise après sinistre
total consiste à restaurer vers une **nouvelle instance** de base de données (voir
§3), jamais à écraser l'instance en cours.

## 2. Restaurer les données d'un établissement précis

Situation typique : une erreur de manipulation a corrompu/supprimé des données
d'un seul établissement, les autres établissements ne sont pas concernés.

1. Portail Super-Admin → `/platform/backups` → identifier une sauvegarde `tenant`
   récente et réussie pour l'établissement concerné.
2. Cliquer "Restaurer chez ce tenant" — un formulaire de confirmation apparaît.
3. **Saisir exactement le slug** de l'établissement (ex. `lycee-ibn-badis`) pour
   confirmer — toute autre valeur bloque l'opération.
4. Valider. Le résultat indique le nombre de lignes fusionnées.

Ce que fait réellement l'opération : pour chaque ligne du dump, `INSERT ...
ON DUPLICATE KEY UPDATE` — les lignes existantes (même id) sont mises à jour avec
les valeurs sauvegardées, les lignes absentes sont recréées. **Aucune ligne des
autres établissements n'est jamais touchée**, même si le contenu de la sauvegarde
était corrompu (garde d'isolation vérifiant l'`etablissement_id` de chaque ligne
avant insertion).

## 3. Reprise après sinistre total (perte de la base de production)

1. Provisionner une **nouvelle** instance MySQL (nouveau serveur ou nouvelle base
   vide sur l'infrastructure de secours).
2. Récupérer le dernier fichier de sauvegarde globale réussie (téléchargé
   régulièrement hors site, voir `docs/deploiement/SAUVEGARDES.md` §6).
3. Utiliser le mécanisme de restauration globale (portail ou appel direct à
   `Core\Backup\BackupService`/`DatabaseRestorer::restoreGlobalToScratch()`) en
   pointant vers cette **nouvelle** base — jamais vers l'ancienne instance
   défaillante.
4. Reconfigurer l'application (`.env` → `DB_*`) pour pointer vers la nouvelle
   instance.
5. Basculer le DNS/reverse proxy vers la nouvelle instance applicative.
6. Vérifier : `curl https://votre-domaine/api/v1/health`, puis un contrôle
   fonctionnel complet (connexion, quelques écrans clés par module actif).

## 4. Vérifier une sauvegarde sans rien restaurer en production

Portail → sauvegarde globale → "Vérifier par restauration" (voir
`docs/deploiement/SAUVEGARDES.md` §3) : restaure réellement dans une base jetable,
rapporte le nombre de tables/lignes restaurées, puis supprime cette base — méthode
recommandée pour valider périodiquement que vos sauvegardes sont exploitables,
sans aucun risque pour la production.

## 5. Historique et traçabilité

Toute tentative de restauration (réussie, échouée, ou bloquée par une confirmation
invalide) est journalisée dans l'historique des restaurations du portail, avec
opérateur, horodatage, nombre de lignes, durée.

## 6. En cas d'échec

Une restauration échouée (intégrité compromise, confirmation invalide, erreur SQL)
n'altère **jamais** partiellement la base cible — chaque restauration tenant est
enveloppée dans une transaction unique (tout ou rien). Consultez le message
d'erreur dans l'historique et vérifiez d'abord l'intégrité de la sauvegarde
(`docs/deploiement/SAUVEGARDES.md` §3) avant de réessayer.
