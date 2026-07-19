<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Plan;
use App\Services\AuditService;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        return view('admin.plans.index', ['plans' => Plan::with('features')->withCount('users')->orderBy('id')->get(), 'features' => Feature::orderBy('sort_order')->get()]);
    }

    public function update(Request $request, Plan $plan, AuditService $audit)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:2000'], 'is_active' => ['boolean'], 'monthly_credit_allowance' => ['required', 'integer', 'min:0', 'max:1000000'], 'history_retention_days' => ['required', 'integer', 'between:1,3650']]);
        $isDisabling = ! ($data['is_active'] ?? false);
        $protectedCodes = ['free', (string) config('features.default_plan', 'free')];
        if ($isDisabling && in_array($plan->code, $protectedCodes, true)) {
            return back()->withErrors(['is_active' => __('platform.errors.default_plan_required')]);
        }
        if ($isDisabling && $plan->users()->exists()) {
            return back()->withErrors(['is_active' => __('platform.errors.plan_in_use')]);
        }
        $before = $plan->toArray();
        $plan->update([...$data, 'is_active' => $request->boolean('is_active')]);
        $audit->record($request->user(), 'plan.updated', $plan, $before, $plan->fresh()->toArray(), $request);

        return back()->with('status', __('platform.saved'));
    }
}
