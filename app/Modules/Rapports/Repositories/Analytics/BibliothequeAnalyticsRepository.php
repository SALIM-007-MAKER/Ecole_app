<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Repositories\Analytics;

use Core\Database;

class BibliothequeAnalyticsRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function empruntsParMois(int $etablissementId, int $nbMois = 12): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DATE_FORMAT(date_emprunt, "%Y-%m") AS mois, COUNT(*) AS nb
             FROM biblio_emprunts
             WHERE etablissement_id = :etab
               AND deleted_at IS NULL
               AND date_emprunt >= DATE_SUB(NOW(), INTERVAL :nb MONTH)
             GROUP BY mois
             ORDER BY mois'
        );
        $stmt->execute([':etab' => $etablissementId, ':nb' => $nbMois]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function ouvragesLesPlusEmpruntes(int $etablissementId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT o.titre, o.auteur, COUNT(e.id) AS nb_emprunts
             FROM biblio_emprunts e
             INNER JOIN biblio_exemplaires ex ON ex.id = e.exemplaire_id
             INNER JOIN biblio_ouvrages o ON o.id = ex.ouvrage_id
             WHERE e.etablissement_id = :etab
               AND e.deleted_at IS NULL
             GROUP BY o.id, o.titre, o.auteur
             ORDER BY nb_emprunts DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':etab', $etablissementId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim',  $limit,           \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function tauxRetour(int $etablissementId): float
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN statut = "retourne" THEN 1 ELSE 0 END) AS retournes
             FROM biblio_emprunts
             WHERE etablissement_id = :etab AND deleted_at IS NULL'
        );
        $stmt->execute([':etab' => $etablissementId]);
        $row   = $stmt->fetch(\PDO::FETCH_ASSOC);
        $total = (int)($row['total'] ?? 0);
        return $total > 0 ? round((int)($row['retournes'] ?? 0) * 100 / $total, 2) : 0.0;
    }

    public function empruntsEnRetard(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.id, e.date_retour_prevue,
                    DATEDIFF(NOW(), e.date_retour_prevue) AS jours_retard,
                    o.titre, o.auteur
             FROM biblio_emprunts e
             INNER JOIN biblio_exemplaires ex ON ex.id = e.exemplaire_id
             INNER JOIN biblio_ouvrages o ON o.id = ex.ouvrage_id
             WHERE e.etablissement_id = :etab
               AND e.statut = "en_cours"
               AND e.date_retour_prevue < NOW()
               AND e.deleted_at IS NULL
             ORDER BY jours_retard DESC'
        );
        $stmt->execute([':etab' => $etablissementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function counters(int $etablissementId): array
    {
        $nbO = $this->pdo->prepare(
            'SELECT COUNT(*) FROM biblio_ouvrages
             WHERE etablissement_id = :etab AND deleted_at IS NULL'
        );
        $nbO->execute([':etab' => $etablissementId]);

        $nbE = $this->pdo->prepare(
            'SELECT COUNT(*) FROM biblio_emprunts
             WHERE etablissement_id = :etab AND statut = "en_cours" AND deleted_at IS NULL'
        );
        $nbE->execute([':etab' => $etablissementId]);

        return [
            'nb_ouvrages'      => (int)$nbO->fetchColumn(),
            'nb_emprunts_cours' => (int)$nbE->fetchColumn(),
        ];
    }
}
