<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Client SMTP minimal (sans dépendance) : connexion, STARTTLS ou SSL implicite, AUTH PLAIN / LOGIN, un message HTML + texte brut.
 *
 * Conçu pour Gmail (smtp.gmail.com:587 + STARTTLS, ou :465 en SSL) avec un « mot de passe d'application », mais valable pour tout serveur SMTP.
 *
 * Sécurité :
 *  - le certificat du serveur est TOUJOURS vérifié (verify_peer + nom d'hôte) ;
 *  - les identifiants ne sont jamais envoyés en clair : sans chiffrement, seul un hôte local (127.0.0.1, ::1, localhost) est accepté ;
 *  - les adresses et le sujet sont refusés/nettoyés s'ils contiennent un retour à la ligne (injection d'en-têtes) ;
 *  - aucun message d'erreur ne contient le nom d'utilisateur ni le mot de passe.
 *
 * @phpstan-type Config array{host:string, port:int, username:string, password:string, encryption:string, timeout:int, ehlo_host?:string}
 */
final class SmtpMailer
{
    /** @var resource|null */
    private $socket = null;

    /** @param array<string,mixed> $config host, port, username, password, encryption (tls|ssl|none), timeout, ehlo_host */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * @throws SmtpException
     */
    public function send(string $fromEmail, string $fromName, string $to, string $subject, string $html, ?string $text = null, string $replyTo = ''): void
    {
        $this->verifierAdresse($fromEmail, 'expéditeur');
        $this->verifierAdresse($to, 'destinataire');
        if ($replyTo !== '') {
            $this->verifierAdresse($replyTo, 'réponse');
        }
        $host       = (string)($this->config['host'] ?? '');
        $port       = (int)($this->config['port'] ?? 587);
        $encryption = strtolower((string)($this->config['encryption'] ?? 'tls'));
        $username   = (string)($this->config['username'] ?? '');
        $password   = (string)($this->config['password'] ?? '');
        $timeout    = max(3, (int)($this->config['timeout'] ?? 15));

        if ($host === '' || $port < 1 || $port > 65535) {
            throw new SmtpException('Serveur SMTP non configuré (hôte ou port manquant).');
        }
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            throw new SmtpException('Chiffrement SMTP invalide (tls, ssl ou none attendu).');
        }
        if ($encryption === 'none' && $username !== '' && !$this->estHoteLocal($host)) {
            throw new SmtpException('Refus : les identifiants SMTP ne peuvent pas être envoyés en clair vers un hôte distant (utilisez tls ou ssl).');
        }

        $message = $this->construireMessage($fromEmail, $fromName, $to, $subject, $html, $text, $replyTo, $host);

        try {
            $this->connecter($host, $port, $encryption, $timeout);
            $this->attendre([220]);
            $capacites = $this->ehlo();

            if ($encryption === 'tls') {
                if (!isset($capacites['STARTTLS'])) {
                    throw new SmtpException('Le serveur SMTP ne propose pas STARTTLS : envoi refusé (chiffrement exigé).');
                }
                $this->commande('STARTTLS', [220]);
                if (@stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
                    throw new SmtpException('Échec de la négociation TLS (certificat du serveur non valide ou non reconnu).');
                }
                $capacites = $this->ehlo();
            }

            if ($username !== '') {
                $this->authentifier($capacites, $username, $password);
            }

            $this->commande('MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->commande('RCPT TO:<' . $to . '>', [250, 251]);
            $this->commande('DATA', [354]);
            $this->ecrire($this->bourrage($message) . "\r\n.");
            $this->attendre([250]);
            try {
                $this->commande('QUIT', [221]);
            } catch (SmtpException) {
                // Le message est accepté : une erreur à la fermeture est sans conséquence.
            }
        } finally {
            if (is_resource($this->socket)) {
                @fclose($this->socket);
            }
            $this->socket = null;
        }
    }

    // ── Protocole ──────────────────────────────────────────────────────────────────────────────────────────────────────────────

