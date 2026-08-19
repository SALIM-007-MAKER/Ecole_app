<?php

namespace App\Modules\Finance\Repositories;

use App\Modules\Finance\DTO\DecaissementFiltersDTO;
use Core\Database;
use PDO;

class DecaissementRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    // ── Numérotation ─────────────────────────────────────────────────────────

    public function genererNumero(string $type, int $annee): string
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO `finance_sequences` (`type`, `annee`, `valeur`) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE `valeur` = `valeur` + 1"
        );
        $stmt->execute([$type, $annee]);
        $stmt = $this->pdo->prepare('SELECT `valeur` FROM `finance_sequences` WHERE `type` = ? AND `annee` = ?');
        $stmt->execute([$type, $annee]);
        $val = (int)$stmt->fetchColumn();
        return sprintf('%s-%d-%04d', $type, $annee, $val);
    }

    // ── Décaissements — lecture ──────────────────────────────────────────────

    public function paginate(DecaissementFiltersDTO $f): array
    {
        $where  = ['d.deleted_at IS NULL'];
        $params = [];

        if ($f->q) {
            $where[]  = '(d.numero LIKE ? OR d.libelle LIKE ? OR fo.nom LIKE ?)';
            $like     = '%' . $f->q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($f->statut) {
            $where[]  = 'd.statut = ?';
            $params[] = $f->statut;
        }
        if ($f->categorieId) {
            $where[]  = 'd.categorie_id = ?';
            $params[] = $f->categorieId;
        }
        if ($f->fournisseurId) {
            $where[]  = 'd.fournisseur_id = ?';
            $params[] = $f->fournisseurId;
        }
        if ($f->dateDebut) {
            $where[]  = 'd.date_depense >= ?';
            $params[] = $f->dateDebut;
        }
        if ($f->dateFin) {
            $where[]  = 'd.date_depense <= ?';
            $params[] = $f->dateFin;
        }
        if ($f->origine) {
            $where[]  = 'd.origine = ?';
            $params[] = $f->origine;
        }

        $whereStr = implode(' AND ', $where);
        $allowedCols = ['numero', 'date_depense', 'montant', 'statut'];
        $col = in_array($f->sortBy, $allowedCols, true) ? 'd.' . $f->sortBy : 'd.date_depense';
        $dir = $f->sortDir === 'ASC' ? 'ASC' : 'DESC';

        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM `finance_decaissements` d
             LEFT JOIN `finance_fournisseurs` fo ON fo.id = d.fournisseur_id
             WHERE {$whereStr}"
        );
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($f->page - 1) * $f->perPage;
        $params[] = $f->perPage;
        $params[] = $offset;

        $stmt = $this->pdo->prepare(
            "SELECT d.*,
                    c.nom AS categorie_nom, c.couleur AS categorie_couleur, c.icone AS categorie_icone,
                    fo.nom AS fournisseur_nom,
                    mp.nom AS mode_nom, mp.code AS mode_code,
                    CONCAT(u1.prenom, ' ', u1.nom) AS saisi_par_nom,
                    (SELECT COUNT(*) FROM finance_justificatifs j WHERE j.decaissement_id = d.id) AS nb_justificatifs
             FROM `finance_decaissements` d
             LEFT JOIN `finance_categories_depenses` c ON c.id = d.categorie_id
             LEFT JOIN `finance_fournisseurs` fo        ON fo.id = d.fournisseur_id
             LEFT JOIN `finance_modes_paiement` mp       ON mp.id = d.mode_paiement_id
             LEFT JOIN `users` u1                        ON u1.id = d.saisi_par
             WHERE {$whereStr}
             ORDER BY {$col} {$dir}
             LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);

        return [
            'data'        => $stmt->fetchAll(PDO::FETCH_OBJ),
            'total'       => $total,
            'page'        => $f->page,
            'per_page'    => $f->perPage,
            'total_pages' => max(1, (int)ceil($total / $f->perPage)),
        ];
    }

    public function findWithDetails(int $id): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT d.*,
                    c.nom AS categorie_nom, c.couleur AS categorie_couleur, c.icone AS categorie_icone,
                    fo.nom AS fournisseur_nom, fo.contact AS fournisseur_contact, fo.telephone AS fournisseur_telephone,
                    mp.nom AS mode_nom, mp.code AS mode_code,
                    CONCAT(u1.prenom, ' ', u1.nom) AS saisi_par_nom,
                    CONCAT(u2.prenom, ' ', u2.nom) AS valide_par_nom,
                    CONCAT(u3.prenom, ' ', u3.nom) AS approuve_par_nom,
                    CONCAT(u4.prenom, ' ', u4.nom) AS paye_par_nom,
                    CONCAT(u5.prenom, ' ', u5.nom) AS annule_par_nom
             FROM `finance_decaissements` d
             LEFT JOIN `finance_categories_depenses` c ON c.id = d.categorie_id
             LEFT JOIN `finance_fournisseurs` fo        ON fo.id = d.fournisseur_id
             LEFT JOIN `finance_modes_paiement` mp       ON mp.id = d.mode_paiement_id
             LEFT JOIN `users` u1 ON u1.id = d.saisi_par
             LEFT JOIN `users` u2 ON u2.id = d.valide_par
             LEFT JOIN `users` u3 ON u3.id = d.approuve_par
             LEFT JOIN `users` u4 ON u4.id = d.paye_par
             LEFT JOIN `users` u5 ON u5.id = d.annule_par
             WHERE d.id = ? AND d.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    public function getJustificatifs(int $decaissementId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT j.*, CONCAT(u.prenom, ' ', u.nom) AS uploade_par_nom
             FROM `finance_justificatifs` j
             LEFT JOIN `users` u ON u.id = j.uploade_par
             WHERE j.decaissement_id = ? ORDER BY j.created_at DESC"
        );
        $stmt->execute([$decaissementId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function countJustificatifs(int $decaissementId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM `finance_justificatifs` WHERE `decaissement_id` = ?');
        $stmt->execute([$decaissementId]);
        return (int)$stmt->fetchColumn();
    }

    public function statsGlobal(?string $dateDebut = null, ?string $dateFin = null): object
    {
        $where  = ['deleted_at IS NULL'];
        $params = [];
        if ($dateDebut) { $where[] = 'date_depense >= ?'; $params[] = $dateDebut; }
        if ($dateFin)   { $where[] = 'date_depense <= ?'; $params[] = $dateFin; }
        $whereStr = implode(' AND ', $where);

        $stmt = $this->pdo->prepare(
            "SELECT
                COUNT(*) AS total_decaissements,
                COALESCE(SUM(montant), 0) AS montant_total,
                COALESCE(SUM(CASE WHEN statut = 'paye' THEN montant ELSE 0 END), 0) AS montant_paye,
                COALESCE(SUM(CASE WHEN statut IN ('soumis','valide','approuve') THEN montant ELSE 0 END), 0) AS montant_en_attente,
                SUM(CASE WHEN statut = 'soumis' THEN 1 ELSE 0 END) AS nb_a_valider,
                SUM(CASE WHEN statut = 'valide' THEN 1 ELSE 0 END) AS nb_a_approuver
             FROM `finance_decaissements`
             WHERE {$whereStr}"
        );
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function statsParCategorie(?string $annee = null): array
    {
        $where  = ["d.deleted_at IS NULL", "d.statut = 'paye'"];
        $params = [];
        if ($annee) { $where[] = 'YEAR(d.date_depense) = ?'; $params[] = (int)$annee; }
        $whereStr = implode(' AND ', $where);

        $stmt = $this->pdo->prepare(
            "SELECT c.nom AS categorie, c.couleur, COALESCE(SUM(d.montant), 0) AS total, COUNT(*) AS nb
             FROM `finance_decaissements` d
             LEFT JOIN `finance_categories_depenses` c ON c.id = d.categorie_id
             WHERE {$whereStr}
             GROUP BY d.categorie_id, c.nom, c.couleur
             ORDER BY total DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    // ── Décaissements — écriture ─────────────────────────────────────────────

    public function insert(array $data): int
    {
        $cols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $phs  = implode(',', array_fill(0, count($data), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO `finance_decaissements` ({$cols}) VALUES ({$phs})");
        $stmt->execute(array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets = implode(',', array_map(fn($c) => "`{$c}` = ?", array_keys($data)));
        $stmt = $this->pdo->prepare("UPDATE `finance_decaissements` SET {$sets} WHERE `id` = ?");
        $stmt->execute([...array_values($data), $id]);
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE `finance_decaissements` SET `deleted_at` = NOW() WHERE `id` = ?');
        $stmt->execute([$id]);
    }

    public function insertJustificatif(array $data): int
    {
        $cols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $phs  = implode(',', array_fill(0, count($data), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO `finance_justificatifs` ({$cols}) VALUES ({$phs})");
        $stmt->execute(array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function findJustificatif(int $id): ?object
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `finance_justificatifs` WHERE `id` = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    public function deleteJustificatif(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM `finance_justificatifs` WHERE `id` = ?');
        $stmt->execute([$id]);
    }

    // ── Catégories de dépenses ───────────────────────────────────────────────

    public function findAllCategories(bool $actifOnly = false): array
    {
        $where = $actifOnly ? 'WHERE actif = 1' : '';
        $stmt = $this->pdo->query("SELECT * FROM `finance_categories_depenses` {$where} ORDER BY nom");
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function findCategorie(int $id): ?object
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `finance_categories_depenses` WHERE `id` = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    public function categorieCodeExists(string $code, int $excludeId = 0): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM `finance_categories_depenses` WHERE `code` = ? AND `id` != ?');
        $stmt->execute([$code, $excludeId]);
        return (bool)$stmt->fetchColumn();
    }

    public function insertCategorie(array $data): int
    {
        $cols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $phs  = implode(',', array_fill(0, count($data), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO `finance_categories_depenses` ({$cols}) VALUES ({$phs})");
        $stmt->execute(array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function updateCategorie(int $id, array $data): void
    {
        $sets = implode(',', array_map(fn($c) => "`{$c}` = ?", array_keys($data)));
        $stmt = $this->pdo->prepare("UPDATE `finance_categories_depenses` SET {$sets} WHERE `id` = ?");
        $stmt->execute([...array_values($data), $id]);
    }

    public function countUsageCategorie(int $categorieId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM `finance_decaissements` WHERE `categorie_id` = ? AND `deleted_at` IS NULL');
        $stmt->execute([$categorieId]);
        return (int)$stmt->fetchColumn();
    }

    // ── Fournisseurs ─────────────────────────────────────────────────────────

    public function paginateFournisseurs(int $page = 1, int $perPage = 25, ?string $q = null, bool $actifOnly = false): array
    {
        $where  = ['1=1'];
        $params = [];
        if ($actifOnly) { $where[] = 'actif = 1'; }
        if ($q) { $where[] = '(nom LIKE ? OR code LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
        $whereStr = implode(' AND ', $where);

        $cnt = $this->pdo->prepare("SELECT COUNT(*) FROM `finance_fournisseurs` WHERE {$whereStr}");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;
        $stmt = $this->pdo->prepare(
            "SELECT f.*, (SELECT COUNT(*) FROM finance_decaissements d WHERE d.fournisseur_id = f.id AND d.deleted_at IS NULL) AS nb_decaissements
             FROM `finance_fournisseurs` f WHERE {$whereStr} ORDER BY nom LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);

        return [
            'data'        => $stmt->fetchAll(PDO::FETCH_OBJ),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    public function findAllFournisseurs(bool $actifOnly = true): array
    {
        $where = $actifOnly ? 'WHERE actif = 1' : '';
        $stmt = $this->pdo->query("SELECT * FROM `finance_fournisseurs` {$where} ORDER BY nom");
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function findFournisseur(int $id): ?object
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `finance_fournisseurs` WHERE `id` = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    public function fournisseurCodeExists(string $code, int $excludeId = 0): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM `finance_fournisseurs` WHERE `code` = ? AND `id` != ?');
        $stmt->execute([$code, $excludeId]);
        return (bool)$stmt->fetchColumn();
    }

    public function insertFournisseur(array $data): int
    {
        $cols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $phs  = implode(',', array_fill(0, count($data), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO `finance_fournisseurs` ({$cols}) VALUES ({$phs})");
        $stmt->execute(array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function updateFournisseur(int $id, array $data): void
    {
        $sets = implode(',', array_map(fn($c) => "`{$c}` = ?", array_keys($data)));
        $stmt = $this->pdo->prepare("UPDATE `finance_fournisseurs` SET {$sets} WHERE `id` = ?");
        $stmt->execute([...array_values($data), $id]);
    }

    public function findModeByCode(string $code): ?object
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `finance_modes_paiement` WHERE `code` = ? LIMIT 1');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
    }

    public function findAllModes(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM `finance_modes_paiement` WHERE `actif` = 1 ORDER BY nom');
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
