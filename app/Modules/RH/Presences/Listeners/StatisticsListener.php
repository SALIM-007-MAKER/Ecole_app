<?php

declare(strict_types=1);

namespace App\Modules\RH\Presences\Listeners;

use Core\Listener;
use Core\Event;

class StatisticsListener implements Listener
{
    public function handle(Event $event): void
    {
        // Stub Phase 6.10 — Statistiques agrégées RH Présences
        // Objectif : alimentation d'un cache de métriques (taux présence, nb retards, heures_sup)
        // pour le tableau de bord RH et la préparation de la future paie (Phase 6.12)
    }
}
