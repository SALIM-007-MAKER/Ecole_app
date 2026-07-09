<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Repositories\Analytics;

use Core\Database;

class InventaireAnalyticsRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function valeurTotaleStock(int $etablissementId): float
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(s.quantite_disponible * a.prix_unitaire), 0) AS valeur
             FROM inv_stocks s
             INNER JOIN inv_articles a ON a.id = s.article_id
             WHERE s.etablissement_id = :etab'
        );
        $stmt->execute([':etab' => $etablissementId]);
        return (float)$stmt->fetchColumn();
    }

    public function articlesEnAlerteStock(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.reference, a.designation, a.seuil_alerte,
                    COALESCE(SUM(s.quantite_disponible), 0) AS stock_total
             FROM inv_articles a
             LEFT JOIN inv_stocks s ON s.article_id = a.id
             WHERE a.etablissement_id = :etab
               AND a.actif = 1
               AND a.deleted_at IS NULL
             GROUP BY a.id, a.reference, a.designation, a.seuil_alerte
             HAVING stock_total <= a.seuil_alerte
             ORDER BY stock_total ASC'
        );
        $stmt->execute([':etab' => $etablissementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function commandesParStatut(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT statut, COUNT(*) AS nb, COALESCE(SUM(montant_total), 0) AS montant
             FROM inv_commandes
             WHERE etablissement_id = :etab AND deleted_at IS NULL
             GROUP BY statut'
        );
        $stmt->execute([':etab' => $etablissementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function coutMaintenanceParArticle(int $etablissementId, string $dateDebut = '', string $dateFin = ''): array
    {
        $where = 'WHERE m.etablissement_id = :etab AND m.deleted_at IS NULL';
        $params = [':etab' => $etablissementId];
        if ($dateDebut) { $where .= ' AND m.created_at >= :dd'; $params[':dd'] = $dateDebut; }
        if ($dateFin)   { $where .= ' AND m.created_at <= :df'; $params[':df'] = $dateFin; }

        $stmt = $this->pdo->prepare(
            "SELECT a.designation, SUM(m.cout) AS cout_total, COUNT(m.id) AS nb_maintenances
             FROM inv_maintenances m
             INNER JOIN inv_articles a ON a.id = m.article_id
             $where
             GROUP BY a.id, a.designation
             ORDER BY cout_total DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function amortissementsActifs(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.designation, am.valeur_actuelle,
                    am.valeur_initiale, am.taux_annuel,
                    am.date_fin_amortissement
             FROM inv_amortissements am
             INNER JOIN inv_articles a ON a.id = am.article_id
             WHERE am.etablissement_id = :etab
             ORDER BY am.valeur_actuelle DESC'
        );
        $stmt->execute([':etab' => $etablissementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function counters(int $etablissementId): array
    {
        $nbA = $this->pdo->prepare(
            'SELECT COUNT(*) FROM inv_articles
             WHERE etablissement_id = :etab AND actif = 1 AND deleted_at IS NULL'
        );
        $nbA->execute([':etab' => $etablissementId]);

        $nbAlerte = $this->pdo->prepare(
            'SELECT a.id FROM inv_articles a
             LEFT JOIN inv_stocks s ON s.article_id = a.id
             WHERE a.etablissement_id = :etab AND a.actif = 1 AND a.deleted_at IS NULL
             GROUP BY a.id HAVING COALESCE(SUM(s.quantite_disponible),0) <= a.seuil_alerte'
        );
        $nbAlerte->execute([':etab' => $etablissementId]);

        return [
            'nb_articles'      => (int)$nbA->fetchColumn(),
            'nb_alertes_stock' => count($nbAlerte->fetchAll()),
        ];
    }
}
