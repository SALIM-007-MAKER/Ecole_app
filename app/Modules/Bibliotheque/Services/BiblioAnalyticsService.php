<?php

declare(strict_types=1);

namespace App\Modules\Bibliotheque\Services;

use Core\Database;

class BiblioAnalyticsService
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function dashboard(int $etablissementId): array
    {
        return [
            'total_ouvrages'         => $this->totalOuvrages($etablissementId),
            'total_exemplaires'      => $this->totalExemplaires($etablissementId),
            'emprunts_en_cours'      => $this->empruntsEnCours($etablissementId),
            'emprunts_en_retard'     => $this->empruntsEnRetard($etablissementId),
            'reservations_actives'   => $this->reservationsActives($etablissementId),
            'penalites_impayees'     => $this->penalitesImpayees($etablissementId),
            'taux_retard'            => $this->tauxRetard($etablissementId),
            'emprunts_par_mois'      => $this->empruntsParMois($etablissementId),
            'ouvrages_populaires'    => $this->ouvragesLesPlusEmpruntes($etablissementId, 5),
            'exemplaires_par_statut' => $this->exemplairesParStatut($etablissementId),
        ];
    }

    public function ouvragesLesPlusEmpruntes(int $etablissementId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT o.id, o.titre, o.isbn, COUNT(e.id) AS nb_emprunts
            FROM biblio_emprunts e
            JOIN biblio_exemplaires ex ON e.exemplaire_id = ex.id
            JOIN biblio_ouvrages o ON ex.ouvrage_id = o.id
            WHERE e.etablissement_id = ?
            GROUP BY o.id, o.titre, o.isbn
            ORDER BY nb_emprunts DESC
            LIMIT ?
        ");
        $stmt->execute([$etablissementId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function tauxRotation(int $etablissementId): float
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT exemplaire_id) / NULLIF(COUNT(DISTINCT ex.id), 0) AS taux
            FROM biblio_exemplaires ex
            LEFT JOIN biblio_emprunts e ON e.exemplaire_id = ex.id AND e.etablissement_id = ?
            WHERE ex.etablissement_id = ? AND ex.deleted_at IS NULL
        ");
        $stmt->execute([$etablissementId, $etablissementId]);
        return (float)($stmt->fetchColumn() ?? 0.0);
    }

    public function tauxRetard(int $etablissementId): float
    {
        $stmt = $this->pdo->prepare("
            SELECT
                SUM(CASE WHEN statut = 'en_retard' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0) AS taux
            FROM biblio_emprunts
            WHERE etablissement_id = ? AND statut IN ('en_cours', 'en_retard')
        ");
        $stmt->execute([$etablissementId]);
        return round((float)($stmt->fetchColumn() ?? 0.0) * 100, 2);
    }

    public function empruntsParMois(int $etablissementId, int $mois = 12): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DATE_FORMAT(date_emprunt, '%Y-%m') AS mois, COUNT(*) AS nb
            FROM biblio_emprunts
            WHERE etablissement_id = ?
              AND date_emprunt >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            GROUP BY mois
            ORDER BY mois ASC
        ");
        $stmt->execute([$etablissementId, $mois]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function empruntsParCategorie(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.nom AS categorie, COUNT(e.id) AS nb_emprunts
            FROM biblio_emprunts e
            JOIN biblio_exemplaires ex ON e.exemplaire_id = ex.id
            JOIN biblio_ouvrage_categories oc ON oc.ouvrage_id = ex.ouvrage_id
            JOIN biblio_categories c ON c.id = oc.categorie_id
            WHERE e.etablissement_id = ?
            GROUP BY c.id, c.nom
            ORDER BY nb_emprunts DESC
        ");
        $stmt->execute([$etablissementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function emprunteursActifs(int $etablissementId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.prenom, u.nom, COUNT(e.id) AS nb_emprunts
            FROM biblio_emprunts e
            JOIN users u ON e.user_id = u.id
            WHERE e.etablissement_id = ?
            GROUP BY u.id, u.prenom, u.nom
            ORDER BY nb_emprunts DESC
            LIMIT ?
        ");
        $stmt->execute([$etablissementId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function delaiMoyenRetour(int $etablissementId): float
    {
        $stmt = $this->pdo->prepare("
            SELECT AVG(DATEDIFF(date_retour_effectif, date_emprunt)) AS delai_moyen
            FROM biblio_emprunts
            WHERE etablissement_id = ? AND date_retour_effectif IS NOT NULL
        ");
        $stmt->execute([$etablissementId]);
        return round((float)($stmt->fetchColumn() ?? 0.0), 1);
    }

    public function amendesCollectees(int $etablissementId): float
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(montant), 0)
            FROM biblio_penalites
            WHERE etablissement_id = ? AND statut = 'payee'
        ");
        $stmt->execute([$etablissementId]);
        return (float)$stmt->fetchColumn();
    }

    public function exemplairesParStatut(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT statut, COUNT(*) AS nb
            FROM biblio_exemplaires
            WHERE etablissement_id = ? AND deleted_at IS NULL
            GROUP BY statut
        ");
        $stmt->execute([$etablissementId]);
        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public function export(string $format, int $etablissementId): string
    {
        $data = $this->dashboard($etablissementId);
        if ($format === 'json') {
            return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        $lines = ["Rapport Bibliothèque - " . date('Y-m-d')];
        foreach ($data as $key => $value) {
            $lines[] = $key . ': ' . (is_array($value) ? json_encode($value) : $value);
        }
        return implode("\n", $lines);
    }

    private function totalOuvrages(int $etablissementId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM biblio_ouvrages WHERE etablissement_id = ? AND deleted_at IS NULL");
        $stmt->execute([$etablissementId]);
        return (int)$stmt->fetchColumn();
    }

    private function totalExemplaires(int $etablissementId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM biblio_exemplaires WHERE etablissement_id = ? AND deleted_at IS NULL");
        $stmt->execute([$etablissementId]);
        return (int)$stmt->fetchColumn();
    }

    private function empruntsEnCours(int $etablissementId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM biblio_emprunts WHERE etablissement_id = ? AND statut IN ('en_cours','en_retard')");
        $stmt->execute([$etablissementId]);
        return (int)$stmt->fetchColumn();
    }

    private function empruntsEnRetard(int $etablissementId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM biblio_emprunts WHERE etablissement_id = ? AND statut = 'en_retard'");
        $stmt->execute([$etablissementId]);
        return (int)$stmt->fetchColumn();
    }

    private function reservationsActives(int $etablissementId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM biblio_reservations WHERE etablissement_id = ? AND statut IN ('en_attente','disponible')");
        $stmt->execute([$etablissementId]);
        return (int)$stmt->fetchColumn();
    }

    private function penalitesImpayees(int $etablissementId): float
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM biblio_penalites WHERE etablissement_id = ? AND statut = 'en_attente'");
        $stmt->execute([$etablissementId]);
        return (float)$stmt->fetchColumn();
    }
}
