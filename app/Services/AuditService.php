<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public function log(string $action, string $description, ?Model $auditable = null): AuditLog
    {
        return AuditLog::create([
            'user_id'        => Auth::id(),
            'ip_address'     => Request::ip(),
            'action'         => $action,
            'description'    => $description,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id'   => $auditable?->id,
        ]);
    }
}
