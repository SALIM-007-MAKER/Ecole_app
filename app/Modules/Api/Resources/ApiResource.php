<?php
declare(strict_types=1);

namespace App\Modules\Api\Resources;

use App\Modules\Api\Auth\AuthContext;

/**
 * Transformateur de données DB → JSON API.
 * Masquage conditionnel selon permissions.
 */
abstract class ApiResource
{
    public function __construct(protected readonly array $data) {}

    abstract public function toArray(AuthContext $ctx): array;

    public static function collection(array $items, AuthContext $ctx, ?self $instance = null): array
    {
        return array_map(
            fn(array $item) => (new static($item))->toArray($ctx),
            $items
        );
    }

    protected function when(bool $condition, mixed $value, mixed $default = null): mixed
    {
        return $condition ? $value : $default;
    }

    protected function whenLoaded(string $key, mixed $value, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->data) ? $value : $default;
    }

    protected function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    protected function str(string $key, string $default = ''): string
    {
        return (string)($this->data[$key] ?? $default);
    }

    protected function int(string $key, int $default = 0): int
    {
        return (int)($this->data[$key] ?? $default);
    }

    protected function float(string $key, float $default = 0.0): float
    {
        return (float)($this->data[$key] ?? $default);
    }

    protected function bool(string $key, bool $default = false): bool
    {
        return (bool)($this->data[$key] ?? $default);
    }

    protected function date(string $key): ?string
    {
        $val = $this->data[$key] ?? null;
        return $val !== null ? (string)$val : null;
    }
}
