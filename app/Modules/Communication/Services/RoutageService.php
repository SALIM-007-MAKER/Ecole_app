<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use Core\Database;
use Core\Event;
use PDO;

/**
 * Cerveau du module Communication.
 * Reçoit un événement cross-module, résout les destinataires,
 * vérifie leurs préférences et dispatche les canaux appropriés.
 */
class RoutageService
{
    private PDO                 $pdo;
    private NotificationService $notifService;
    private QueueService        $queueService;
    private TemplateService     $templateService;
    private PreferenceService   $prefService;

    public function __construct()
    {
        $this->pdo             = Database::getInstance()->getConnection();
        $this->notifService    = new NotificationService();
        $this->queueService    = new QueueService();
        $this->templateService = new TemplateService();
        $this->prefService     = new PreferenceService();
    }

    /**
     * Point d'entrée principal — appelé par CrossModuleListener.
     *
     * @param Event  $event   L'événement cross-module
     * @param string $type    Le type de notification (ex: 'absence_eleve')
     * @param array  $contexte Données supplémentaires pour les templates
     */
    public function router(Event $event, string $type, array $contexte = []): void
    {
        $data         = $event->toArray();
        $destinataires = $this->resoudreDestinataires($type, $data);

        if (empty($destinataires)) {
            return;
        }

        $config = $this->configPourType($type);

        foreach ($destinataires as $userId) {
            $this->dispatcher((int) $userId, $type, $config, $data, $contexte);
        }
    }

    /** Résout les user_ids destinataires selon le type d'événement */
    private function resoudreDestinataires(string $type, array $data): array
    {
        return match (true) {
            // Événements Vie Scolaire — absence : notifier les parents de l'élève
            in_array($type, ['absence_eleve', 'justificatif_accepte', 'justificatif_refuse'], true)
                => $this->parentsDeEleve((int) ($data['eleve_id'] ?? $data['student_id'] ?? 0)),

            // Événements Finance — notifier le payeur/débiteur
            in_array($type, ['facture_emise', 'paiement_recu', 'remboursement', 'facture_annulee'], true)
                => $this->parentsByUserId((int) ($data['user_id'] ?? $data['parent_id'] ?? 0)),

            // Événements Académique — bulletin : parents + élève
            $type === 'bulletin_publie'
                => $this->parentsEtEleveDeEleve((int) ($data['eleve_id'] ?? 0)),

            // Événements RH — notifier l'employé concerné
            in_array($type, ['conge_approuve', 'conge_rejete', 'contrat_expire',
                              'evaluation_rh', 'certification_expire',
                              'formation_disponible'], true)
                => [(int) ($data['user_id'] ?? $data['employee_user_id'] ?? 0)],

            // Demande de congé — notifier les admins (approbation requise)
            $type === 'conge_demande'
                => $this->usersByRole('admin', (int) ($data['etablissement_id'] ?? 1)),

            // Événements Documents — notifier les destinataires du partage
            $type === 'document_partage'
                => [(int) ($data['destinataire_user_id'] ?? $data['shared_with_user_id'] ?? 0)],

            in_array($type, ['signature_requise', 'document_expire'], true)
                => [(int) ($data['user_id'] ?? 0)],

            // Événements Récompenses — élève + parents
            $type === 'recompense'
                => $this->parentsEtEleveDeEleve((int) ($data['eleve_id'] ?? $data['student_id'] ?? 0)),

            // Activités / emplois du temps — notifier l'utilisateur directement concerné
            in_array($type, ['activite_annulee', 'inscription_activite', 'emploi_du_temps'], true)
                => [(int) ($data['user_id'] ?? 0)],

            default => [],
        };
    }

    private function dispatcher(int $userId, string $type, array $config, array $data, array $contexte): void
    {
        if ($userId <= 0) {
            return;
        }

        $variables = array_merge($data, $contexte);
        $titre     = $config['titre']  ?? ucfirst(str_replace('_', ' ', $type));
        $corps     = $this->rendreCorp($config['template_code'] ?? $type, 'internal', $variables)
                     ?? $config['corps_defaut'] ?? '';
        $module    = $config['module_source'] ?? 'communication';
        $priorite  = $config['priorite']      ?? 'normale';

        // Canal internal (toujours sauf si désactivé)
        if ($this->prefService->peutRecevoir($userId, $type, 'internal')) {
            $this->notifService->creer(
                $userId, $config['type_notif'] ?? 'info',
                $titre, $corps, $module,
                null, null, $config['url_action'] ?? null, $priorite
            );
        }

        // Canal email
        if ($this->prefService->peutRecevoir($userId, $type, 'email')) {
            $user = $this->userEmail($userId);
            if ($user !== null) {
                $contenu = $this->rendreCanalEmail($config['template_code'] ?? $type, $variables);
                $this->queueService->enqueue('email', $type, [
                    'user_id' => $userId,
                    'email'   => $user['email'],
                    'sujet'   => $contenu['sujet'] ?? $titre,
                    'corps'   => $contenu['corps_html'] ?? $corps,
                ], null, [], $priorite);
            }
        }

        // Canal push
        if ($this->prefService->peutRecevoir($userId, $type, 'push')) {
            $pushService = new PushService();
            $pushService->envoyerAUser($userId, $titre, substr($corps, 0, 200), $config['type_notif'] ?? 'info');
        }

        // Canal SMS (haute priorité uniquement, ex: absence)
        if ($priorite === 'haute' || $priorite === 'critique') {
            if ($this->prefService->peutRecevoir($userId, $type, 'sms')) {
                $user = $this->userTel($userId);
                if ($user !== null) {
                    $smsCorp = $this->rendreCanalSms($config['template_code'] ?? $type, $variables);
                    $this->queueService->enqueue('sms', $type, [
                        'user_id' => $userId,
                        'tel'     => $user['telephone'],
                        'corps'   => $smsCorp,
                    ], null, [], $priorite);
                }
            }
        }
    }

