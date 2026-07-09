<?php
declare(strict_types=1);

namespace App\Modules\Portals\DTO;

class SearchResultsDTO
{
    public function __construct(
        public readonly string $query,
        /** @var SearchResultDTO[] */
        public readonly array  $results,
        public readonly int    $total,
        public readonly int    $timeMs,
    ) {}

    public function toArray(): array
    {
        return [
            'query'   => $this->query,
            'results' => array_map(fn($r) => $r->toArray(), $this->results),
            'total'   => $this->total,
            'time_ms' => $this->timeMs,
        ];
    }
}
