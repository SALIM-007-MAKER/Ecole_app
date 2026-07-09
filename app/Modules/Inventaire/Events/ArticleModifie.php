<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Events;

use Core\Event;

class ArticleModifie extends Event
{
    public function __construct(
        public readonly int    $articleId,
        public readonly string $designation,
        public readonly int    $userId,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'article_id'  => $this->articleId,
            'designation' => $this->designation,
            'user_id'     => $this->userId,
        ];
    }
}
