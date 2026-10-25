<?php

namespace App\Modules\Academique\Models;

use Core\Model;
use Core\Tenant\TenantScope;

class PeriodeScolaireModel extends Model
{
    protected string $table = 'periodes_scolaires';

    /** Types historiques/techniques (incl. 'trimestre', conservé uniquement comme point
     *  d'extension future — non proposé par défaut, voir TYPES_OFFICIELS). */
    public const TYPES = ['trimestre', 'semestre', 'custom'];

    public const TYPE_LABELS = [
        'trimestre' => 'Trimestre',
        'semestre'  => 'Semestre',
        'custom'    => 'Période personnalisée',
    ];

    /** Système officiel du Complexe Scolaire Privé La Persévérance : découpage
     *  semestriel (2 semestres/an). 'trimestre' est délibérément exclu de la
     *  sélection par défaut. */
    public const TYPES_OFFICIELS = ['semestre', 'custom'];

    public const TYPES_OFFICIELS_LABELS = [
        'semestre' => 'Semestre',
        'custom'   => 'Période personnalisée',
    ];

    /** Machine d'états : preparation → ouverte → cloturee → archivee
     *  (cloturee → ouverte : réouverture possible, admin).
     *  Le verrouillage (`verrouille_par`/`verrouille_le`) est indépendant du
     *  statut : il ne s'applique qu'à une période `cloturee` et bloque toute
     *  modification, y compris la réouverture/l'archivage, jusqu'à
     *  déverrouillage explicite par un administrateur. */
    public const STATUTS = ['preparation', 'ouverte', 'cloturee', 'archivee'];

    public const STATUT_LABELS = [
        'preparation' => 'Préparation',
        'ouverte'     => 'Ouverte',
        'cloturee'    => 'Clôturée',
        'archivee'    => 'Archivée',
    ];

    public const STATUT_COLORS = [
        'preparation' => 'sky',
        'ouverte'     => 'emerald',
        'cloturee'    => 'amber',
        'archivee'    => 'slate',
    ];

    /** Propre à l'établissement (colonne etablissement_id, T051) ; partagé tant que la migration n'est pas appliquée. */
    protected function ownershipRestriction(): ?array
    {
        return TenantScope::restrictionReferentiel('periodes_scolaires');
    }

    public function insert(array $data): int
    {
        if (!array_key_exists('etablissement_id', $data) && ($e = TenantScope::valeurInsertion('periodes_scolaires')) !== null) {
            $data['etablissement_id'] = $e;
        }
        return parent::insert($data);
    }

    public function findActive(?string $annee = null): ?\stdClass
    {
        [$ts, $tp] = TenantScope::referentiel('periodes_scolaires', 'etablissement_id');
        if ($annee !== null) {
            return $this->queryOne(
                'SELECT * FROM periodes_scolaires WHERE is_active = 1 AND annee_scolaire = ?' . $ts . ' LIMIT 1',
                [$annee, ...$tp]
            ) ?: null;
        }
        return $this->queryOne(
            'SELECT * FROM periodes_scolaires WHERE is_active = 1' . $ts . ' ORDER BY annee_scolaire DESC LIMIT 1',
            $tp
        ) ?: null;
    }

    public function findByAnnee(string $annee): array
    {
        [$ts, $tp] = TenantScope::referentiel('periodes_scolaires', 'etablissement_id');
        return $this->query(
            'SELECT * FROM periodes_scolaires WHERE annee_scolaire = ?' . $ts . ' ORDER BY ordre ASC, numero ASC',
            [$annee, ...$tp]
        );
    }

    public function deactiverTous(string $annee): void
    {
        [$ts, $tp] = TenantScope::referentiel('periodes_scolaires', 'etablissement_id');
        $this->execute(
            'UPDATE periodes_scolaires SET is_active = 0 WHERE annee_scolaire = ?' . $ts,
            [$annee, ...$tp]
        );
    }

    public function getAnneesScolaires(): array
    {
        [$ts, $tp] = TenantScope::referentiel('periodes_scolaires', 'etablissement_id', 'WHERE');
        $rows = $this->query(
            'SELECT DISTINCT annee_scolaire FROM periodes_scolaires' . $ts . ' ORDER BY annee_scolaire DESC',
            $tp
        );
        return array_column($rows, 'annee_scolaire');
    }

    public function findForSelect(?string $annee = null): array
    {
        if ($annee !== null) {
            return $this->query(
                "SELECT id,
                        CONCAT(nom, IF(is_active, ' ★', '')) AS label,
                        statut, is_active
                 FROM periodes_scolaires
                 WHERE annee_scolaire = ? AND statut != 'archivee'" . TenantScope::referentiel('periodes_scolaires', 'etablissement_id')[0] . "
                 ORDER BY ordre ASC, numero ASC",
                [$annee, ...TenantScope::referentiel('periodes_scolaires', 'etablissement_id')[1]]
            );
        }
        return $this->query(
            "SELECT id,
                    CONCAT('[', annee_scolaire, '] ', nom, IF(is_active, ' ★', '')) AS label,
                    statut, is_active
             FROM periodes_scolaires
             WHERE statut != 'archivee'" . TenantScope::referentiel('periodes_scolaires', 'etablissement_id')[0] . "
             ORDER BY annee_scolaire DESC, ordre ASC, numero ASC",
            TenantScope::referentiel('periodes_scolaires', 'etablissement_id')[1]
        );
    }
}
