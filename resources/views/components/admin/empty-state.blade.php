@props(['title' => null])

<div class="admin-empty-state">
    <x-icon name="inbox" />
    <strong>{{ $title ?? __('platform.no_items') }}</strong>
    @if(trim((string) $slot) !== '')<p>{{ $slot }}</p>@endif
</div>
