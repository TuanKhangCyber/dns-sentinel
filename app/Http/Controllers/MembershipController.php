<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use App\Models\Plan;
use App\Services\FeatureAccessService;
use App\Services\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MembershipController extends Controller
{
    public function index(Request $request, MembershipService $memberships, FeatureAccessService $access): View
    {
        $plans = Plan::query()->where('is_active', true)->with(['features' => fn ($query) => $query->where('features.is_visible', true)])->orderBy('id')->get();
        $current = $memberships->effectivePlan($request->user());
        $statuses = Feature::query()->where('is_visible', true)->pluck('code')->mapWithKeys(fn ($code) => [$code => $access->status($request->user(), $code)]);

        return view('membership.index', compact('plans', 'current', 'statuses'));
    }

    public function requestUpgrade(): RedirectResponse
    {
        return back()->with('status', __('platform.upgrade_request_notice'));
    }

    public function credits(Request $request, MembershipService $memberships): View
    {
        $user = $request->user()->load('wallet');
        $plan = $memberships->effectivePlan($user);
        $transactions = $user->creditTransactions()->latest()->paginate(25);

        return view('membership.credits', compact('user', 'plan', 'transactions'));
    }
}
