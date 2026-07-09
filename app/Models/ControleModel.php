<?php

namespace App\Models;

use Core\Model;

class ControleModel extends Model
{
    protected string $table = 'controles';

    public const TYPES = [
        'controle' => 'Contrôle',
        'devoir'   => 'Devoir surveillé',
        'examen'   => 'Examen',
        'tp'       => 'Travaux Pratiques',
        'oral'     => 'Oral',
    ];

    public function findByContextFiltered(int $classeId, int $matiereId, int $periodeId): array
    {
        $where  = 'c.classe_id = ? AND c.periode_id = ?';
        $params = [$classeId, $periodeId];
        if ($matiereId > 0) {
            $where   .= ' AND c.matiere_id = ?';
            $params[] = $matiereId;
        }

        return $this->query(
            "SELECT c.*,
                    m.nom AS matiere_nom, m.coefficient AS matiere_coef,
                    cl.nom AS classe_nom, cl.niveau AS classe_niveau,
                    p.nom AS periode_nom,
                    COUNT(DISTINCT n.id) AS nb_notes,
                    AVG(CASE WHEN n.absent = 0 THEN n.note END) AS note_moy
             FROM controles c
             JOIN matieres m  ON m.id  = c.matiere_id
             JOIN classes cl  ON cl.id = c.classe_id
             JOIN periodes p  ON p.id  = c.periode_id
             LEFT JOIN notes n ON n.controle_id = c.id
             WHERE {$where}
             GROUP BY c.id
             ORDER BY m.nom, c.date_controle, c.id",
            $params
        );
    }

    public function findWithDetails(int $id): ?\stdClass
    {
        return $this->queryOne(
            'SELECT c.*,
                    m.nom AS matiere_nom, m.coefficient AS matiere_coef,
                    cl.nom AS classe_nom, cl.niveau AS classe_niveau,
                    p.nom AS periode_nom, p.annee_scolaire,
                    COUNT(DISTINCT n.id) AS nb_notes,
                    AVG(CASE WHEN n.absent = 0 THEN n.note END) AS note_moy,
                    MIN(CASE WHEN n.absent = 0 THEN n.note END) AS note_min,
                    MAX(CASE WHEN n.absent = 0 THEN n.note END) AS note_max_val,
                    SUM(n.absent) AS nb_absents
             FROM controles c
             JOIN matieres m  ON m.id  = c.matiere_id
             JOIN classes cl  ON cl.id = c.classe_id
             JOIN periodes p  ON p.id  = c.periode_id
             LEFT JOIN notes n ON n.controle_id = c.id
             WHERE c.id = ?
             GROUP BY c.id',
            [$id]
        ) ?: null;
    }

    public function findByMatiereClassePeriode(int $matiereId, int $classeId, int $periodeId): array
    {
        return $this->query(
            'SELECT * FROM controles
             WHERE matiere_id = ? AND classe_id = ? AND periode_id = ?
             ORDER BY date_controle, id',
            [$matiereId, $classeId, $periodeId]
        );
    }

    public function countNotes(int $controleId): int
    {
        return $this->count('controle_id = ?', [$controleId]);
    }
}
