<?php

declare(strict_types=1);

namespace App\Modules\Communication\DTO;

class PreferenceDTO
{
    public function __construct(
        public readonly string $typeNotification,
        public readonly bool   $canalInternal = true,
        public readonly bool   $canalEmail    = true,
        public readonly bool   $canalSms      = false,
        public readonly bool   $canalPush     = true,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            typeNotification: $data['type_notification'] ?? '',
            canalInternal:    (bool) ($data['canal_internal'] ?? true),
            canalEmail:       (bool) ($data['canal_email']    ?? true),
            canalSms:         (bool) ($data['canal_sms']      ?? false),
            canalPush:        (bool) ($data['canal_push']     ?? true),
        );
    }
}
