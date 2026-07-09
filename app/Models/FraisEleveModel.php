<?php

namespace App\Models;

use Core\Model;

class FraisEleveModel extends Model
{
    protected string $table = 'frais_eleves';

    private function baseSelect(): string
    {
        return "SELECT fe.*,
                ft.nom          AS frais_nom,
                ft.periodicite,
                CONCAT(e.prenom,' ',e.nom) AS eleve_nom,
                e.matricule,
                COALESCE(c.nom,'')    AS classe_nom,
                COALESCE(c.niveau,'') AS classe_niveau,
                COALESCE(SUM(p.montant),0) AS montant_paye,
                fe.montant - COALESCE(SUM(p.montant),0) AS reste
                FROM `frais_eleves` fe
                JOIN `frais_types` ft ON ft.id = fe.frais_type_id
                JOIN `eleves` e       ON e.id  = fe.eleve_id
                LEFT JOIN `classes` c ON c.id  = e.classe_id
                LEFT JOIN `paiements` p ON p.frais_eleve_id = fe.id";
    }

    public function findImpayes(array $filters = []): array
    {
        $cond   = ["fe.statut != 'paye'"];
        $params = [];

        if (!empty($filters['annee'])) {
            $cond[]   = "fe.annee_scolaire = ?";
            $params[] = $filters['annee'];
        }
        if (!empty($filters['classe_id'])) {
            $cond[]   = "e.classe_id = ?";
            $params[] = (int)$filters['classe_id'];
        }
        if (!empty($filters['frais_type_id'])) {
            $cond[]   = "fe.frais_type_id = ?";
            $params[] = (int)$filters['frais_type_id'];
        }

        $where = 'WHERE ' . implode(' AND ', $cond);

        return $this->query(
            $this->baseSelect() . "
            {$where}
            GROUP BY fe.id
            HAVING reste > 0
            ORDER BY reste DESC, e.nom",
            $params
        );
    }

    public function findForEleve(int $eleveId, string $annee): array
    {
        return $this->query(
            $this->baseSelect() . "
            WHERE fe.eleve_id = ? AND fe.annee_scolaire = ?
            GROUP BY fe.id
            ORDER BY ft.nom",
            [$eleveId, $annee]
        );
    }

    public function findWithDetails(int $id): ?\stdClass
    {
        return $this->queryOne(
            $this->baseSelect() . " WHERE fe.id = ? GROUP BY fe.id",
            [$id]
        ) ?: null;
    }

    public function getStatsRecouvrement(string $annee): array
    {
        $row = $this->queryOne(
            "SELECT
                COALESCE(SUM(fe.montant), 0)    AS total_attendu,
                COUNT(*)                         AS nb_total,
                SUM(fe.statut = 'paye')          AS nb_payes,
                SUM(fe.statut = 'partiel')       AS nb_partiels,
                SUM(fe.statut = 'en_attente')    AS nb_impayer,
                COALESCE((SELECT SUM(montant) FROM paiements WHERE annee_scolaire = fe.annee_scolaire), 0) AS total_encaisse
             FROM frais_eleves fe
             WHERE fe.annee_scolaire = ?",
            [$annee]
        );
        return $row ? (array)$row : [];
    }

    public function affecterClasse(int $classeId, int $fraisTypeId, float $montant, ?string $echeance, string $annee): int
    {
        $eleves = $this->query(
            "SELECT id FROM eleves WHERE classe_id = ? AND actif = 1",
            [$classeId]
        );
        $count = 0;
        foreach ($eleves as $el) {
            $this->execute(
                "INSERT INTO frais_eleves (eleve_id, frais_type_id, annee_scolaire, montant, echeance)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE montant=VALUES(montant), echeance=VALUES(echeance), updated_at=NOW()",
                [$el->id, $fraisTypeId, $annee, $montant, $echeance]
            );
            $count++;
        }
        return $count;
    }

    public function affecterTous(int $fraisTypeId, float $montant, ?string $echeance, string $annee): int
    {
        $eleves = $this->query("SELECT id FROM eleves WHERE actif = 1");
        $count  = 0;
        foreach ($eleves as $el) {
            $this->execute(
                "INSERT INTO frais_eleves (eleve_id, frais_type_id, annee_scolaire, montant, echeance)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE montant=VALUES(montant), echeance=VALUES(echeance), updated_at=NOW()",
                [$el->id, $fraisTypeId, $annee, $montant, $echeance]
            );
            $count++;
        }
        return $count;
    }

    public function recalculerStatut(int $id): void
    {
        $this->execute(
            "UPDATE frais_eleves fe SET statut = CASE
                WHEN COALESCE((SELECT SUM(montant) FROM paiements p WHERE p.frais_eleve_id = fe.id),0) >= fe.montant THEN 'paye'
                WHEN COALESCE((SELECT SUM(montant) FROM paiements p WHERE p.frais_eleve_id = fe.id),0)  > 0          THEN 'partiel'
                ELSE 'en_attente'
             END, updated_at = NOW()
             WHERE id = ?",
            [$id]
        );
    }
}
