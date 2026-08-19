<?php

namespace App\Modules\Finance\Repositories;

use Core\Database;

class AccountingRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getPdo(): \PDO
    {
        return $this->pdo;
    }

    // ── Exercices ─────────────────────────────────────────────────────────────

    public function findExerciceCourant(): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM finance_exercices
             WHERE statut IN ('ouvert','reouvert')
             ORDER BY date_debut DESC
             LIMIT 1"
        );
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function findExercice(int $id): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.*,
                    COUNT(DISTINCT p.id)                                       AS nb_periodes,
                    COUNT(DISTINCT CASE WHEN p.statut='ouverte' THEN p.id END) AS nb_periodes_ouvertes,
                    COUNT(DISTINCT ec.id)                                       AS nb_ecritures,
                    COALESCE(SUM(CASE WHEN c.type='produit' AND ec.statut NOT IN ('extourne') THEN le.credit - le.debit ELSE 0 END),0) AS total_produits,
                    COALESCE(SUM(CASE WHEN c.type='charge'  AND ec.statut NOT IN ('extourne') THEN le.debit - le.credit ELSE 0 END),0) AS total_charges
             FROM finance_exercices e
             LEFT JOIN finance_periodes_comptables p  ON p.exercice_id = e.id
             LEFT JOIN finance_ecritures ec           ON ec.exercice_id = e.id
             LEFT JOIN finance_lignes_ecriture le     ON le.ecriture_id = ec.id
             LEFT JOIN finance_comptes c              ON c.id = le.compte_id
             WHERE e.id = ?
             GROUP BY e.id"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function listExercices(): array
    {
        $stmt = $this->pdo->query(
            "SELECT e.*,
                    COUNT(DISTINCT p.id)                                       AS nb_periodes,
                    COUNT(DISTINCT CASE WHEN p.statut='ouverte' THEN p.id END) AS nb_periodes_ouvertes,
                    COUNT(DISTINCT ec.id)                                       AS nb_ecritures
             FROM finance_exercices e
             LEFT JOIN finance_periodes_comptables p ON p.exercice_id = e.id
             LEFT JOIN finance_ecritures ec          ON ec.exercice_id = e.id
             GROUP BY e.id
             ORDER BY e.date_debut DESC"
        );
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function insertExercice(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO finance_exercices
             (libelle, date_debut, date_fin, statut, solde_report, note, ouvert_par)
             VALUES (?, ?, ?, 'ouvert', ?, ?, ?)"
        );
        $stmt->execute([
            $data['libelle'],
            $data['date_debut'],
            $data['date_fin'],
            $data['solde_report'] ?? 0,
            $data['note'] ?? null,
            $data['ouvert_par'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateExercice(int $id, array $data): void
    {
        $sets  = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
        $stmt  = $this->pdo->prepare("UPDATE finance_exercices SET {$sets} WHERE id = ?");
        $stmt->execute([...array_values($data), $id]);
    }

    // ── Périodes ──────────────────────────────────────────────────────────────

    public function findPeriodePourDate(int $exerciceId, string $date): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM finance_periodes_comptables
             WHERE exercice_id = ?
               AND date_debut <= ?
               AND date_fin   >= ?
             LIMIT 1"
        );
        $stmt->execute([$exerciceId, $date, $date]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function findPeriode(int $id): ?object
    {
        $stmt = $this->pdo->prepare("SELECT * FROM finance_periodes_comptables WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function getPeriodesByExercice(int $exerciceId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*,
                    COUNT(e.id)                                          AS nb_ecritures,
                    COALESCE(SUM(le.debit),  0)                          AS total_debit,
                    COALESCE(SUM(le.credit), 0)                          AS total_credit
             FROM finance_periodes_comptables p
             LEFT JOIN finance_ecritures e      ON e.periode_id = p.id
             LEFT JOIN finance_lignes_ecriture le ON le.ecriture_id = e.id
             WHERE p.exercice_id = ?
             GROUP BY p.id
             ORDER BY p.numero ASC"
        );
        $stmt->execute([$exerciceId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function insertPeriode(array $data): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO finance_periodes_comptables
             (exercice_id, numero, libelle, date_debut, date_fin, statut)
             VALUES (?, ?, ?, ?, ?, 'ouverte')"
        );
        $stmt->execute([
            $data['exercice_id'],
            $data['numero'],
            $data['libelle'],
            $data['date_debut'],
            $data['date_fin'],
        ]);
    }

    public function updatePeriode(int $id, array $data): void
    {
        $sets = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
        $stmt = $this->pdo->prepare("UPDATE finance_periodes_comptables SET {$sets} WHERE id = ?");
        $stmt->execute([...array_values($data), $id]);
    }

    public function cloturerToutesPeriodes(int $exerciceId, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE finance_periodes_comptables
             SET statut = 'cloturee', cloture_par = ?, cloture_le = NOW()
             WHERE exercice_id = ? AND statut = 'ouverte'"
        );
        $stmt->execute([$userId, $exerciceId]);
    }

    // ── Journaux ──────────────────────────────────────────────────────────────

    public function findJournalByCode(string $code): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM finance_journaux_comptables WHERE code = ? AND actif = 1"
        );
        $stmt->execute([$code]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function listJournaux(): array
    {
        return $this->pdo->query(
            "SELECT * FROM finance_journaux_comptables WHERE actif = 1 ORDER BY code"
        )->fetchAll(\PDO::FETCH_OBJ);
    }

    // ── Comptes ───────────────────────────────────────────────────────────────

    public function findCompteByCode(string $code): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM finance_comptes WHERE code = ?"
        );
        $stmt->execute([$code]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function listComptes(bool $actifOnly = true): array
    {
        $where = $actifOnly ? 'WHERE actif = 1' : '';
        return $this->pdo->query(
            "SELECT * FROM finance_comptes {$where} ORDER BY code ASC"
        )->fetchAll(\PDO::FETCH_OBJ);
    }

    public function getComptesSoldes(int $exerciceId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*,
                    COALESCE(SUM(le.debit),  0) AS total_debit,
                    COALESCE(SUM(le.credit), 0) AS total_credit,
                    COALESCE(SUM(le.debit - le.credit), 0) AS solde
             FROM finance_comptes c
             LEFT JOIN finance_lignes_ecriture le ON le.compte_id = c.id
             LEFT JOIN finance_ecritures e        ON e.id = le.ecriture_id
               AND e.exercice_id = ?
               AND e.statut NOT IN ('extourne')
             WHERE c.actif = 1
             GROUP BY c.id
             ORDER BY c.code ASC"
        );
        $stmt->execute([$exerciceId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function getComptesByClasse(int $classe, int $exerciceId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*,
                    COALESCE(SUM(le.debit),  0) AS solde_debit,
                    COALESCE(SUM(le.credit), 0) AS solde_credit
             FROM finance_comptes c
             LEFT JOIN finance_lignes_ecriture le ON le.compte_id = c.id
             LEFT JOIN finance_ecritures e        ON e.id = le.ecriture_id
               AND e.exercice_id = ?
               AND e.statut NOT IN ('extourne')
             WHERE c.classe = ? AND c.actif = 1
             GROUP BY c.id
             HAVING (solde_debit > 0 OR solde_credit > 0)
             ORDER BY c.code ASC"
        );
        $stmt->execute([$exerciceId, $classe]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    // ── Règles comptables ─────────────────────────────────────────────────────

    public function findRegle(string $typeSource): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM finance_regles_comptables WHERE type_source = ? AND actif = 1"
        );
        $stmt->execute([$typeSource]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    // ── Écritures ─────────────────────────────────────────────────────────────

    public function genererNumero(int $annee): string
    {
        $this->pdo->exec(
            "INSERT INTO finance_sequences (type, annee, valeur)
             VALUES ('ECR', {$annee}, 1)
             ON DUPLICATE KEY UPDATE valeur = valeur + 1"
        );
        $row = $this->pdo->query(
            "SELECT valeur FROM finance_sequences WHERE type='ECR' AND annee={$annee}"
        )->fetch(\PDO::FETCH_OBJ);
        return sprintf('ECR-%d-%04d', $annee, $row->valeur);
    }

    public function insertEcriture(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO finance_ecritures
             (numero, date_ecriture, journal_id, exercice_id, periode_id,
              libelle, reference, source, statut, extourne_de, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['numero'],
            $data['date_ecriture'],
            $data['journal_id'],
            $data['exercice_id'],
            $data['periode_id'],
            $data['libelle'],
            $data['reference'] ?? null,
            $data['source']    ?? null,
            $data['statut']    ?? 'valide',
            $data['extourne_de'] ?? null,
            $data['created_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateEcriture(int $id, array $data): void
    {
        $sets = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
        $stmt = $this->pdo->prepare("UPDATE finance_ecritures SET {$sets} WHERE id = ?");
        $stmt->execute([...array_values($data), $id]);
    }

    public function insertLigneEcriture(array $data): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO finance_lignes_ecriture
             (ecriture_id, compte_id, libelle, debit, credit, created_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['ecriture_id'],
            $data['compte_id'],
            $data['libelle'],
            $data['debit'],
            $data['credit'],
            $data['created_by'],
        ]);
    }

    public function findEcriture(int $id): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.*,
                    j.code    AS journal_code,
                    j.libelle AS journal_libelle,
                    ex.libelle AS exercice_libelle,
                    p.libelle  AS periode_libelle,
                    COALESCE(SUM(le.debit),  0) AS total_debit,
                    COALESCE(SUM(le.credit), 0) AS total_credit
             FROM finance_ecritures e
             JOIN finance_journaux_comptables j ON j.id = e.journal_id
             JOIN finance_exercices ex          ON ex.id = e.exercice_id
             JOIN finance_periodes_comptables p ON p.id  = e.periode_id
             LEFT JOIN finance_lignes_ecriture le ON le.ecriture_id = e.id
             WHERE e.id = ?
             GROUP BY e.id, j.code, j.libelle, ex.libelle, p.libelle"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function findEcritureByReference(string $reference, string $source): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM finance_ecritures
             WHERE reference = ? AND source = ? AND statut = 'valide'
             LIMIT 1"
        );
        $stmt->execute([$reference, $source]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function getLignesByEcriture(int $ecritureId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT le.*, c.code AS compte_code, c.libelle AS compte_libelle, c.type AS compte_type
             FROM finance_lignes_ecriture le
             JOIN finance_comptes c ON c.id = le.compte_id
             WHERE le.ecriture_id = ?
             ORDER BY le.id ASC"
        );
        $stmt->execute([$ecritureId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function paginateEcritures(array $filters, int $page, int $perPage): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['exercice_id'])) {
            $where[] = 'e.exercice_id = ?';
            $params[] = $filters['exercice_id'];
        }
        if (!empty($filters['periode_id'])) {
            $where[] = 'e.periode_id = ?';
            $params[] = $filters['periode_id'];
        }
        if (!empty($filters['journal_code'])) {
            $where[] = 'j.code = ?';
            $params[] = $filters['journal_code'];
        }
        if (!empty($filters['compte_code'])) {
            $where[] = 'EXISTS (
                SELECT 1 FROM finance_lignes_ecriture le2
                JOIN finance_comptes c2 ON c2.id = le2.compte_id
                WHERE le2.ecriture_id = e.id AND c2.code LIKE ?
            )';
            $params[] = $filters['compte_code'] . '%';
        }
        if (!empty($filters['statut'])) {
            $where[] = 'e.statut = ?';
            $params[] = $filters['statut'];
        }
        if (!empty($filters['source'])) {
            $where[] = 'e.source = ?';
            $params[] = $filters['source'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(e.numero LIKE ? OR e.libelle LIKE ? OR e.reference LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params = array_merge($params, [$like, $like, $like]);
        }
        if (!empty($filters['date_debut'])) {
            $where[] = 'e.date_ecriture >= ?';
            $params[] = $filters['date_debut'];
        }
        if (!empty($filters['date_fin'])) {
            $where[] = 'e.date_ecriture <= ?';
            $params[] = $filters['date_fin'];
        }

        $sql = "SELECT e.*,
                       j.code      AS journal_code,
                       j.libelle   AS journal_libelle,
                       ex.libelle  AS exercice_libelle,
                       COALESCE(SUM(le.debit),  0) AS total_debit,
                       COALESCE(SUM(le.credit), 0) AS total_credit
                FROM finance_ecritures e
                JOIN finance_journaux_comptables j ON j.id = e.journal_id
                JOIN finance_exercices ex          ON ex.id = e.exercice_id
                LEFT JOIN finance_lignes_ecriture le ON le.ecriture_id = e.id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY e.id, j.code, j.libelle, ex.libelle
                ORDER BY e.date_ecriture DESC, e.id DESC";

        // Count
        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM finance_ecritures e
             JOIN finance_journaux_comptables j ON j.id = e.journal_id
             JOIN finance_exercices ex          ON ex.id = e.exercice_id
             WHERE " . implode(' AND ', $where)
        );
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Data
        $offset = ($page - 1) * $perPage;
        $dataStmt = $this->pdo->prepare($sql . " LIMIT {$perPage} OFFSET {$offset}");
        $dataStmt->execute($params);
        $items = $dataStmt->fetchAll(\PDO::FETCH_OBJ);

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ];
    }

    // ── Grand Livre ───────────────────────────────────────────────────────────

    public function getGrandLivre(array $filters): array
    {
        $where  = ["e.statut NOT IN ('extourne')"];
        $params = [];

        if (!empty($filters['exercice_id'])) {
            $where[] = 'e.exercice_id = ?';
            $params[] = $filters['exercice_id'];
        }
        if (!empty($filters['compte_code'])) {
            $where[] = 'c.code LIKE ?';
            $params[] = $filters['compte_code'] . '%';
        }
        if (!empty($filters['classe'])) {
            $where[] = 'c.classe = ?';
            $params[] = (int)$filters['classe'];
        }
        if (!empty($filters['type'])) {
            $where[] = 'c.type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['date_debut'])) {
            $where[] = 'e.date_ecriture >= ?';
            $params[] = $filters['date_debut'];
        }
        if (!empty($filters['date_fin'])) {
            $where[] = 'e.date_ecriture <= ?';
            $params[] = $filters['date_fin'];
        }

        $stmt = $this->pdo->prepare(
            "SELECT
                c.code          AS compte_code,
                c.libelle       AS compte_libelle,
                c.type          AS compte_type,
                c.classe        AS compte_classe,
                e.date_ecriture,
                e.numero        AS ecriture_numero,
                e.libelle       AS ecriture_libelle,
                e.reference,
                j.code          AS journal_code,
                le.libelle      AS ligne_libelle,
                le.debit,
                le.credit
             FROM finance_lignes_ecriture le
             JOIN finance_ecritures e            ON e.id = le.ecriture_id
             JOIN finance_comptes c              ON c.id = le.compte_id
             JOIN finance_journaux_comptables j  ON j.id = e.journal_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY c.code ASC, e.date_ecriture ASC, e.id ASC, le.id ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    // ── Balance générale ──────────────────────────────────────────────────────

    public function getBalance(int $exerciceId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                c.code,
                c.libelle,
                c.type,
                c.classe,
                COALESCE(SUM(le.debit),  0) AS total_debit,
                COALESCE(SUM(le.credit), 0) AS total_credit,
                COALESCE(SUM(le.debit - le.credit), 0) AS solde
             FROM finance_comptes c
             LEFT JOIN finance_lignes_ecriture le ON le.compte_id = c.id
             LEFT JOIN finance_ecritures e        ON e.id = le.ecriture_id
               AND e.exercice_id = ?
               AND e.statut NOT IN ('extourne')
             WHERE c.actif = 1
             GROUP BY c.id, c.code, c.libelle, c.type, c.classe
             HAVING (total_debit > 0 OR total_credit > 0)
             ORDER BY c.code ASC"
        );
        $stmt->execute([$exerciceId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    // ── Stats dashboard ───────────────────────────────────────────────────────

    public function getStatsDashboard(int $exerciceId): object
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                COUNT(DISTINCT e.id)                                                      AS nb_ecritures,
                COALESCE(SUM(CASE WHEN c.type='produit' THEN le.credit - le.debit ELSE 0 END), 0) AS total_produits,
                COALESCE(SUM(CASE WHEN c.type='charge'  THEN le.debit - le.credit ELSE 0 END), 0) AS total_charges,
                COALESCE(SUM(CASE WHEN c.code LIKE '53%' THEN le.debit - le.credit ELSE 0 END), 0) AS solde_caisse,
                COALESCE(SUM(CASE WHEN c.code LIKE '51%' THEN le.debit - le.credit ELSE 0 END), 0) AS solde_banque
             FROM finance_ecritures e
             JOIN finance_lignes_ecriture le ON le.ecriture_id = e.id
             JOIN finance_comptes c          ON c.id = le.compte_id
             WHERE e.exercice_id = ? AND e.statut NOT IN ('extourne')"
        );
        $stmt->execute([$exerciceId]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: (object)[
            'nb_ecritures'  => 0,
            'total_produits'=> 0,
            'total_charges' => 0,
            'solde_caisse'  => 0,
            'solde_banque'  => 0,
        ];
    }

    public function getRecentEcritures(int $exerciceId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.*,
                    j.code     AS journal_code,
                    j.libelle  AS journal_libelle,
                    COALESCE(SUM(le.debit), 0)  AS total_debit,
                    COALESCE(SUM(le.credit), 0) AS total_credit
             FROM finance_ecritures e
             JOIN finance_journaux_comptables j  ON j.id = e.journal_id
             LEFT JOIN finance_lignes_ecriture le ON le.ecriture_id = e.id
             WHERE e.exercice_id = ?
             GROUP BY e.id, j.code, j.libelle
             ORDER BY e.date_ecriture DESC, e.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$exerciceId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    // ── Paiements (pour lookup mode lors remboursement) ───────────────────────

    /**
     * Code du mode de paiement (ex: 'ESP', 'VIR') d'un paiement, via la
     * table de référence `finance_modes_paiement` — corrige FN-C-002
     * (l'ancienne requête lisait une colonne `mode_paiement` inexistante
     * sur `finance_paiements`, qui n'a que `mode_paiement_id`).
     */
    public function findPaiementMode(int $paiementId): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT mp.code FROM finance_paiements p
             JOIN finance_modes_paiement mp ON mp.id = p.mode_paiement_id
             WHERE p.id = ?"
        );
        $stmt->execute([$paiementId]);
        $row = $stmt->fetch(\PDO::FETCH_OBJ);
        return $row ? $row->code : null;
    }

    /**
     * Catégorie comptable ('caisse' ou 'banque') d'un mode de paiement,
     * lue depuis `finance_modes_paiement.categorie` — remplace les
     * tableaux PHP hardcodés CASH_MODES/BANK_MODES (voir NIGER_APP_
     * CONFIGURATION_AUDIT.md). Un mode inconnu ou inactif retombe sur
     * 'caisse' (comportement le plus restrictif, jamais un blocage).
     */
    public function findModeCategorie(string $code): string
    {
        $stmt = $this->pdo->prepare(
            "SELECT categorie FROM finance_modes_paiement WHERE code = ?"
        );
        $stmt->execute([$code]);
        $row = $stmt->fetch(\PDO::FETCH_OBJ);
        return $row ? $row->categorie : 'caisse';
    }
}
