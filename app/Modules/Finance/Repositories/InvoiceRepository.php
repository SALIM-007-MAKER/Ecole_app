<?php

namespace App\Modules\Finance\Repositories;

use Core\Database;
use App\Modules\Finance\DTO\InvoiceFiltersDTO;

class InvoiceRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ----------------------------------------------------------------
    // Numérotation séquentielle thread-safe (MySQL atomic UPSERT)
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
        $val = (int)$stmt->fetchColumn();
        return sprintf('%s-%d-%04d', $type, $annee, $val);
    }

    // ----------------------------------------------------------------
    // Factures — lecture
    // ----------------------------------------------------------------
    public function paginate(InvoiceFiltersDTO $f): array
    {
        $where  = ['1=1'];
        $params = [];

        if ($f->q) {
            $where[]  = '(ff.numero LIKE ? OR CONCAT(e.prenom," ",e.nom) LIKE ?)';
            $like     = '%' . $f->q . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if ($f->statut) {
            $where[]  = 'ff.statut = ?';
            $params[] = $f->statut;
        }
        if ($f->anneeScolaire) {
            $where[]  = 'ff.annee_scolaire = ?';
            $params[] = $f->anneeScolaire;
        }
        if ($f->eleveId) {
            $where[]  = 'ff.eleve_id = ?';
            $params[] = $f->eleveId;
        }
        if ($f->classeId) {
            $where[]  = 'e.classe_id = ?';
            $params[] = $f->classeId;
        }
        if ($f->niveau) {
            $where[]  = 'c.niveau = ?';
            $params[] = $f->niveau;
        }
        if ($f->dateDebut) {
            $where[]  = 'ff.date_emission >= ?';
            $params[] = $f->dateDebut;
        }
        if ($f->dateFin) {
            $where[]  = 'ff.date_emission <= ?';
            $params[] = $f->dateFin;
        }

        $whereStr = implode(' AND ', $where);
        $allowedCols = ['numero','date_emission','date_echeance','montant_total','statut'];
        $col = in_array($f->sortBy, $allowedCols, true) ? 'ff.' . $f->sortBy : 'ff.date_emission';
        $dir = $f->sortDir === 'ASC' ? 'ASC' : 'DESC';

        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM `finance_factures` ff
             JOIN `eleves` e ON e.id = ff.eleve_id
             LEFT JOIN `classes` c ON c.id = e.classe_id
             WHERE {$whereStr}"
        );
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($f->page - 1) * $f->perPage;
        $params[] = $f->perPage;
        $params[] = $offset;

        $stmt = $this->pdo->prepare(
            "SELECT ff.*,
                    CONCAT(e.prenom,' ',e.nom) AS eleve_nom,
                    e.matricule AS numero_matricule,
                    c.nom AS classe_nom
             FROM `finance_factures` ff
             JOIN `eleves` e ON e.id = ff.eleve_id
             LEFT JOIN `classes` c ON c.id = e.classe_id
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
            "SELECT ff.*,
                    CONCAT(e.prenom,' ',e.nom) AS eleve_nom,
                    e.matricule AS numero_matricule,
                    c.nom AS classe_nom,
                    c.niveau,
                    ue.prenom AS emetteur_prenom, ue.nom AS emetteur_nom,
                    ua.prenom AS annuleur_prenom, ua.nom AS annuleur_nom
             FROM `finance_factures` ff
             JOIN `eleves` e  ON e.id = ff.eleve_id
             LEFT JOIN `classes` c ON c.id = e.classe_id
             LEFT JOIN `users` ue ON ue.id = ff.emise_par
             LEFT JOIN `users` ua ON ua.id = ff.annulee_par
             WHERE ff.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function getLignes(int $factureId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT lf.*, ft.nom AS frais_nom, ft.code AS frais_code
             FROM `finance_lignes_facture` lf
             LEFT JOIN `finance_frais_types` ft ON ft.id = lf.frais_type_id
             WHERE lf.facture_id = ?
             ORDER BY lf.ordre, lf.id"
        );
        $stmt->execute([$factureId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function getRemises(int $factureId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, u.prenom AS accordeur_prenom, u.nom AS accordeur_nom
             FROM `finance_remises` r
             LEFT JOIN `users` u ON u.id = r.accordee_par
             WHERE r.facture_id = ?
             ORDER BY r.created_at"
        );
        $stmt->execute([$factureId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function getPenalites(int $factureId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, u.prenom AS auteur_prenom, u.nom AS auteur_nom
             FROM `finance_penalites` p
             LEFT JOIN `users` u ON u.id = p.applique_par
             WHERE p.facture_id = ?
             ORDER BY p.created_at"
        );
        $stmt->execute([$factureId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function getEcheancier(int $factureId): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT ec.*, GROUP_CONCAT(e.id) AS echeances_ids
             FROM `finance_echeanciers` ec
             LEFT JOIN `finance_echeances` e ON e.echeancier_id = ec.id
             WHERE ec.facture_id = ?
             GROUP BY ec.id"
        );
        $stmt->execute([$factureId]);
        $ech = $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
        if ($ech) {
            $stmt2 = $this->pdo->prepare(
                'SELECT * FROM `finance_echeances` WHERE `echeancier_id` = ? ORDER BY `numero_ordre`'
            );
            $stmt2->execute([$ech->id]);
            $ech->echeances = $stmt2->fetchAll(\PDO::FETCH_OBJ);
        }
        return $ech;
    }

    public function getAvoirs(int $factureId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT av.*, u.prenom, u.nom
             FROM `finance_avoirs` av
             LEFT JOIN `users` u ON u.id = av.emis_par
             WHERE av.facture_origine_id = ?"
        );
        $stmt->execute([$factureId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    // ----------------------------------------------------------------
    // Factures — écriture
    // ----------------------------------------------------------------
    public function insert(array $data): int
    {
        $cols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $phs  = implode(',', array_fill(0, count($data), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO `finance_factures` ({$cols}) VALUES ({$phs})");
        $stmt->execute(array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets = implode(',', array_map(fn($c) => "`{$c}` = ?", array_keys($data)));
        $stmt = $this->pdo->prepare("UPDATE `finance_factures` SET {$sets} WHERE `id` = ?");
        $stmt->execute([...array_values($data), $id]);
    }

    public function insertLigne(array $data): int
    {
        $cols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $phs  = implode(',', array_fill(0, count($data), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO `finance_lignes_facture` ({$cols}) VALUES ({$phs})");
        $stmt->execute(array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function deleteLigne(int $ligneId, int $factureId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM `finance_lignes_facture` WHERE `id` = ? AND `facture_id` = ?'
        );
        $stmt->execute([$ligneId, $factureId]);
        return $stmt->rowCount() > 0;
    }

    public function insertRemise(array $data): int
    {
        $cols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $phs  = implode(',', array_fill(0, count($data), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO `finance_remises` ({$cols}) VALUES ({$phs})");
        $stmt->execute(array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function insertPenalite(array $data): int
    {
        $cols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $phs  = implode(',', array_fill(0, count($data), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO `finance_penalites` ({$cols}) VALUES ({$phs})");
        $stmt->execute(array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function insertEcheancier(int $factureId, int $nb): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `finance_echeanciers` (`facture_id`, `nb_echeances`) VALUES (?, ?)'
        );
        $stmt->execute([$factureId, $nb]);
        return (int)$this->pdo->lastInsertId();
    }

    public function insertEcheance(array $data): void
    {
        $cols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $phs  = implode(',', array_fill(0, count($data), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO `finance_echeances` ({$cols}) VALUES ({$phs})");
        $stmt->execute(array_values($data));
    }

    public function insertAvoir(array $data): int
    {
        $cols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $phs  = implode(',', array_fill(0, count($data), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO `finance_avoirs` ({$cols}) VALUES ({$phs})");
        $stmt->execute(array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    // ----------------------------------------------------------------
    // Agrégats pour recalcul
    // ----------------------------------------------------------------
    public function sumLignes(int $factureId): float
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(`montant_total`), 0) FROM `finance_lignes_facture` WHERE `facture_id` = ?'
        );
        $stmt->execute([$factureId]);
        return (float)$stmt->fetchColumn();
    }

    public function sumRemises(int $factureId): float
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(`montant_calcule`), 0) FROM `finance_remises` WHERE `facture_id` = ?'
        );
        $stmt->execute([$factureId]);
        return (float)$stmt->fetchColumn();
    }

    public function sumPenalites(int $factureId): float
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(`montant`), 0) FROM `finance_penalites` WHERE `facture_id` = ?'
        );
        $stmt->execute([$factureId]);
        return (float)$stmt->fetchColumn();
    }

    // ----------------------------------------------------------------
    // Génération de masse
    // ----------------------------------------------------------------
    public function getElevesByClasse(int $classeId, string $annee): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.id AS eleve_id, CONCAT(e.prenom,' ',e.nom) AS nom
             FROM `eleves` e
             WHERE e.classe_id = ? AND e.actif = 1"
        );
        $stmt->execute([$classeId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function getElevesByNiveau(string $niveau, string $annee): array
    {
        if (empty($niveau)) {
            $stmt = $this->pdo->query(
                "SELECT e.id AS eleve_id, CONCAT(e.prenom,' ',e.nom) AS nom
                 FROM `eleves` e WHERE e.actif = 1"
            );
            return $stmt->fetchAll(\PDO::FETCH_OBJ);
        }
        $stmt = $this->pdo->prepare(
            "SELECT e.id AS eleve_id, CONCAT(e.prenom,' ',e.nom) AS nom
             FROM `eleves` e
             JOIN `classes` c ON c.id = e.classe_id
             WHERE c.niveau = ? AND e.actif = 1"
        );
        $stmt->execute([$niveau]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function factureExiste(int $eleveId, string $annee, array $fraisTypeIds): bool
    {
        if (empty($fraisTypeIds)) {
            return false;
        }
        $phs  = implode(',', array_fill(0, count($fraisTypeIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT ff.id)
             FROM `finance_factures` ff
             JOIN `finance_lignes_facture` lf ON lf.facture_id = ff.id
             WHERE ff.eleve_id = ? AND ff.annee_scolaire = ?
               AND lf.frais_type_id IN ({$phs})
               AND ff.statut NOT IN ('annulee')"
        );
        $stmt->execute([$eleveId, $annee, ...$fraisTypeIds]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function getFraisTypesForMasse(array $ids): array
    {
        $phs  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `finance_frais_types` WHERE `id` IN ({$phs}) AND `statut` = 'actif'"
        );
        $stmt->execute($ids);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    // ----------------------------------------------------------------
    // Statistiques dashboard
    // ----------------------------------------------------------------
    public function statsGlobal(?string $annee = null): object
    {
        $where  = $annee ? "WHERE ff.annee_scolaire = '{$annee}'" : '';
        $stmt   = $this->pdo->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN statut='brouillon' THEN 1 ELSE 0 END) AS brouillons,
                SUM(CASE WHEN statut='emise' THEN 1 ELSE 0 END) AS emises,
                SUM(CASE WHEN statut IN('partiellement_payee','payee') THEN 1 ELSE 0 END) AS payees,
                SUM(CASE WHEN statut='en_retard' THEN 1 ELSE 0 END) AS en_retard,
                SUM(CASE WHEN statut='annulee' THEN 1 ELSE 0 END) AS annulees,
                COALESCE(SUM(montant_total),0) AS montant_total_global,
                COALESCE(SUM(montant_paye),0)  AS montant_paye_global
             FROM `finance_factures` ff {$where}"
        );
        return $stmt->fetch(\PDO::FETCH_OBJ);
    }

    public function getAnneesActives(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT annee_scolaire FROM `finance_factures` ORDER BY annee_scolaire DESC"
        );
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    // ----------------------------------------------------------------
    // PDO accessor (for Service transaction management)
    // ----------------------------------------------------------------
    public function getPdo(): \PDO
    {
        return $this->pdo;
    }
}
