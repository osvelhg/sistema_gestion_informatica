<?php

namespace App\Policies;

use App\Models\EquipmentFile;
use App\Models\User;
use App\Support\UserEntityAccess;

class EquipmentFilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('expedientes.index');
    }

    public function view(User $user, EquipmentFile $file): bool
    {
        return $user->can('expedientes.index') && $this->entityAllowed($user, $file->entity_id);
    }

    public function create(User $user): bool
    {
        return $user->can('expedientes.store');
    }

    public function update(User $user, EquipmentFile $file): bool
    {
        return $user->can('expedientes.update') && $this->entityAllowed($user, $file->entity_id);
    }

    public function delete(User $user, EquipmentFile $file): bool
    {
        return $user->can('expedientes.destroy') && $this->entityAllowed($user, $file->entity_id);
    }

    public function move(User $user, EquipmentFile $file): bool
    {
        return $user->can('expedientes.move') && $this->entityAllowed($user, $file->entity_id);
    }

    private function entityAllowed(User $user, ?int $entityId): bool
    {
        $allowed = UserEntityAccess::allowedEntityIds($user);
        if ($allowed === null) {
            return true;
        }
        if ($entityId === null) {
            return false;
        }

        return $allowed !== [] && in_array((int) $entityId, array_map('intval', $allowed), true);
    }
}
