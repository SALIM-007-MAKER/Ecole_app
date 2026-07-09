<?php

namespace App\Modules\Finance\Repositories;

use Core\Database;
use App\Modules\Finance\DTO\CashSessionFiltersDTO;

class CashRegisterRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ----------------------------------------------------------------
    // Sessions — lecture
    // ----------------------------------------------------------------
    public function paginate(CashSessionFiltersDTO $f): array
    {
        $where  = ['s.deleted_at IS NULL'];
        $params = [];

        if ($f->q) {
            $like     = '%' . $f->q . '%';
            $where[]  = '(s.numero LIKE ? OR CONCAT(u.prenom," ",u.nom) LIKE ?)';
            $params[] = $like;
            $params[] = $like;
        }
        if ($f->statut) {
            $where[]  = 's.statut = ?';
            $params[] = $f->statut;
        }
        if ($f->caissierId) {
            $where[]  = 's.caissier_id = ?';
            $params[] = $f->caissierId;
        }
        if ($f->dateDebut) {
            $where[]  = 's.date_ouverture >= ?';
            $params[] = $f->dateDebut;
        }
        if ($f->dateFin) {
            $where[]  = 's.date_ouverture <= ?';
            $params[] = $f->dateFin;
        }

        $whereStr    = implode(' AND ', $where);
        $allowedCols = ['date_ouverture', 'numero', 'solde_initial', 'total_recettes', 'statut'];
        $col         = in_array($f->sortBy, $allowedCols, true) ? 's.' . $f->sortBy : 's.date_ouverture';
        $dir         = $f->sortDir === 'ASC' ? 'ASC' : 'DESC';

        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM `finance_sessions_caisse` s
             JOIN `users` u ON u.id = s.caissier_id
             WHERE {$whereStr}"
        );
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset   = ($f->page - 1) * $f->perPage;
        $params[] = $f->perPage;
        $params[] = $offset;

        $stmt = $this->pdo->prepare(
            "SELECT s.*,
                    CONCAT(u.prenom,' ',u.nom) AS caissier_nom,
                    u.email AS caissier_email,
                    CONCAT(uf.prenom,' ',uf.nom) AS ferme_par_nom
             FROM `finance_sessions_caisse` s
             JOIN `users` u  ON u.id  = s.caissier_id
             LEFT JOIN `users` uf ON uf.id = s.ferme_par
             WHERE {$whereStr}
             ORDER BY {$col} {$dir}
             LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);

        return [
            'data'        => $stmt->fetchAll(\PDO::FETCH_OBJ),
            'total'       => $total,
            'page'        => $f->page,
            'per_page'    => $f->perPage,
            'total_pages' => max(1, (int)ceil($total / $f->perPage)),
        ];
    }

    public function findWithDetails(int $id): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.*,
                    CONCAT(u.prenom,' ',u.nom) AS caissier_nom,
                    CONCAT(uo.prenom,' ',uo.nom) AS ouvert_par_nom,
                    CONCAT(uf.prenom,' ',uf.nom) AS ferme_par_nom
             FROM `finance_sessions_caisse` s
             JOIN `users` u   ON u.id  = s.caissier_id
             JOIN `users` uo  ON uo.id = s.ouvert_par
             LEFT JOIN `users` uf ON uf.id = s.ferme_par
             WHERE s.id = ? AND s.deleted_at IS NULL"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function findSessionActive(int $userId): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `finance_sessions_caisse`
             WHERE `caissier_id` = ? AND `statut` IN ('ouverte','en_activite') AND `deleted_at` IS NULL
             ORDER BY `created_at` DESC LIMIT 1"
        );
        $stmt->execute([$userId]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function findAnyActive(): array
    {
        $stmt = $this->pdo->query(
            "SELECT s.*, CONCAT(u.prenom,' ',u.nom) AS caissier_nom
             FROM `finance_sessions_caisse` s
             JOIN `users` u ON u.id = s.caissier_id
             WHERE s.statut IN ('ouverte','en_activite') AND s.deleted_at IS NULL
             ORDER BY s.date_ouverture DESC"
        );
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    // ----------------------------------------------------------------
    // Sessions — écriture
    // ----------------------------------------------------------------
    public function insert(array $data): int
    {
        $cols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $phs  = implode(',', array_fill(0, count($data), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO `finance_sessions_caisse` ({$cols}) VALUES ({$phs})");
        $stmt->execute(array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets = implode(',', array_map(fn($c) => "`{$c}` = ?", array_keys($data)));
        $stmt = $this->pdo->prepare("UPDATE `finance_sessions_caisse` SET {$sets} WHERE `id` = ?");
        $stmt->execute([...array_values($data), $id]);
    }

    // ----------------------------------------------------------------
    // Calcul des totaux depuis les mouvements (source de vérité)
    // ----------------------------------------------------------------
    public function calculerTotaux(int $sessionId): object
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN type='recette'      AND statut='actif' THEN montant ELSE 0 END), 0) AS total_recettes,
                COALESCE(SUM(CASE WHEN type='decaissement' AND statut='actif' THEN montant ELSE 0 END), 0) AS total_decaissements,
                COALESCE(SUM(CASE WHEN sens='credit' AND type NOT IN ('ouverture') AND statut='actif' THEN montant ELSE 0 END), 0) AS total_credits,
                COALESCE(SUM(CASE WHEN sens='debit'  AND statut='actif' THEN montant ELSE 0 END), 0) AS total_debits
             FROM `finance_mouvements_caisse`
             WHERE `session_id` = ?"
        );
        $stmt->execute([$sessionId]);
        return $stmt->fetch(\PDO::FETCH_OBJ);
    }

    // ----------------------------------------------------------------
    // Numérotation séquentielle (réutilise finance_sequences)
    // ----------------------------------------------------------------
    public function genererNumero(string $type, int $annee): string
    {
        $this->pdo->exec(
            "INSERT INTO `finance_sequences` (`type`, `annee`, `valeur`) VALUES ('{$type}', {$annee}, 1)
             ON DUPLICATE KEY UPDATE `valeur` = `valeur` + 1"
        );
        $stmt = $this->pdo->prepare(
            'SELECT `valeur` FROM `finance_sequences` WHERE `type` = ? AND `annee` = ?'
        );
        $stmt->execute([$type, $annee]);
        return sprintf('%s-%d-%04d', $type, $annee, (int)$stmt->fetchColumn());
    }

    // ----------------------------------------------------------------
    // Journal de caisse
    // ----------------------------------------------------------------
    public function upsertJournal(array $data): void
    {
        $cols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $phs  = implode(',', array_fill(0, count($data), '?'));
        $updates = implode(',', array_map(fn($c) => "`{$c}` = VALUES(`{$c}`)", array_keys($data)));
        $stmt = $this->pdo->prepare(
            "INSERT INTO `finance_journaux_caisse` ({$cols}) VALUES ({$phs})
             ON DUPLICATE KEY UPDATE {$updates}"
        );
        $stmt->execute(array_values($data));
    }

    public function getJournal(int $sessionId): ?object
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `finance_journaux_caisse` WHERE `session_id` = ? LIMIT 1'
        );
        $stmt->execute([$sessionId]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    // ----------------------------------------------------------------
    // Stats dashboard
    // ----------------------------------------------------------------
    public function statsGlobal(?string $dateDebut = null, ?string $dateFin = null): object
    {
        $where  = ['s.deleted_at IS NULL'];
        $params = [];
        if ($dateDebut) { $where[] = 's.date_ouverture >= ?'; $params[] = $dateDebut; }
        if ($dateFin)   { $where[] = 's.date_ouverture <= ?'; $params[] = $dateFin; }
        $whereStr = implode(' AND ', $where);

        $stmt = $this->pdo->prepare(
            "SELECT
                COUNT(*) AS total_sessions,
                SUM(CASE WHEN s.statut IN ('ouverte','en_activite') THEN 1 ELSE 0 END) AS sessions_actives,
                SUM(CASE WHEN s.statut = 'fermee' THEN 1 ELSE 0 END) AS sessions_fermees,
                COALESCE(SUM(s.total_recettes), 0) AS total_recettes,
                COALESCE(SUM(s.total_decaissements), 0) AS total_decaissements
             FROM `finance_sessions_caisse` s
             WHERE {$whereStr}"
        );
        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_OBJ);
    }

    public function getCaissiers(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT u.id, CONCAT(u.prenom,' ',u.nom) AS nom
             FROM `users` u
             JOIN `finance_sessions_caisse` s ON s.caissier_id = u.id AND s.deleted_at IS NULL
             ORDER BY u.nom"
        );
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    // ----------------------------------------------------------------
    // PDO accessor
    // ----------------------------------------------------------------
    public function getPdo(): \PDO
    {
        return $this->pdo;
    }
}
