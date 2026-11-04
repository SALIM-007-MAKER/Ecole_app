<?php

namespace App\Modules\Academique\Models;

use Core\Model;
use Core\Tenant\TenantScope;

class EvaluationModel extends Model
{
    protected string $table = 'evaluations';

    public const STATUTS = ['brouillon', 'publiee', 'verrouillee', 'archivee'];

    public const STATUT_LABELS = [
        'brouillon'   => 'Brouillon',
        'publiee'     => 'Publiée',
        'verrouillee' => 'Verrouillée',
        'archivee'    => 'Archivée',
    ];

    public const STATUT_COLORS = [
        'brouillon'   => 'blue',
        'publiee'     => 'emerald',
        'verrouillee' => 'red',
        'archivee'    => 'slate',
    ];

    public const STATUT_ICONS = [
        'brouillon'   => 'file-edit',
        'publiee'     => 'check-circle',
        'verrouillee' => 'lock',
        'archivee'    => 'archive',
    ];

    /** Une évaluation appartient à l'établissement de sa classe (Lot 3). */
    protected function ownershipRestriction(): ?array
    {
        return TenantScope::restrictionClasse('classe_id');
    }

    public function findByClasseEtPeriode(int $classeId, int $periodeScolaireId): array
    {
        return $this->query(
            "SELECT ev.*,
                    te.nom AS type_nom, te.couleur AS type_couleur, te.icone AS type_icone,
                    m.nom AS matiere_nom
             FROM `evaluations` ev
             JOIN `types_evaluations` te ON te.id = ev.type_evaluation_id
             JOIN `matieres` m           ON m.id  = ev.matiere_id
             WHERE ev.classe_id = ? AND ev.periode_scolaire_id = ?
               AND ev.statut != 'archivee'" . TenantScope::classe('ev.classe_id')[0] . "

             ORDER BY ev.date_evaluation ASC, m.nom ASC, ev.libelle ASC",
            [$classeId, $periodeScolaireId, ...TenantScope::classe('ev.classe_id')[1]]
        );
    }

    public function findActifsByMatierePeriode(int $matiereId, int $classeId, int $periodeScolaireId): array
    {
        return $this->query(
            "SELECT ev.*, te.nom AS type_nom
             FROM `evaluations` ev
             JOIN `types_evaluations` te ON te.id = ev.type_evaluation_id
             WHERE ev.matiere_id = ? AND ev.classe_id = ? AND ev.periode_scolaire_id = ?
               AND ev.statut IN ('publiee','verrouillee')" . TenantScope::classe('ev.classe_id')[0] . "

             ORDER BY ev.date_evaluation ASC, ev.id ASC",
            [$matiereId, $classeId, $periodeScolaireId, ...TenantScope::classe('ev.classe_id')[1]]
        );
    }
}
