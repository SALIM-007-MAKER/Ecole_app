<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\Listeners;

use Core\Database;
use Core\Event;
use Core\Listener;

class StatisticsListener implements Listener
{
    public function handle(Event $event): void
    {
        $class = get_class($event);

        if (str_ends_with($class, 'TimetablePublished')) {
            $this->incrementEdtPublications($event->edtId);
        }

        if (str_ends_with($class, 'TimetableConflictDetected')) {
            $this->logConflict($event->edtId, $event->typeConflit);
        }
    }

    private function incrementEdtPublications(int $edtId): void
    {
        try {
            $db   = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                UPDATE vs_emplois_du_temps
                SET updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $edtId]);
        } catch (\Throwable) {}
    }

    private function logConflict(int $edtId, string $typeConflit): void
    {
        // Placeholder — compteur de conflits détectés par type
        // Extensible sans régression quand la table de statistiques sera ajoutée
    }
}
