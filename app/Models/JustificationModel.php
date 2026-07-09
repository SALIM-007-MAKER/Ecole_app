<?php

namespace App\Models;

use Core\Model;

class JustificationModel extends Model
{
    protected string $table = 'justifications';

    public function findByAbsence(int $absenceId): ?\stdClass
    {
        return $this->queryOne(
            "SELECT j.*,
                    CONCAT(COALESCE(u.prenom,''),' ',COALESCE(u.nom,'')) AS soumis_par_nom
             FROM   `justifications` j
             LEFT JOIN `users` u ON u.id = j.soumis_par
             WHERE  j.absence_id = ?",
            [$absenceId]
        ) ?: null;
    }

    public function upsert(int $absenceId, int $userId, string $motif, ?string $docPath): void
    {
        $existing = $this->findByAbsence($absenceId);
        if ($existing) {
            $this->execute(
                "UPDATE `justifications`
                 SET motif=?, document_path=COALESCE(?,document_path),
                     statut='en_attente', soumis_par=?, updated_at=NOW()
                 WHERE absence_id=?",
                [$motif, $docPath, $userId, $absenceId]
            );
        } else {
            $this->execute(
                "INSERT INTO `justifications`
                    (absence_id, soumis_par, motif, document_path, statut)
                 VALUES (?,?,?,?,'en_attente')",
                [$absenceId, $userId, $motif, $docPath]
            );
        }
    }

    public function valider(int $id, string $statut, ?string $commentaire, int $adminId): void
    {
        $this->execute(
            "UPDATE `justifications`
             SET statut=?, commentaire_admin=?, soumis_par=?, updated_at=NOW()
             WHERE id=?",
            [$statut, $commentaire, $adminId, $id]
        );
    }
}
