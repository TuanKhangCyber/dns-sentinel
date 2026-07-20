@extends('admin.layout')
@section('title', __('platform.about_faq'))
@section('admin-content')
<x-admin.page-header :title="__('platform.about_faq')" :description="__('platform.content_intro')" :eyebrow="__('platform.content')">
    <x-slot:actions><a class="button-link button-secondary" href="{{ route('about') }}" target="_blank" rel="noopener">{{ __('platform.preview') }}</a></x-slot:actions>
</x-admin.page-header>

<form class="admin-panel admin-form" method="POST" action="{{ route('admin.content.about', $about) }}" data-submit-lock>
    @csrf @method('PUT')
    <div class="admin-section-heading"><div><p class="eyebrow">{{ __('platform.public_page') }}</p><h2>{{ __('platform.about') }}</h2></div><span class="status-badge {{ $about->is_published ? 'safe' : 'warning' }}">{{ $about->is_published ? __('platform.published') : __('platform.draft') }}</span></div>
    <div class="form-section-grid"><label>{{ __('platform.title_vi') }}<input name="title_vi" value="{{ old('title_vi', $about->title['vi'] ?? '') }}" required maxlength="200"></label><label>{{ __('platform.title_en') }}<input name="title_en" value="{{ old('title_en', $about->title['en'] ?? '') }}" required maxlength="200"></label></div>
    <div class="form-section-grid"><label>{{ __('platform.content_vi') }}<textarea name="content_vi" rows="9" required maxlength="20000">{{ old('content_vi', $about->content['vi'] ?? '') }}</textarea></label><label>{{ __('platform.content_en') }}<textarea name="content_en" rows="9" required maxlength="20000">{{ old('content_en', $about->content['en'] ?? '') }}</textarea></label></div>
    <p class="form-hint">{{ __('platform.content_escape_hint') }}</p><label class="admin-switch"><input type="checkbox" name="is_published" value="1" @checked($about->is_published)><span></span>{{ __('platform.published') }}</label><div class="sticky-form-actions"><button>{{ __('platform.save') }}</button></div>
</form>

<div class="admin-section-heading standalone"><div><p class="eyebrow">FAQ</p><h2>{{ __('platform.faq_management') }}</h2></div></div>
<form class="admin-filter-bar" method="GET"><label class="admin-search-field"><span>{{ __('platform.search') }}</span><input name="q" value="{{ request('q') }}" placeholder="{{ __('platform.search_faq') }}"></label><label><span>{{ __('platform.category') }}</span><select name="category"><option value="">{{ __('platform.all') }}</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(request('category')===$category)>{{ $category }}</option>@endforeach</select></label><label><span>{{ __('platform.status') }}</span><select name="published"><option value="">{{ __('platform.all') }}</option><option value="1" @selected(request('published')==='1')>{{ __('platform.published') }}</option><option value="0" @selected(request('published')==='0')>{{ __('platform.draft') }}</option></select></label><div class="filter-actions"><button>{{ __('platform.filter') }}</button><a class="button-link button-secondary" href="{{ route('admin.content.index') }}">{{ __('platform.reset') }}</a></div></form>

<details class="admin-panel admin-create-panel"><summary><x-icon name="content" /> {{ __('platform.add_faq') }}</summary>@include('admin.content.faq-form', ['faq' => null])</details>
<div class="admin-faq-list">
@forelse($faqs as $faq)<details class="admin-panel"><summary><span><strong>{{ $faq->translated('question') }}</strong><small>{{ $faq->category }} · {{ $faq->is_published ? __('platform.published') : __('platform.draft') }}</small></span></summary><div class="admin-faq-editor">@include('admin.content.faq-form', ['faq' => $faq])<form method="POST" action="{{ route('admin.faqs.destroy', $faq) }}" data-confirm="{{ __('platform.confirm_delete') }}" data-confirm-title="{{ __('platform.delete') }}" data-submit-lock>@csrf @method('DELETE')<button class="button-danger">{{ __('platform.delete') }}</button></form></div></details>@empty<x-admin.empty-state />@endforelse
</div><div class="admin-pagination">{{ $faqs->links() }}</div>
@endsection
