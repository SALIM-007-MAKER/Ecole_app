<?php

namespace App\Modules\Academique\Policies;

use App\Models\EleveModel;
use App\Modules\Academique\Services\TeacherScope;
use App\Modules\Scolarite\Repositories\FamilleRepository;

class BulletinPolicy
{
    private FamilleRepository $familleRepo;
    private EleveModel $eleveModel;
    private TeacherScope $teacherScope;

    public function __construct(
        ?FamilleRepository $familleRepo = null,
        ?EleveModel $eleveModel = null,
        ?TeacherScope $teacherScope = null,
    ) {
        $this->familleRepo  = $familleRepo  ?? new FamilleRepository();
        $this->eleveModel   = $eleveModel   ?? new EleveModel();
        $this->teacherScope = $teacherScope ?? new TeacherScope();
    }

    /**
     * Rôles dont l'accès aux bulletins est limité par PROPRIÉTÉ ou par PÉRIMÈTRE (et non par
     * la seule permission) : un permission « bulletins.view » ne suffit jamais pour eux.
     */
    private const ROLES_RESTREINTS = ['parent', 'eleve', 'enseignant'];

    /**
     * Pages de résultats de classe / classement général de l'ancien module /bulletins
     * (grille de toutes les matières, rang, moyenne générale de TOUTE la classe) : réservées
     * au personnel de direction/secrétariat. Parents et élèves ont leurs propres écrans ;
     * un enseignant ne doit voir que ses classes et ses matières (écrans V2 filtrés), jamais
     * le classement général — qui révélerait des résultats hors de son périmètre.
     */
    public function canBrowseClassResults(array $user): bool
    {
        if (in_array($user['role'] ?? '', self::ROLES_RESTREINTS, true)) {
            return false;
        }
        return $this->has($user, 'bulletins.view') || $this->has($user, 'academique.bulletin.view');
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
    /**
     * Peut consulter le bulletin d'UN élève précis (aperçu V1 comme impression V2).
     *
     *  - parent     : uniquement les enfants qui lui sont rattachés ;
     *  - élève      : uniquement son propre bulletin ;
     *  - enseignant : uniquement les élèves des classes où il est affecté pour l'année
     *                 scolaire de la période demandée (`$periodeId`, obligatoire pour lui) ;
     *  - autres rôles : selon leurs permissions de consultation de bulletins.
     *
     * Refus par défaut : identifiant d'élève ou de période inexistant, année inconnue, compte
     * sans fiche professeur, etc. ne donnent jamais accès.
     */
    public function canViewForParent(array $user, int $eleveId, ?int $periodeId = null): bool
    {
        $role = $user['role'] ?? '';

        if ($role === 'parent') {
            return $this->familleRepo->estParentDe((int)$user['id'], $eleveId);
        }

        if ($role === 'eleve') {
            $eleve = $this->eleveModel->findByUserId((int)($user['id'] ?? 0));
            return $eleve !== false && (int)$eleve->id === $eleveId;
        }

        if ($role === 'enseignant') {
            if (!$this->canView($user) || $periodeId === null) {
                return false;
            }
            $annee = $this->teacherScope->anneeDeLaPeriode($periodeId);
            if ($annee === null) {
                return false;
            }
            return $this->teacherScope->enseigneEleve(
                (int)($user['id'] ?? 0),
                (int)($user['etablissement_id'] ?? 0),
                $eleveId,
                $annee
            );
        }

        // Direction, secrétariat…: la permission ne suffit pas — l'élève doit appartenir à l'établissement de l'utilisateur (Lot 3)
        if (!$this->eleveDeLEtablissement($user, $eleveId)) {
            return false;
        }
        return $this->canView($user);
    }

    /** L'élève existe-t-il dans l'établissement de la session ? Refus par défaut (élève inconnu, établissement absent). */
    private function eleveDeLEtablissement(array $user, int $eleveId): bool
    {
        $etab = (int)($user['etablissement_id'] ?? 0);
        if ($etab <= 0) {
            return false;
        }
        $row = $this->eleveModel->queryOne('SELECT etablissement_id FROM eleves WHERE id = ?', [$eleveId]);
        return $row !== false && $row !== null && (int)$row->etablissement_id === $etab;
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