    /** Configuration par type de notification */
    private function configPourType(string $type): array
    {
        $configs = [
            'bulletin_publie'      => ['type_notif' => 'success', 'module_source' => 'academique',   'priorite' => 'normale'],
            'absence_eleve'        => ['type_notif' => 'alert',   'module_source' => 'vie_scolaire', 'priorite' => 'haute'],
            'justificatif_accepte' => ['type_notif' => 'success', 'module_source' => 'vie_scolaire', 'priorite' => 'normale'],
            'justificatif_refuse'  => ['type_notif' => 'warning', 'module_source' => 'vie_scolaire', 'priorite' => 'normale'],
            'facture_emise'        => ['type_notif' => 'info',    'module_source' => 'finance',      'priorite' => 'normale'],
            'paiement_recu'        => ['type_notif' => 'success', 'module_source' => 'finance',      'priorite' => 'normale'],
            'remboursement'        => ['type_notif' => 'info',    'module_source' => 'finance',      'priorite' => 'normale'],
            'conge_approuve'       => ['type_notif' => 'success', 'module_source' => 'rh',           'priorite' => 'normale'],
            'conge_rejete'         => ['type_notif' => 'warning', 'module_source' => 'rh',           'priorite' => 'normale'],
            'conge_demande'        => ['type_notif' => 'info',    'module_source' => 'rh',           'priorite' => 'normale'],
            'contrat_expire'       => ['type_notif' => 'warning', 'module_source' => 'rh',           'priorite' => 'haute'],
            'document_partage'     => ['type_notif' => 'info',    'module_source' => 'documents',    'priorite' => 'normale'],
            'signature_requise'    => ['type_notif' => 'alert',   'module_source' => 'documents',    'priorite' => 'haute'],
            'recompense'           => ['type_notif' => 'success', 'module_source' => 'vie_scolaire', 'priorite' => 'basse'],
            'formation_disponible' => ['type_notif' => 'info',    'module_source' => 'rh',           'priorite' => 'basse'],
        ];

        return $configs[$type] ?? ['type_notif' => 'info', 'module_source' => 'communication', 'priorite' => 'normale'];
    }

    // ── Résolution des destinataires ──────────────────────────

    private function parentsDeEleve(int $eleveId): array
    {
        if ($eleveId <= 0) {
            return [];
        }
        // Cherche dans les tables familles (structure V2)
        $st = $this->pdo->prepare(
            "SELECT DISTINCT f.user_id FROM familles f
             INNER JOIN famille_membre_liens l ON l.famille_id = f.id
             WHERE l.eleve_id = :eid AND f.user_id IS NOT NULL"
        );
        $st->execute([':eid' => $eleveId]);
        $rows = $st->fetchAll(PDO::FETCH_COLUMN);

        // Fallback V1 : table eleves.parent_user_id
        if (empty($rows)) {
            $st2 = $this->pdo->prepare(
                "SELECT parent_user_id FROM eleves WHERE id = :eid AND parent_user_id IS NOT NULL LIMIT 1"
            );
            $st2->execute([':eid' => $eleveId]);
            $r = $st2->fetchColumn();
            if ($r) {
                $rows = [(int) $r];
            }
        }

        return array_filter(array_map('intval', $rows));
    }

    private function parentsEtEleveDeEleve(int $eleveId): array
    {
        $parents = $this->parentsDeEleve($eleveId);
        $st = $this->pdo->prepare("SELECT user_id FROM eleves WHERE id = :eid AND user_id IS NOT NULL LIMIT 1");
        $st->execute([':eid' => $eleveId]);
        $eleveUserId = $st->fetchColumn();
        if ($eleveUserId) {
            $parents[] = (int) $eleveUserId;
        }
        return array_unique(array_filter($parents));
    }

    private function parentsByUserId(int $userId): array
    {
        return $userId > 0 ? [$userId] : [];
    }

    private function usersByRole(string $role, int $etablissementId): array
    {
        $st = $this->pdo->prepare(
            "SELECT id FROM users WHERE role = :role AND etablissement_id = :etab AND actif = 1 LIMIT 10"
        );
        $st->execute([':role' => $role, ':etab' => $etablissementId]);
        return array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id');
    }

    private function userEmail(int $userId): ?array
    {
        $st = $this->pdo->prepare("SELECT email FROM users WHERE id = :uid AND email IS NOT NULL LIMIT 1");
        $st->execute([':uid' => $userId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function userTel(int $userId): ?array
    {
        $st = $this->pdo->prepare("SELECT telephone FROM users WHERE id = :uid AND telephone IS NOT NULL LIMIT 1");
        $st->execute([':uid' => $userId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ── Rendu des templates ───────────────────────────────────

    private function rendreCorp(string $code, string $canal, array $variables): ?string
    {
        $tpl = $this->templateService->trouverParCode($code, $canal);
        if ($tpl === null) {
            return null;
        }
        $rendered = $this->templateService->render($tpl, $variables);
        return $rendered['corps_texte'] ?? null;
    }

    private function rendreCanalEmail(string $code, array $variables): array
    {
        $tpl = $this->templateService->trouverParCode($code, 'email');
        if ($tpl === null) {
            return ['sujet' => null, 'corps_html' => null];
        }
        return $this->templateService->render($tpl, $variables);
    }

    private function rendreCanalSms(string $code, array $variables): string
    {
        $tpl = $this->templateService->trouverParCode($code, 'sms');
        if ($tpl === null) {
            return '';
        }
        $rendered = $this->templateService->render($tpl, $variables);
        return substr($rendered['corps_texte'] ?? '', 0, 160);
    }
}
