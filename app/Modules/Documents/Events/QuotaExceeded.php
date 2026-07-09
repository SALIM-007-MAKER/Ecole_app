<?php

declare(strict_types=1);

namespace App\Modules\Documents\Events;

use Core\Event;

class QuotaExceeded extends Event
{
    public function __construct(
        public readonly string $moduleSource,
        public readonly int    $quotaOctets,
        public readonly int    $utilisOctets,
        public readonly int    $tentativeOctets,
    ) { parent::__construct(); }

    public function toArray(): array
    {
        return [
            'module_source'    => $this->moduleSource,
            'quota_octets'     => $this->quotaOctets,
            'utilise_octets'   => $this->utilisOctets,
            'tentative_octets' => $this->tentativeOctets,
        ];
    }
}