    private function connecter(string $host, int $port, string $encryption, int $timeout): void
    {
        $contexte = stream_context_create(['ssl' => [
            'verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host, 'allow_self_signed' => false,
        ]]);
        $schema = $encryption === 'ssl' ? 'ssl' : 'tcp';
        $this->socket = @stream_socket_client("{$schema}://{$host}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $contexte);
        if (!is_resource($this->socket)) {
            throw new SmtpException("Connexion au serveur SMTP impossible ({$host}:{$port}) : " . $this->nettoyer((string)$errstr));
        }
        stream_set_timeout($this->socket, $timeout);
    }

    /** @return array<string,string> capacités annoncées (clé en majuscules) */
    private function ehlo(): array
    {
        $reponse = $this->commande('EHLO ' . $this->nomEhlo(), [250]);
        $capacites = [];
        foreach (explode("\n", $reponse) as $i => $ligne) {
            if ($i === 0) { continue; }
            $ligne = trim(substr($ligne, 4));
            if ($ligne === '') { continue; }
            [$mot, $reste] = array_pad(explode(' ', $ligne, 2), 2, '');
            $capacites[strtoupper($mot)] = $reste;
        }
        return $capacites;
    }

    /** @param array<string,string> $capacites */
    private function authentifier(array $capacites, #[\SensitiveParameter] string $username, #[\SensitiveParameter] string $password): void
    {
        $mecanismes = isset($capacites['AUTH']) ? preg_split('/\s+/', strtoupper($capacites['AUTH'])) : [];
        if ($mecanismes === [] || $mecanismes === false) {
            throw new SmtpException("Le serveur SMTP n'annonce aucune authentification alors que des identifiants sont configurés.");
        }
        if (in_array('PLAIN', $mecanismes, true)) {
            $this->commande('AUTH PLAIN ' . base64_encode("\0" . $username . "\0" . $password), [235], true);
            return;
        }
        if (in_array('LOGIN', $mecanismes, true)) {
            $this->commande('AUTH LOGIN', [334], true);
            $this->commande(base64_encode($username), [334], true);
            $this->commande(base64_encode($password), [235], true);
            return;
        }
        throw new SmtpException('Aucun mécanisme d\'authentification SMTP pris en charge (PLAIN ou LOGIN attendu).');
    }

    /**
     * @param list<int> $attendus codes de réponse acceptés
     * @param bool $secret la commande contient des identifiants : jamais répétée dans un message d'erreur
     */
    private function commande(#[\SensitiveParameter] string $commande, array $attendus, bool $secret = false): string
    {
        $this->ecrire($commande);
        return $this->attendre($attendus, $secret ? 'authentification' : $this->resume($commande));
    }

    /** Écrit TOUT (une écriture peut être partielle, surtout en TLS). Le paramètre est sensible : jamais recopié dans une trace d'exception. */
    private function ecrire(#[\SensitiveParameter] string $donnees): void
    {
        $reste = $donnees . "\r\n";
        while ($reste !== '') {
            $ecrit = @fwrite($this->socket, $reste);
            if ($ecrit === false || $ecrit === 0) {
                throw new SmtpException('Connexion SMTP interrompue pendant l\'envoi.');
            }
            $reste = substr($reste, $ecrit);
        }
    }

    /** @param list<int> $attendus */
    private function attendre(array $attendus, string $contexte = 'réponse'): string
    {
        $reponse = '';
        $code = 0;
        for ($i = 0; $i < 100; $i++) {
            $ligne = @fgets($this->socket, 1024);
            if ($ligne === false) {
                $meta = stream_get_meta_data($this->socket);
                throw new SmtpException(!empty($meta['timed_out']) ? 'Le serveur SMTP ne répond pas (délai dépassé).' : 'Connexion SMTP fermée par le serveur.');
            }
            $reponse .= rtrim($ligne, "\r\n") . "\n";
            if (!preg_match('/^(\d{3})([ -])/', $ligne, $m)) {
                throw new SmtpException('Réponse SMTP incompréhensible.');
            }
            $code = (int)$m[1];
            if ($m[2] === ' ') {
                break;
            }
        }
        if (!in_array($code, $attendus, true)) {
            $detail = $this->nettoyer(trim(substr(strtok($reponse, "\n") ?: '', 4)));
            throw new SmtpException("Le serveur SMTP a refusé l'étape « {$contexte} » (code {$code}" . ($detail !== '' ? " : {$detail}" : '') . ').');
        }
        return rtrim($reponse, "\n");
    }

    // ── Message ────────────────────────────────────────────────────────────────────────────────────────────────────────────────

    private function construireMessage(string $fromEmail, string $fromName, string $to, string $subject, string $html, ?string $text, string $replyTo, string $host): string
    {
        $domaine = substr(strrchr($fromEmail, '@') ?: '@localhost', 1);
        $frontiere = '=_ecole_' . bin2hex(random_bytes(12));
        $texte = $text ?? trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));

        $entetes = [
            'Date: ' . date('r'),
            'From: ' . $this->nomAffiche($this->sansSautDeLigne($fromName)) . ' <' . $fromEmail . '>',
            'To: <' . $to . '>',
            'Subject: ' . $this->encoderEntete($this->sansSautDeLigne($subject), 9),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domaine . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $frontiere . '"',
            'Auto-Submitted: auto-generated',   // message automatique : pas de réponse automatique ni de boucle de courrier
            'X-Mailer: EcoleApp',
        ];
        if ($replyTo !== '') {
            $entetes[] = 'Reply-To: <' . $replyTo . '>';
        }

        $corps = '--' . $frontiere . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($texte), 76, "\r\n")
            . '--' . $frontiere . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html), 76, "\r\n")
            . '--' . $frontiere . '--';

