<?php

namespace App\Services;

use App\Models\EleveModel;
use App\Models\NotificationModel;
use App\Models\NotificationLogModel;
use App\Models\NotificationPreferenceModel;
use App\Models\UserModel;

class NotificationService
{
    public const TRIGGERS = [
        'note'     => ['label' => 'Nouvelle note',   'icon' => 'journal-check', 'color' => 'primary'],
        'absence'  => ['label' => 'Absence',          'icon' => 'person-x',     'color' => 'warning'],
        'paiement' => ['label' => 'Paiement',         'icon' => 'cash-coin',    'color' => 'success'],
        'annonce'  => ['label' => 'Annonce',           'icon' => 'megaphone',   'color' => 'info'],
    ];

    private NotificationModel           $notifModel;
    private NotificationPreferenceModel $prefModel;
    private NotificationLogModel        $logModel;
    private EmailService                $emailSvc;
    private SmsService                  $smsSvc;
    private UserModel                   $userModel;

    public function __construct()
    {
        $this->notifModel = new NotificationModel();
        $this->prefModel  = new NotificationPreferenceModel();
        $this->logModel   = new NotificationLogModel();
        $this->emailSvc   = new EmailService();
        $this->smsSvc     = new SmsService();
        $this->userModel  = new UserModel();
    }

    // ─── API publique ─────────────────────────────────────────────────────────

    /**
     * Envoie une notification à un utilisateur sur tous ses canaux activés.
     */
    public function notify(
        int    $userId,
        string $trigger,
        string $titre,
        string $message,
        string $lien = ''
    ): void {
        $user = $this->userModel->findById($userId);
        if (!$user) {
            return;
        }

        $prefs      = $this->prefModel->getForUser($userId, $trigger);
        $canInterne = $prefs['interne'] ?? true;
        $canEmail   = $prefs['email']   ?? false;
        $canSms     = $prefs['sms']     ?? false;

        // ── Canal interne ──────────────────────────────────────────────────
        if ($canInterne) {
            try {
                $this->notifModel->createForUser($userId, $trigger, $titre, $message, $lien);
                $this->logModel->log($userId, $trigger, 'interne', $titre, $message, null, 'envoye');
            } catch (\Throwable $e) {
                $this->logModel->log($userId, $trigger, 'interne', $titre, $message, null, 'echoue', $e->getMessage());
            }
        }

        // ── Canal email ────────────────────────────────────────────────────
        if ($canEmail && !empty($user->email)) {
            try {
                $html = $this->emailSvc->buildHtml($titre, $message, $lien);
                $ok   = $this->emailSvc->send($user->email, $titre, $html);
                $this->logModel->log($userId, $trigger, 'email', $titre, $message, $user->email,
                    $ok ? 'envoye' : 'echoue',
                    $ok ? null    : 'Échec de la fonction mail() — vérifier la config serveur'
                );
            } catch (\Throwable $e) {
                $this->logModel->log($userId, $trigger, 'email', $titre, $message, $user->email ?? null, 'echoue', $e->getMessage());
            }
        }

        // ── Canal SMS ──────────────────────────────────────────────────────
        $phone = $user->telephone ?? null;
        if ($canSms && $phone) {
            try {
                $smsText = '[EcoleApp] ' . $titre . ': ' . mb_substr($message, 0, 140);
                $result  = $this->smsSvc->send($phone, $smsText);
                $this->logModel->log($userId, $trigger, 'sms', $titre, $message, $phone,
                    $result['ok'] ? 'envoye' : 'echoue',
                    $result['error']
                );
            } catch (\Throwable $e) {
                $this->logModel->log($userId, $trigger, 'sms', $titre, $message, $phone, 'echoue', $e->getMessage());
            }
        }
    }

    /**
     * Notifie plusieurs utilisateurs en une seule passe.
     */
    public function notifyBulk(array $userIds, string $trigger, string $titre, string $message, string $lien = ''): void
    {
        foreach (array_unique($userIds) as $uid) {
            $this->notify((int)$uid, $trigger, $titre, $message, $lien);
        }
    }

    // ─── Déclencheurs métier ──────────────────────────────────────────────────

