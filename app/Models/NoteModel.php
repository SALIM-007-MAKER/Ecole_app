<?php

namespace App\Models;

use Core\Model;

class NoteModel extends Model
{
    protected string $table = 'notes';
    protected bool $tenantScoped = true;

    // ── Mentions ────────────────────────────────────────────────────────────────
    public const MENTIONS = [
        ['seuil' => 16, 'label' => 'Très Bien',  'class' => 'success'],
        ['seuil' => 14, 'label' => 'Bien',        'class' => 'primary'],
        ['seuil' => 12, 'label' => 'Assez Bien',  'class' => 'info'],
        ['seuil' => 10, 'label' => 'Passable',    'class' => 'warning'],
        ['seuil' =>  0, 'label' => 'Insuffisant', 'class' => 'danger'],
    ];

    public function getMention(float $moyenne): string
    {
        foreach (self::MENTIONS as $m) {
            if ($moyenne >= $m['seuil']) return $m['label'];
        }
        return 'Insuffisant';
    }

    public function getMentionClass(string $mention): string
    {
        return match ($mention) {
            'Très Bien'  => 'success',
            'Bien'       => 'primary',
            'Assez Bien' => 'info',
            'Passable'   => 'warning',
            default      => 'danger',
        };
    }

    // ── Lecture ─────────────────────────────────────────────────────────────────

    public function findByControle(int $controleId): array
    {
        return $this->query(
            'SELECT e.id AS eleve_id, e.nom, e.prenom, e.matricule,
                    n.id AS note_id, n.note, n.absent, n.appreciation
             FROM eleves e
             JOIN controles c ON c.id = ? AND c.classe_id = e.classe_id
             LEFT JOIN notes n ON n.eleve_id = e.id AND n.controle_id = c.id
             WHERE e.actif = 1 AND e.etablissement_id = ?
             ORDER BY e.nom, e.prenom',
            [$controleId, $this->tenantId()]
        );
    }

    public function getMatieresPourClasse(int $classeId, int $periodeId): array
    {
        return $this->query(
            'SELECT DISTINCT m.id, m.nom, m.coefficient, m.volume_horaire
             FROM controles c
             JOIN matieres m ON m.id = c.matiere_id
             WHERE c.classe_id = ? AND c.periode_id = ? AND m.etablissement_id = ?
             ORDER BY m.nom',
            [$classeId, $periodeId, $this->tenantId()]
        );
    }

    public function getNotesForClasse(int $classeId, int $periodeId): array
    {
        return $this->query(
            'SELECT e.id AS eleve_id, e.nom, e.prenom,
                    m.id AS matiere_id, m.nom AS matiere_nom, m.coefficient,
                    mm.moyenne,
                    mg.moyenne_generale, mg.rang, mg.mention
             FROM eleves e
             JOIN (
                 SELECT DISTINCT matiere_id
                 FROM controles
                 WHERE classe_id = ? AND periode_id = ?
             ) c_mat ON 1=1
             JOIN matieres m ON m.id = c_mat.matiere_id
             LEFT JOIN moyennes_matieres mm
                 ON mm.eleve_id = e.id AND mm.matiere_id = m.id
                 AND mm.classe_id = ? AND mm.periode_id = ?
             LEFT JOIN moyennes_generales mg
                 ON mg.eleve_id = e.id AND mg.classe_id = ? AND mg.periode_id = ?
             WHERE e.classe_id = ? AND e.actif = 1 AND e.etablissement_id = ?
             ORDER BY e.nom, e.prenom, m.nom',
            [$classeId, $periodeId, $classeId, $periodeId, $classeId, $periodeId, $classeId, $this->tenantId()]
        );
    }

    public function getClassementClasse(int $classeId, int $periodeId): array
    {
        return $this->query(
            'SELECT e.id, e.nom, e.prenom, e.matricule, e.sexe, e.photo,
                    mg.moyenne_generale, mg.rang, mg.mention
             FROM moyennes_generales mg
             JOIN eleves e ON e.id = mg.eleve_id
             WHERE mg.classe_id = ? AND mg.periode_id = ? AND e.etablissement_id = ?
             ORDER BY mg.rang ASC, e.nom ASC',
            [$classeId, $periodeId, $this->tenantId()]
        );
    }

