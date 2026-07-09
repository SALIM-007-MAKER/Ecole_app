<?php
declare(strict_types=1);

namespace App\Modules\Rapports\Contracts;

/**
 * STUB V3 — Extract-Transform-Load pipeline.
 * Implémentation prévue Phase V3 avec jobs queue.
 */
interface ETLInterface
{
    public function extract(string $source, array $params): array;

    public function transform(array $rawData, string $schema): array;

    public function load(array $transformedData, string $target): bool;

    public function runPipeline(string $pipelineName): bool;
}
