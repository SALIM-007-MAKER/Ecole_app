<?php

declare(strict_types=1);

namespace App\Modules\RH\Formations\Events;

use Core\Event;

class CertificationGranted extends Event
{
    public function __construct(
        public readonly int     $empCertId,
        public readonly int     $employeId,
        public readonly int     $certificationId,
        public readonly string  $certificationCode,
        public readonly string  $dateObtention,
        public readonly ?string $dateExpiration,
        public readonly int     $grantedBy
    ) {}

    public function toArray(): array
    {
        return [
            'emp_cert_id'       => $this->empCertId,
            'employe_id'        => $this->employeId,
            'certification_id'  => $this->certificationId,
            'certification_code'=> $this->certificationCode,
            'date_obtention'    => $this->dateObtention,
            'date_expiration'   => $this->dateExpiration,
            'granted_by'        => $this->grantedBy,
            'fired_at'          => $this->firedAt(),
        ];
    }
}