    public function getBulletinData(int $eleveId, int $classeId, int $periodeId): array
    {
        $matieres = $this->query(
            'SELECT DISTINCT m.id, m.nom, m.coefficient, m.volume_horaire,
                    mm.moyenne AS moyenne_matiere
             FROM controles c
             JOIN matieres m ON m.id = c.matiere_id
             LEFT JOIN moyennes_matieres mm
                 ON mm.matiere_id = m.id AND mm.eleve_id = ?
                 AND mm.classe_id = ? AND mm.periode_id = ?
             WHERE c.classe_id = ? AND c.periode_id = ?
             ORDER BY m.nom',
            [$eleveId, $classeId, $periodeId, $classeId, $periodeId]
        );

        foreach ($matieres as &$mat) {
            $mat->controles = $this->query(
                'SELECT c.id, c.libelle, c.type, c.coefficient AS ctrl_coef,
                        c.note_max, c.date_controle,
                        n.note, n.absent
                 FROM controles c
                 LEFT JOIN notes n ON n.controle_id = c.id AND n.eleve_id = ?
                 WHERE c.matiere_id = ? AND c.classe_id = ? AND c.periode_id = ?
                 ORDER BY c.date_controle, c.id',
                [$eleveId, (int)$mat->id, $classeId, $periodeId]
            );
        }
        unset($mat);

        $mg = $this->queryOne(
            'SELECT mg.moyenne_generale, mg.rang, mg.mention,
                    (SELECT COUNT(*) FROM moyennes_generales
                     WHERE classe_id = mg.classe_id AND periode_id = mg.periode_id) AS total_eleves,
                    (SELECT MAX(moyenne_generale) FROM moyennes_generales
                     WHERE classe_id = mg.classe_id AND periode_id = mg.periode_id) AS meilleure,
                    (SELECT MIN(moyenne_generale) FROM moyennes_generales
                     WHERE classe_id = mg.classe_id AND periode_id = mg.periode_id) AS moins_bonne
             FROM moyennes_generales mg
             WHERE mg.eleve_id = ? AND mg.classe_id = ? AND mg.periode_id = ?',
            [$eleveId, $classeId, $periodeId]
        ) ?: null;

        return [
            'matieres'         => $matieres,
            'moyenne_generale' => $mg?->moyenne_generale,
            'rang'             => $mg?->rang,
            'mention'          => $mg?->mention,
            'total_eleves'     => (int)($mg?->total_eleves ?? 0),
            'meilleure_moy'    => $mg?->meilleure,
            'moins_bonne_moy'  => $mg?->moins_bonne,
        ];
    }

    public function globalStats(): array
    {
        $nb_notes = $this->count();
        $nb_ctrl  = (int)($this->queryOne('SELECT COUNT(*) AS n FROM controles')?->n ?? 0);
        $nb_bull  = (int)($this->queryOne('SELECT COUNT(*) AS n FROM moyennes_generales')?->n ?? 0);
        $moy      = $this->queryOne('SELECT ROUND(AVG(moyenne_generale),2) AS m FROM moyennes_generales')?->m;
        return [
            'nb_notes'    => $nb_notes,
            'nb_controles'=> $nb_ctrl,
            'nb_bulletins'=> $nb_bull,
            'moy_generale'=> $moy,
        ];
    }

    // ── Écriture ────────────────────────────────────────────────────────────────

    public function upsert(int $eleveId, int $controleId, ?float $note, int $absent = 0): void
    {
        $this->execute(
            'INSERT INTO notes (eleve_id, controle_id, note, absent, etablissement_id)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE note = VALUES(note), absent = VALUES(absent), updated_at = NOW()',
            [$eleveId, $controleId, $note, $absent, $this->tenantId()]
        );
    }

    // ── Calcul des moyennes ─────────────────────────────────────────────────────

    public function calculerMoyenneMatiere(int $eleveId, int $matiereId, int $classeId, int $periodeId): ?float
    {
        $row = $this->queryOne(
            'SELECT
                SUM(CASE WHEN n.absent = 1 THEN 0 ELSE n.note END * c.coefficient) AS somme,
                SUM(c.coefficient) AS coef_total
             FROM controles c
             INNER JOIN notes n
                 ON n.controle_id = c.id AND n.eleve_id = ?
                 AND (n.absent = 1 OR n.note IS NOT NULL)
                 AND n.etablissement_id = ?
             WHERE c.matiere_id = ? AND c.classe_id = ? AND c.periode_id = ?',
            [$eleveId, $this->tenantId(), $matiereId, $classeId, $periodeId]
        );

        if (!$row || !$row->coef_total) return null;
        return round((float)$row->somme / (float)$row->coef_total, 2);
    }

