<?php

declare(strict_types=1);

namespace App\Modules\Documents\Listeners;

use Core\Event;
use Core\Listener;
use App\Modules\Documents\Events\DocumentShared;
use App\Modules\Documents\Events\DocumentExpired;
use App\Modules\Documents\Events\DocumentSignatureRequested;
use App\Modules\Documents\Events\DocumentSigned;
use App\Modules\Documents\Events\QuotaExceeded;

class NotificationListener implements Listener
{
    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof DocumentShared             => $this->onShared($event),
            $event instanceof DocumentExpired            => $this->onExpired($event),
            $event instanceof DocumentSignatureRequested => $this->onSignatureRequested($event),
            $event instanceof DocumentSigned             => $this->onSigned($event),
            $event instanceof QuotaExceeded              => $this->onQuotaExceeded($event),
            default => null,
        };
    }

    private function onShared(DocumentShared $e): void
    {
        // notify destinataire_id if internal user
        if ($e->destinataireId !== null && $e->destinataireType === 'user') {
            $this->insertNotification(
                $e->destinataireId,
                'document_shared',
                "Un document a été partagé avec vous.",
                ['document_id' => $e->documentId, 'permission' => $e->permission]
            );
        }
    }

    private function onExpired(DocumentExpired $e): void
    {
        // notifications handled via batch cron — no user target here
    }

    private function onSignatureRequested(DocumentSignatureRequested $e): void
    {
        foreach ($e->signataires as $signataire) {
            $userId = $signataire['user_id'] ?? null;
            if ($userId === null) continue;
            $this->insertNotification(
                (int)$userId,
                'signature_requested',
                "Votre signature est requise sur un document.",
                ['document_id' => $e->documentId]
            );
        }
    }

    private function onSigned(DocumentSigned $e): void
    {
        // notify document owner if completion reaches 100 %
        if ($e->completionPercent >= 100.0) {
            // owner lookup is expensive here — skip in favour of a dedicated SignatureCompleted event V3
        }
    }

    private function onQuotaExceeded(QuotaExceeded $e): void
    {
        // alert admins — resolved via cron report in V2, push alert planned V3
    }

    private function insertNotification(int $userId, string $type, string $message, array $data): void
    {
        try {
            $pdo = \Core\Database::getInstance()->getConnection();
            $stmt = $pdo->prepare(
                "INSERT INTO notifications (user_id, type, message, data, lu, created_at)
                 VALUES (:user_id, :type, :message, :data, 0, NOW())"
            );
            $stmt->execute([
                'user_id' => $userId,
                'type'    => $type,
                'message' => $message,
                'data'    => json_encode($data),
            ]);
        } catch (\Throwable) {
            // notifications are best-effort — never block the main flow
        }
    }
}
