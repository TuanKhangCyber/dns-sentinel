<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CreditController extends Controller
{
    public function index(Request $request): View
    {
        $types = CreditTransaction::distinct()->orderBy('type')->pluck('type');
        $features = CreditTransaction::whereNotNull('feature_code')->distinct()->orderBy('feature_code')->pluck('feature_code');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in($types->all())],
            'feature' => ['nullable', 'string', 'max:80'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $transactions = CreditTransaction::query()->with(['user:id,name,email', 'actor:id,name'])
            ->when(filled($filters['q'] ?? null), fn ($query) => $query->whereHas('user', fn ($user) => $user->where('name', 'like', '%'.$filters['q'].'%')->orWhere('email', 'like', '%'.$filters['q'].'%')))
            ->when(filled($filters['type'] ?? null), fn ($query) => $query->where('type', $filters['type']))
            ->when(filled($filters['feature'] ?? null), fn ($query) => $query->where('feature_code', $filters['feature']))
            ->when(filled($filters['from'] ?? null), fn ($query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when(filled($filters['to'] ?? null), fn ($query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->latest()->paginate(30)->withQueryString();

        return view('admin.credits.index', compact('transactions', 'types', 'features'));
    }
}
