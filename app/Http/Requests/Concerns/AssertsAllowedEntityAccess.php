<?php

namespace App\Http\Requests\Concerns;

use App\Support\UserEntityAccess;
use Illuminate\Validation\ValidationException;

trait AssertsAllowedEntityAccess
{
    protected function assertEntityAllowedForUser(mixed $entityId, string $attribute = 'entity_id'): void
    {
        if ($entityId === null || $entityId === '') {
            return;
        }

        $user = $this->user();
        $allowed = UserEntityAccess::allowedEntityIds($user);
        if ($allowed === null) {
            return;
        }

        $id = (int) $entityId;
        if ($allowed === [] || ! in_array($id, array_map('intval', $allowed), true)) {
            throw ValidationException::withMessages([
                $attribute => 'No tiene acceso a esta entidad.',
            ]);
        }
    }
}
