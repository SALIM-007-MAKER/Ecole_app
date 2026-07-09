<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Channels\SmsChannel;

class SmsService
{
    private SmsChannel $channel;

    public function __construct()
    {
        $this->channel = new SmsChannel();
    }

    public function envoyer(array $job): bool
    {
        return $this->channel->envoyer($job);
    }

    public function disponible(): bool
    {
        return $this->channel->disponible(); // false en V2
    }

    public function canal(): string
    {
        return 'sms';
    }
}
