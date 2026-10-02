<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $action = $request->string('action')->trim()->toString();
        $from = $request->date('from');
        $to = $request->date('to');

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