        return implode("\r\n", $entetes) . "\r\n\r\n" . $corps;
    }

    /** Valeur d'en-tête non ASCII : mots encodés RFC 2047 (UTF-8, base64) de 75 caractères au plus, repliés par CRLF + espace. */
    private function encoderEntete(string $texte, int $decalage = 0): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $texte) === 1) {
            return $texte;
        }
        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($texte, 'UTF-8', 'B', "\r\n", $decalage);
        }
        return '=?UTF-8?B?' . base64_encode($texte) . '?=';
    }

    /** Nom affiché de l'expéditeur : encodé s'il est accentué, sinon entre guillemets (virgule, point-virgule, guillemet…). */
    private function nomAffiche(string $nom): string
    {
        if ($nom === '') {
            return '';
        }
        if (preg_match('/^[\x20-\x7E]*$/', $nom) === 1) {
            return '"' . addcslashes($nom, '"\\') . '"';
        }
        return '=?UTF-8?B?' . base64_encode($nom) . '?=';
    }

    /** Doublement des points en début de ligne (transparence SMTP) et normalisation des fins de ligne. */
    private function bourrage(string $message): string
    {
        $message = str_replace(["\r\n", "\r"], "\n", $message);
        $message = str_replace("\n", "\r\n", $message);
        return preg_replace('/^\./m', '..', $message) ?? $message;
    }

    // ── Utilitaires ────────────────────────────────────────────────────────────────────────────────────────────────────────────

    private function verifierAdresse(string $adresse, string $role): void
    {
        if ($adresse === '' || strlen($adresse) > 254 || preg_match('/[\r\n\0<>,;\s"]/', $adresse) === 1 || filter_var($adresse, FILTER_VALIDATE_EMAIL) === false) {
            throw new SmtpException("Adresse {$role} invalide.");
        }
    }

    private function sansSautDeLigne(string $texte): string
    {
        return trim(preg_replace('/[\r\n]+/', ' ', $texte) ?? '');
    }

    private function estHoteLocal(string $host): bool
    {
        return in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '[::1]'], true);
    }

    private function nomEhlo(): string
    {
        $nom = (string)($this->config['ehlo_host'] ?? 'localhost');
        return preg_match('/^[A-Za-z0-9.\-]+$/', $nom) === 1 ? $nom : 'localhost';
    }

    private function resume(string $commande): string
    {
        return strtoupper(strtok($commande, ' ') ?: 'commande') . (str_starts_with($commande, 'MAIL FROM') ? ' FROM' : (str_starts_with($commande, 'RCPT TO') ? ' TO' : ''));
    }

    /** Détail renvoyé par le serveur : une ligne, sans caractère de contrôle, longueur limitée. */
    private function nettoyer(string $texte): string
    {
        return mb_substr(preg_replace('/[^\P{C}]+/u', ' ', $texte) ?? '', 0, 160);
    }
}
