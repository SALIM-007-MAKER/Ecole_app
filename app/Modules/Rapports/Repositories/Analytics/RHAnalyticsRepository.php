<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Repositories\Analytics;

use Core\Database;

class RHAnalyticsRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function effectifsParDepartement(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.departement, COUNT(e.id) AS nb
             FROM rh_employes e
             WHERE e.etablissement_id = :etab
               AND e.statut = "actif"
               AND e.deleted_at IS NULL
             GROUP BY e.departement
             ORDER BY nb DESC'
        );
        $stmt->execute([':etab' => $etablissementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function tauxPresence(int $etablissementId, string $dateDebut = '', string $dateFin = ''): float
    {
        $where = 'WHERE etablissement_id = :etab AND deleted_at IS NULL';
        $params = [':etab' => $etablissementId];
        if ($dateDebut) { $where .= ' AND date_pointage >= :dd'; $params[':dd'] = $dateDebut; }
        if ($dateFin)   { $where .= ' AND date_pointage <= :df'; $params[':df'] = $dateFin; }

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN statut = 'present' THEN 1 ELSE 0 END) AS presents
             FROM rh_presences $where"
        );
        $stmt->execute($params);
        $row   = $stmt->fetch(\PDO::FETCH_ASSOC);
        $total = (int)($row['total'] ?? 0);
        return $total > 0 ? round((int)($row['presents'] ?? 0) * 100 / $total, 2) : 0.0;
    }

    public function congesParType(int $etablissementId, string $dateDebut = '', string $dateFin = ''): array
    {
        $where = 'WHERE etablissement_id = :etab AND statut = "approuve" AND deleted_at IS NULL';
        $params = [':etab' => $etablissementId];
        if ($dateDebut) { $where .= ' AND date_debut >= :dd'; $params[':dd'] = $dateDebut; }
        if ($dateFin)   { $where .= ' AND date_debut <= :df'; $params[':df'] = $dateFin; }

        $stmt = $this->pdo->prepare(
            "SELECT type_conge, COUNT(*) AS nb,
                    SUM(DATEDIFF(date_fin, date_debut) + 1) AS nb_jours
             FROM rh_conges $where
             GROUP BY type_conge
             ORDER BY nb DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function scoresEvaluations(int $etablissementId, string $periode = ''): array
    {
        $where = 'WHERE e.etablissement_id = :etab AND e.statut = "finalise" AND e.deleted_at IS NULL';
        $params = [':etab' => $etablissementId];
        if ($periode) { $where .= ' AND DATE_FORMAT(e.created_at, "%Y-%m") = :p'; $params[':p'] = $periode; }

        $stmt = $this->pdo->prepare(
            "SELECT emp.poste, ROUND(AVG(e.score_global), 2) AS score_moyen, COUNT(e.id) AS nb
             FROM rh_evaluations e
             INNER JOIN rh_employes emp ON emp.id = e.employe_id
             $where
             GROUP BY emp.poste
             ORDER BY score_moyen DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function contratsExpirant(int $etablissementId, int $joursAvance = 30): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.type_contrat, c.date_fin,
                    CONCAT(e.prenom, " ", e.nom) AS employe
             FROM rh_contrats c
             INNER JOIN rh_employes e ON e.id = c.employe_id
             WHERE c.etablissement_id = :etab
               AND c.statut = "actif"
               AND c.date_fin IS NOT NULL
               AND c.date_fin <= DATE_ADD(NOW(), INTERVAL :j DAY)
               AND c.date_fin >= NOW()
               AND c.deleted_at IS NULL
             ORDER BY c.date_fin ASC'
        );
        $stmt->execute([':etab' => $etablissementId, ':j' => $joursAvance]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function counters(int $etablissementId): array
    {
        $nbE = $this->pdo->prepare(
            'SELECT COUNT(*) FROM rh_employes
             WHERE etablissement_id = :etab AND statut = "actif" AND deleted_at IS NULL'
        );
        $nbE->execute([':etab' => $etablissementId]);

        $nbC = $this->pdo->prepare(
            'SELECT COUNT(*) FROM rh_conges
             WHERE etablissement_id = :etab AND statut = "en_attente" AND deleted_at IS NULL'
        );
        $nbC->execute([':etab' => $etablissementId]);

        return [
            'nb_employes_actifs' => (int)$nbE->fetchColumn(),
            'nb_conges_attente'  => (int)$nbC->fetchColumn(),
        ];
    }
}
