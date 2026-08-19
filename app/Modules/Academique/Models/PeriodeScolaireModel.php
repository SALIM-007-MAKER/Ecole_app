<?php

namespace App\Modules\Academique\Models;

use Core\Model;

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

    public function findActive(?string $annee = null): ?\stdClass
    {
        if ($annee !== null) {
            return $this->queryOne(
                'SELECT * FROM periodes_scolaires WHERE is_active = 1 AND annee_scolaire = ? LIMIT 1',
                [$annee]
            ) ?: null;
        }
        return $this->queryOne(
            'SELECT * FROM periodes_scolaires WHERE is_active = 1 ORDER BY annee_scolaire DESC LIMIT 1'
        ) ?: null;
    }

    public function findByAnnee(string $annee): array
    {
        return $this->query(
            'SELECT * FROM periodes_scolaires WHERE annee_scolaire = ? ORDER BY ordre ASC, numero ASC',
            [$annee]
        );
    }

    public function deactiverTous(string $annee): void
    {
        $this->execute(
            'UPDATE periodes_scolaires SET is_active = 0 WHERE annee_scolaire = ?',
            [$annee]
        );
    }

    public function getAnneesScolaires(): array
    {
        $rows = $this->query(
            'SELECT DISTINCT annee_scolaire FROM periodes_scolaires ORDER BY annee_scolaire DESC'
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
                 WHERE annee_scolaire = ? AND statut != 'archivee'
                 ORDER BY ordre ASC, numero ASC",
                [$annee]
            );
        }
        return $this->query(
            "SELECT id,
                    CONCAT('[', annee_scolaire, '] ', nom, IF(is_active, ' ★', '')) AS label,
                    statut, is_active
             FROM periodes_scolaires
             WHERE statut != 'archivee'
             ORDER BY annee_scolaire DESC, ordre ASC, numero ASC"
        );
    }
}