    public function calculerMoyenneGenerale(int $eleveId, int $classeId, int $periodeId): ?float
    {
        $row = $this->queryOne(
            'SELECT
                SUM(mm.moyenne * m.coefficient) AS somme,
                SUM(m.coefficient)              AS coef_total
             FROM moyennes_matieres mm
             JOIN matieres m ON m.id = mm.matiere_id AND m.etablissement_id = ?
             WHERE mm.eleve_id = ? AND mm.classe_id = ? AND mm.periode_id = ?
               AND mm.moyenne IS NOT NULL',
            [$this->tenantId(), $eleveId, $classeId, $periodeId]
        );

        if (!$row || !$row->coef_total) return null;
        return round((float)$row->somme / (float)$row->coef_total, 2);
    }

    public function recalculerDepuisControle(int $controleId): void
    {
        $ctrl = $this->queryOne('SELECT * FROM controles WHERE id = ?', [$controleId]);
        if (!$ctrl) return;

        $eleves = $this->query(
            'SELECT id FROM eleves WHERE classe_id = ? AND actif = 1 AND etablissement_id = ?',
            [(int)$ctrl->classe_id, $this->tenantId()]
        );

        $this->db->beginTransaction();
        try {
            foreach ($eleves as $el) {
                $this->sauvegarderMoyenneMatiere(
                    (int)$el->id, (int)$ctrl->matiere_id, (int)$ctrl->classe_id, (int)$ctrl->periode_id
                );
                $this->sauvegarderMoyenneGenerale((int)$el->id, (int)$ctrl->classe_id, (int)$ctrl->periode_id);
            }
            $this->recalculerClassement((int)$ctrl->classe_id, (int)$ctrl->periode_id);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function recalculerClasse(int $classeId, int $periodeId): void
    {
        $eleves   = $this->query('SELECT id FROM eleves WHERE classe_id = ? AND actif = 1 AND etablissement_id = ?', [$classeId, $this->tenantId()]);
        $matieres = $this->query(
            'SELECT DISTINCT matiere_id FROM controles WHERE classe_id = ? AND periode_id = ?',
            [$classeId, $periodeId]
        );

        $this->db->beginTransaction();
        try {
            foreach ($eleves as $el) {
                foreach ($matieres as $mat) {
                    $this->sauvegarderMoyenneMatiere(
                        (int)$el->id, (int)$mat->matiere_id, $classeId, $periodeId
                    );
                }
                $this->sauvegarderMoyenneGenerale((int)$el->id, $classeId, $periodeId);
            }
            $this->recalculerClassement($classeId, $periodeId);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function sauvegarderMoyenneMatiere(int $eleveId, int $matiereId, int $classeId, int $periodeId): void
    {
        $moy = $this->calculerMoyenneMatiere($eleveId, $matiereId, $classeId, $periodeId);
        $this->execute(
            'INSERT INTO moyennes_matieres (eleve_id, matiere_id, classe_id, periode_id, moyenne)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE moyenne = VALUES(moyenne), updated_at = NOW()',
            [$eleveId, $matiereId, $classeId, $periodeId, $moy]
        );
    }

    private function sauvegarderMoyenneGenerale(int $eleveId, int $classeId, int $periodeId): void
    {
        $moy = $this->calculerMoyenneGenerale($eleveId, $classeId, $periodeId);
        if ($moy === null) return;

        $mention = $this->getMention($moy);
        $this->execute(
            'INSERT INTO moyennes_generales (eleve_id, classe_id, periode_id, moyenne_generale, mention)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE moyenne_generale = VALUES(moyenne_generale),
                                     mention = VALUES(mention), updated_at = NOW()',
            [$eleveId, $classeId, $periodeId, $moy, $mention]
        );
    }

    public function recalculerClassement(int $classeId, int $periodeId): void
    {
        $rows = $this->query(
            'SELECT eleve_id, moyenne_generale
             FROM moyennes_generales
             WHERE classe_id = ? AND periode_id = ? AND moyenne_generale IS NOT NULL
             ORDER BY moyenne_generale DESC, eleve_id ASC',
            [$classeId, $periodeId]
        );

        $rang  = 0;
        $count = 0;
        $prev  = null;
        foreach ($rows as $row) {
            $count++;
            if ((float)$row->moyenne_generale !== $prev) {
                $rang = $count;
            }
            $this->execute(
                'UPDATE moyennes_generales SET rang = ?
                 WHERE eleve_id = ? AND classe_id = ? AND periode_id = ?',
                [$rang, (int)$row->eleve_id, $classeId, $periodeId]
            );
            $prev = (float)$row->moyenne_generale;
        }
    }
}
