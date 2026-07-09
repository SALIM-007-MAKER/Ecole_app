<?php

namespace App\Modules\Finance\Policies;

/**
 * Permissions Finance V2 caisse :
 *   finance.caisse.view        — consulter les sessions et journaux
 *   finance.caisse.ouvrir      — ouvrir une nouvelle session
 *   finance.caisse.fermer      — fermer sa propre session ou toute session (admin)
 *   finance.caisse.mouvement   — enregistrer un mouvement manuel
 *   finance.caisse.rapprocher  — valider le rapprochement du journal
 *   finance.caisse.admin       — voir toutes les sessions (pas seulement les siennes)
 */
class CashRegisterPolicy
{
    public function canView(array $user): bool
    {
        return $this->has($user, 'finance.caisse.view')
            || $this->has($user, 'finance.caisse.ouvrir')
            || $this->has($user, 'finance.caisse.admin');
    }

    public function canOuvrir(array $user): bool
    {
        return $this->has($user, 'finance.caisse.ouvrir');
    }

    public function canFermer(array $user): bool
    {
        return $this->has($user, 'finance.caisse.fermer')
            || $this->has($user, 'finance.caisse.admin');
    }

    public function canEnregistrerMouvement(array $user): bool
    {
        return $this->has($user, 'finance.caisse.mouvement')
            || $this->has($user, 'finance.caisse.ouvrir');
    }

    public function canAnnulerMouvement(array $user): bool
    {
        return $this->has($user, 'finance.caisse.admin')
            || $this->has($user, 'finance.caisse.mouvement');
    }

    public function canRapprocher(array $user): bool
    {
        return $this->has($user, 'finance.caisse.rapprocher')
            || $this->has($user, 'finance.caisse.admin');
    }

    public function canAdmin(array $user): bool
    {
        return $this->has($user, 'finance.caisse.admin');
    }

    public function canViewSession(array $user, object $session): bool
    {
        if ($this->canAdmin($user)) {
            return true;
        }
        return $this->canView($user) && (int)$session->caissier_id === (int)$user['id'];
    }

    private function has(array $user, string $perm): bool
    {
        return in_array($perm, $user['permissions'] ?? [], true);
    }
}
