<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Events;

use Core\Event;

class ArticleAjoute extends Event
{
    public function __construct(
        public readonly int    $articleId,
        public readonly string $reference,
        public readonly string $designation,
        public readonly string $type,
        public readonly int    $userId,
        public readonly int    $etablissementId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'article_id'       => $this->articleId,
            'reference'        => $this->reference,
            'designation'      => $this->designation,
            'type'             => $this->type,
            'user_id'          => $this->userId,
            'etablissement_id' => $this->etablissementId,
        ];
    }
}
