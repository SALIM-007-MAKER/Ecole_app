<?php

namespace App\Modules\Finance\Repositories;

use Core\Database;

class FinancialReportRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ── Dashboard KPIs ────────────────────────────────────────────────────────

    public function getDashboardStats(string $date = ''): object
    {
        $date = $date ?: date('Y-m-d');
        $annee = (int)date('Y', strtotime($date));
        $mois  = (int)date('m', strtotime($date));

        $stmt = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN DATE(p.date_paiement) = ? THEN p.montant_applique ELSE 0 END), 0)                                           AS recettes_jour,
                COALESCE(SUM(CASE WHEN YEAR(p.date_paiement) = ? AND MONTH(p.date_paiement) = ? THEN p.montant_applique ELSE 0 END), 0)             AS recettes_mois,
                COALESCE(SUM(CASE WHEN YEAR(p.date_paiement) = ? THEN p.montant_applique ELSE 0 END), 0)                                            AS recettes_annee,
                COUNT(CASE WHEN DATE(p.date_paiement) = ? THEN 1 END)                                                                               AS nb_paiements_jour,
                COUNT(CASE WHEN YEAR(p.date_paiement) = ? AND MONTH(p.date_paiement) = ? THEN 1 END)                                                AS nb_paiements_mois
             FROM finance_paiements p
             WHERE p.statut = 'complete'"
        );
        $stmt->execute([$date, $annee, $mois, $annee, $date, $annee, $mois]);
        $paiStats = $stmt->fetch(\PDO::FETCH_OBJ);

        $stmt2 = $this->pdo->query(
            "SELECT
                COALESCE(SUM(CASE WHEN statut IN ('emise','partiellement_payee','en_retard') THEN montant_total - montant_paye ELSE 0 END), 0) AS creances_total,
                COUNT(CASE WHEN statut IN ('emise','partiellement_payee','en_retard') THEN 1 END)                                               AS creances_nb,
                COALESCE(SUM(CASE WHEN statut = 'en_retard' THEN montant_total - montant_paye ELSE 0 END), 0)                                  AS impayes_total,
                COUNT(CASE WHEN statut = 'en_retard' THEN 1 END)                                                                               AS impayes_nb,
                COALESCE(SUM(CASE WHEN statut NOT IN ('brouillon','annulee') THEN montant_paye ELSE 0 END), 0)                                 AS total_encaisse,
                COALESCE(SUM(CASE WHEN statut NOT IN ('brouillon','annulee') THEN montant_total ELSE 0 END), 0)                                AS total_emis
             FROM finance_factures"
        );
        $facStats = $stmt2->fetch(\PDO::FETCH_OBJ);

        $stmt3 = $this->pdo->query(
            "SELECT COALESCE(SUM(solde_theorique), 0) AS tresorerie_caisse
             FROM finance_sessions_caisse
             WHERE statut IN ('ouverte','en_activite')"
        );
        $caisseStats = $stmt3->fetch(\PDO::FETCH_OBJ);

        $taux = ($facStats->total_emis > 0)
            ? round(($facStats->total_encaisse / $facStats->total_emis) * 100, 1)
            : 0;

        return (object)[
            'recettes_jour'      => (float)$paiStats->recettes_jour,
            'recettes_mois'      => (float)$paiStats->recettes_mois,
            'recettes_annee'     => (float)$paiStats->recettes_annee,
            'nb_paiements_jour'  => (int)$paiStats->nb_paiements_jour,
            'nb_paiements_mois'  => (int)$paiStats->nb_paiements_mois,
            'creances_total'     => (float)$facStats->creances_total,
            'creances_nb'        => (int)$facStats->creances_nb,
            'impayes_total'      => (float)$facStats->impayes_total,
            'impayes_nb'         => (int)$facStats->impayes_nb,
            'tresorerie_caisse'  => (float)$caisseStats->tresorerie_caisse,
            'taux_recouvrement'  => $taux,
        ];
    }

    public function getEvolutionMensuelle(int $annee): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                DATE_FORMAT(p.date_paiement, '%Y-%m')   AS mois_key,
                DATE_FORMAT(p.date_paiement, '%m/%Y')   AS mois_label,
                COALESCE(SUM(p.montant_applique), 0)    AS recettes,
                COUNT(p.id)                             AS nb_paiements
             FROM finance_paiements p
             WHERE p.statut = 'complete' AND YEAR(p.date_paiement) = ?
             GROUP BY DATE_FORMAT(p.date_paiement, '%Y-%m')
             ORDER BY mois_key ASC"
        );
        $stmt->execute([$annee]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function getTopDebiteurs(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                e.id, e.nom, e.prenom, e.matricule,
                COALESCE(c.nom, '—') AS classe_nom,
                SUM(ff.montant_total - ff.montant_paye) AS montant_du,
                COUNT(ff.id)                            AS nb_factures
             FROM finance_factures ff
             JOIN eleves e           ON e.id  = ff.eleve_id
             LEFT JOIN classes c     ON c.id  = e.classe_id
             WHERE ff.statut IN ('emise','partiellement_payee','en_retard')
             GROUP BY e.id
             ORDER BY montant_du DESC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function getStatsParMode(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                mp.nom       AS mode_nom,
                mp.code      AS mode_code,
                mp.icone     AS mode_icone,
                COUNT(p.id)  AS nb,
                COALESCE(SUM(p.montant_applique), 0) AS total
             FROM finance_paiements p
             JOIN finance_modes_paiement mp ON mp.id = p.mode_paiement_id
             WHERE p.statut = 'complete'
             GROUP BY mp.id
             ORDER BY total DESC"
        );
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    // ── Paiements ─────────────────────────────────────────────────────────────

    public function paginatePaiements(array $f, int $page, int $perPage): array
    {
        [$where, $params] = $this->buildPaiementsWhere($f);
        $whereStr = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT p.id)
             FROM finance_paiements p
             JOIN finance_factures ff    ON ff.id = p.facture_id
             JOIN eleves e               ON e.id  = ff.eleve_id
             LEFT JOIN classes c         ON c.id  = e.classe_id
             LEFT JOIN finance_modes_paiement mp ON mp.id = p.mode_paiement_id
             WHERE {$whereStr}"
        );
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            "SELECT
                p.id, p.numero, p.statut, p.montant, p.montant_applique, p.date_paiement,
                p.reference_externe, p.created_at,
                ff.numero AS facture_numero, ff.annee_scolaire, ff.montant_total,
                CONCAT(e.prenom,' ',e.nom) AS eleve_nom,
                e.matricule AS eleve_matricule,
                COALESCE(c.nom,'—')  AS classe_nom,
                COALESCE(c.niveau,'—') AS niveau,
                COALESCE(mp.nom,'—') AS mode_nom,
                COALESCE(mp.code,'')  AS mode_code
             FROM finance_paiements p
             JOIN finance_factures ff    ON ff.id = p.facture_id
             JOIN eleves e               ON e.id  = ff.eleve_id
             LEFT JOIN classes c         ON c.id  = e.classe_id
             LEFT JOIN finance_modes_paiement mp ON mp.id = p.mode_paiement_id
             WHERE {$whereStr}
             ORDER BY p.date_paiement DESC, p.id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute(array_merge($params, [$perPage, $offset]));

        return [
            'items'       => $stmt->fetchAll(\PDO::FETCH_OBJ),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    public function getSommePaiements(array $f): object
    {
        [$where, $params] = $this->buildPaiementsWhere($f);
        $stmt = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(p.montant_applique), 0) AS total_encaisse,
                COUNT(DISTINCT p.id)                  AS nb_paiements,
                COALESCE(AVG(p.montant_applique), 0)  AS moy_paiement
             FROM finance_paiements p
             JOIN finance_factures ff    ON ff.id = p.facture_id
             JOIN eleves e               ON e.id  = ff.eleve_id
             LEFT JOIN classes c         ON c.id  = e.classe_id
             LEFT JOIN finance_modes_paiement mp ON mp.id = p.mode_paiement_id
             WHERE " . implode(' AND ', $where)
        );
        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: (object)['total_encaisse'=>0,'nb_paiements'=>0,'moy_paiement'=>0];
    }

    public function getPaiementsParClasse(array $f): array
    {
        [$where, $params] = $this->buildPaiementsWhere($f);
        $stmt = $this->pdo->prepare(
            "SELECT
                COALESCE(c.nom,'Sans classe')      AS classe_nom,
                COALESCE(c.niveau,'—')             AS niveau,
                COUNT(DISTINCT p.id)               AS nb_paiements,
                COUNT(DISTINCT e.id)               AS nb_eleves,
                COALESCE(SUM(p.montant_applique),0) AS total
             FROM finance_paiements p
             JOIN finance_factures ff ON ff.id = p.facture_id
             JOIN eleves e            ON e.id  = ff.eleve_id
             LEFT JOIN classes c      ON c.id  = e.classe_id
             LEFT JOIN finance_modes_paiement mp ON mp.id = p.mode_paiement_id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY c.id
             ORDER BY total DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    private function buildPaiementsWhere(array $f): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($f['statut'])) {
            $where[] = 'p.statut = ?';
            $params[] = $f['statut'];
        } else {
            $where[] = "p.statut = 'complete'";
        }
        if (!empty($f['mode_paiement'])) {
            $where[]  = 'mp.code = ?';
            $params[] = $f['mode_paiement'];
        }
        if (!empty($f['annee_scolaire'])) {
            $where[]  = 'ff.annee_scolaire = ?';
            $params[] = $f['annee_scolaire'];
        }
        if (!empty($f['classe_id'])) {
            $where[]  = 'e.classe_id = ?';
            $params[] = $f['classe_id'];
        }
        if (!empty($f['niveau'])) {
            $where[]  = 'c.niveau = ?';
            $params[] = $f['niveau'];
        }
        if (!empty($f['eleve_id'])) {
            $where[]  = 'ff.eleve_id = ?';
            $params[] = $f['eleve_id'];
        }
        if (!empty($f['date_debut'])) {
            $where[]  = 'p.date_paiement >= ?';
            $params[] = $f['date_debut'];
        }
        if (!empty($f['date_fin'])) {
            $where[]  = 'p.date_paiement <= ?';
            $params[] = $f['date_fin'];
        }
        if (!empty($f['q'])) {
            $like     = '%' . $f['q'] . '%';
            $where[]  = '(p.numero LIKE ? OR CONCAT(e.prenom," ",e.nom) LIKE ? OR e.matricule LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        return [$where, $params];
    }

    // ── Factures ──────────────────────────────────────────────────────────────

    public function paginateFactures(array $f, int $page, int $perPage): array
    {
        [$where, $params] = $this->buildFacturesWhere($f);
        $whereStr = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(ff.id) FROM finance_factures ff
             JOIN eleves e      ON e.id = ff.eleve_id
             LEFT JOIN classes c ON c.id = e.classe_id
             WHERE {$whereStr}"
        );
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            "SELECT
                ff.id, ff.numero, ff.statut, ff.annee_scolaire,
                ff.montant_total, ff.montant_paye,
                ff.montant_total - ff.montant_paye AS montant_restant,
                ff.date_emission, ff.date_echeance,
                CONCAT(e.prenom,' ',e.nom) AS eleve_nom,
                e.matricule AS eleve_matricule,
                COALESCE(c.nom,'—')   AS classe_nom,
                COALESCE(c.niveau,'—') AS niveau
             FROM finance_factures ff
             JOIN eleves e      ON e.id = ff.eleve_id
             LEFT JOIN classes c ON c.id = e.classe_id
             WHERE {$whereStr}
             ORDER BY ff.date_emission DESC, ff.id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute(array_merge($params, [$perPage, $offset]));

        return [
            'items'       => $stmt->fetchAll(\PDO::FETCH_OBJ),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    public function getStatsFactures(array $f): object
    {
        [$where, $params] = $this->buildFacturesWhere($f);
        $stmt = $this->pdo->prepare(
            "SELECT
                COUNT(ff.id)                                                                AS nb_total,
                COUNT(CASE WHEN ff.statut='emise'              THEN 1 END)                  AS nb_emises,
                COUNT(CASE WHEN ff.statut='payee'              THEN 1 END)                  AS nb_payees,
                COUNT(CASE WHEN ff.statut='partiellement_payee' THEN 1 END)                 AS nb_partielles,
                COUNT(CASE WHEN ff.statut='en_retard'          THEN 1 END)                  AS nb_retard,
                COUNT(CASE WHEN ff.statut='annulee'            THEN 1 END)                  AS nb_annulees,
                COALESCE(SUM(ff.montant_total), 0)                                          AS total_emis,
                COALESCE(SUM(ff.montant_paye), 0)                                           AS total_encaisse,
                COALESCE(SUM(ff.montant_total - ff.montant_paye), 0)                        AS total_restant,
                COALESCE(SUM(CASE WHEN ff.statut='payee' THEN ff.montant_total ELSE 0 END), 0) AS total_payees
             FROM finance_factures ff
             JOIN eleves e      ON e.id = ff.eleve_id
             LEFT JOIN classes c ON c.id = e.classe_id
             WHERE " . implode(' AND ', $where)
        );
        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: (object)[];
    }

    private function buildFacturesWhere(array $f): array
    {
        $where  = ["ff.statut != 'brouillon'"];
        $params = [];

        if (!empty($f['statut'])) {
            $where[]  = 'ff.statut = ?';
            $params[] = $f['statut'];
        }
        if (!empty($f['annee_scolaire'])) {
            $where[]  = 'ff.annee_scolaire = ?';
            $params[] = $f['annee_scolaire'];
        }
        if (!empty($f['classe_id'])) {
            $where[]  = 'e.classe_id = ?';
            $params[] = $f['classe_id'];
        }
        if (!empty($f['niveau'])) {
            $where[]  = 'c.niveau = ?';
            $params[] = $f['niveau'];
        }
        if (!empty($f['eleve_id'])) {
            $where[]  = 'ff.eleve_id = ?';
            $params[] = $f['eleve_id'];
        }
        if (!empty($f['date_debut'])) {
            $where[]  = 'ff.date_emission >= ?';
            $params[] = $f['date_debut'];
        }
        if (!empty($f['date_fin'])) {
            $where[]  = 'ff.date_emission <= ?';
            $params[] = $f['date_fin'];
        }
        if (!empty($f['q'])) {
            $like     = '%' . $f['q'] . '%';
            $where[]  = '(ff.numero LIKE ? OR CONCAT(e.prenom," ",e.nom) LIKE ? OR e.matricule LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        return [$where, $params];
    }

    // ── Impayés ───────────────────────────────────────────────────────────────

    public function getImpayes(array $f): array
    {
        $where  = ["ff.statut IN ('emise','partiellement_payee','en_retard')"];
        $params = [];

        if (!empty($f['annee_scolaire'])) {
            $where[]  = 'ff.annee_scolaire = ?';
            $params[] = $f['annee_scolaire'];
        }
        if (!empty($f['classe_id'])) {
            $where[]  = 'e.classe_id = ?';
            $params[] = $f['classe_id'];
        }
        if (!empty($f['niveau'])) {
            $where[]  = 'c.niveau = ?';
            $params[] = $f['niveau'];
        }
        if (!empty($f['tranche'])) {
            switch ($f['tranche']) {
                case '0-30':   $where[] = 'DATEDIFF(CURDATE(), COALESCE(ff.date_echeance, ff.date_emission)) BETWEEN 0 AND 30';  break;
                case '31-60':  $where[] = 'DATEDIFF(CURDATE(), COALESCE(ff.date_echeance, ff.date_emission)) BETWEEN 31 AND 60'; break;
                case '61-90':  $where[] = 'DATEDIFF(CURDATE(), COALESCE(ff.date_echeance, ff.date_emission)) BETWEEN 61 AND 90'; break;
                case '+90':    $where[] = 'DATEDIFF(CURDATE(), COALESCE(ff.date_echeance, ff.date_emission)) > 90';              break;
            }
        }
        if (!empty($f['q'])) {
            $like     = '%' . $f['q'] . '%';
            $where[]  = '(ff.numero LIKE ? OR CONCAT(e.prenom," ",e.nom) LIKE ? OR e.matricule LIKE ?)';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $stmt = $this->pdo->prepare(
            "SELECT
                ff.id, ff.numero, ff.statut, ff.annee_scolaire,
                ff.montant_total, ff.montant_paye,
                ff.montant_total - ff.montant_paye AS montant_restant,
                ff.date_emission, ff.date_echeance,
                DATEDIFF(CURDATE(), COALESCE(ff.date_echeance, ff.date_emission)) AS jours_retard,
                CASE
                    WHEN DATEDIFF(CURDATE(), COALESCE(ff.date_echeance, ff.date_emission)) <= 30 THEN '0-30j'
                    WHEN DATEDIFF(CURDATE(), COALESCE(ff.date_echeance, ff.date_emission)) <= 60 THEN '31-60j'
                    WHEN DATEDIFF(CURDATE(), COALESCE(ff.date_echeance, ff.date_emission)) <= 90 THEN '61-90j'
                    ELSE '+90j'
                END AS tranche_age,
                CONCAT(e.prenom,' ',e.nom) AS eleve_nom,
                e.matricule AS eleve_matricule,
                COALESCE(c.nom,'—')   AS classe_nom,
                COALESCE(c.niveau,'—') AS niveau
             FROM finance_factures ff
             JOIN eleves e      ON e.id = ff.eleve_id
             LEFT JOIN classes c ON c.id = e.classe_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY jours_retard DESC, montant_restant DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function getImpayesAging(array $f = []): array
    {
        $filterClause = '';
        $params = [];
        if (!empty($f['annee_scolaire'])) {
            $filterClause = 'AND ff.annee_scolaire = ?';
            $params[] = $f['annee_scolaire'];
        }

        $stmt = $this->pdo->prepare(
            "SELECT
                CASE
                    WHEN DATEDIFF(CURDATE(), COALESCE(ff.date_echeance, ff.date_emission)) <= 30 THEN '0-30j'
                    WHEN DATEDIFF(CURDATE(), COALESCE(ff.date_echeance, ff.date_emission)) <= 60 THEN '31-60j'
                    WHEN DATEDIFF(CURDATE(), COALESCE(ff.date_echeance, ff.date_emission)) <= 90 THEN '61-90j'
                    ELSE '+90j'
                END AS tranche,
                COUNT(ff.id)                                         AS nb,
                COALESCE(SUM(ff.montant_total - ff.montant_paye), 0) AS montant
             FROM finance_factures ff
             WHERE ff.statut IN ('emise','partiellement_payee','en_retard') {$filterClause}
             GROUP BY tranche
             ORDER BY FIELD(tranche, '0-30j','31-60j','61-90j','+90j')"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    // ── Caisse ────────────────────────────────────────────────────────────────

    public function paginateSessions(array $f, int $page, int $perPage): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($f['statut'])) {
            $where[]  = 's.statut = ?';
            $params[] = $f['statut'];
        }
        if (!empty($f['date_debut'])) {
            $where[]  = 's.date_ouverture >= ?';
            $params[] = $f['date_debut'];
        }
        if (!empty($f['date_fin'])) {
            $where[]  = 's.date_ouverture <= ?';
            $params[] = $f['date_fin'];
        }

        $whereStr = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM finance_sessions_caisse s WHERE {$whereStr}"
        );
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            "SELECT s.*,
                    CONCAT(u.prenom,' ',u.nom) AS caissier_nom,
                    (SELECT COUNT(*) FROM finance_mouvements_caisse m WHERE m.session_id = s.id AND m.statut='actif') AS nb_mouvements
             FROM finance_sessions_caisse s
             LEFT JOIN users u ON u.id = s.caissier_id
             WHERE {$whereStr}
             ORDER BY s.date_ouverture DESC, s.id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute(array_merge($params, [$perPage, $offset]));

        return [
            'items'       => $stmt->fetchAll(\PDO::FETCH_OBJ),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    public function getStatsCaisse(array $f = []): object
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($f['date_debut'])) { $where[] = 's.date_ouverture >= ?'; $params[] = $f['date_debut']; }
        if (!empty($f['date_fin']))   { $where[] = 's.date_ouverture <= ?'; $params[] = $f['date_fin'];   }

        $stmt = $this->pdo->prepare(
            "SELECT
                COUNT(s.id)                                                           AS nb_sessions,
                COALESCE(SUM(s.total_recettes), 0)                                    AS total_recettes,
                COALESCE(SUM(s.total_decaissements), 0)                               AS total_decaissements,
                COALESCE(SUM(ABS(s.ecart)), 0)                                        AS total_ecart,
                COUNT(CASE WHEN s.ecart != 0 AND s.statut='fermee' THEN 1 END)        AS nb_ecarts,
                COALESCE(SUM(CASE WHEN s.statut IN ('ouverte','en_activite') THEN s.solde_theorique ELSE 0 END), 0) AS solde_actuel
             FROM finance_sessions_caisse s
             WHERE " . implode(' AND ', $where)
        );
        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: (object)[];
    }

    // ── Analytique ────────────────────────────────────────────────────────────

    public function getComparatifAnnuel(array $annees): array
    {
        if (empty($annees)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($annees), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT
                YEAR(p.date_paiement)               AS annee,
                COALESCE(SUM(p.montant_applique), 0) AS recettes,
                COUNT(p.id)                          AS nb_paiements
             FROM finance_paiements p
             WHERE p.statut = 'complete' AND YEAR(p.date_paiement) IN ({$placeholders})
             GROUP BY YEAR(p.date_paiement)
             ORDER BY annee ASC"
        );
        $stmt->execute($annees);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function getComparatifParClasse(string $anneeScolaire): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                COALESCE(c.nom,'Sans classe') AS classe_nom,
                COALESCE(c.niveau,'—')        AS niveau,
                COUNT(DISTINCT ff.eleve_id)   AS nb_eleves,
                COALESCE(SUM(ff.montant_total), 0) AS montant_total,
                COALESCE(SUM(ff.montant_paye), 0)  AS montant_paye,
                COALESCE(SUM(ff.montant_total - ff.montant_paye), 0) AS montant_restant,
                CASE WHEN SUM(ff.montant_total) > 0
                     THEN ROUND(SUM(ff.montant_paye) / SUM(ff.montant_total) * 100, 1)
                     ELSE 0 END AS taux_paiement
             FROM finance_factures ff
             JOIN eleves e      ON e.id = ff.eleve_id
             LEFT JOIN classes c ON c.id = e.classe_id
             WHERE ff.statut NOT IN ('brouillon','annulee') AND ff.annee_scolaire = ?
             GROUP BY c.id
             ORDER BY montant_total DESC"
        );
        $stmt->execute([$anneeScolaire]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function getProjectionMensuelle(int $annee): array
    {
        // Moyenne sur les 3 derniers mois pour projeter les mois restants
        $stmt = $this->pdo->prepare(
            "SELECT
                mp.annee, mp.mois, mp.recettes_reelles,
                ROUND(AVG(mp.recettes_reelles) OVER (ORDER BY mp.annee, mp.mois ROWS BETWEEN 2 PRECEDING AND CURRENT ROW), 0) AS tendance
             FROM (
                SELECT
                    YEAR(p.date_paiement)  AS annee,
                    MONTH(p.date_paiement) AS mois,
                    SUM(p.montant_applique) AS recettes_reelles
                FROM finance_paiements p
                WHERE p.statut = 'complete' AND YEAR(p.date_paiement) = ?
                GROUP BY YEAR(p.date_paiement), MONTH(p.date_paiement)
             ) mp
             ORDER BY mp.annee, mp.mois"
        );
        $stmt->execute([$annee]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    // ── KPIs financiers par année scolaire (Sept→Août) ────────────────────────
    // Remplace RapportModel::getKpiFinance/getFinanceParMois/getDepensesParCategorie/
    // getRecouvrementParFrais (V1, tables paiements/depenses/frais_eleves/frais_types)
    // — sources exclusivement V2 : finance_paiements, finance_decaissements,
    // finance_categories_depenses, finance_factures, finance_lignes_facture.

    private function anneeScolaireYears(string $anneeScolaire): array
    {
        $parts = explode('-', $anneeScolaire);
        return [(int)($parts[0] ?? date('Y')), (int)($parts[1] ?? (date('Y') + 1))];
    }

    public function getKpiFinanceAnnuel(string $anneeScolaire): object
    {
        [$y1, $y2] = $this->anneeScolaireYears($anneeScolaire);

        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(p.montant_applique), 0) AS total
             FROM finance_paiements p
             WHERE p.statut = 'complete'
               AND ((YEAR(p.date_paiement) = ? AND MONTH(p.date_paiement) >= 9)
                 OR (YEAR(p.date_paiement) = ? AND MONTH(p.date_paiement) < 9))"
        );
        $stmt->execute([$y1, $y2]);
        $recettes = (float)$stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(d.montant), 0) AS total
             FROM finance_decaissements d
             WHERE d.statut = 'paye' AND d.deleted_at IS NULL
               AND ((YEAR(d.date_depense) = ? AND MONTH(d.date_depense) >= 9)
                 OR (YEAR(d.date_depense) = ? AND MONTH(d.date_depense) < 9))"
        );
        $stmt->execute([$y1, $y2]);
        $depenses = (float)$stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(montant_total), 0) AS total
             FROM finance_factures
             WHERE statut != 'brouillon' AND annee_scolaire = ?"
        );
        $stmt->execute([$anneeScolaire]);
        $fraisTotal = (float)$stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(montant_total - montant_paye), 0) AS total
             FROM finance_factures
             WHERE statut IN ('emise','partiellement_payee','en_retard') AND annee_scolaire = ?"
        );
        $stmt->execute([$anneeScolaire]);
        $impayes = (float)$stmt->fetchColumn();

        return (object)[
            'recettes'          => $recettes,
            'depenses'          => $depenses,
            'impayes'           => $impayes,
            'frais_total'       => $fraisTotal,
            'solde'             => $recettes - $depenses,
            'taux_recouvrement' => $fraisTotal > 0 ? round($recettes / $fraisTotal * 100, 1) : 0.0,
        ];
    }

    public function getRecettesDepensesParMois(string $anneeScolaire): array
    {
        [$y1, $y2] = $this->anneeScolaireYears($anneeScolaire);

        $stmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(p.date_paiement, '%Y-%m') AS mois, SUM(p.montant_applique) AS total
             FROM finance_paiements p
             WHERE p.statut = 'complete'
               AND ((YEAR(p.date_paiement) = ? AND MONTH(p.date_paiement) >= 9)
                 OR (YEAR(p.date_paiement) = ? AND MONTH(p.date_paiement) < 9))
             GROUP BY mois"
        );
        $stmt->execute([$y1, $y2]);
        $recettes = $stmt->fetchAll(\PDO::FETCH_OBJ);

        $stmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(d.date_depense, '%Y-%m') AS mois, SUM(d.montant) AS total
             FROM finance_decaissements d
             WHERE d.statut = 'paye' AND d.deleted_at IS NULL
               AND ((YEAR(d.date_depense) = ? AND MONTH(d.date_depense) >= 9)
                 OR (YEAR(d.date_depense) = ? AND MONTH(d.date_depense) < 9))
             GROUP BY mois"
        );
        $stmt->execute([$y1, $y2]);
        $depenses = $stmt->fetchAll(\PDO::FETCH_OBJ);

        return ['recettes' => $recettes, 'depenses' => $depenses];
    }

    public function getDepensesParCategorieScolaire(string $anneeScolaire): array
    {
        [$y1, $y2] = $this->anneeScolaireYears($anneeScolaire);

        $stmt = $this->pdo->prepare(
            "SELECT dc.nom, dc.couleur, COALESCE(SUM(d.montant), 0) AS total
             FROM finance_categories_depenses dc
             LEFT JOIN finance_decaissements d ON d.categorie_id = dc.id
                AND d.statut = 'paye' AND d.deleted_at IS NULL
                AND ((YEAR(d.date_depense) = ? AND MONTH(d.date_depense) >= 9)
                  OR (YEAR(d.date_depense) = ? AND MONTH(d.date_depense) < 9))
             GROUP BY dc.id
             HAVING total > 0
             ORDER BY total DESC"
        );
        $stmt->execute([$y1, $y2]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function getRecouvrementParTypeFrais(string $anneeScolaire): array
    {
        // Le paiement est enregistré au niveau facture (pas par ligne) : on
        // proratise le montant payé de chaque facture sur ses lignes, au prorata
        // du poids de la ligne dans le total de la facture.
        $stmt = $this->pdo->prepare(
            "SELECT
                ft.nom,
                COUNT(DISTINCT ff.eleve_id)                                              AS nb_eleves,
                COALESCE(SUM(fl.montant_total), 0)                                       AS montant_total,
                COALESCE(SUM(
                    fl.montant_total * IF(ff.montant_total > 0, ff.montant_paye / ff.montant_total, 0)
                ), 0)                                                                    AS montant_paye
             FROM finance_lignes_facture fl
             JOIN finance_factures ff    ON ff.id = fl.facture_id
             JOIN finance_frais_types ft ON ft.id = fl.frais_type_id
             WHERE ff.statut != 'brouillon' AND ff.annee_scolaire = ?
             GROUP BY ft.id
             ORDER BY montant_total DESC"
        );
        $stmt->execute([$anneeScolaire]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    // ── Référentiels (classes, niveaux, modes, années) ────────────────────────

    public function getAnneesScolaires(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT annee_scolaire FROM finance_factures ORDER BY annee_scolaire DESC"
        );
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getClasses(): array
    {
        $stmt = $this->pdo->query("SELECT id, nom, niveau FROM classes ORDER BY " . \App\Models\ClasseModel::ordreNiveauSql() . ", nom");
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function getNiveaux(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT niveau FROM classes WHERE niveau IS NOT NULL AND niveau != '' ORDER BY " . \App\Models\ClasseModel::ordreNiveauSql()
        );
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getModesPaiement(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, code, nom, icone FROM finance_modes_paiement WHERE actif = 1 ORDER BY nom"
        );
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }
}
