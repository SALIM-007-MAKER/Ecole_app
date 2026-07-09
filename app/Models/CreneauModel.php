<?php

namespace App\Models;

use Core\Model;

class CreneauModel extends Model
{
    protected string $table = 'creneaux';

    const TYPES = [
        'cours'      => ['label' => 'Cours',        'badge' => 'primary',   'icon' => 'book'],
        'pause'      => ['label' => 'Pause',         'badge' => 'secondary', 'icon' => 'cup-hot'],
        'recreation' => ['label' => 'Récréation',    'badge' => 'info',      'icon' => 'emoji-laughing'],
        'priere'     => ['label' => 'Prière',        'badge' => 'warning',   'icon' => 'moon-stars'],
    ];

    public function findAllActifs(): array
    {
        return $this->query(
            'SELECT * FROM creneaux WHERE actif = 1 ORDER BY ordre ASC, heure_debut ASC'
        );
    }

    public function findCours(): array
    {
        return $this->query(
            'SELECT * FROM creneaux WHERE type = "cours" AND actif = 1 ORDER BY ordre ASC'
        );
    }

    public function getNextOrdre(): int
    {
        $r = $this->queryOne('SELECT MAX(ordre) AS max_ordre FROM creneaux');
        return ($r ? (int)$r->max_ordre : 0) + 1;
    }
}
