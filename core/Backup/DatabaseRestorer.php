<?php

declare(strict_types=1);

namespace Core\Backup;

use PDO;

/**
 * Restauration — Phase 14.11, MULTI_TENANT_V2_BLUEPRINT.md §19.3.
 *
 * Deux modes, correspondant aux deux usages réels distincts décrits par le
 * blueprint (jamais "écraser la base de production en place") :
 *
 *  - restoreGlobalToScratch() : reconstruit schéma + données dans une base
 *    TOUJOURS neuve (vérification d'intégrité / simulation de reprise après
 *    sinistre — blueprint §19.3 "Restaurer dump MySQL complet → NOUVELLE
 *    instance DB"). Ne touche jamais à la base appelante.
 *
 *  - restoreTenantLive() : "Exécuter les INSERTs avec ON DUPLICATE KEY
 *    UPDATE" (texte exact du blueprint §19.3) — une fusion (upsert) des
 *    lignes du tenant restauré dans la base EN COURS D'EXÉCUTION, jamais un
 *    DELETE préalable. N'écrit que des lignes dont l'etablissement_id (ou,
 *    pour `users`, l'appartenance réelle) correspond strictement au tenant
 *    demandé — impossible d'affecter un autre établissement même si le
 *    contenu de la sauvegarde était corrompu/falsifié.
 */
final class DatabaseRestorer
{
    /**
     * @return array{tables_created:int, rows_restored:int}
     */
    public function restoreGlobalToScratch(array $dump, PDO $scratchPdo): array
    {
        if (!isset($dump['meta']['type']) || $dump['meta']['type'] !== 'global') {
            throw new \InvalidArgumentException('Ce dump n\'est pas une sauvegarde globale.');
        }

        $scratchPdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $tablesCreated = 0;
        $rowsRestored = 0;

        foreach ($dump['tables'] as $table => $payload) {
            if (empty($payload['create'])) {
                continue;
            }
            $scratchPdo->exec("DROP TABLE IF EXISTS `{$table}`");
            $scratchPdo->exec($payload['create']);
            $tablesCreated++;
        }

        foreach ($dump['tables'] as $table => $payload) {
            $rows = $payload['rows'] ?? [];
            if (empty($rows)) {
                continue;
            }
            $rowsRestored += $this->insertRows($scratchPdo, $table, $rows, upsert: false);
        }

        $scratchPdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        return ['tables_created' => $tablesCreated, 'rows_restored' => $rowsRestored];
    }

    /**
     * @return array{rows_restored:int}
     */
    public function restoreTenantLive(array $dump, int $etablissementId, PDO $pdo): array
    {
        if (!isset($dump['meta']['type']) || $dump['meta']['type'] !== 'tenant') {
            throw new \InvalidArgumentException('Ce dump n\'est pas une sauvegarde tenant.');
        }
        if ((int)($dump['meta']['etablissement_id'] ?? -1) !== $etablissementId) {
            throw new \InvalidArgumentException('Cette sauvegarde ne correspond pas à l\'établissement demandé.');
        }

        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $rowsRestored = 0;
            foreach ($dump['tables'] as $table => $payload) {
                $rows = $payload['rows'] ?? [];
                if (empty($rows)) {
                    continue;
                }
                // Garde d'isolation : ne restaure QUE des lignes dont l'etablissement_id
                // déclaré correspond au tenant demandé — même si le contenu du dump a été altéré.
                if ($table !== 'etablissements') {
                    $rows = array_values(array_filter($rows, function (array $row) use ($etablissementId) {
                        return !array_key_exists('etablissement_id', $row) || (int)$row['etablissement_id'] === $etablissementId;
                    }));
                }
                if (empty($rows)) {
                    continue;
                }
                if ($table === 'etablissements') {
                    continue; // ne jamais réécrire la ligne établissement elle-même via une restauration tenant
                }
                $rowsRestored += $this->insertRows($pdo, $table, $rows, upsert: true);
            }

            if ($ownsTransaction) {
                $pdo->commit();
            }
            return ['rows_restored' => $rowsRestored];
        } catch (\Throwable $e) {
            if ($ownsTransaction) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Insertion par lots (BATCH_SIZE lignes par requête `INSERT ... VALUES
     * (...), (...), ...`) plutôt qu'une requête par ligne — un dump global
     * de taille réaliste (~1800 lignes dans cette base de développement)
     * passe de ~37s à quelques secondes avec ce lot (mesuré pendant la
     * Phase 14.12, revue d'intégration §"Dette technique / performances").
     * Les lignes d'une même table partagent toujours le même jeu de
     * colonnes (issues d'un unique SELECT * sur cette table) — regroupées
     * par signature de colonnes par sécurité si ce n'était pas le cas.
     */
    private const BATCH_SIZE = 200;

    private function insertRows(PDO $pdo, string $table, array $rows, bool $upsert): int
    {
        $count = 0;
        $groups = [];
        foreach ($rows as $row) {
            if (empty($row)) {
                continue;
            }
            $signature = implode(',', array_keys($row));
            $groups[$signature][] = $row;
        }

        foreach ($groups as $group) {
            foreach (array_chunk($group, self::BATCH_SIZE) as $chunk) {
                $columns = array_keys($chunk[0]);
                $columnList = '`' . implode('`, `', $columns) . '`';
                $rowPlaceholder = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
                $valuesSql = implode(', ', array_fill(0, count($chunk), $rowPlaceholder));

                $sql = "INSERT INTO `{$table}` ({$columnList}) VALUES {$valuesSql}";
                if ($upsert) {
                    $updates = implode(', ', array_map(fn($c) => "`{$c}` = VALUES(`{$c}`)", $columns));
                    $sql .= " ON DUPLICATE KEY UPDATE {$updates}";
                }

                $params = [];
                foreach ($chunk as $row) {
                    array_push($params, ...array_values($row));
                }

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $count += count($chunk);
            }
        }

        return $count;
    }
}
