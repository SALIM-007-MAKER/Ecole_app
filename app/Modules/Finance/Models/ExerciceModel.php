<?php

namespace App\Modules\Finance\Models;

class ExerciceModel
{
    public const STATUTS = [
        'ouvert'   => 'Ouvert',
        'cloture'  => 'Clôturé',
        'reouvert' => 'Réouvert',
    ];

    public const STATUT_COLORS = [
        'ouvert'   => 'emerald',
        'cloture'  => 'slate',
        'reouvert' => 'amber',
    ];

    public int     $id;
    public string  $libelle;
    public string  $date_debut;
    public string  $date_fin;
    public string  $statut;
    public float   $solde_report;
    public ?string $note;
    public ?string $date_cloture;
    public ?int    $cloture_par;
    public ?int    $ouvert_par;
    public string  $created_at;
    public string  $updated_at;

    public ?int    $nb_periodes        = null;
    public ?int    $nb_periodes_ouvertes = null;
    public ?int    $nb_ecritures       = null;
    public ?float  $total_produits     = null;
    public ?float  $total_charges      = null;

    public function statutLabel(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function statutColor(): string
    {
        return self::STATUT_COLORS[$this->statut] ?? 'slate';
    }

    public function estOuvert(): bool
    {
        return in_array($this->statut, ['ouvert', 'reouvert'], true);
    }

    public function estCloturable(): bool
    {
        return $this->estOuvert() && ($this->nb_periodes_ouvertes ?? 1) === 0;
    }

    public function resultatNet(): float
    {
        return round(
            ($this->total_produits ?? 0) - ($this->total_charges ?? 0),
            2
        );
    }

    public function dureeMois(): int
    {
        $debut = new \DateTime($this->date_debut);
        $fin   = new \DateTime($this->date_fin);
        return (int)$debut->diff($fin)->m + ($debut->diff($fin)->y * 12) + 1;
    }
}
