<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Repositories\Analytics;

use Core\Database;

class VieScolaireAnalyticsRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function absencesParClasse(int $etablissementId, string $dateDebut = '', string $dateFin = ''): array
    {
        $where = 'WHERE a.etablissement_id = :etab AND a.deleted_at IS NULL';
        $params = [':etab' => $etablissementId];
        if ($dateDebut) { $where .= ' AND a.date_absence >= :dd'; $params[':dd'] = $dateDebut; }
        if ($dateFin)   { $where .= ' AND a.date_absence <= :df'; $params[':df'] = $dateFin; }

        $stmt = $this->pdo->prepare(
            "SELECT c.nom AS classe, COUNT(a.id) AS nb_absences,
                    SUM(CASE WHEN a.justifiee = 1 THEN 1 ELSE 0 END) AS nb_justifiees
             FROM vs_absences a
             INNER JOIN classes c ON c.id = a.classe_id
             $where
             GROUP BY c.id, c.nom
             ORDER BY nb_absences DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function retardsParClasse(int $etablissementId, string $dateDebut = '', string $dateFin = ''): array
    {
        $where = 'WHERE r.etablissement_id = :etab AND r.deleted_at IS NULL';
        $params = [':etab' => $etablissementId];
        if ($dateDebut) { $where .= ' AND r.date_retard >= :dd'; $params[':dd'] = $dateDebut; }
        if ($dateFin)   { $where .= ' AND r.date_retard <= :df'; $params[':df'] = $dateFin; }

        $stmt = $this->pdo->prepare(
            "SELECT c.nom AS classe, COUNT(r.id) AS nb_retards
             FROM vs_retards r
             INNER JOIN classes c ON c.id = r.classe_id
             $where
             GROUP BY c.id, c.nom
             ORDER BY nb_retards DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function incidentsParType(int $etablissementId, string $dateDebut = '', string $dateFin = ''): array
    {
        $where = 'WHERE i.etablissement_id = :etab AND i.deleted_at IS NULL';
        $params = [':etab' => $etablissementId];
        if ($dateDebut) { $where .= ' AND i.date_incident >= :dd'; $params[':dd'] = $dateDebut; }
        if ($dateFin)   { $where .= ' AND i.date_incident <= :df'; $params[':df'] = $dateFin; }

        $stmt = $this->pdo->prepare(
            "SELECT i.type_incident, COUNT(i.id) AS nb
             FROM vs_incidents_discipline i
             $where
             GROUP BY i.type_incident
             ORDER BY nb DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function evolutionAbsences(int $etablissementId, int $nbMois = 6): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DATE_FORMAT(date_absence, "%Y-%m") AS mois, COUNT(*) AS nb
             FROM vs_absences
             WHERE etablissement_id = :etab
               AND deleted_at IS NULL
               AND date_absence >= DATE_SUB(NOW(), INTERVAL :nb MONTH)
             GROUP BY mois
             ORDER BY mois'
        );
        $stmt->execute([':etab' => $etablissementId, ':nb' => $nbMois]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function counters(int $etablissementId, string $dateDebut = '', string $dateFin = ''): array
    {
        $whereAbs = 'WHERE etablissement_id = :etab AND deleted_at IS NULL';
        $params   = [':etab' => $etablissementId];
        if ($dateDebut) { $whereAbs .= ' AND date_absence >= :dd'; $params[':dd'] = $dateDebut; }
        if ($dateFin)   { $whereAbs .= ' AND date_absence <= :df'; $params[':df'] = $dateFin; }

        $nbAbs = $this->pdo->prepare("SELECT COUNT(*) FROM vs_absences $whereAbs");
        $nbAbs->execute($params);

        $whereRet = str_replace('date_absence', 'date_retard', $whereAbs);
        $nbRet = $this->pdo->prepare("SELECT COUNT(*) FROM vs_retards $whereRet");
        $nbRet->execute($params);

        $nbInc = $this->pdo->prepare(
            'SELECT COUNT(*) FROM vs_incidents_discipline
             WHERE etablissement_id = :etab AND deleted_at IS NULL'
        );
        $nbInc->execute([':etab' => $etablissementId]);

        return [
            'nb_absences' => (int)$nbAbs->fetchColumn(),
            'nb_retards'  => (int)$nbRet->fetchColumn(),
            'nb_incidents' => (int)$nbInc->fetchColumn(),
        ];
    }
}
