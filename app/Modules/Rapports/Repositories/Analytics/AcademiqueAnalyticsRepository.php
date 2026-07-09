<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Repositories\Analytics;

use Core\Database;

class AcademiqueAnalyticsRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function moyenneGeneraleParClasse(int $etablissementId, int $periodeId = 0): array
    {
        $sql = 'SELECT c.nom AS classe,
                       ROUND(AVG(n.note), 2) AS moyenne,
                       COUNT(DISTINCT n.eleve_id) AS nb_notes
                FROM notes_v2 n
                INNER JOIN classes c ON c.id = n.classe_id
                WHERE n.etablissement_id = :etab
                  AND n.deleted_at IS NULL'
             . ($periodeId ? ' AND n.periode_id = :pid' : '')
             . ' GROUP BY c.id, c.nom
                ORDER BY moyenne DESC';
        $params = [':etab' => $etablissementId];
        if ($periodeId) $params[':pid'] = $periodeId;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function distribMentions(int $etablissementId, int $periodeId = 0): array
    {
        $sql = 'SELECT
                    SUM(CASE WHEN n.note >= 16 THEN 1 ELSE 0 END) AS tres_bien,
                    SUM(CASE WHEN n.note >= 14 AND n.note < 16 THEN 1 ELSE 0 END) AS bien,
                    SUM(CASE WHEN n.note >= 12 AND n.note < 14 THEN 1 ELSE 0 END) AS assez_bien,
                    SUM(CASE WHEN n.note >= 10 AND n.note < 12 THEN 1 ELSE 0 END) AS passable,
                    SUM(CASE WHEN n.note < 10 THEN 1 ELSE 0 END) AS insuffisant
                FROM notes_v2 n
                WHERE n.etablissement_id = :etab
                  AND n.deleted_at IS NULL'
             . ($periodeId ? ' AND n.periode_id = :pid' : '');
        $params = [':etab' => $etablissementId];
        if ($periodeId) $params[':pid'] = $periodeId;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    public function tauxReussiteParMatiere(int $etablissementId, int $periodeId = 0): array
    {
        $sql = 'SELECT m.nom AS matiere,
                       ROUND(AVG(n.note), 2) AS moyenne,
                       ROUND(SUM(CASE WHEN n.note >= 10 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) AS taux_reussite
                FROM notes_v2 n
                INNER JOIN matieres m ON m.id = n.matiere_id
                WHERE n.etablissement_id = :etab
                  AND n.deleted_at IS NULL'
             . ($periodeId ? ' AND n.periode_id = :pid' : '')
             . ' GROUP BY m.id, m.nom
                ORDER BY taux_reussite DESC';
        $params = [':etab' => $etablissementId];
        if ($periodeId) $params[':pid'] = $periodeId;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function evolutionMoyennesParPeriode(int $etablissementId, ?int $classeId = null): array
    {
        $sql = 'SELECT ps.libelle AS periode,
                       ROUND(AVG(n.note), 2) AS moyenne
                FROM notes_v2 n
                INNER JOIN periodes_scolaires ps ON ps.id = n.periode_id
                WHERE n.etablissement_id = :etab
                  AND n.deleted_at IS NULL'
             . ($classeId ? ' AND n.classe_id = :cid' : '')
             . ' GROUP BY ps.id, ps.libelle, ps.date_debut
                ORDER BY ps.date_debut';
        $params = [':etab' => $etablissementId];
        if ($classeId) $params[':cid'] = $classeId;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function counters(int $etablissementId): array
    {
        $nb = $this->pdo->prepare(
            'SELECT COUNT(*) FROM notes_v2
             WHERE etablissement_id = :etab AND deleted_at IS NULL'
        );
        $nb->execute([':etab' => $etablissementId]);
        $moy = $this->pdo->prepare(
            'SELECT ROUND(AVG(note), 2) FROM notes_v2
             WHERE etablissement_id = :etab AND deleted_at IS NULL'
        );
        $moy->execute([':etab' => $etablissementId]);
        return [
            'nb_notes'        => (int)$nb->fetchColumn(),
            'moyenne_generale' => (float)$moy->fetchColumn(),
        ];
    }
}
