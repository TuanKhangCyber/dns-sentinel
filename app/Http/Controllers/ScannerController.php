<?php

namespace App\Http\Controllers;

use App\Models\Scan;
use App\Services\FeatureAccessService;
use App\Services\MembershipService;
use App\Services\Scanner\ScanComparisonService;
use App\Services\Scanner\ScanProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScannerController extends Controller
{
    public function __construct(
        private readonly ScanProfileService $profiles,
        private readonly ScanComparisonService $comparisons,
        private readonly FeatureAccessService $features,
        private readonly MembershipService $memberships,
    ) {}

    public function index(): View
    {
        $user = request()->user();

        return view('scanner.index', [
            'profiles' => $this->profiles->all(),
            'nmapAccess' => $this->features->status($user, 'nmap_scan'),
            'vulnerabilityAccess' => $this->features->status($user, 'vulnerability_scan'),
        ]);
    }

    public function history(Request $request): View
    {
        $this->features->authorize($request->user(), 'scan_history');
        $validated = $request->validate([
            'target' => ['nullable', 'string', 'max:253'],
            'profile' => ['nullable', 'string', Rule::in(array_keys(config('scanner.profiles', [])))],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'severity' => ['nullable', Rule::in(['critical', 'high', 'medium', 'low', 'informational'])],
        ]);

        $scans = Scan::query()->where('user_id', $request->user()->id)
            ->where('created_at', '>=', now()->subDays($this->memberships->effectivePlan($request->user())->history_retention_days))
            ->when(filled($validated['target'] ?? null), fn ($query) => $query->where('target', 'like', '%'.$validated['target'].'%'))
            ->when(filled($validated['profile'] ?? null), fn ($query) => $query->where('profile', $validated['profile']))
            ->when(filled($validated['date_from'] ?? null), fn ($query) => $query->whereDate('created_at', '>=', $validated['date_from']))
            ->when(filled($validated['date_to'] ?? null), fn ($query) => $query->whereDate('created_at', '<=', $validated['date_to']))
            ->when(filled($validated['severity'] ?? null), fn ($query) => $query->whereHas('findings', fn ($findings) => $findings->where('severity', $validated['severity'])))
            ->latest()->paginate(20)->withQueryString();

        return view('scanner.history', ['scans' => $scans, 'profiles' => $this->profiles->all()]);
    }

    public function show(Request $request, Scan $scan): View
    {
        Gate::authorize('view', $scan);
        $this->features->authorize($request->user(), 'scan_history');

        return view('scanner.show', ['scan' => $scan]);
    }

    public function compare(Request $request): View
    {
        $this->features->authorize($request->user(), 'scan_compare');
        $validated = $request->validate(['left' => ['required', 'integer'], 'right' => ['required', 'integer', 'different:left']]);
        $left = Scan::where('user_id', $request->user()->id)->findOrFail($validated['left']);
        $right = Scan::where('user_id', $request->user()->id)->findOrFail($validated['right']);
        $comparison = $this->comparisons->compare($left, $right);

        return view('scanner.compare', compact('left', 'right', 'comparison'));
    }
}
