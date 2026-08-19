<?php

namespace App\Modules\Finance\Repositories;

use Core\Database;
use App\Modules\Finance\DTO\PaymentFiltersDTO;

class PaymentRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ----------------------------------------------------------------
    // Paiements — lecture
    // ----------------------------------------------------------------
    public function paginate(PaymentFiltersDTO $f): array
    {
        $where  = ['1=1'];
        $params = [];

        if ($f->q) {
            $where[]  = '(p.numero LIKE ? OR ff.numero LIKE ? OR CONCAT(e.prenom," ",e.nom) LIKE ?)';
            $like     = '%' . $f->q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($f->statut) {
            $where[]  = 'p.statut = ?';
            $params[] = $f->statut;
        }
        if ($f->modePaiement) {
            $where[]  = 'mp.code = ?';
            $params[] = $f->modePaiement;
        }
        if ($f->factureId) {
            $where[]  = 'p.facture_id = ?';
            $params[] = $f->factureId;
        }
        if ($f->eleveId) {
            $where[]  = 'e.id = ?';
            $params[] = $f->eleveId;
        }
        if ($f->anneeScolaire) {
            $where[]  = 'ff.annee_scolaire = ?';
            $params[] = $f->anneeScolaire;
        }
        if ($f->dateDebut) {
            $where[]  = 'p.date_paiement >= ?';
            $params[] = $f->dateDebut;
        }
        if ($f->dateFin) {
            $where[]  = 'p.date_paiement <= ?';
            $params[] = $f->dateFin;
        }
        if ($f->origine) {
            $where[]  = 'p.origine = ?';
            $params[] = $f->origine;
        }

        $whereStr = implode(' AND ', $where);
        $allowedCols = ['numero','date_paiement','montant','statut'];
        $col = in_array($f->sortBy, $allowedCols, true) ? 'p.' . $f->sortBy : 'p.date_paiement';
        $dir = $f->sortDir === 'ASC' ? 'ASC' : 'DESC';

        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM `finance_paiements` p
             JOIN `finance_factures` ff  ON ff.id = p.facture_id
             JOIN `eleves` e             ON e.id  = ff.eleve_id
             LEFT JOIN `finance_modes_paiement` mp ON mp.id = p.mode_paiement_id
             WHERE {$whereStr}"
        );
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($f->page - 1) * $f->perPage;
        $params[] = $f->perPage;
        $params[] = $offset;

        $stmt = $this->pdo->prepare(
            "SELECT p.*,
                    ff.numero  AS facture_numero,
                    ff.annee_scolaire,
                    ff.statut  AS facture_statut,
                    CONCAT(e.prenom,' ',e.nom) AS eleve_nom,
                    e.matricule AS eleve_matricule,
                    mp.nom     AS mode_nom,
                    mp.code    AS mode_code,
                    mp.icone   AS mode_icone,
                    r.numero   AS recu_numero,
                    r.id       AS recu_id
             FROM `finance_paiements` p
             JOIN `finance_factures` ff  ON ff.id = p.facture_id
             JOIN `eleves` e             ON e.id  = ff.eleve_id
             LEFT JOIN `finance_modes_paiement` mp ON mp.id = p.mode_paiement_id
             LEFT JOIN `finance_recus` r            ON r.paiement_id = p.id
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
            "SELECT p.*,
                    ff.numero  AS facture_numero,
                    ff.annee_scolaire,
                    ff.statut  AS facture_statut,
                    ff.montant_total,
                    ff.montant_paye AS facture_montant_paye,
                    ff.eleve_id,
                    CONCAT(e.prenom,' ',e.nom) AS eleve_nom,
                    e.matricule AS eleve_matricule,
                    mp.nom     AS mode_nom,
                    mp.code    AS mode_code,
                    mp.icone   AS mode_icone,
                    r.numero   AS recu_numero,
                    r.id       AS recu_id,
                    r.date_emission AS recu_date,
                    ue.prenom  AS caissier_prenom,   ue.nom AS caissier_nom,
                    uv.prenom  AS valideur_prenom,   uv.nom AS valideur_nom,
                    ua.prenom  AS annuleur_prenom,   ua.nom AS annuleur_nom
             FROM `finance_paiements` p
             JOIN `finance_factures` ff ON ff.id = p.facture_id
             JOIN `eleves` e            ON e.id  = ff.eleve_id
             LEFT JOIN `finance_modes_paiement` mp ON mp.id   = p.mode_paiement_id
             LEFT JOIN `finance_recus` r             ON r.paiement_id = p.id
             LEFT JOIN `users` ue ON ue.id = p.encaisse_par
             LEFT JOIN `users` uv ON uv.id = p.valide_par
             LEFT JOIN `users` ua ON ua.id = p.annule_par
             WHERE p.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function getByFacture(int $factureId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*,
                    mp.nom AS mode_nom, mp.code AS mode_code, mp.icone AS mode_icone,
                    r.numero AS recu_numero, r.id AS recu_id
             FROM `finance_paiements` p
             LEFT JOIN `finance_modes_paiement` mp ON mp.id = p.mode_paiement_id
             LEFT JOIN `finance_recus` r            ON r.paiement_id = p.id
             WHERE p.facture_id = ?
             ORDER BY p.date_paiement DESC, p.id DESC"
        );
        $stmt->execute([$factureId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function findRecu(int $paiementId): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.*,
                    CONCAT(e.prenom,' ',e.nom) AS eleve_nom,
                    e.matricule,
                    ff.numero  AS facture_numero,
                    ff.annee_scolaire,
                    mp.nom     AS mode_nom,
                    mp.code    AS mode_code,
                    ue.prenom  AS emetteur_prenom, ue.nom AS emetteur_nom
             FROM `finance_recus` r
             JOIN `finance_paiements` p    ON p.id  = r.paiement_id
             JOIN `finance_factures` ff    ON ff.id = r.facture_id
             JOIN `eleves` e               ON e.id  = ff.eleve_id
             LEFT JOIN `finance_modes_paiement` mp ON mp.id = p.mode_paiement_id
             LEFT JOIN `users` ue           ON ue.id = r.emis_par
             WHERE r.paiement_id = ?"
        );
        $stmt->execute([$paiementId]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function getRemboursements(int $paiementId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT rem.*, u.prenom, u.nom
             FROM `finance_remboursements` rem
             LEFT JOIN `users` u ON u.id = rem.realise_par
             WHERE rem.paiement_id = ?"
        );
        $stmt->execute([$paiementId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function getTropPercu(int $paiementId): ?object
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `finance_trop_percus` WHERE `paiement_id` = ? LIMIT 1'
        );
        $stmt->execute([$paiementId]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function getTropPercusByEleve(int $eleveId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT tp.*, ff.numero AS facture_numero
             FROM `finance_trop_percus` tp
             JOIN `finance_factures` ff ON ff.id = tp.facture_id
             WHERE tp.eleve_id = ? AND tp.statut = 'en_attente'
             ORDER BY tp.created_at DESC"
        );
        $stmt->execute([$eleveId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function getModesPaiement(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM `finance_modes_paiement` WHERE `actif` = 1 ORDER BY `ordre`"
        );
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function findModeByCode(string $code): ?object
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `finance_modes_paiement` WHERE `code` = ?'
        );
        $stmt->execute([$code]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    // ----------------------------------------------------------------
    // Agrégats
    // ----------------------------------------------------------------
    public function sumPaiementsComplete(int $factureId): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(`montant_applique`), 0)
             FROM `finance_paiements`
             WHERE `facture_id` = ? AND `statut` = 'complete'"
        );
        $stmt->execute([$factureId]);
        return (float)$stmt->fetchColumn();
    }

    public function statsGlobal(?string $dateDebut = null, ?string $dateFin = null): object
    {
        $where  = ['1=1'];
        $params = [];
        if ($dateDebut) { $where[] = 'p.date_paiement >= ?'; $params[] = $dateDebut; }
        if ($dateFin)   { $where[] = 'p.date_paiement <= ?'; $params[] = $dateFin; }
        $whereStr = implode(' AND ', $where);

        $stmt = $this->pdo->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN p.statut='complete'  THEN 1 ELSE 0 END) AS completes,
                SUM(CASE WHEN p.statut='initie'    THEN 1 ELSE 0 END) AS inities,
                SUM(CASE WHEN p.statut='annule'    THEN 1 ELSE 0 END) AS annules,
                SUM(CASE WHEN p.statut='rembourse' THEN 1 ELSE 0 END) AS rembourses,
                COALESCE(SUM(CASE WHEN p.statut='complete' THEN p.montant_applique ELSE 0 END), 0) AS montant_total_encaisse,
                COALESCE(SUM(CASE WHEN p.statut='rembourse' THEN p.montant_applique ELSE 0 END), 0) AS montant_rembourse
             FROM `finance_paiements` p
             WHERE {$whereStr}"
        );
        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_OBJ);
    }

    public function statsParMode(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $where  = ["p.statut = 'complete'"];
        $params = [];
        if ($dateDebut) { $where[] = 'p.date_paiement >= ?'; $params[] = $dateDebut; }
        if ($dateFin)   { $where[] = 'p.date_paiement <= ?'; $params[] = $dateFin; }
        $whereStr = implode(' AND ', $where);

        $stmt = $this->pdo->prepare(
            "SELECT mp.nom AS mode, mp.code, COUNT(*) AS nb, SUM(p.montant_applique) AS total
             FROM `finance_paiements` p
             LEFT JOIN `finance_modes_paiement` mp ON mp.id = p.mode_paiement_id
             WHERE {$whereStr}
             GROUP BY mp.id, mp.nom, mp.code
             ORDER BY total DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    // ----------------------------------------------------------------
    // Facture (lecture partielle — pour mise à jour du statut)
    // ----------------------------------------------------------------
    public function getFacture(int $factureId): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT ff.*, CONCAT(e.prenom,' ',e.nom) AS eleve_nom
             FROM `finance_factures` ff
             JOIN `eleves` e ON e.id = ff.eleve_id
             WHERE ff.id = ?"
        );
        $stmt->execute([$factureId]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function updateFacture(int $factureId, array $data): void
    {
        $sets = implode(',', array_map(fn($c) => "`{$c}` = ?", array_keys($data)));
        $stmt = $this->pdo->prepare("UPDATE `finance_factures` SET {$sets} WHERE `id` = ?");
        $stmt->execute([...array_values($data), $factureId]);
    }

    public function getAvoir(int $avoirId): ?object
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `finance_avoirs` WHERE `id` = ?');
        $stmt->execute([$avoirId]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function updateAvoir(int $avoirId, array $data): void
    {
        $sets = implode(',', array_map(fn($c) => "`{$c}` = ?", array_keys($data)));
        $stmt = $this->pdo->prepare("UPDATE `finance_avoirs` SET {$sets} WHERE `id` = ?");
        $stmt->execute([...array_values($data), $avoirId]);
    }

    // ----------------------------------------------------------------
    // Écriture paiements
    // ----------------------------------------------------------------
    public function insert(array $data): int
    {
        $cols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $phs  = implode(',', array_fill(0, count($data), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO `finance_paiements` ({$cols}) VALUES ({$phs})");
        $stmt->execute(array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets = implode(',', array_map(fn($c) => "`{$c}` = ?", array_keys($data)));
        $stmt = $this->pdo->prepare("UPDATE `finance_paiements` SET {$sets} WHERE `id` = ?");
        $stmt->execute([...array_values($data), $id]);
    }

    // ----------------------------------------------------------------
    // Écriture reçus / trop-perçus / remboursements
    // ----------------------------------------------------------------
    public function insertRecu(array $data): int
    {
        $cols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $phs  = implode(',', array_fill(0, count($data), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO `finance_recus` ({$cols}) VALUES ({$phs})");
        $stmt->execute(array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function insertTropPercu(array $data): int
    {
        $cols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $phs  = implode(',', array_fill(0, count($data), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO `finance_trop_percus` ({$cols}) VALUES ({$phs})");
        $stmt->execute(array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function updateTropPercu(int $id, array $data): void
    {
        $sets = implode(',', array_map(fn($c) => "`{$c}` = ?", array_keys($data)));
        $stmt = $this->pdo->prepare("UPDATE `finance_trop_percus` SET {$sets} WHERE `id` = ?");
        $stmt->execute([...array_values($data), $id]);
    }

    public function insertRemboursement(array $data): int
    {
        $cols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $phs  = implode(',', array_fill(0, count($data), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO `finance_remboursements` ({$cols}) VALUES ({$phs})");
        $stmt->execute(array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    // ----------------------------------------------------------------
    // Numérotation séquentielle (réutilise finance_sequences)
    // ----------------------------------------------------------------
    public function genererNumero(string $type, int $annee): string
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO `finance_sequences` (`type`, `annee`, `valeur`) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE `valeur` = `valeur` + 1"
        );
        $stmt->execute([$type, $annee]);
        $stmt = $this->pdo->prepare(
            'SELECT `valeur` FROM `finance_sequences` WHERE `type` = ? AND `annee` = ?'
        );
        $stmt->execute([$type, $annee]);
        return sprintf('%s-%d-%04d', $type, $annee, (int)$stmt->fetchColumn());
    }

    // ----------------------------------------------------------------
    // Mise à jour échéance (si paiement ciblé)
    // ----------------------------------------------------------------
    public function updateEcheance(int $echeanceId, array $data): void
    {
        $sets = implode(',', array_map(fn($c) => "`{$c}` = ?", array_keys($data)));
        $stmt = $this->pdo->prepare("UPDATE `finance_echeances` SET {$sets} WHERE `id` = ?");
        $stmt->execute([...array_values($data), $echeanceId]);
    }

    public function getEcheance(int $echeanceId): ?object
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `finance_echeances` WHERE `id` = ?');
        $stmt->execute([$echeanceId]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    // ----------------------------------------------------------------
    // PDO accessor
    // ----------------------------------------------------------------
    public function getPdo(): \PDO
    {
        return $this->pdo;
    }
}
