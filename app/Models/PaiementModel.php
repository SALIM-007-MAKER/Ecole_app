<?php

namespace App\Models;

use Core\Model;

class PaiementModel extends Model
{
    protected string $table = 'paiements';

    public const MODES = [
        'especes'  => ['label' => 'Espèces',   'icon' => 'cash-stack',           'class' => 'success'],
        'cheque'   => ['label' => 'Chèque',    'icon' => 'bank',                 'class' => 'primary'],
        'virement' => ['label' => 'Virement',  'icon' => 'arrow-left-right',     'class' => 'info'],
        'carte'    => ['label' => 'Carte',     'icon' => 'credit-card-2-front',  'class' => 'warning'],
    ];

    private function baseSelect(): string
    {
        return "SELECT p.*,
                CONCAT(e.prenom,' ',e.nom) AS eleve_nom,
                e.matricule,
                COALESCE(c.nom,'')    AS classe_nom,
                COALESCE(c.niveau,'') AS classe_niveau,
                ft.nom                AS frais_nom,
                CONCAT(COALESCE(u.prenom,''),' ',COALESCE(u.nom,'')) AS encaisseur_nom
                FROM `paiements` p
                JOIN `eleves` e      ON e.id = p.eleve_id
                LEFT JOIN `classes`  c  ON c.id = e.classe_id
                LEFT JOIN `frais_eleves` fe ON fe.id = p.frais_eleve_id
                LEFT JOIN `frais_types`  ft ON ft.id = fe.frais_type_id
                LEFT JOIN `users`    u  ON u.id = p.encaisse_par";
    }

    public function paginateFiltered(int $page, int $perPage, array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $total = (int)($this->queryOne(
            "SELECT COUNT(*) AS n FROM `paiements` p
             JOIN `eleves` e ON e.id = p.eleve_id
             {$where}", $params
        )->n ?? 0);

        $offset = ($page - 1) * $perPage;
        $rows   = $this->query(
            $this->baseSelect() . " {$where}
            ORDER BY p.date_paiement DESC, p.id DESC
            LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['items'=>$rows,'total'=>$total,'pages'=>(int)ceil($total/$perPage),'currentPage'=>$page];
    }

    private function buildWhere(array $f): array
    {
        $c = []; $p = [];
        if (!empty($f['q'])) {
            $t = '%'.$f['q'].'%';
            $c[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.matricule LIKE ? OR p.reference LIKE ?)";
            array_push($p, $t,$t,$t,$t);
        }
        if (!empty($f['annee']))        { $c[] = "p.annee_scolaire = ?"; $p[] = $f['annee']; }
        if (!empty($f['eleve_id']))     { $c[] = "p.eleve_id = ?";       $p[] = (int)$f['eleve_id']; }
        if (!empty($f['mode']))         { $c[] = "p.mode_paiement = ?";  $p[] = $f['mode']; }
        if (!empty($f['date_debut']))   { $c[] = "p.date_paiement >= ?"; $p[] = $f['date_debut']; }
        if (!empty($f['date_fin']))     { $c[] = "p.date_paiement <= ?"; $p[] = $f['date_fin']; }
        if (!empty($f['classe_id']))    { $c[] = "e.classe_id = ?";      $p[] = (int)$f['classe_id']; }
        $w = $c ? 'WHERE '.implode(' AND ',$c) : '';
        return [$w, $p];
    }

    public function findWithDetails(int $id): ?\stdClass
    {
        return $this->queryOne($this->baseSelect()." WHERE p.id = ?", [$id]) ?: null;
    }

    public function getStatsGlobales(string $annee): array
    {
        $row = $this->queryOne(
            "SELECT
                COALESCE(SUM(montant),0)              AS total,
                COUNT(*)                               AS nb,
                SUM(mode_paiement='especes')           AS nb_especes,
                SUM(mode_paiement='cheque')            AS nb_cheque,
                SUM(mode_paiement='virement')          AS nb_virement,
                SUM(mode_paiement='carte')             AS nb_carte,
                COALESCE(SUM(CASE WHEN date_paiement = CURDATE() THEN montant END),0) AS total_aujourd_hui,
                COALESCE(SUM(CASE WHEN MONTH(date_paiement)=MONTH(CURDATE()) AND YEAR(date_paiement)=YEAR(CURDATE()) THEN montant END),0) AS total_ce_mois
             FROM paiements WHERE annee_scolaire = ?",
            [$annee]
        );
        return $row ? (array)$row : [];
    }

    public function getStatsMensuelles(string $annee): array
    {
        // Extract year part from "2025-2026" → 2025 then 2026 according to month
        $yearParts = explode('-', $annee);
        $y1 = $yearParts[0] ?? date('Y');
        $y2 = $yearParts[1] ?? ((int)$y1 + 1);

        return $this->query(
            "SELECT MONTH(date_paiement) AS mois, SUM(montant) AS total, COUNT(*) AS nb
             FROM paiements
             WHERE (YEAR(date_paiement) = ? AND MONTH(date_paiement) >= 9)
                OR (YEAR(date_paiement) = ? AND MONTH(date_paiement) <= 8)
             GROUP BY mois ORDER BY
                CASE WHEN MONTH(date_paiement) >= 9 THEN MONTH(date_paiement)-9
                     ELSE MONTH(date_paiement)+3 END",
            [$y1, $y2]
        );
    }

    public function getStatsMensuellesTotales(int $anneeNum): array
    {
        return $this->query(
            "SELECT MONTH(date_paiement) AS mois, SUM(montant) AS total
             FROM paiements WHERE YEAR(date_paiement) = ?
             GROUP BY mois ORDER BY mois",
            [$anneeNum]
        );
    }

    public function getForDate(string $date): array
    {
        return $this->query(
            $this->baseSelect() . " WHERE p.date_paiement = ? ORDER BY p.id DESC",
            [$date]
        );
    }

    public function getForRapport(int $anneeNum, ?int $mois = null): array
    {
        if ($mois) {
            return $this->query(
                $this->baseSelect() . "
                WHERE YEAR(p.date_paiement)=? AND MONTH(p.date_paiement)=?
                ORDER BY p.date_paiement",
                [$anneeNum, $mois]
            );
        }
        return $this->query(
            $this->baseSelect() . " WHERE YEAR(p.date_paiement)=? ORDER BY p.date_paiement",
            [$anneeNum]
        );
    }

    public function findByEleve(int $eleveId): array
    {
        return $this->query(
            $this->baseSelect() . " WHERE p.eleve_id = ? ORDER BY p.date_paiement DESC",
            [$eleveId]
        );
    }
}
