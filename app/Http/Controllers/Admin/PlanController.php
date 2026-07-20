<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Plan;
use App\Services\AuditService;
use App\Services\SystemSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanController extends Controller
{
    public function index()
    {
        return view('admin.plans.index', ['plans' => Plan::with('features')->withCount('users')->orderBy('id')->get(), 'features' => Feature::orderBy('sort_order')->get()]);
    }

    public function update(Request $request, Plan $plan, AuditService $audit, SystemSettingService $settings)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:2000'], 'is_active' => ['boolean'], 'monthly_credit_allowance' => ['required', 'integer', 'min:0', 'max:1000000'], 'history_retention_days' => ['required', 'integer', 'between:1,3650']]);
        DB::transaction(function () use ($request, $plan, $data, $audit, $settings): void {
            $managedPlan = Plan::query()->lockForUpdate()->findOrFail($plan->id);
            $isDisabling = ! $request->boolean('is_active');
            $protectedCodes = array_unique([
                'free',
                (string) config('features.default_plan', 'free'),
                (string) $settings->get('default_plan', config('features.default_plan', 'free')),
            ]);
            if ($isDisabling && in_array($managedPlan->code, $protectedCodes, true)) {
                throw ValidationException::withMessages(['is_active' => __('platform.errors.default_plan_required')]);
            }
            if ($isDisabling && $managedPlan->users()->exists()) {
                throw ValidationException::withMessages(['is_active' => __('platform.errors.plan_in_use')]);
            }
            $before = $managedPlan->toArray();
            $managedPlan->update([...$data, 'is_active' => ! $isDisabling]);
            $audit->record($request->user(), 'plan.updated', $managedPlan, $before, $managedPlan->fresh()->toArray(), $request);
        }, 3);

        return back()->with('status', __('platform.saved'));
    }
}
