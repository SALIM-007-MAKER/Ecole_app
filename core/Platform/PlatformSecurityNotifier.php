<?php

declare(strict_types=1);

namespace Core\Platform;

use App\Services\EmailService;
use App\Shared\Http\ClientIp;
use Core\Database;
use Core\Logger;
use PDO;

/**
 * Alertes de sécurité par e-mail envoyées à un OPÉRATEUR de la plateforme.
 *
 * Événements
 *  - toujours envoyés (non désactivables) : mot de passe modifié / réinitialisé, 2FA activée / désactivée / réinitialisée, codes de secours
 *    régénérés, code de secours utilisé pour se connecter ;
 *  - nouvelle connexion : réglage « Alertes de connexion » (PlatformSettings::ALERTES_CONNEXION, activé par défaut).
 *
 * Garanties
 *  - L'alerte n'est JAMAIS bloquante : aucune exception ne sort d'ici, l'opération sécurisée (connexion, activation de la 2FA…) a déjà eu lieu.
 *    Un échec d'envoi est consigné au journal technique et au journal d'audit global (`operator.notification.failed`, sans adresse ni contenu).
 *  - Le message ne contient AUCUN secret (ni mot de passe, ni code, ni lien de connexion) : seulement ce qui s'est passé, quand, d'où.
 *  - Envoi différé APRÈS la réponse au navigateur (SMTP = plusieurs secondes) ; immédiat en ligne de commande.
 *  - Inactif tant qu'aucun envoi réel n'est possible (SMTP non configuré) : rien n'est tenté, rien n'est journalisé en échec (voir `disponible()`,
 *    affiché sur la page « Sécurité (2FA) »).
 *  - Un opérateur sans adresse e-mail n'est pas alerté (aucune erreur).
 */
final class PlatformSecurityNotifier
{
    public const CONNEXION = 'connexion';
    public const CODE_SECOURS_UTILISE = 'code_secours_utilise';
    public const MOT_DE_PASSE_MODIFIE = 'mot_de_passe_modifie';
    public const MOT_DE_PASSE_REINITIALISE = 'mot_de_passe_reinitialise';
    public const DEUX_FACTEURS_ACTIVEE = '2fa_activee';
    public const DEUX_FACTEURS_DESACTIVEE = '2fa_desactivee';
    public const DEUX_FACTEURS_REINITIALISEE = '2fa_reinitialisee';
    public const CODES_REGENERES = 'codes_regeneres';

    /** @var array<string,array{objet:string,titre:string,texte:string}> */
    private const MESSAGES = [
        self::CONNEXION => [
            'objet' => 'Nouvelle connexion à votre compte opérateur',
            'titre' => 'Nouvelle connexion',
            'texte' => 'Une connexion à votre compte opérateur de la plateforme vient d\'avoir lieu.',
        ],
        self::CODE_SECOURS_UTILISE => [
            'objet' => 'Un code de secours a été utilisé sur votre compte opérateur',
            'titre' => 'Code de secours utilisé',
            'texte' => 'Un de vos codes de secours vient d\'être utilisé pour vous connecter (il ne fonctionne plus). Pensez à en régénérer.',
        ],
        self::MOT_DE_PASSE_MODIFIE => [
            'objet' => 'Le mot de passe de votre compte opérateur a été modifié',
            'titre' => 'Mot de passe modifié',
            'texte' => 'Le mot de passe de votre compte opérateur vient d\'être modifié.',
        ],
        self::MOT_DE_PASSE_REINITIALISE => [
            'objet' => 'Le mot de passe de votre compte opérateur a été réinitialisé',
            'titre' => 'Mot de passe réinitialisé',
            'texte' => 'Le mot de passe de votre compte opérateur a été réinitialisé par un administrateur. Vous devrez en choisir un nouveau à votre prochaine connexion.',
        ],
        self::DEUX_FACTEURS_ACTIVEE => [
            'objet' => 'La double authentification est activée sur votre compte opérateur',
            'titre' => 'Double authentification activée',
            'texte' => 'La double authentification (2FA) vient d\'être activée sur votre compte opérateur. De nouveaux codes de secours ont été émis.',
        ],
        self::DEUX_FACTEURS_DESACTIVEE => [
            'objet' => 'La double authentification est désactivée sur votre compte opérateur',
            'titre' => 'Double authentification désactivée',
            'texte' => 'La double authentification (2FA) vient d\'être désactivée sur votre compte opérateur. Seul votre mot de passe protège désormais la connexion.',
        ],
        self::DEUX_FACTEURS_REINITIALISEE => [
            'objet' => 'La double authentification de votre compte opérateur a été réinitialisée',
            'titre' => 'Double authentification réinitialisée',
            'texte' => 'La double authentification (2FA) de votre compte opérateur a été retirée par un administrateur. Vous pouvez vous connecter avec votre seul mot de passe : réactivez-la dès que possible.',
        ],
        self::CODES_REGENERES => [
            'objet' => 'Vos codes de secours ont été régénérés',
            'titre' => 'Codes de secours régénérés',
            'texte' => 'De nouveaux codes de secours viennent d\'être émis pour votre compte opérateur. Les anciens ne fonctionnent plus.',
        ],
    ];

    /** @var (callable(string,string,string):bool)|null remplaçant de l'envoi réel (tests) */
    private static $envoyeur = null;

    /** Remplace l'envoi réel (to, objet, html) => envoyé ? Les tests s'en servent pour capter les messages ; null = envoi réel. */
    public static function definirEnvoyeur(?callable $envoyeur): void
    {
        self::$envoyeur = $envoyeur;
    }

