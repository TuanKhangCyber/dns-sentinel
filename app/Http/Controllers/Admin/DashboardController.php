<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\Feature;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $metrics = [
            'users' => User::count(), 'free_users' => User::whereHas('plan', fn ($q) => $q->where('code', 'free'))->count(),
            'plus_users' => User::whereHas('plan', fn ($q) => $q->where('code', 'plus'))->count(),
            'active_users' => User::where('status', 'active')->count(), 'suspended_users' => User::where('status', 'suspended')->count(),
            'scans' => Scan::count(), 'credits_granted' => CreditTransaction::where('amount', '>', 0)->sum('amount'),
            'credits_used' => abs((int) CreditTransaction::where('type', 'usage')->sum('amount')),
            'features_enabled' => Feature::where('is_enabled', true)->count(), 'features_disabled' => Feature::where('is_enabled', false)->count(),
        ];
        $scanStatuses = Scan::query()->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');

        return view('admin.dashboard', compact('metrics', 'scanStatuses'));
    }
}
