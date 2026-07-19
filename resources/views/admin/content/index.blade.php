@extends('admin.layout')
@section('title', 'About / FAQ')
@section('admin-content')
<header class="platform-hero"><p class="eyebrow">CONTENT</p><h1>About / FAQ</h1></header>
<form class="platform-card form-grid" method="POST" action="{{ route('admin.content.about', $about) }}" data-submit-lock>
    @csrf @method('PUT')
    <h2>About</h2>
    <label>Title VI<input name="title_vi" value="{{ $about->title['vi'] ?? '' }}" required></label>
    <label>Title EN<input name="title_en" value="{{ $about->title['en'] ?? '' }}" required></label>
    <label>Content VI<textarea name="content_vi" rows="8" required>{{ $about->content['vi'] ?? '' }}</textarea></label>
    <label>Content EN<textarea name="content_en" rows="8" required>{{ $about->content['en'] ?? '' }}</textarea></label>
    <label class="checkbox-label"><input type="checkbox" name="is_published" value="1" @checked($about->is_published)> Published</label>
    <button>{{ __('platform.save') }}</button>
</form>
<details class="platform-card"><summary>Add FAQ</summary>@include('admin.content.faq-form', ['faq' => null])</details>
@foreach($faqs as $faq)
    <details class="platform-card"><summary>{{ $faq->question['en'] ?? '' }}</summary>
        @include('admin.content.faq-form', ['faq' => $faq])
        <form method="POST" action="{{ route('admin.faqs.destroy', $faq) }}" data-confirm="{{ __('platform.confirm_delete') }}">@csrf @method('DELETE')<button class="button-danger">Delete</button></form>
    </details>
@endforeach
@endsection
