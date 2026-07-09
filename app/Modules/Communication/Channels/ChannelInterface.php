<?php

declare(strict_types=1);

namespace App\Modules\Communication\Channels;

interface ChannelInterface
{
    public function envoyer(array $job): bool;
    public function disponible(): bool;
    public function canal(): string;
}
