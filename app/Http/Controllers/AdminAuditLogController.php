<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'action' => ['nullable', 'string', 'max:64', 'alpha_dash'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $action = trim((string) ($validated['action'] ?? ''));
        $from = isset($validated['from']) ? $request->date('from') : null;
        $to = isset($validated['to']) ? $request->date('to') : null;

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $acoes = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('admin.audit.index', compact('logs', 'acoes', 'action', 'from', 'to'));
    }
}
