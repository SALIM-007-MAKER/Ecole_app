<?php

declare(strict_types=1);

namespace App\Modules\Documents\Listeners;

use Core\Event;
use Core\Listener;

/**
 * Search index synchronisation — FULLTEXT MySQL index handles V2.
 * External search engine (Meilisearch / Elasticsearch) planned V3.
 */
class SearchIndexListener implements Listener
{
    public function handle(Event $event): void
    {
        // no-op in V2: MySQL FULLTEXT index is updated automatically on INSERT/UPDATE
    }
}