    /**
     * Déclenché quand un élève est marqué absent ou en retard.
     */
    public function onAbsence(int $eleveId, string $date, string $type = 'absence'): void
    {
        $eleve = (new EleveModel())->findById($eleveId);
        if (!$eleve) {
            return;
        }

        $dateF = date('d/m/Y', strtotime($date));
        $typeL = $type === 'retard' ? 'retard' : 'absence';
        $titre = ucfirst($typeL) . ' signalé — ' . $dateF;
        $msg   = "Un {$typeL} a été enregistré pour {$eleve->prenom} {$eleve->nom} le {$dateF}.";

        // Notifier le parent
        if (!empty($eleve->parent_id)) {
            $this->notify((int)$eleve->parent_id, 'absence', $titre, $msg, BASE_URL . '/parent/absences');
        }

        // Notifier le compte élève (via email matching)
        if (!empty($eleve->email)) {
            $userEleve = $this->userModel->findByEmail($eleve->email);
            if ($userEleve && ($userEleve->role ?? '') === 'eleve') {
                $this->notify((int)$userEleve->id, 'absence', $titre, $msg, BASE_URL . '/eleve/dashboard');
            }
        }
    }

    /**
     * Déclenché quand un paiement est enregistré.
     */
    public function onPaiement(int $eleveId, float $montant, string $fraisNom = ''): void
    {
        $eleve = (new EleveModel())->findById($eleveId);
        if (!$eleve || empty($eleve->parent_id)) {
            return;
        }

        $montantF = number_format($montant, 0, ',', ' ');
        $titre    = "Paiement reçu — {$montantF} F";
        $msg      = "Un paiement de {$montantF} F a été enregistré pour {$eleve->prenom} {$eleve->nom}"
                  . ($fraisNom ? " ({$fraisNom})" : '') . '.';

        $this->notify((int)$eleve->parent_id, 'paiement', $titre, $msg, BASE_URL . '/parent/paiements');
    }

    /**
     * Déclenché quand des notes / bulletins sont disponibles.
     */
    public function onNote(int $eleveId, string $periodeNom, float $moyenne): void
    {
        $eleve = (new EleveModel())->findById($eleveId);
        if (!$eleve) {
            return;
        }

        $moyF  = number_format($moyenne, 2, ',', '');
        $titre = "Résultats — {$periodeNom}";
        $msg   = "Les résultats de {$eleve->prenom} {$eleve->nom} pour la période « {$periodeNom} » sont disponibles. Moyenne : {$moyF}/20.";

        if (!empty($eleve->parent_id)) {
            $this->notify((int)$eleve->parent_id, 'note', $titre, $msg, BASE_URL . '/parent/bulletin');
        }
        if (!empty($eleve->email)) {
            $userEleve = $this->userModel->findByEmail($eleve->email);
            if ($userEleve && ($userEleve->role ?? '') === 'eleve') {
                $this->notify((int)$userEleve->id, 'note', $titre, $msg, BASE_URL . '/eleve/bulletin');
            }
        }
    }

    /**
     * Déclenché quand une annonce est publiée.
     */
    public function onAnnonce(object $annonce): void
    {
        $roles = match ($annonce->audience ?? 'tous') {
            'parents'     => ['parent'],
            'eleves'      => ['eleve'],
            'enseignants' => ['enseignant'],
            default       => ['parent', 'eleve', 'enseignant', 'secretaire', 'comptable'],
        };

        $titre   = 'Nouvelle annonce : ' . $annonce->titre;
        $message = mb_substr(strip_tags($annonce->contenu ?? ''), 0, 200);
        if (mb_strlen($annonce->contenu ?? '') > 200) {
            $message .= '…';
        }
        $lien = BASE_URL . '/annonces';

        $users   = $this->userModel->findAllWithRoles($roles);
        $userIds = array_map(fn($u) => (int)$u->id, $users);

        $this->notifyBulk($userIds, 'annonce', $titre, $message, $lien);
    }

    /**
     * Envoi de test depuis l'interface admin.
     */
    public function sendTest(int $adminId, string $canal, string $message): bool
    {
        $admin = $this->userModel->findById($adminId);
        if (!$admin) {
            return false;
        }

        $titre = 'Test de notification — Ecole App';

        if ($canal === 'email') {
            if (empty($admin->email)) {
                return false;
            }
            $html = $this->emailSvc->buildHtml($titre, $message);
            $ok   = $this->emailSvc->send($admin->email, $titre, $html);
            $this->logModel->log($adminId, 'annonce', 'email', $titre, $message, $admin->email, $ok ? 'envoye' : 'echoue', $ok ? null : 'Échec mail()');
            return $ok;
        }

        if ($canal === 'sms') {
            $phone = $admin->telephone ?? null;
            if (!$phone) {
                return false;
            }
            $result = $this->smsSvc->send($phone, '[EcoleApp] ' . $message);
            $this->logModel->log($adminId, 'annonce', 'sms', $titre, $message, $phone, $result['ok'] ? 'envoye' : 'echoue', $result['error']);
            return $result['ok'];
        }

        // Canal interne par défaut
        $this->notifModel->createForUser($adminId, 'annonce', $titre, $message);
        $this->logModel->log($adminId, 'annonce', 'interne', $titre, $message, null, 'envoye');
        return true;
    }
}
