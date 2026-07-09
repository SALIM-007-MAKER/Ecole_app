<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Channels\EmailChannel;

class EmailService
{
    private EmailChannel $channel;

    public function __construct()
    {
        $this->channel = new EmailChannel();
    }

    public function envoyer(array $job): bool
    {
        return $this->channel->envoyer($job);
    }

    public function disponible(): bool
    {
        return $this->channel->disponible();
    }

    public function canal(): string
    {
        return 'email';
    }
}
