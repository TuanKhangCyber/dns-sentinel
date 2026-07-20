<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request, AuditService $auditService): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $audits = AuditLog::query()->with('actor:id,name,email')
            ->when(filled($filters['q'] ?? null), function ($query) use ($filters): void {
                $query->where(function ($nested) use ($filters): void {
                    $nested->where('target_type', 'like', '%'.$filters['q'].'%')
                        ->orWhereHas('actor', fn ($actor) => $actor->where('name', 'like', '%'.$filters['q'].'%')->orWhere('email', 'like', '%'.$filters['q'].'%'));
                });
            })
            ->when(filled($filters['action'] ?? null), fn ($query) => $query->where('action', $filters['action']))
            ->when(filled($filters['from'] ?? null), fn ($query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->latest()->paginate(30)->withQueryString();
        $audits->getCollection()->each(function (AuditLog $audit) use ($auditService): void {
            $audit->setAttribute('before', $auditService->sanitize($audit->before ?? []));
            $audit->setAttribute('after', $auditService->sanitize($audit->after ?? []));
        });

        return view('admin.audit.index', [
            'audits' => $audits,
            'actions' => AuditLog::distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
