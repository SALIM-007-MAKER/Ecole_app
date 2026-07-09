<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Contracts;

/**
 * STUB V3 — Star-schema Data Warehouse.
 * Implémentation prévue Phase V3 avec ETL nocturne.
 */
interface DataWarehouseInterface
{
    public function query(string $dimension, array $filters, string $periode): array;

    public function factTable(string $name): array;

    public function dimensionValues(string $dimension): array;
}
