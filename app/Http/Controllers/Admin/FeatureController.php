<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Plan;
use App\Services\AuditService;
use App\Services\FeatureRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeatureController extends Controller
{
    public function index(FeatureRegistry $registry)
    {
        return view('admin.features.index', ['features' => Feature::with('plans')->orderBy('sort_order')->get(), 'plans' => Plan::orderBy('id')->get(), 'registry' => $registry]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate(['code' => ['required', 'regex:/^[a-z][a-z0-9_]{2,79}$/', 'unique:features,code'], 'name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000'], 'category' => ['required', 'regex:/^[a-z][a-z0-9_]{1,59}$/'], 'is_visible' => ['boolean'], 'credit_cost' => ['integer', 'min:0', 'max:1000000'], 'sort_order' => ['integer', 'min:0', 'max:65535']]);
        $feature = Feature::create([...$data, 'is_enabled' => false, 'is_visible' => $request->boolean('is_visible')]);
        $audit->record($request->user(), 'feature.metadata_created', $feature, [], $feature->toArray(), $request);

        return back()->with('status', __('platform.saved'));
    }

    public function update(Request $request, Feature $feature, FeatureRegistry $registry, AuditService $audit)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000'], 'category' => ['required', 'regex:/^[a-z][a-z0-9_]{1,59}$/'], 'is_enabled' => ['boolean'], 'is_visible' => ['boolean'], 'credit_cost' => ['required', 'integer', 'min:0', 'max:1000000'], 'sort_order' => ['required', 'integer', 'min:0', 'max:65535'], 'plans' => ['array'], 'plans.*' => [Rule::exists('plans', 'id')]]);
        if ($request->boolean('is_enabled') && ! $registry->has($feature->code)) {
            return back()->withErrors(['is_enabled' => __('platform.errors.feature_unregistered')]);
        }
        $before = $feature->load('plans')->toArray();
        $feature->update([...$data, 'is_enabled' => $request->boolean('is_enabled'), 'is_visible' => $request->boolean('is_visible')]);
        $feature->plans()->sync(collect($data['plans'] ?? [])->mapWithKeys(fn ($id) => [$id => ['is_enabled' => true]])->all());
        $audit->record($request->user(), 'feature.updated', $feature, $before, $feature->fresh()->load('plans')->toArray(), $request);

        return back()->with('status', __('platform.saved'));
    }
}
