<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\DTO\PreferenceDTO;
use App\Modules\Communication\Events\PreferencesUpdated;
use App\Modules\Communication\Repositories\PreferenceRepository;
use Core\EventDispatcher;

class PreferenceService
{
    private PreferenceRepository $repo;

    public function __construct()
    {
        $this->repo = new PreferenceRepository();
    }

    public function obtenirPourUser(int $userId): array
    {
        $rows = $this->repo->findByUser($userId);
        $map  = [];
        foreach ($rows as $row) {
            $map[$row['type_notification']] = [
                'internal' => (bool) $row['canal_internal'],
                'email'    => (bool) $row['canal_email'],
                'sms'      => (bool) $row['canal_sms'],
                'push'     => (bool) $row['canal_push'],
            ];
        }
        return $map;
    }

    public function mettreAJour(int $userId, PreferenceDTO $dto): void
    {
        $this->repo->upsert($userId, $dto->typeNotification, [
            'internal' => $dto->canalInternal,
            'email'    => $dto->canalEmail,
            'sms'      => $dto->canalSms,
            'push'     => $dto->canalPush,
        ]);

        EventDispatcher::dispatch(new PreferencesUpdated($userId, $dto->typeNotification, [
            'internal' => $dto->canalInternal,
            'email'    => $dto->canalEmail,
            'sms'      => $dto->canalSms,
            'push'     => $dto->canalPush,
        ]));
    }

    /**
     * Vérifie si un utilisateur accepte le canal $canal pour le type $type.
     * Par défaut (aucune préférence enregistrée) : internal=oui, email=oui, sms=non, push=oui.
     */
    public function peutRecevoir(int $userId, string $type, string $canal): bool
    {
        $pref = $this->repo->findByUserAndType($userId, $type);

        if ($pref === null) {
            return match ($canal) {
                'sms'   => false,
                default => true,
            };
        }

        return match ($canal) {
            'internal' => (bool) $pref['canal_internal'],
            'email'    => (bool) $pref['canal_email'],
            'sms'      => (bool) $pref['canal_sms'],
            'push'     => (bool) $pref['canal_push'],
            default    => false,
        };
    }

    public function reinitialiser(int $userId): void
    {
        $this->repo->deleteByUser($userId);
    }
}
