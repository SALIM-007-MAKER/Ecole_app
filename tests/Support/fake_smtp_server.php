<?php
/**
 * Faux serveur SMTP pour les tests (127.0.0.1 uniquement, aucun envoi réel).
 *
 *   php tests/Support/fake_smtp_server.php <fichier_port> <fichier_transcription> [nb_connexions=1]
 *
 * Écrit le port choisi dans <fichier_port> dès que le serveur écoute, puis une transcription JSON (commandes reçues, message DATA) dans
 * <fichier_transcription> après CHAQUE connexion. Comportement par variables d'environnement :
 *   FAKE_SMTP_AUTH   plain | login | none   mécanismes annoncés (défaut plain)
 *   FAKE_SMTP_USER / FAKE_SMTP_PASS         identifiants acceptés (défaut u / p)
 *   FAKE_SMTP_FAIL   none | auth | rcpt | mail   étape refusée (535 / 550 / 553)
 *   FAKE_SMTP_DELAY_MS   délai (ms) avant la bannière : simule un serveur lent (mesure du temps de réponse)
 *   FAKE_SMTP_STARTTLS   1 = annonce STARTTLS (le faux serveur ne sait pas chiffrer : sert à tester le refus de continuer en clair)
 */

[$_, $fichierPort, $fichierTranscription, $nb] = $argv + [null, '', '', '1'];
$serveur = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if ($serveur === false) { fwrite(STDERR, "écoute impossible : $errstr\n"); exit(1); }
$port = (int)explode(':', stream_socket_get_name($serveur, false))[1];
file_put_contents($fichierPort, (string)$port);

$auth  = getenv('FAKE_SMTP_AUTH') ?: 'plain';
$user  = getenv('FAKE_SMTP_USER') ?: 'u';
$pass  = getenv('FAKE_SMTP_PASS') ?: 'p';
$echec = getenv('FAKE_SMTP_FAIL') ?: 'none';
$tls   = getenv('FAKE_SMTP_STARTTLS') === '1';
$delai = (int)(getenv('FAKE_SMTP_DELAY_MS') ?: 0);

$transcriptions = [];
for ($i = 0; $i < (int)$nb; $i++) {
    $client = @stream_socket_accept($serveur, 20);
    if ($client === false) { break; }
    stream_set_timeout($client, 10);
    $t = ['commandes' => [], 'message' => null, 'auth_ok' => false];
    $envoyer = static fn(string $l) => fwrite($client, $l . "\r\n");
    if ($delai > 0) { usleep($delai * 1000); }
    $envoyer('220 fake.smtp ESMTP pret');
    $enData = false; $donnees = '';
    while (($ligne = fgets($client, 8192)) !== false) {
        $ligne = rtrim($ligne, "\r\n");
        if ($enData) {
            if ($ligne === '.') {
                $enData = false;
                $t['message'] = $donnees;
                $envoyer('250 2.0.0 OK message accepte');
            } else {
                $donnees .= $ligne . "\r\n";
            }
            continue;
        }
        $t['commandes'][] = $ligne;
        $cmd = strtoupper(strtok($ligne, ' '));
        switch ($cmd) {
            case 'EHLO':
                $envoyer('250-fake.smtp salut');
                if ($tls) { $envoyer('250-STARTTLS'); }
                if ($auth === 'plain') { $envoyer('250-AUTH PLAIN LOGIN'); }
                elseif ($auth === 'login') { $envoyer('250-AUTH LOGIN'); }
                $envoyer('250 8BITMIME');
                break;
            case 'AUTH':
                $parts = explode(' ', $ligne);
                if (strtoupper($parts[1] ?? '') === 'PLAIN') {
                    $ok = ($echec !== 'auth') && base64_decode($parts[2] ?? '') === "\0{$user}\0{$pass}";
                    $t['auth_ok'] = $ok;
                    $envoyer($ok ? '235 2.7.0 Authentification reussie' : '535 5.7.8 Identifiants refuses');
                } else {   // LOGIN
                    $envoyer('334 VXNlcm5hbWU6');
                    $u = base64_decode(rtrim((string)fgets($client, 1024), "\r\n"));
                    $envoyer('334 UGFzc3dvcmQ6');
                    $p = base64_decode(rtrim((string)fgets($client, 1024), "\r\n"));
                    $ok = ($echec !== 'auth') && $u === $user && $p === $pass;
                    $t['auth_ok'] = $ok;
                    $t['commandes'][] = '[LOGIN identifiants recus]';
                    $envoyer($ok ? '235 2.7.0 Authentification reussie' : '535 5.7.8 Identifiants refuses');
                }
                break;
            case 'MAIL':
                $envoyer($echec === 'mail' ? '553 5.1.8 expediteur refuse' : '250 2.1.0 OK');
                break;
            case 'RCPT':
                $envoyer($echec === 'rcpt' ? '550 5.1.1 destinataire inconnu' : '250 2.1.5 OK');
                break;
            case 'DATA':
                $enData = true; $donnees = '';
                $envoyer('354 Fin par <CRLF>.<CRLF>');
                break;
            case 'QUIT':
                $envoyer('221 2.0.0 Au revoir');
                break 2;
            default:
                $envoyer('502 5.5.2 commande inconnue');
        }
    }
    fclose($client);
    $transcriptions[] = $t;
    file_put_contents($fichierTranscription, json_encode($transcriptions, JSON_UNESCAPED_UNICODE));
}
