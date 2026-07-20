@extends('admin.layout')
@section('title', __('platform.audit_logs'))
@section('admin-content')
<x-admin.page-header :title="__('platform.audit_logs')" :description="__('platform.audit_intro')" :eyebrow="__('platform.security_and_compliance')" />

<form class="admin-filter-bar" method="GET" action="{{ route('admin.audit.index') }}">
    <label class="admin-search-field"><span>{{ __('platform.search') }}</span><input name="q" value="{{ request('q') }}" placeholder="{{ __('platform.actor_or_target') }}"></label>
    <label><span>{{ __('platform.action') }}</span><select name="action"><option value="">{{ __('platform.all') }}</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(request('action')===$action)>{{ $action }}</option>@endforeach</select></label>
    <label><span>{{ __('platform.from') }}</span><input type="date" name="from" value="{{ request('from') }}"></label><label><span>{{ __('platform.to') }}</span><input type="date" name="to" value="{{ request('to') }}"></label>
    <div class="filter-actions"><button>{{ __('platform.filter') }}</button><a class="button-link button-secondary" href="{{ route('admin.audit.index') }}">{{ __('platform.reset') }}</a></div>
</form>

<section class="admin-panel admin-table-panel"><div class="table-wrap"><table class="admin-table"><thead><tr><th scope="col">{{ __('platform.time') }}</th><th scope="col">{{ __('platform.actor') }}</th><th scope="col">{{ __('platform.action') }}</th><th scope="col">{{ __('platform.target') }}</th><th scope="col">{{ __('platform.ip_address') }}</th><th scope="col">{{ __('platform.changes') }}</th></tr></thead><tbody>
@forelse($audits as $audit)<tr><td data-label="{{ __('platform.time') }}">{{ $audit->created_at->format('d/m/Y H:i:s') }}</td><td data-label="{{ __('platform.actor') }}">{{ $audit->actor?->name ?? __('platform.system') }}<small>{{ $audit->actor?->email }}</small></td><td data-label="{{ __('platform.action') }}"><span class="status-badge info">{{ $audit->action }}</span></td><td data-label="{{ __('platform.target') }}">{{ $audit->target_type ? class_basename($audit->target_type) : '—' }}@if($audit->target_id)<small>#{{ $audit->target_id }}</small>@endif</td><td data-label="{{ __('platform.ip_address') }}">{{ $audit->ip_address ?? '—' }}</td><td data-label="{{ __('platform.changes') }}">@if($audit->before || $audit->after)<details class="audit-details"><summary>{{ __('platform.view_changes') }}</summary><div><strong>{{ __('platform.before') }}</strong><pre>{{ json_encode($audit->before ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre><strong>{{ __('platform.after') }}</strong><pre>{{ json_encode($audit->after ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div></details>@else—@endif</td></tr>@empty<tr class="admin-table-empty"><td colspan="6"><x-admin.empty-state /></td></tr>@endforelse
</tbody></table></div><div class="admin-pagination">{{ $audits->links() }}</div></section>
@endsection
