<?php

declare(strict_types=1);

namespace Core\Backup;

use PDO;

/**
 * Extraction de sauvegardes — Phase 14.11, MULTI_TENANT_V2_BLUEPRINT.md §19.
 *
 * Format de sauvegarde STRUCTURÉ (tableau PHP sérialisé en JSON), pas un
 * dump SQL brut : c'est ce même code qui écrit ET relit le format
 * (DatabaseRestorer), donc aucune ambiguïté d'analyse syntaxique SQL
 * (pas de découpage fragile sur des `;` qui pourraient apparaître dans une
 * valeur de colonne) — chaque ligne est une simple liste de valeurs déjà
 * typées par PDO (JSON gère nativement string/int/float/null/bool).
 *
 * Découverte dynamique des tables tenant-scopées via
 * INFORMATION_SCHEMA.COLUMNS (colonne `etablissement_id`) plutôt qu'une
 * liste figée en dur — reste exact si un futur module ajoute une table
 * tenant-scopée sans que ce fichier soit mis à jour.
 *
 * EXCLUSION `platform_*` : ces tables (platform_backups, platform_restores,
 * platform_plans, platform_operators...) portent parfois elles-mêmes une
 * colonne `etablissement_id` (ex: platform_backups, pour savoir à quel
 * tenant une sauvegarde appartient — blueprint §19.4) mais sont des
 * métadonnées du SYSTÈME de plateforme, jamais des données métier d'un
 * établissement. Les inclure dans une sauvegarde/restauration tenant serait
 * non seulement hors-sujet mais activement dangereux : une sauvegarde
 * tenant capturerait sa PROPRE ligne `platform_backups` en cours
 * d'écriture (storage_path pas encore finalisé), et la restaurer plus tard
 * écraserait la ligne réelle avec cet état incomplet — bogue trouvé et
 * corrigé pendant les tests (voir MULTI_TENANT_BACKUP_DR_IMPLEMENTATION_REPORT.md).
 */
final class DatabaseDumper
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<string> */
    public function tenantScopedTables(): array
    {
        $stmt = $this->pdo->query(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE COLUMN_NAME = 'etablissement_id' AND TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME NOT LIKE 'platform\\_%'
             ORDER BY TABLE_NAME"
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return list<string> toutes les tables de la base courante */
    public function allTables(): array
    {
        $stmt = $this->pdo->query(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME"
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Sauvegarde globale (Niveau 1 du blueprint §19.1) : schéma + données de
     * TOUTES les tables — destinée à une restauration vers une NOUVELLE
     * instance de base (reprise après sinistre), jamais un remplacement en
     * place de la base en production.
     */
    public function dumpGlobal(): array
    {
        $tables = [];
        foreach ($this->allTables() as $table) {
            $tables[$table] = [
                'create' => $this->showCreateTable($table),
                'rows'   => $this->fetchAllRows($table),
            ];
        }

        return [
            'meta'   => [
                'type'           => 'global',
                'generated_at'   => date('c'),
                'db_name'        => $this->pdo->query('SELECT DATABASE()')->fetchColumn(),
                'table_count'    => count($tables),
            ],
            'tables' => $tables,
        ];
    }

    /**
     * Sauvegarde par tenant (Niveau 2, §19.1/§19.2) : UNIQUEMENT les lignes
     * de CET établissement, sur les tables tenant-scopées + la ligne
     * `etablissements` elle-même. `users` est filtré via `user_etablissements`
     * (appartenance réelle multi-établissement, Phase 14.6) plutôt que la
     * colonne `users.etablissement_id` (transition V1, peut être imprécise
     * pour un utilisateur multi-écoles) — recommandation explicite du
     * blueprint §19.2. Ne contient PAS le schéma (restauration = mise à
     * jour d'une base où l'application tourne déjà).
     */
    public function dumpTenant(int $etablissementId): array
    {
        $tables = [];

        $etabStmt = $this->pdo->prepare("SELECT * FROM etablissements WHERE id = ?");
        $etabStmt->execute([$etablissementId]);
        $etabRow = $etabStmt->fetch(PDO::FETCH_ASSOC);
        if ($etabRow === false) {
            throw new \InvalidArgumentException("Établissement introuvable : {$etablissementId}");
        }
        $tables['etablissements'] = ['rows' => [$etabRow]];

        foreach ($this->tenantScopedTables() as $table) {
            if ($table === 'users') {
                $stmt = $this->pdo->prepare(
                    "SELECT * FROM users WHERE id IN (SELECT user_id FROM user_etablissements WHERE etablissement_id = ?)"
                );
            } else {
                $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE etablissement_id = ?");
            }
            $stmt->execute([$etablissementId]);
            $tables[$table] = ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
        }

        return [
            'meta'   => [
                'type'             => 'tenant',
                'etablissement_id' => $etablissementId,
                'generated_at'     => date('c'),
                'db_name'          => $this->pdo->query('SELECT DATABASE()')->fetchColumn(),
                'table_count'      => count($tables),
            ],
            'tables' => $tables,
        ];
    }

    /**
     * Sauvegarde différentielle — approximation volontairement simple
     * (blueprint 14.11 : "si prévue") : ne contient que les lignes créées
     * depuis $since, sur la base des colonnes `created_at` (présentes sur
     * toutes les tables tenant-scopées de cette application). Une
     * différentielle fondée sur le binlog MySQL (capture aussi les UPDATE/
     * DELETE) est hors de portée applicative — voir rapport §"Hors périmètre".
     */
    public function dumpDifferential(\DateTimeImmutable $since, ?int $etablissementId = null): array
    {
        $tables = [];
        $tableList = $etablissementId !== null ? $this->tenantScopedTables() : $this->allTables();

        foreach ($tableList as $table) {
            if (!$this->hasColumn($table, 'created_at')) {
                continue; // table sans created_at : non éligible à une différentielle par date
            }

            if ($etablissementId !== null && $table === 'users') {
                $stmt = $this->pdo->prepare(
                    "SELECT * FROM users WHERE created_at >= ? AND id IN (SELECT user_id FROM user_etablissements WHERE etablissement_id = ?)"
                );
                $stmt->execute([$since->format('Y-m-d H:i:s'), $etablissementId]);
            } elseif ($etablissementId !== null) {
                $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE created_at >= ? AND etablissement_id = ?");
                $stmt->execute([$since->format('Y-m-d H:i:s'), $etablissementId]);
            } else {
                $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE created_at >= ?");
                $stmt->execute([$since->format('Y-m-d H:i:s')]);
            }

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $tables[$table] = ['rows' => $rows];
            }
        }

        return [
            'meta'   => [
                'type'             => 'differential',
                'since'            => $since->format('c'),
                'etablissement_id' => $etablissementId,
                'generated_at'     => date('c'),
                'db_name'          => $this->pdo->query('SELECT DATABASE()')->fetchColumn(),
                'table_count'      => count($tables),
            ],
            'tables' => $tables,
        ];
    }

    private function showCreateTable(string $table): string
    {
        $stmt = $this->pdo->query("SHOW CREATE TABLE `{$table}`");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['Create Table'] ?? '';
    }

    private function fetchAllRows(string $table): array
    {
        return $this->pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    }

    private function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (!array_key_exists($key, $cache)) {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
            );
            $stmt->execute([$table, $column]);
            $cache[$key] = (int)$stmt->fetchColumn() > 0;
        }
        return $cache[$key];
    }
}
