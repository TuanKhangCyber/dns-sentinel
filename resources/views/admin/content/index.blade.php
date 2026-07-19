@extends('admin.layout')
@section('title', 'About / FAQ')
@section('admin-content')
<header class="platform-hero"><p class="eyebrow">{{ strtoupper(__('platform.content')) }}</p><h1>{{ __('platform.about_faq') }}</h1></header>
<form class="platform-card form-grid" method="POST" action="{{ route('admin.content.about', $about) }}" data-submit-lock>
    @csrf @method('PUT')
    <h2>{{ __('platform.about') }}</h2>
    <label>{{ __('platform.title_vi') }}<input name="title_vi" value="{{ $about->title['vi'] ?? '' }}" required></label>
    <label>{{ __('platform.title_en') }}<input name="title_en" value="{{ $about->title['en'] ?? '' }}" required></label>
    <label>{{ __('platform.content_vi') }}<textarea name="content_vi" rows="8" required>{{ $about->content['vi'] ?? '' }}</textarea></label>
    <label>{{ __('platform.content_en') }}<textarea name="content_en" rows="8" required>{{ $about->content['en'] ?? '' }}</textarea></label>
    <label class="checkbox-label"><input type="checkbox" name="is_published" value="1" @checked($about->is_published)> {{ __('platform.published') }}</label>
    <button>{{ __('platform.save') }}</button>
</form>
<details class="platform-card"><summary>{{ __('platform.add_faq') }}</summary>@include('admin.content.faq-form', ['faq' => null])</details>
@foreach($faqs as $faq)
    <details class="platform-card"><summary>{{ $faq->question['en'] ?? '' }}</summary>
        @include('admin.content.faq-form', ['faq' => $faq])
        <form method="POST" action="{{ route('admin.faqs.destroy', $faq) }}" data-confirm="{{ __('platform.confirm_delete') }}">@csrf @method('DELETE')<button class="button-danger">{{ __('platform.delete') }}</button></form>
    </details>
@endforeach
@endsection
