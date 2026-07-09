<?php

namespace App\Modules\Finance\Models;

class EcritureModel
{
    public const STATUTS = [
        'brouillon' => 'Brouillon',
        'valide'    => 'Validée',
        'extourne'  => 'Extournée',
        'cloture'   => 'Clôturée',
    ];

    public const STATUT_COLORS = [
        'brouillon' => 'amber',
        'valide'    => 'emerald',
        'extourne'  => 'slate',
        'cloture'   => 'violet',
    ];

    public const SOURCES_LABELS = [
        'payment_completed'     => 'Paiement encaissé',
        'payment_refunded'      => 'Remboursement',
        'invoice_cancelled'     => 'Annulation facture',
        'cash_recette_manuel'   => 'Recette caisse (manuel)',
        'cash_decaissement_manuel' => 'Décaissement caisse (manuel)',
        'expense_validated'     => 'Dépense validée',
        'exercice_cloture'      => 'Clôture exercice',
        'extourne'              => 'Écriture d\'extourne',
    ];

    public int     $id;
    public string  $numero;
    public string  $date_ecriture;
    public int     $journal_id;
    public int     $exercice_id;
    public int     $periode_id;
    public string  $libelle;
    public ?string $reference;
    public ?string $source;
    public string  $statut;
    public ?int    $extourne_de;
    public int     $created_by;
    public string  $created_at;

    // Jointures optionnelles
    public ?string $journal_code   = null;
    public ?string $journal_libelle = null;
    public ?string $exercice_libelle = null;
    public ?string $periode_libelle  = null;
    public ?string $createur_nom     = null;
    public ?float  $total_debit      = null;
    public ?float  $total_credit     = null;

    public function statutLabel(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    public function statutColor(): string
    {
        return self::STATUT_COLORS[$this->statut] ?? 'slate';
    }

    public function sourceLabel(): string
    {
        return self::SOURCES_LABELS[$this->source ?? ''] ?? ($this->source ?? '—');
    }

    public function estExtournable(): bool
    {
        return $this->statut === 'valide';
    }

    public function estEquilibree(): bool
    {
        return $this->total_debit !== null
            && $this->total_credit !== null
            && abs($this->total_debit - $this->total_credit) < 0.01;
    }
}
