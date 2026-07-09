<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\DTO\DiffusionDTO;
use App\Modules\Communication\DTO\GroupeDTO;
use App\Modules\Communication\Events\DiffusionEnvoyee;
use App\Modules\Communication\Repositories\GroupeRepository;
use Core\Database;
use Core\EventDispatcher;
use PDO;

class DiffusionService
{
    private GroupeRepository    $groupeRepo;
    private QueueService        $queueService;
    private NotificationService $notifService;
    private PDO                 $pdo;

    public function __construct()
    {
        $this->groupeRepo   = new GroupeRepository();
        $this->queueService = new QueueService();
        $this->notifService = new NotificationService();
        $this->pdo          = Database::getInstance()->getConnection();
    }

    public function diffuser(DiffusionDTO $dto, int $userId, int $etablissementId = 1): array
    {
        $userIds = $this->resoudreDestinataires($dto, $etablissementId);
        $queued  = 0;

        foreach ($userIds as $uid) {
            foreach ($dto->canaux as $canal) {
                if ($canal === 'internal') {
                    $this->notifService->creer(
                        $uid, 'info', $dto->sujet, $dto->corps,
                        'communication', null, null, null, $dto->priorite
                    );
                    $queued++;
                } else {
                    $destinataire = $this->resolveContactForUser($uid, $canal);
                    if ($destinataire !== null) {
                        $this->queueService->enqueue(
                            $canal, 'diffusion', $destinataire,
                            $dto->templateId, $dto->variables, $dto->priorite,
                            null, $etablissementId
                        );
                        $queued++;
                    }
                }
            }
        }

        EventDispatcher::dispatch(new DiffusionEnvoyee(null, $dto->sujet, count($userIds), $userId));
        return ['queued' => $queued, 'total' => count($userIds)];
    }

    public function resoudreDestinataires(DiffusionDTO $dto, int $etablissementId = 1): array
    {
        return match ($dto->cibleType) {
            'groupe'       => $this->groupeRepo->memberUserIds($dto->cibleId ?? 0),
            'role'         => $this->usersByRole($dto->roleCode ?? '', $etablissementId),
            'classe'       => $this->usersByClasse($dto->cibleId ?? 0),
            'tous'         => $this->allUsers($etablissementId),
            default        => [],
        };
    }

    public function creerGroupe(GroupeDTO $dto, int $userId, int $etablissementId = 1): int
    {
        return $this->groupeRepo->insert([
            'nom'             => $dto->nom,
            'description'     => $dto->description,
            'type'            => $dto->type,
            'criteres'        => $dto->criteres,
            'etablissement_id'=> $etablissementId,
            'created_by'      => $userId,
        ]);
    }

    public function modifierGroupe(int $groupeId, GroupeDTO $dto, int $userId): void
    {
        $this->groupeRepo->update($groupeId, ['nom' => $dto->nom, 'description' => $dto->description]);
    }

    public function supprimerGroupe(int $groupeId, int $userId): void
    {
        $this->groupeRepo->softDelete($groupeId);
    }

    public function ajouterMembres(int $groupeId, array $userIds, int $userId): void
    {
        foreach ($userIds as $uid) {
            $this->groupeRepo->insertMembre($groupeId, (int) $uid, $userId);
        }
    }

    public function retirerMembre(int $groupeId, int $userId, int $adminId): void
    {
        $this->groupeRepo->deleteMembre($groupeId, $userId);
    }

    private function usersByRole(string $role, int $etablissementId): array
    {
        $st = $this->pdo->prepare(
            "SELECT id FROM users WHERE role = :role AND etablissement_id = :etab AND actif = 1"
        );
        $st->execute([':role' => $role, ':etab' => $etablissementId]);
        return array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id');
    }

    private function usersByClasse(int $classeId): array
    {
        $st = $this->pdo->prepare(
            "SELECT user_id FROM eleves WHERE classe_id = :cid AND user_id IS NOT NULL"
        );
        $st->execute([':cid' => $classeId]);
        return array_column($st->fetchAll(PDO::FETCH_ASSOC), 'user_id');
    }

    private function allUsers(int $etablissementId): array
    {
        $st = $this->pdo->prepare(
            "SELECT id FROM users WHERE etablissement_id = :etab AND actif = 1"
        );
        $st->execute([':etab' => $etablissementId]);
        return array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id');
    }

    private function resolveContactForUser(int $userId, string $canal): ?array
    {
        $st = $this->pdo->prepare("SELECT email, telephone FROM users WHERE id = :uid LIMIT 1");
        $st->execute([':uid' => $userId]);
        $user = $st->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return null;
        }
        return match ($canal) {
            'email' => !empty($user['email']) ? ['user_id' => $userId, 'email' => $user['email']] : null,
            'sms'   => !empty($user['telephone']) ? ['user_id' => $userId, 'tel' => $user['telephone']] : null,
            default => null,
        };
    }
}
