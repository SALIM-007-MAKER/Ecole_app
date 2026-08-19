<?php

namespace App\Shared\Auth;

use App\Models\EleveModel;
use Core\Session;

/**
 * Restreint l'accès aux dossiers élèves pour les rôles parent/eleve : un
 * parent ne voit que ses propres enfants, un élève ne voit que lui-même.
 * Les rôles staff (admin/directeur/enseignant/secretaire/comptable) ne sont
 * pas restreints ici — leur permission (`requirePermission()`) suffit déjà.
 *
 * Sans ce contrôle, un `eleve_id`/`id` pris tel quel dans l'URL/le POST
 * permet à un parent ou un élève de consulter/justifier le dossier de
 * n'importe quel autre élève (IDOR).
 */
trait EleveScopeTrait
{
    /**
     * @return int[]|null Liste des eleve_id autorisés pour parent/eleve,
     *                     ou null si le rôle courant n'est pas restreint.
     */
    protected function myEleveIds(): ?array
    {
        $user = Session::getUser();
        $role = $user['role'] ?? '';

        if ($role === 'parent') {
            $enfants = (new EleveModel())->findByParent((int)($user['id'] ?? 0));
            return array_map(fn($e) => (int)$e->id, $enfants);
        }

        if ($role === 'eleve') {
            // M007 — eleves.user_id (FK fiable) plutôt que l'email, qui
            // n'est ni UNIQUE ni synchronisé entre `eleves` et `users`.
            $eleve = (new EleveModel())->findByUserId((int)($user['id'] ?? 0));
            return $eleve ? [(int)$eleve->id] : [];
        }

        return null;
    }

    /**
     * Bloque (403) si l'élève ciblé n'appartient pas au périmètre autorisé
     * de l'utilisateur courant. Sans effet pour les rôles staff.
     */
    protected function assertOwnEleve(int $eleveId): void
    {
        $scope = $this->myEleveIds();
        if ($scope !== null && !in_array($eleveId, $scope, true)) {
            \Core\Logger::security('IDOR_BLOCKED', "Tentative d'accès eleve_id={$eleveId} hors périmètre autorisé");
            http_response_code(403);
            $this->render('errors/403', ['title' => 'Accès non autorisé'], 'main');
            exit;
        }
    }
}
