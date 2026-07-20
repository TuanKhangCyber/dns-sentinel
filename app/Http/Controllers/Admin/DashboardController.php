<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\Feature;
use App\Models\Scan;
use App\Models\User;
use App\Services\RecaptchaService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(RecaptchaService $recaptcha): View
    {
        $totalUsers = User::count();
        $plusUsers = User::whereHas('plan', fn ($query) => $query->where('code', 'plus')->where('is_active', true))
            ->where('membership_status', 'active')
            ->where(fn ($query) => $query->whereNull('membership_expires_at')->orWhere('membership_expires_at', '>', now()))
            ->count();
        $metrics = [
            'users' => $totalUsers, 'free_users' => max(0, $totalUsers - $plusUsers),
            'plus_users' => $plusUsers,
            'active_users' => User::where('status', 'active')->count(), 'suspended_users' => User::where('status', 'suspended')->count(),
            'admins' => User::where('role', 'admin')->count(), 'current_credits' => CreditWallet::sum('balance'),
            'scans' => Scan::count(),
            'credits_granted' => CreditTransaction::where('amount', '>', 0)->whereIn('type', ['grant', 'adjustment', 'monthly_reset'])->sum('amount'),
            'credits_used' => abs((int) CreditTransaction::where('type', 'usage')->sum('amount')),
            'features_enabled' => Feature::where('is_enabled', true)->count(), 'features_disabled' => Feature::where('is_enabled', false)->count(),
        ];
        $scanStatuses = Scan::query()->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status');
        $serviceStatuses = [
            'Nmap' => filled(config('scanner.nmap_binary')) && config('scanner.allowlist') !== [],
            'Nessus' => (bool) config('scanner.nessus.enabled') && filled(config('scanner.nessus.url'))
                && filled(config('scanner.nessus.access_key')) && filled(config('scanner.nessus.secret_key')),
            'Google reCAPTCHA' => $recaptcha->configured(),
        ];
        $recentAudits = AuditLog::with('actor:id,name,email')->latest()->limit(8)->get();
        $recentUsers = User::with('plan:id,name')->latest()->limit(6)->get();

        return view('admin.dashboard', compact('metrics', 'scanStatuses', 'serviceStatuses', 'recentAudits', 'recentUsers'));
    }
}
