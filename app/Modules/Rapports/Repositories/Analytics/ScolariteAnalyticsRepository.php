<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Repositories\Analytics;

use Core\Database;

class ScolariteAnalyticsRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function effectifsParClasse(int $etablissementId, string $anneeScolaire = ''): array
    {
        $sql = 'SELECT c.nom AS classe, c.niveau, c.filiere,
                       COUNT(i.id) AS nb_eleves
                FROM classes c
                LEFT JOIN inscriptions i ON i.classe_id = c.id
                    AND i.deleted_at IS NULL
                    ' . ($anneeScolaire ? 'AND i.annee_scolaire = :as' : '') . '
                WHERE c.etablissement_id = :etab
                  AND c.deleted_at IS NULL
                GROUP BY c.id, c.nom, c.niveau, c.filiere
                ORDER BY c.niveau, c.nom';

        $params = [':etab' => $etablissementId];
        if ($anneeScolaire) $params[':as'] = $anneeScolaire;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function evolutionInscriptions(int $etablissementId, int $nbMois = 12): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DATE_FORMAT(created_at, "%Y-%m") AS mois,
                    COUNT(*) AS nb
             FROM inscriptions
             WHERE etablissement_id = :etab
               AND deleted_at IS NULL
               AND created_at >= DATE_SUB(NOW(), INTERVAL :nb MONTH)
             GROUP BY mois
             ORDER BY mois'
        );
        $stmt->execute([':etab' => $etablissementId, ':nb' => $nbMois]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function repartitionGenre(int $etablissementId, string $anneeScolaire = ''): array
    {
        $sql = 'SELECT e.genre, COUNT(i.id) AS nb
                FROM inscriptions i
                INNER JOIN eleves e ON e.id = i.eleve_id
                WHERE i.etablissement_id = :etab
                  AND i.deleted_at IS NULL
                  ' . ($anneeScolaire ? 'AND i.annee_scolaire = :as' : '') . '
                GROUP BY e.genre';
        $params = [':etab' => $etablissementId];
        if ($anneeScolaire) $params[':as'] = $anneeScolaire;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function tauxRemplissageClasses(int $etablissementId, string $anneeScolaire = ''): array
    {
        $sql = 'SELECT c.nom AS classe, c.capacite_max,
                       COUNT(i.id) AS nb_inscrits,
                       ROUND(COUNT(i.id) * 100 / GREATEST(c.capacite_max, 1), 1) AS taux
                FROM classes c
                LEFT JOIN inscriptions i ON i.classe_id = c.id
                    AND i.deleted_at IS NULL
                    ' . ($anneeScolaire ? 'AND i.annee_scolaire = :as' : '') . '
                WHERE c.etablissement_id = :etab
                  AND c.deleted_at IS NULL
                GROUP BY c.id, c.nom, c.capacite_max
                ORDER BY taux DESC';
        $params = [':etab' => $etablissementId];
        if ($anneeScolaire) $params[':as'] = $anneeScolaire;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function counters(int $etablissementId, string $anneeScolaire = ''): array
    {
        $params = [':etab' => $etablissementId];
        $asClause = $anneeScolaire ? 'AND annee_scolaire = :as' : '';
        if ($anneeScolaire) $params[':as'] = $anneeScolaire;

        $nbEleves = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT eleve_id) AS n FROM inscriptions
             WHERE etablissement_id = :etab AND deleted_at IS NULL $asClause"
        );
        $nbEleves->execute($params);

        $nbClasses = $this->pdo->prepare(
            'SELECT COUNT(*) AS n FROM classes WHERE etablissement_id = :etab AND deleted_at IS NULL'
        );
        $nbClasses->execute([':etab' => $etablissementId]);

        return [
            'nb_eleves'  => (int)$nbEleves->fetchColumn(),
            'nb_classes' => (int)$nbClasses->fetchColumn(),
        ];
    }
}
