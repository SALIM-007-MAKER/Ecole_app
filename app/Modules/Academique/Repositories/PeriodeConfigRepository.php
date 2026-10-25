<?php

namespace App\Modules\Academique\Repositories;

use Core\Database;
use Core\Tenant\TenantScope;
use PDO;

class PeriodeConfigRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Configuration PROPRE à l'établissement (T053). Un établissement sans configuration reçoit une copie du gabarit (lignes sans
     * propriétaire) — copie du modèle, jamais attribution. Sans colonne (base non migrée) ou sans contexte : configuration partagée.
     */
    /** Portée de la configuration : l'établissement courant ; SANS contexte (CLI, tests) uniquement le gabarit (lignes sans propriétaire). */
    private function scope(string $connecteur): array
    {
        if (!TenantScope::colonneEtablissement('periodes_scolaires_config')) { return ['', []]; }
        $id = TenantScope::id();
        return $id === null ? [" {$connecteur} etablissement_id IS NULL", []] : [" {$connecteur} etablissement_id = ?", [$id]];
    }

    private function amorcer(): void
    {
        $etab = TenantScope::id();
        if ($etab === null || !TenantScope::colonneEtablissement('periodes_scolaires_config')) { return; }
        $n = $this->pdo->prepare('SELECT COUNT(*) FROM `periodes_scolaires_config` WHERE etablissement_id = ?');
        $n->execute([$etab]);
        if ((int)$n->fetchColumn() > 0) { return; }
        $cols = array_column($this->pdo->query(
            "SELECT column_name AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'periodes_scolaires_config' AND column_name NOT IN ('id', 'etablissement_id', 'updated_at', 'updated_by') ORDER BY ordinal_position"
        )->fetchAll(PDO::FETCH_ASSOC), 'c');
        $liste = '`' . implode('`, `', $cols) . '`';
        $this->pdo->prepare("INSERT INTO `periodes_scolaires_config` (etablissement_id, $liste) SELECT ?, $liste FROM `periodes_scolaires_config` WHERE etablissement_id IS NULL")->execute([$etab]);
    }

    public function all(): array
    {
        $this->amorcer();
        [$ts, $tp] = $this->scope('WHERE');
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `periodes_scolaires_config`" . $ts . " ORDER BY ordre ASC, numero ASC"
        );
        $stmt->execute($tp);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function find(int $numero): ?\stdClass
    {
        [$ts, $tp] = $this->scope('AND');
        $stmt = $this->pdo->prepare("SELECT * FROM `periodes_scolaires_config` WHERE numero = ?" . $ts);
        $stmt->execute([$numero, ...$tp]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    public function update(int $numero, array $data, int $userId): void
    {
        $data['updated_by'] = $userId;
        $sets   = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[]   = "`{$col}` = ?";
            $params[] = $val;
        }
        $params[] = $numero;
        [$ts, $tp] = $this->scope('AND');
        array_push($params, ...$tp);

        $stmt = $this->pdo->prepare(
            "UPDATE `periodes_scolaires_config` SET " . implode(', ', $sets) . " WHERE numero = ?" . $ts
        );
        $stmt->execute($params);
    }

    public function count(): int
    {
        $this->amorcer();
        [$ts, $tp] = $this->scope('WHERE');
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `periodes_scolaires_config`" . $ts);
        $stmt->execute($tp);
        return (int)$stmt->fetchColumn();
    }
}