    /** Un envoi réel est-il possible ? (SMTP configuré, ou envoyeur de test installé.) */
    public static function disponible(): bool
    {
        if (self::$envoyeur !== null) {
            return true;
        }
        try {
            $c = require ROOT_PATH . '/config/mail.php';
        } catch (\Throwable) {
            return false;
        }
        return ($c['driver'] ?? '') === 'smtp' && trim((string)($c['username'] ?? '')) !== '';
    }

    /**
     * Alerte l'opérateur. Ne lève jamais d'exception.
     *
     * @param array{ip?:string,agent?:string,methode?:string} $contexte facultatif : déduit de la requête courante quand il manque
     */
    public static function notifier(int $operatorId, string $evenement, array $contexte = [], ?PDO $pdo = null): void
    {
        try {
            if (!isset(self::MESSAGES[$evenement]) || $operatorId <= 0 || !self::disponible()) {
                return;
            }
            if ($evenement === self::CONNEXION && PlatformSettings::valeur(PlatformSettings::ALERTES_CONNEXION) !== '1') {
                return;
            }
            $pdo ??= Database::getInstance()->getConnection();
            $s = $pdo->prepare('SELECT email, nom FROM platform_operators WHERE id = ? AND actif = 1');
            $s->execute([$operatorId]);
            $op = $s->fetch(PDO::FETCH_ASSOC);
            $email = is_array($op) ? trim((string)($op['email'] ?? '')) : '';
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                return;
            }

            $contexte += self::contexteRequete();
            $envoi = static function () use ($operatorId, $evenement, $email, $contexte): void {
                self::envoyer($operatorId, $evenement, $email, $contexte);
            };
            self::executer($envoi);
        } catch (\Throwable $e) {
            self::echec($operatorId, $evenement, get_class($e));
        }
    }

    /** Construit et envoie le message. Consigne tout échec. */
    private static function envoyer(int $operatorId, string $evenement, string $to, array $contexte): void
    {
        try {
            $m = self::MESSAGES[$evenement];
            $html = self::html($m['titre'], $m['texte'], $contexte);
            $ok = self::$envoyeur !== null
                ? (bool)(self::$envoyeur)($to, $m['objet'], $html)
                : (new EmailService())->send($to, $m['objet'], $html);
            if (!$ok) {
                self::echec($operatorId, $evenement, 'envoi_refuse');
            }
        } catch (\Throwable $e) {
            self::echec($operatorId, $evenement, get_class($e));
        }
    }

    private static function html(string $titre, string $texte, array $contexte): string
    {
        $fuseau = new \DateTimeZone(PlatformSettings::valeur(PlatformSettings::FUSEAU) ?: 'UTC');
        $quand = (new \DateTimeImmutable('now', $fuseau))->format('d/m/Y à H:i:s (T)');
        $lignes = ['Date : ' . $quand, 'Adresse IP : ' . ($contexte['ip'] ?? 'inconnue')];
        if (($contexte['agent'] ?? '') !== '') {
            $lignes[] = 'Navigateur : ' . $contexte['agent'];
        }
        if (($contexte['methode'] ?? '') !== '') {
            $lignes[] = 'Second facteur : ' . ($contexte['methode'] === 'recovery' ? 'code de secours' : 'application d\'authentification');
        }
        $message = $texte . "\n\n" . implode("\n", $lignes)
            . "\n\nSi vous n'êtes pas à l'origine de cette action, changez immédiatement votre mot de passe et prévenez un autre super administrateur."
            . "\nPar sécurité, ce message ne contient aucun lien.";
        return (new EmailService())->buildHtml($titre, $message);
    }

    /** IP et navigateur de la requête courante ; en ligne de commande, l'origine est le serveur. @return array{ip:string,agent:string} */
    private static function contexteRequete(): array
    {
        if (PHP_SAPI === 'cli' && !isset($_SERVER['REMOTE_ADDR'])) {
            return ['ip' => 'ligne de commande (accès serveur)', 'agent' => ''];
        }
        $ip = ClientIp::resolve($_SERVER, ClientIp::trustedFromEnvironment());
        return [
            'ip'    => $ip !== '' ? $ip : 'inconnue',
            'agent' => mb_substr(preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string)($_SERVER['HTTP_USER_AGENT'] ?? '')) ?? '', 0, 160),
        ];
    }

    /** Web : après la réponse au navigateur ; ligne de commande et tests : tout de suite. */
    private static function executer(callable $envoi): void
    {
        if (PHP_SAPI === 'cli' || self::$envoyeur !== null) {
            $envoi();
            return;
        }
        ignore_user_abort(true);
        register_shutdown_function(static function () use ($envoi): void {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            flush();
            @set_time_limit(60);
            $envoi();
        });
    }

    private static function echec(int $operatorId, string $evenement, string $cause): void
    {
        try {
            Logger::error("[MAIL] Alerte de sécurité « {$evenement} » non envoyée ({$cause}) pour l'opérateur #{$operatorId}.");
        } catch (\Throwable) {
            // le journal technique lui-même est indisponible : rien d'autre à tenter
        }
        try {
            PlatformAuditService::make()->record('operator.notification.failed', [
                'actor' => ['type' => 'system', 'id' => null, 'email' => null, 'niveau' => 'system'],
                'entity_type' => 'platform_operator', 'entity_id' => $operatorId, 'resultat' => 'failure',
                'apres' => ['evenement' => $evenement, 'cause' => $cause],
            ]);
        } catch (\Throwable) {
            // sans trace possible, l'échec reste dans le journal technique
        }
    }
}
