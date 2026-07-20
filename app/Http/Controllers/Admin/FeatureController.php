<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Plan;
use App\Services\AuditService;
use App\Services\FeatureRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FeatureController extends Controller
{
    public function index(Request $request, FeatureRegistry $registry)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:60'],
            'state' => ['nullable', Rule::in(['enabled', 'disabled', 'visible', 'hidden', 'registered', 'unregistered'])],
            'plan' => ['nullable', Rule::exists('plans', 'code')],
        ]);
        $features = Feature::query()->with('plans')
            ->when(filled($filters['q'] ?? null), fn ($query) => $query->where(fn ($nested) => $nested->where('code', 'like', '%'.$filters['q'].'%')->orWhere('name', 'like', '%'.$filters['q'].'%')))
            ->when(filled($filters['category'] ?? null), fn ($query) => $query->where('category', $filters['category']))
            ->when(($filters['state'] ?? null) === 'enabled', fn ($query) => $query->where('is_enabled', true))
            ->when(($filters['state'] ?? null) === 'disabled', fn ($query) => $query->where('is_enabled', false))
            ->when(($filters['state'] ?? null) === 'visible', fn ($query) => $query->where('is_visible', true))
            ->when(($filters['state'] ?? null) === 'hidden', fn ($query) => $query->where('is_visible', false))
            ->when(($filters['state'] ?? null) === 'registered', fn ($query) => $query->whereIn('code', array_keys($registry->all())))
            ->when(($filters['state'] ?? null) === 'unregistered', fn ($query) => $query->whereNotIn('code', array_keys($registry->all())))
            ->when(filled($filters['plan'] ?? null), fn ($query) => $query->whereHas('plans', fn ($plan) => $plan->where('code', $filters['plan'])))
            ->orderBy('sort_order')->paginate(12)->withQueryString();

        return view('admin.features.index', [
            'features' => $features,
            'plans' => Plan::orderBy('id')->get(),
            'categories' => Feature::distinct()->orderBy('category')->pluck('category'),
            'registry' => $registry,
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate(['code' => ['required', 'regex:/^[a-z][a-z0-9_]{2,79}$/', 'unique:features,code'], 'name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000'], 'category' => ['required', 'regex:/^[a-z][a-z0-9_]{1,59}$/'], 'is_visible' => ['boolean'], 'credit_cost' => ['integer', 'min:0', 'max:1000000'], 'sort_order' => ['integer', 'min:0', 'max:65535']]);
        DB::transaction(function () use ($request, $data, $audit): void {
            $feature = Feature::create([...$data, 'is_enabled' => false, 'is_visible' => $request->boolean('is_visible')]);
            $audit->record($request->user(), 'feature.metadata_created', $feature, [], $feature->toArray(), $request);
        }, 3);

        return back()->with('status', __('platform.saved'));
    }

    public function update(Request $request, Feature $feature, FeatureRegistry $registry, AuditService $audit)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000'], 'category' => ['required', 'regex:/^[a-z][a-z0-9_]{1,59}$/'], 'is_enabled' => ['boolean'], 'is_visible' => ['boolean'], 'credit_cost' => ['required', 'integer', 'min:0', 'max:1000000'], 'sort_order' => ['required', 'integer', 'min:0', 'max:65535'], 'plans' => ['array'], 'plans.*' => [Rule::exists('plans', 'id')]]);
        if ($request->boolean('is_enabled') && ! $registry->has($feature->code)) {
            return back()->withErrors(['is_enabled' => __('platform.errors.feature_unregistered')]);
        }
        DB::transaction(function () use ($request, $feature, $data, $audit): void {
            $managedFeature = Feature::query()->lockForUpdate()->findOrFail($feature->id);
            $before = $managedFeature->load('plans')->toArray();
            $managedFeature->update([...$data, 'is_enabled' => $request->boolean('is_enabled'), 'is_visible' => $request->boolean('is_visible')]);
            $managedFeature->plans()->sync(collect($data['plans'] ?? [])->mapWithKeys(fn ($id) => [$id => ['is_enabled' => true]])->all());
            $audit->record($request->user(), 'feature.updated', $managedFeature, $before, $managedFeature->fresh()->load('plans')->toArray(), $request);
        }, 3);

        return back()->with('status', __('platform.saved'));
    }
}
