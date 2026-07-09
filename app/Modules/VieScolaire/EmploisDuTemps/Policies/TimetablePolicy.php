<?php

namespace App\Modules\VieScolaire\EmploisDuTemps\Policies;

class TimetablePolicy
{
    public function canView(array $user): bool
    {
        return in_array('timetable.view', $user['permissions'] ?? [], true);
    }

    public function canCreate(array $user): bool
    {
        return in_array('timetable.create', $user['permissions'] ?? [], true);
    }

    public function canUpdate(array $user): bool
    {
        return in_array('timetable.update', $user['permissions'] ?? [], true);
    }

    public function canPublish(array $user): bool
    {
        return in_array('timetable.publish', $user['permissions'] ?? [], true);
    }

    public function canExport(array $user): bool
    {
        return in_array('timetable.export', $user['permissions'] ?? [], true);
    }

    /** EDT modifiable = non archivé. */
    public function canModifyEdt(array $user, array $edt): bool
    {
        return $this->canUpdate($user)
            && $edt['statut'] !== 'archive';
    }

    /** EDT publiable = brouillon uniquement. */
    public function canPublishEdt(array $user, array $edt): bool
    {
        return $this->canPublish($user)
            && $edt['statut'] === 'brouillon';
    }
}
