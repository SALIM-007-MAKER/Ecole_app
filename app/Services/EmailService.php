<?php

namespace App\Services;

use Core\Tenant\BrandingService;

class EmailService
{
    private array $config;

    /** @param array<string,mixed>|null $config configuration explicite (tests) ; null = config/mail.php */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? require ROOT_PATH . '/config/mail.php';
    }

    public function send(string $to, string $subject, string $htmlBody): bool
    {
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $fromName  = $this->config['from_name']  ?? BrandingService::forCurrentRequest()->appName;
        $fromEmail = $this->config['from_email'] ?? 'noreply@ecole-app.local';
        $replyTo   = $this->config['reply_to']   ?? $fromEmail;

        if (($this->config['driver'] ?? 'mail') === 'smtp') {
            return $this->sendSmtp($to, $subject, $htmlBody, (string)$fromName, (string)$fromEmail, (string)($this->config['reply_to'] ?? ''));
        }

        $headers = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->encodeHeader($fromName) . ' <' . $fromEmail . '>',
            'Reply-To: ' . $replyTo,
            'X-Mailer: EcoleApp/1.0',
            'X-Priority: 3',
        ]);

        try {
            return @mail($to, $this->encodeHeader($subject), $htmlBody, $headers);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Envoi réel par SMTP (Gmail…). Ne lève jamais : renvoie false et journalise la cause (sans identifiant ni mot de passe).
     * Texte brut alternatif : le HTML sans balises, avec l'adresse de chaque lien après son libellé.
     */
    private function sendSmtp(string $to, string $subject, string $htmlBody, string $fromName, string $fromEmail, string $replyTo): bool
    {
        $texte = preg_replace_callback('/<a\s[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', static fn(array $m): string => trim(preg_replace('/\s*(&rarr;|→)\s*$/u', '', trim(strip_tags($m[2]))) ?? '') . ' : ' . html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), $htmlBody) ?? $htmlBody;
        $texte = trim(html_entity_decode(strip_tags(str_ireplace(['</p>', '<br>', '<br/>', '</h2>', '</h1>'], "\n", $texte)), ENT_QUOTES, 'UTF-8'));
        $texte = preg_replace("/\n{3,}/", "\n\n", $texte) ?? $texte;

        try {
            // Garde-fou des tests : sur une COPIE jetable de la base (ecole_app_copy_*), aucun e-mail ne part vers un serveur distant.
            $estLocal = in_array(strtolower((string)($this->config['host'] ?? '')), ['localhost', '127.0.0.1', '::1'], true);
            if (!$estLocal && str_starts_with((string)($_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: ''), 'ecole_app_copy_')) {
                throw new SmtpException('Envoi réel désactivé : base de test (copie jetable).');
            }
            if (($this->config['username'] ?? '') === '' && !in_array(strtolower((string)($this->config['host'] ?? '')), ['localhost', '127.0.0.1', '::1'], true)) {
                throw new SmtpException('SMTP non configuré : MAIL_USERNAME / MAIL_PASSWORD sont vides.');
            }
            (new SmtpMailer($this->config))->send($fromEmail, $fromName, $to, $subject, $htmlBody, $texte, $replyTo);
            return true;
        } catch (\Throwable $e) {
            \Core\Logger::error('[MAIL] Envoi SMTP échoué vers ' . $this->masquer($to) . ' : ' . ($e instanceof SmtpException ? $e->getMessage() : get_class($e)));
            return false;
        }
    }

    /** a***@domaine.tld : le journal ne contient pas l'adresse complète. */
    private function masquer(string $email): string
    {
        $at = strrpos($email, '@');
        return $at === false ? '***' : substr($email, 0, 1) . '***' . substr($email, $at);
    }

    public function buildHtml(string $titre, string $message, string $lien = '', string $libelleLien = 'Voir le détail'): string
    {
        $esc      = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $appName  = $esc(BrandingService::forCurrentRequest()->appName);
        $lienHtml = '';
        // Lien : http(s) absolu ou chemin du site seulement (jamais javascript:, data:, etc.).
        if ($lien && preg_match('#^(?:https?://|/(?!/))#i', $lien) === 1) {
            $lienHtml = '<p style="margin-top:20px">
                <a href="' . $esc($lien) . '"
                   style="background:#0d6efd;color:white;padding:10px 20px;border-radius:5px;text-decoration:none;display:inline-block">
                   ' . $esc($libelleLien) . ' &rarr;
                </a></p>';
        }

        return '<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>' . $esc($titre) . '</title></head>
<body style="margin:0;padding:0;background:#f8f9fa;font-family:Arial,Helvetica,sans-serif">
<div style="max-width:600px;margin:32px auto">
  <div style="background:#0d6efd;color:#ffffff;padding:22px 28px;border-radius:8px 8px 0 0">
    <h1 style="margin:0;font-size:20px;font-weight:700">🎓 ' . $appName . '</h1>
  </div>
  <div style="background:#ffffff;border:1px solid #dee2e6;border-top:none;padding:28px;border-radius:0 0 8px 8px">
    <h2 style="color:#212529;font-size:17px;margin-top:0">' . $esc($titre) . '</h2>
    <p style="color:#495057;line-height:1.65;font-size:14px">' . nl2br($esc($message)) . '</p>
    ' . $lienHtml . '
    <hr style="border:none;border-top:1px solid #dee2e6;margin:24px 0 16px">
    <p style="color:#adb5bd;font-size:11px;margin:0">
      Ce message est envoyé automatiquement par ' . $appName . '. Merci de ne pas répondre directement.
    </p>
  </div>
</div>
</body>
</html>';
    }

    private function encodeHeader(string $text): string
    {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }
}
