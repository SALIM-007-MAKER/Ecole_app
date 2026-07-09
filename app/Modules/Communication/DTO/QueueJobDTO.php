<?php

declare(strict_types=1);

namespace App\Modules\Communication\DTO;

class QueueJobDTO
{
    public function __construct(
        public readonly string  $canal,
        public readonly string  $type,
        public readonly ?int    $userId              = null,
        public readonly ?string $destinataireEmail   = null,
        public readonly ?string $destinataireTel     = null,
        public readonly ?string $pushToken           = null,
        public readonly ?string $sujet               = null,
        public readonly ?string $corps               = null,
        public readonly ?int    $templateId          = null,
        public readonly array   $variables           = [],
        public readonly string  $priorite            = 'normale',
        public readonly ?string $planifieAt          = null,
        public readonly int     $etablissementId     = 1,
    ) {}

    public static function forEmail(
        string $type,
        string $email,
        string $sujet,
        string $corps,
        ?int   $userId = null,
        string $priorite = 'normale',
        int    $etablissementId = 1,
    ): self {
        return new self(
            canal: 'email',
            type:  $type,
            userId: $userId,
            destinataireEmail: $email,
            sujet: $sujet,
            corps: $corps,
            priorite: $priorite,
            etablissementId: $etablissementId,
        );
    }

    public static function forSms(
        string $type,
        string $tel,
        string $corps,
        ?int   $userId = null,
        string $priorite = 'normale',
        int    $etablissementId = 1,
    ): self {
        return new self(
            canal: 'sms',
            type:  $type,
            userId: $userId,
            destinataireTel: $tel,
            corps: $corps,
            priorite: $priorite,
            etablissementId: $etablissementId,
        );
    }

    public static function forPush(
        string $type,
        string $token,
        string $titre,
        string $corps,
        ?int   $userId = null,
        string $priorite = 'normale',
        int    $etablissementId = 1,
    ): self {
        return new self(
            canal: 'push',
            type:  $type,
            userId: $userId,
            pushToken: $token,
            sujet: $titre,
            corps: $corps,
            priorite: $priorite,
            etablissementId: $etablissementId,
        );
    }
}
