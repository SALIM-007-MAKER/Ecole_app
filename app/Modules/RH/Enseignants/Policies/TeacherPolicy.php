<?php

namespace App\Modules\RH\Enseignants\Policies;

class TeacherPolicy
{
    public function canView(array $user): bool
    {
        return $this->has($user, 'teacher.view');
    }

    public function canCreate(array $user): bool
    {
        return $this->has($user, 'teacher.create');
    }

    public function canUpdate(array $user): bool
    {
        return $this->has($user, 'teacher.update');
    }

    public function canAssign(array $user): bool
    {
        return $this->has($user, 'teacher.assign');
    }

    public function canExport(array $user): bool
    {
        return $this->has($user, 'teacher.export');
    }

    private function has(array $user, string $perm): bool
    {
        return in_array($perm, $user['permissions'] ?? [], true);
    }
}
