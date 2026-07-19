@extends('layouts.app')
@section('content')
<div class="admin-layout">
    <aside class="admin-sidebar" aria-label="Admin">
        <strong>Admin</strong>
        <a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>Dashboard</a>
        <a href="{{ route('admin.users.index') }}" @if(request()->routeIs('admin.users.*')) aria-current="page" @endif>{{ __('platform.users') }}</a>
        <a href="{{ route('admin.plans.index') }}" @if(request()->routeIs('admin.plans.*')) aria-current="page" @endif>Plans</a>
        <a href="{{ route('admin.features.index') }}" @if(request()->routeIs('admin.features.*')) aria-current="page" @endif>Features</a>
        <a href="{{ route('admin.content.index') }}" @if(request()->routeIs('admin.content.*') || request()->routeIs('admin.faqs.*')) aria-current="page" @endif>About / FAQ</a>
        <a href="{{ route('admin.settings.index') }}" @if(request()->routeIs('admin.settings.*')) aria-current="page" @endif>{{ __('platform.settings') }}</a>
    </aside>
    <section class="admin-content">@yield('admin-content')</section>
</div>
@endsection
