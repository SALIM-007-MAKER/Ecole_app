<?php

namespace App\Models;

use Core\Model;

class PeriodeModel extends Model
{
    protected string $table = 'periodes';
    protected bool $tenantScoped = true;

    public function findForSelect(?string $annee = null): array
    {
        $sql    = 'SELECT id, CONCAT(nom, " — ", annee_scolaire) AS label FROM periodes WHERE etablissement_id = ?';
        $params = [$this->tenantId()];
        if ($annee) {
            $sql    .= ' AND annee_scolaire = ?';
            $params[] = $annee;
        }
        $sql .= ' ORDER BY annee_scolaire DESC, id ASC';
        return $this->query($sql, $params);
    }

    public function findActive(): ?\stdClass
    {
        return $this->queryOne(
            'SELECT * FROM periodes WHERE actif = 1 AND etablissement_id = ? ORDER BY id DESC LIMIT 1',
            [$this->tenantId()]
        ) ?: null;
    }

    public function findByAnnee(string $annee): array
    {
        return $this->query(
            'SELECT * FROM periodes WHERE annee_scolaire = ? AND etablissement_id = ? ORDER BY id',
            [$annee, $this->tenantId()]
        );
    }

    public function getAnnees(): array
    {
        $rows = $this->query(
            'SELECT DISTINCT annee_scolaire FROM periodes WHERE etablissement_id = ? ORDER BY annee_scolaire DESC',
            [$this->tenantId()]
        );
        return array_column($rows, 'annee_scolaire');
    }

    public function findWithStats(): array
    {
        return $this->query(
            'SELECT p.*,
                    COUNT(DISTINCT c.id)  AS nb_controles,
                    COUNT(DISTINCT n.id)  AS nb_notes,
                    COUNT(DISTINCT mg.id) AS nb_bulletins
             FROM periodes p
             LEFT JOIN controles c ON c.periode_id = p.id
             LEFT JOIN notes n     ON n.controle_id = c.id
             LEFT JOIN moyennes_generales mg ON mg.periode_id = p.id
             WHERE p.etablissement_id = ?
             GROUP BY p.id
             ORDER BY p.annee_scolaire DESC, p.id ASC',
            [$this->tenantId()]
        );
    }
}
