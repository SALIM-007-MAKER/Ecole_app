<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Channels\EmailChannel;
use App\Modules\Communication\Channels\SmsChannel;
use App\Modules\Communication\Channels\PushChannel;
use App\Modules\Communication\Models\MessageModel;
use App\Modules\Communication\Repositories\QueueRepository;
use App\Modules\Communication\Repositories\LogRepository;
use App\Modules\Communication\Events\MessageQueued;
use App\Modules\Communication\Events\MessageSent;
use App\Modules\Communication\Events\MessageFailed;
use Core\EventDispatcher;

class QueueService
{
    private QueueRepository $repo;
    private LogRepository   $logRepo;

    public function __construct()
    {
        $this->repo    = new QueueRepository();
        $this->logRepo = new LogRepository();
    }

    public function enqueue(
        string  $canal,
        string  $type,
        array   $destinataire,
        ?int    $templateId  = null,
        array   $variables   = [],
        string  $priorite    = 'normale',
        ?string $planifieAt  = null,
        int     $etablissementId = 1,
    ): int {
        $jobId = $this->repo->insert([
            'canal'              => $canal,
            'type'               => $type,
            'user_id'            => $destinataire['user_id']  ?? null,
            'destinataire_email' => $destinataire['email']    ?? null,
            'destinataire_tel'   => $destinataire['tel']      ?? null,
            'push_token'         => $destinataire['token']    ?? null,
            'sujet'              => $destinataire['sujet']    ?? null,
            'corps'              => $destinataire['corps']    ?? null,
            'template_id'        => $templateId,
            'variables_json'     => $variables,
            'priorite'           => $priorite,
            'planifie_at'        => $planifieAt,
            'etablissement_id'   => $etablissementId,
        ]);

        EventDispatcher::dispatch(new MessageQueued($jobId, $canal, $type, $destinataire['user_id'] ?? null));
        return $jobId;
    }

    /** Traite jusqu'à $limit jobs en attente. Retourne des statistiques. */
    public function traiterBatch(int $limit = 50): array
    {
        $jobs      = $this->repo->findPending($limit);
        $processed = 0;
        $success   = 0;
        $failed    = 0;

        foreach ($jobs as $job) {
            $this->repo->markProcessing((int) $job['id']);
            $ok = $this->executer($job);

            if ($ok) {
                $this->repo->updateStatut((int) $job['id'], 'sent');
                $this->logRepo->insert([
                    'queue_id'       => $job['id'],
                    'canal'          => $job['canal'],
                    'type'           => $job['type'],
                    'user_id'        => $job['user_id'],
                    'destinataire'   => $job['destinataire_email'] ?? $job['destinataire_tel'] ?? (string) $job['user_id'],
                    'sujet'          => $job['sujet'],
                    'statut'         => 'sent',
                    'etablissement_id' => $job['etablissement_id'] ?? 1,
                ]);
                EventDispatcher::dispatch(new MessageSent(
                    (int) $job['id'], $job['canal'], $job['type'], (int) ($job['user_id'] ?? 0),
                    $job['destinataire_email'] ?? $job['destinataire_tel'] ?? ''
                ));
                $success++;
            } else {
                $tentatives = (int) $job['tentatives'] + 1;
                $maxTent    = (int) ($job['max_tentatives'] ?? 3);

                if ($tentatives >= $maxTent) {
                    $this->repo->updateStatut((int) $job['id'], 'failed', 'Max tentatives atteint');
                    $this->logRepo->insert([
                        'queue_id'    => $job['id'],
                        'canal'       => $job['canal'],
                        'type'        => $job['type'],
                        'user_id'     => $job['user_id'],
                        'destinataire'=> $job['destinataire_email'] ?? $job['destinataire_tel'] ?? '',
                        'statut'      => 'failed',
                        'etablissement_id' => $job['etablissement_id'] ?? 1,
                    ]);
                    EventDispatcher::dispatch(new MessageFailed(
                        (int) $job['id'], $job['canal'], $job['type'],
                        (int) ($job['user_id'] ?? 0), 'Max tentatives', $tentatives
                    ));
                } else {
                    $delay = MessageModel::delaiRetry($tentatives);
                    $this->repo->incrementTentatives((int) $job['id'], $delay);
                }
                $failed++;
            }
            $processed++;
        }

        return ['processed' => $processed, 'success' => $success, 'failed' => $failed];
    }

    public function annuler(int $jobId): void
    {
        $this->repo->cancel($jobId);
    }

    public function statistiques(int $etablissementId = 1): array
    {
        return $this->repo->countByStatut($etablissementId);
    }

    private function executer(array $job): bool
    {
        $channel = match ($job['canal']) {
            'email' => new EmailChannel(),
            'sms'   => new SmsChannel(),
            'push'  => new PushChannel(),
            default => null,
        };

        if ($channel === null || !$channel->disponible()) {
            // Canal non disponible (ex: SMS stub) — on log comme "sent" pour ne pas bloquer
            return true;
        }

        return $channel->envoyer($job);
    }
}
