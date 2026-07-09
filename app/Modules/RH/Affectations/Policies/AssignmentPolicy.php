<?php

declare(strict_types=1);

namespace App\Modules\RH\Affectations\Policies;

class AssignmentPolicy
{
    public function canView(array $user): bool   { return $this->has($user, 'assignment.view'); }
    public function canCreate(array $user): bool { return $this->has($user, 'assignment.create'); }
    public function canUpdate(array $user): bool { return $this->has($user, 'assignment.update'); }
    public function canArchive(array $user): bool{ return $this->has($user, 'assignment.archive'); }
    public function canExport(array $user): bool { return $this->has($user, 'assignment.export'); }

    private function has(array $user, string $perm): bool
    {
        return in_array($perm, $user['permissions'] ?? [], true);
    }
}
