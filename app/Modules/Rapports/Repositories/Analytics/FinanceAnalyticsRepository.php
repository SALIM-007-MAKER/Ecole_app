<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Repositories\Analytics;

use Core\Database;

class FinanceAnalyticsRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function recettesParMois(int $etablissementId, int $nbMois = 12): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DATE_FORMAT(date_paiement, "%Y-%m") AS mois,
                    SUM(montant) AS total
             FROM finance_paiements
             WHERE etablissement_id = :etab
               AND deleted_at IS NULL
               AND date_paiement >= DATE_SUB(NOW(), INTERVAL :nb MONTH)
             GROUP BY mois
             ORDER BY mois'
        );
        $stmt->execute([':etab' => $etablissementId, ':nb' => $nbMois]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function facturesImpayees(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ff.id, ff.numero, ff.montant_ttc,
                    ff.montant_paye, ff.echeance,
                    CONCAT(e.prenom, " ", e.nom) AS eleve
             FROM finance_factures ff
             LEFT JOIN eleves e ON e.id = ff.eleve_id
             WHERE ff.etablissement_id = :etab
               AND ff.statut IN ("en_attente","partiel")
               AND ff.deleted_at IS NULL
             ORDER BY ff.echeance ASC'
        );
        $stmt->execute([':etab' => $etablissementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function tauxRecouvrement(int $etablissementId, string $dateDebut = '', string $dateFin = ''): float
    {
        $where = 'WHERE ff.etablissement_id = :etab AND ff.deleted_at IS NULL';
        $params = [':etab' => $etablissementId];
        if ($dateDebut) { $where .= ' AND ff.created_at >= :dd'; $params[':dd'] = $dateDebut; }
        if ($dateFin)   { $where .= ' AND ff.created_at <= :df'; $params[':df'] = $dateFin; }

        $stmt = $this->pdo->prepare(
            "SELECT SUM(montant_paye) AS paye, SUM(montant_ttc) AS total
             FROM finance_factures ff $where"
        );
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $total = (float)($row['total'] ?? 0);
        return $total > 0 ? round((float)($row['paye'] ?? 0) * 100 / $total, 2) : 0.0;
    }

    public function mouvementsCaisse(int $etablissementId, string $dateDebut = '', string $dateFin = ''): array
    {
        $where = 'WHERE etablissement_id = :etab AND deleted_at IS NULL';
        $params = [':etab' => $etablissementId];
        if ($dateDebut) { $where .= ' AND created_at >= :dd'; $params[':dd'] = $dateDebut; }
        if ($dateFin)   { $where .= ' AND created_at <= :df'; $params[':df'] = $dateFin; }

        $stmt = $this->pdo->prepare(
            "SELECT type_mouvement, SUM(montant) AS total, COUNT(*) AS nb
             FROM finance_mouvements_caisse $where
             GROUP BY type_mouvement"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function counters(int $etablissementId): array
    {
        $totalF = $this->pdo->prepare(
            'SELECT COALESCE(SUM(montant_ttc), 0) FROM finance_factures
             WHERE etablissement_id = :etab AND deleted_at IS NULL'
        );
        $totalF->execute([':etab' => $etablissementId]);

        $totalP = $this->pdo->prepare(
            'SELECT COALESCE(SUM(montant), 0) FROM finance_paiements
             WHERE etablissement_id = :etab AND deleted_at IS NULL'
        );
        $totalP->execute([':etab' => $etablissementId]);

        $nbImp = $this->pdo->prepare(
            'SELECT COUNT(*) FROM finance_factures
             WHERE etablissement_id = :etab AND statut IN ("en_attente","partiel") AND deleted_at IS NULL'
        );
        $nbImp->execute([':etab' => $etablissementId]);

        return [
            'total_facture' => (float)$totalF->fetchColumn(),
            'total_paye'    => (float)$totalP->fetchColumn(),
            'nb_impayes'    => (int)$nbImp->fetchColumn(),
        ];
    }
}
