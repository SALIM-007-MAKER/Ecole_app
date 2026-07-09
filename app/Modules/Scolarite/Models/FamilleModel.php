<?php

namespace App\Modules\Scolarite\Models;

use Core\Model;

class FamilleModel extends Model
{
    protected string $table      = 'familles';
    protected string $primaryKey = 'id';

    public const LIENS_PARENTE = ['pere', 'mere', 'tuteur', 'autre'];

    public const LIENS_LABELS = [
        'pere'   => 'Père',
        'mere'   => 'Mère',
        'tuteur' => 'Tuteur légal',
        'autre'  => 'Autre',
    ];

    /** Toutes les familles actives pour un élève (ses responsables). */
    public function findByEleve(int $eleveId): array
    {
        return $this->query(
            "SELECT f.*, fe.lien_parente, fe.est_contact_principal, fe.est_contact_urgence, fe.ordre
             FROM `familles` f
             JOIN `familles_eleves` fe ON fe.famille_id = f.id
             WHERE fe.eleve_id = ?
             ORDER BY fe.ordre ASC",
            [$eleveId]
        );
    }

    /** Famille + liste de tous ses élèves. */
    public function findWithEleves(int $familleId): array
    {
        return $this->query(
            "SELECT e.id, e.nom, e.prenom, e.matricule,
                    fe.lien_parente, fe.est_contact_principal, fe.est_contact_urgence
             FROM `familles_eleves` fe
             JOIN `eleves` e ON e.id = fe.eleve_id
             WHERE fe.famille_id = ?
             ORDER BY fe.ordre ASC, e.nom ASC",
            [$familleId]
        );
    }

    /** Chercher une famille par email (unicité optionnelle). */
    public function findByEmail(string $email): object|false
    {
        return $this->queryOne(
            "SELECT * FROM `familles` WHERE email = ? LIMIT 1",
            [$email]
        ) ?: false;
    }
}
