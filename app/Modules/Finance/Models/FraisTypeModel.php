<?php

namespace App\Modules\Finance\Models;

use Core\Model;

class FraisTypeModel extends Model
{
    protected string $table      = 'finance_frais_types';
    protected string $primaryKey = 'id';

    public const STATUTS = ['actif', 'inactif', 'archive'];

    public function findActifs(): array
    {
        return $this->query(
            "SELECT fft.*, fcf.nom AS categorie_nom, fcf.couleur AS categorie_couleur
             FROM `finance_frais_types` fft
             LEFT JOIN `finance_categories_frais` fcf ON fcf.id = fft.categorie_id
             WHERE fft.statut = 'actif'
             ORDER BY fft.nom"
        );
    }

    public function findForSelect(?string $anneeScolaire = null): array
    {
        $params = [];
        $where  = "fft.statut = 'actif'";

        if ($anneeScolaire !== null) {
            $where   .= " AND (fft.annee_scolaire IS NULL OR fft.annee_scolaire = ?)";
            $params[] = $anneeScolaire;
        }

        return $this->query(
            "SELECT fft.id, fft.nom, fft.montant_defaut, fft.devise,
                    fft.periodicite, fft.est_obligatoire, fcf.nom AS categorie_nom
             FROM `finance_frais_types` fft
             LEFT JOIN `finance_categories_frais` fcf ON fcf.id = fft.categorie_id
             WHERE $where
             ORDER BY fft.nom",
            $params
        );
    }

    public function codeExists(string $code, int $excludeId = 0): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM `finance_frais_types` WHERE code = ? AND id != ?"
        );
        $stmt->execute([$code, $excludeId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /** Décode niveaux_cibles depuis JSON vers tableau PHP. */
    public function getNiveauxArray(object $fraisType): array
    {
        if (empty($fraisType->niveaux_cibles)) {
            return [];
        }
        $decoded = json_decode($fraisType->niveaux_cibles, true);
        return is_array($decoded) ? $decoded : [];
    }
}
