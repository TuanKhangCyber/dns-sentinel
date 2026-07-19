@extends('layouts.app')
@section('content')
<div class="admin-layout">
    <aside class="admin-sidebar" aria-label="Admin">
        <strong>{{ __('platform.admin') }}</strong>
        <a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>{{ __('platform.dashboard') }}</a>
        <a href="{{ route('admin.users.index') }}" @if(request()->routeIs('admin.users.*')) aria-current="page" @endif>{{ __('platform.users') }}</a>
        <a href="{{ route('admin.plans.index') }}" @if(request()->routeIs('admin.plans.*')) aria-current="page" @endif>{{ __('platform.plans') }}</a>
        <a href="{{ route('admin.features.index') }}" @if(request()->routeIs('admin.features.*')) aria-current="page" @endif>{{ __('platform.features') }}</a>
        <a href="{{ route('admin.content.index') }}" @if(request()->routeIs('admin.content.*') || request()->routeIs('admin.faqs.*')) aria-current="page" @endif>{{ __('platform.about_faq') }}</a>
        <a href="{{ route('admin.settings.index') }}" @if(request()->routeIs('admin.settings.*')) aria-current="page" @endif>{{ __('platform.settings') }}</a>
    </aside>
    <section class="admin-content">@yield('admin-content')</section>
</div>
@endsection
