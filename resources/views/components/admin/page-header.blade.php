@props(['title', 'description' => null, 'eyebrow' => null])

<header class="admin-page-header">
    <div>
        @if($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endif
        <h1>{{ $title }}</h1>
        @if($description)<p>{{ $description }}</p>@endif
    </div>
    @if(isset($actions) && trim((string) $actions) !== '')<div class="admin-page-actions">{{ $actions }}</div>@endif
</header>
