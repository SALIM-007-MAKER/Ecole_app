<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\EmailService;
use Core\Queue\Job;

/**
 * Envoi d'email différé — exemple de tâche réelle prête à l'emploi
 * (Phase 14.9). Volontairement NON câblée dans les contrôleurs/listeners
 * existants qui appellent aujourd'hui EmailService::send() directement
 * (AuthController, NotificationHandler...) : les basculer sur la file
 * changerait un comportement métier synchrone existant, ce qui sort du
 * périmètre "adapter l'infrastructure cache/queue" de cette phase. Cette
 * classe démontre et teste le mécanisme pour un futur appelant.
 *
 * @see MULTI_TENANT_CACHE_QUEUE_IMPLEMENTATION_REPORT.md
 */
final class SendEmailJob implements Job
{
    public function __construct(private readonly ?EmailService $mailer = null)
    {
    }

    /** @param array{to:string, subject:string, html_body:string} $payload */
    public function handle(array $payload): void
    {
        $mailer = $this->mailer ?? new EmailService();
        $mailer->send($payload['to'], $payload['subject'], $payload['html_body']);
    }
}
