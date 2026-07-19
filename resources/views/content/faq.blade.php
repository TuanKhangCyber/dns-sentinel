@extends('layouts.app')
@section('title', 'FAQ')
@section('content')
<section class="platform-hero"><h1>FAQ</h1><p>{{ __('platform.faq_intro') }}</p></section>
<form method="GET" class="filter-row"><input name="q" value="{{ request('q') }}" placeholder="{{ __('platform.search_faq') }}"><input name="category" value="{{ request('category') }}" placeholder="{{ __('platform.category') }}"><button>{{ __('platform.search') }}</button></form>
<div class="faq-list">@forelse($items as $item)<details class="platform-card"><summary>{{ $item->translated('question') }} <span class="badge unknown">{{ $item->category }}</span></summary><p class="prose-text">{{ $item->translated('answer') }}</p></details>@empty<p class="empty-state">{{ __('platform.no_items') }}</p>@endforelse</div>
@endsection
