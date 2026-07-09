<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Channels\PushChannel;
use App\Modules\Communication\Repositories\PushTokenRepository;

class PushService
{
    private PushTokenRepository $repo;
    private PushChannel         $channel;

    public function __construct()
    {
        $this->repo    = new PushTokenRepository();
        $this->channel = new PushChannel();
    }

    public function enregistrerToken(int $userId, string $token, string $plateforme = 'web'): void
    {
        $this->repo->upsert($userId, $token, $plateforme);
    }

    public function revoquerToken(string $token): void
    {
        $this->repo->revoke($token);
    }

    public function tokensActifs(int $userId): array
    {
        return $this->repo->findByUser($userId);
    }

    public function envoyerAUser(int $userId, string $titre, string $corps, string $type = 'info'): array
    {
        $tokens  = $this->repo->findByUser($userId);
        $results = [];

        foreach ($tokens as $tokenRow) {
            $ok = $this->channel->envoyer([
                'push_token' => $tokenRow['token'],
                'sujet'      => $titre,
                'corps'      => $corps,
            ]);
            if (!$ok) {
                // Révoquer les tokens invalides (retour false = token expiré)
                $this->repo->revoke($tokenRow['token']);
            } else {
                $this->repo->updateLastUsed($tokenRow['token']);
            }
            $results[] = ['token' => substr($tokenRow['token'], 0, 20) . '...', 'ok' => $ok];
        }

        return $results;
    }

    public function disponible(): bool
    {
        return $this->channel->disponible();
    }
}
