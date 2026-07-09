<?php

namespace App\Modules\Academique\Models;

use Core\Model;

class PeriodeScolaireModel extends Model
{
    protected string $table = 'periodes_scolaires';

    public const TYPES = ['trimestre', 'semestre', 'custom'];

    public const TYPE_LABELS = [
        'trimestre' => 'Trimestre',
        'semestre'  => 'Semestre',
        'custom'    => 'Période personnalisée',
    ];

    public const STATUTS = ['ouverte', 'fermee', 'verrouillee', 'archivee'];

    public const STATUT_LABELS = [
        'ouverte'     => 'Ouverte',
        'fermee'      => 'Fermée',
        'verrouillee' => 'Verrouillée',
        'archivee'    => 'Archivée',
    ];

    public const STATUT_COLORS = [
        'ouverte'     => 'emerald',
        'fermee'      => 'amber',
        'verrouillee' => 'red',
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
