<?php

declare(strict_types=1);

namespace App\Modules\Documents\Listeners;

use Core\Event;
use Core\Listener;
use App\Modules\Documents\Events\DocumentUploaded;
use App\Modules\Documents\Events\DocumentPurged;
use App\Modules\Documents\Repositories\QuotaRepository;
use App\Modules\Documents\Repositories\DocumentRepository;

class QuotaListener implements Listener
{
    private QuotaRepository   $quotas;
    private DocumentRepository $docs;

    public function __construct()
    {
        $this->quotas = new QuotaRepository();
        $this->docs   = new DocumentRepository();
    }

    public function handle(Event $event): void
    {
        match (true) {
            $event instanceof DocumentUploaded => $this->onUploaded($event),
            $event instanceof DocumentPurged   => $this->onPurged($event),
            default => null,
        };
    }

    private function onUploaded(DocumentUploaded $e): void
    {
        $this->quotas->incrementer($e->moduleSource, $e->tailleOctets);
    }

    private function onPurged(DocumentPurged $e): void
    {
        $doc = $this->docs->findById($e->documentId);
        if ($doc === null) return;

        $taille = (int)($doc['taille_octets'] ?? 0);
        if ($taille > 0) {
            $this->quotas->decrementer($doc['module_source'], $taille);
        }
    }
}
