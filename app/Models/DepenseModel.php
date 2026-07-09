<?php

namespace App\Models;

use Core\Model;

class DepenseModel extends Model
{
    protected string $table = 'depenses';

    private function baseSelect(): string
    {
        return "SELECT d.*,
                COALESCE(dc.nom,'Divers')     AS categorie_nom,
                COALESCE(dc.couleur,'#858796') AS categorie_couleur,
                CONCAT(COALESCE(u.prenom,''),' ',COALESCE(u.nom,'')) AS saisi_par_nom
                FROM `depenses` d
                LEFT JOIN `depenses_categories` dc ON dc.id = d.categorie_id
                LEFT JOIN `users` u ON u.id = d.saisi_par";
    }

    public function paginateFiltered(int $page, int $perPage, array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $total = (int)($this->queryOne(
            "SELECT COUNT(*) AS n FROM `depenses` d {$where}", $params
        )->n ?? 0);

        $offset = ($page - 1) * $perPage;
        $rows   = $this->query(
            $this->baseSelect() . " {$where}
            ORDER BY d.date_depense DESC, d.id DESC
            LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['items'=>$rows,'total'=>$total,'pages'=>(int)ceil($total/$perPage),'currentPage'=>$page];
    }

    private function buildWhere(array $f): array
    {
        $c = []; $p = [];
        if (!empty($f['q']))            { $t='%'.$f['q'].'%'; $c[]="d.libelle LIKE ?";     $p[]=$t; }
        if (!empty($f['categorie_id'])) { $c[]="d.categorie_id = ?";    $p[]=(int)$f['categorie_id']; }
        if (!empty($f['mode']))         { $c[]="d.mode_paiement = ?";   $p[]=$f['mode']; }
        if (!empty($f['date_debut']))   { $c[]="d.date_depense >= ?";   $p[]=$f['date_debut']; }
        if (!empty($f['date_fin']))     { $c[]="d.date_depense <= ?";   $p[]=$f['date_fin']; }
        $w = $c ? 'WHERE '.implode(' AND ',$c) : '';
        return [$w, $p];
    }

    public function findWithDetails(int $id): ?\stdClass
    {
        return $this->queryOne($this->baseSelect()." WHERE d.id = ?", [$id]) ?: null;
    }

    public function getStatsGlobales(int $anneeNum): array
    {
        $row = $this->queryOne(
            "SELECT
                COALESCE(SUM(montant),0) AS total,
                COUNT(*) AS nb,
                COALESCE(SUM(CASE WHEN date_depense=CURDATE() THEN montant END),0) AS total_aujourd_hui,
                COALESCE(SUM(CASE WHEN MONTH(date_depense)=MONTH(CURDATE()) AND YEAR(date_depense)=YEAR(CURDATE()) THEN montant END),0) AS total_ce_mois
             FROM depenses WHERE YEAR(date_depense) = ?",
            [$anneeNum]
        );
        return $row ? (array)$row : [];
    }

    public function getStatsMensuelles(int $anneeNum): array
    {
        return $this->query(
            "SELECT MONTH(date_depense) AS mois, SUM(montant) AS total, COUNT(*) AS nb
             FROM depenses WHERE YEAR(date_depense) = ?
             GROUP BY mois ORDER BY mois",
            [$anneeNum]
        );
    }

    public function getStatsParCategorie(int $anneeNum, ?int $mois = null): array
    {
        $params = [$anneeNum];
        $extra  = '';
        if ($mois) { $extra = 'AND MONTH(date_depense)=?'; $params[] = $mois; }

        return $this->query(
            "SELECT dc.nom, dc.couleur, COALESCE(SUM(d.montant),0) AS total
             FROM depenses_categories dc
             LEFT JOIN depenses d ON d.categorie_id = dc.id
                AND YEAR(d.date_depense) = ? {$extra}
             GROUP BY dc.id ORDER BY total DESC",
            $params
        );
    }

    public function getForDate(string $date): array
    {
        return $this->query(
            $this->baseSelect() . " WHERE d.date_depense = ? ORDER BY d.id DESC",
            [$date]
        );
    }

    public function getForRapport(int $anneeNum, ?int $mois = null): array
    {
        if ($mois) {
            return $this->query(
                $this->baseSelect() . "
                WHERE YEAR(d.date_depense)=? AND MONTH(d.date_depense)=?
                ORDER BY d.date_depense",
                [$anneeNum, $mois]
            );
        }
        return $this->query(
            $this->baseSelect() . " WHERE YEAR(d.date_depense)=? ORDER BY d.date_depense",
            [$anneeNum]
        );
    }

    public function getCategories(): array
    {
        return $this->query("SELECT * FROM depenses_categories ORDER BY nom");
    }
}
