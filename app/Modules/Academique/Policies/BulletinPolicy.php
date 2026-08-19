<?php

namespace App\Modules\Academique\Policies;

use App\Models\EleveModel;
use App\Modules\Scolarite\Repositories\FamilleRepository;

class BulletinPolicy
{
    private FamilleRepository $familleRepo;
    private EleveModel $eleveModel;

    public function __construct(?FamilleRepository $familleRepo = null, ?EleveModel $eleveModel = null)
    {
        $this->familleRepo = $familleRepo ?? new FamilleRepository();
        $this->eleveModel  = $eleveModel  ?? new EleveModel();
    }

    /** Peut générer un bulletin (admin, directeur, prof principal). */
    public function canGenerate(array $user): bool
    {
        return $this->has($user, 'academique.bulletin.generate')
            || $this->has($user, 'academique.bulletin.admin');
    }

    /** Peut visualiser un bulletin (enseignant, parent avec accès). */
    public function canView(array $user): bool
    {
        return $this->has($user, 'academique.bulletin.view')
            || $this->has($user, 'academique.bulletin.generate')
            || $this->has($user, 'academique.bulletin.admin');
    }

    /**
     * AN-C-002 — Un parent ne peut visualiser que le bulletin de SES
     * propres enfants (lien direct `eleves.parent_id` ou co-parent via une
     * famille partagée, cf. FamilleRepository::estParentDe()) ; un élève ne
     * peut visualiser que SON PROPRE bulletin, via `eleves.user_id`
     * (M007 — FK 1:1 fiable vers `users.id`, même résolution que
     * EspaceEleveController::getEleve() ; remplace l'ancien rapprochement
     * par email, abandonné car `eleves.email` n'était ni UNIQUE ni
     * synchronisé avec `users.email`).
     *
     * Les rôles `parent` et `eleve` reçoivent tous deux `academique.
     * bulletin.view` en RBAC pour accéder À LA FONCTIONNALITÉ (contrairement
     * à un rôle sans accès aux bulletins), mais cette permission ne doit
     * jamais, à elle seule, autoriser l'accès au bulletin d'un élève tiers
     * — d'où la vérification d'ownership AVANT canView() pour ces deux
     * rôles précis (l'inverse laisserait la permission large primer et
     * annulerait la restriction).
     */
    public function canViewForParent(array $user, int $eleveId): bool
    {
        $role = $user['role'] ?? '';

        if ($role === 'parent') {
            return $this->familleRepo->estParentDe((int)$user['id'], $eleveId);
        }

        if ($role === 'eleve') {
            $eleve = $this->eleveModel->findByUserId((int)($user['id'] ?? 0));
            return $eleve !== false && (int)$eleve->id === $eleveId;
        }

        return $this->canView($user);
    }

    /** Peut publier un bulletin (le rendre visible aux parents/élèves). */
    public function canPublish(array $user): bool
    {
        return $this->has($user, 'academique.bulletin.publish')
            || $this->has($user, 'academique.bulletin.admin');
    }

    /** Peut archiver un bulletin. */
    public function canArchive(array $user): bool
    {
        return $this->has($user, 'academique.bulletin.archive')
            || $this->has($user, 'academique.bulletin.admin');
    }

    /** Peut ajouter ou modifier l'appréciation du directeur. */
    public function canAddDirecteurAppreciation(array $user): bool
    {
        return $this->has($user, 'academique.bulletin.admin');
    }

    /** Peut exporter en PDF. */
    public function canExport(array $user): bool
    {
        return $this->has($user, 'academique.bulletin.export')
            || $this->has($user, 'academique.bulletin.generate')
            || $this->has($user, 'academique.bulletin.admin');
    }

    /** Peut visualiser via token de vérification (public, sans auth). */
    public function canVerify(): bool
    {
        return true;
    }

    private function has(array $user, string $permission): bool
    {
        $perms = $user['permissions'] ?? [];
        return in_array($permission, $perms, true)
            || in_array('*', $perms, true);
    }
}
