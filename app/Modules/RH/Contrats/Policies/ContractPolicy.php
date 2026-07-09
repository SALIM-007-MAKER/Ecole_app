<?php

declare(strict_types=1);

namespace App\Modules\RH\Contrats\Policies;

class ContractPolicy
{
    public function canView(array $user): bool      { return $this->has($user, 'contract.view'); }
    public function canCreate(array $user): bool    { return $this->has($user, 'contract.create'); }
    public function canUpdate(array $user): bool    { return $this->has($user, 'contract.update'); }
    public function canRenew(array $user): bool     { return $this->has($user, 'contract.renew'); }
    public function canTerminate(array $user): bool { return $this->has($user, 'contract.terminate'); }
    public function canArchive(array $user): bool   { return $this->has($user, 'contract.archive'); }
    public function canExport(array $user): bool    { return $this->has($user, 'contract.export'); }

    private function has(array $user, string $perm): bool
    {
        return in_array($perm, $user['permissions'] ?? [], true);
    }
}
