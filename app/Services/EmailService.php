<?php

namespace App\Services;

use Core\Tenant\BrandingService;

class EmailService
{
    private array $config;

    public function __construct()
    {
        $this->config = require ROOT_PATH . '/config/mail.php';
    }

    public function send(string $to, string $subject, string $htmlBody): bool
    {
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $fromName  = $this->config['from_name']  ?? BrandingService::forCurrentRequest()->appName;
        $fromEmail = $this->config['from_email'] ?? 'noreply@ecole-app.local';
        $replyTo   = $this->config['reply_to']   ?? $fromEmail;

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

    public function buildHtml(string $titre, string $message, string $lien = ''): string
    {
        $esc      = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $appName  = $esc(BrandingService::forCurrentRequest()->appName);
        $lienHtml = '';
        if ($lien) {
            $lienHtml = '<p style="margin-top:20px">
                <a href="' . $esc($lien) . '"
                   style="background:#0d6efd;color:white;padding:10px 20px;border-radius:5px;text-decoration:none;display:inline-block">
                   Voir le détail &rarr;
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
