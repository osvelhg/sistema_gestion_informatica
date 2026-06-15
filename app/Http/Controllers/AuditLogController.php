<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($request->get('action'), fn($q, $v) => $q->where('action', $v))
            ->when($request->get('search'), fn($q, $v) => $q->where('description', 'ilike', "%{$v}%"))
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('AuditLogs/Index', [
            'logs'    => $logs,
            'filters' => $request->only(['action', 'search']),
        ]);
    }
}
