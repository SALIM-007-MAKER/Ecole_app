<?php

declare(strict_types=1);

namespace App\Modules\RH\Formations\Events;

use Core\Event;

class CertificationExpired extends Event
{
    public function __construct(
        public readonly int    $empCertId,
        public readonly int    $employeId,
        public readonly string $certificationCode,
        public readonly string $dateExpiration
    ) {}

    public function toArray(): array
    {
        return [
            'emp_cert_id'        => $this->empCertId,
            'employe_id'         => $this->employeId,
            'certification_code' => $this->certificationCode,
            'date_expiration'    => $this->dateExpiration,
            'fired_at'           => $this->firedAt(),
        ];
    }
}
