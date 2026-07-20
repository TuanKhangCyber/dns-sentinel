<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="icon" type="image/jpeg" href="{{ asset('images/dns-logo.jpg') }}">
    @include('partials.theme-bootstrap')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="platform-page">
@include('partials.app-navigation', ['placement' => 'platform'])
@if($notice = app(\App\Services\SystemSettingService::class)->get('maintenance_notice', ''))<div class="maintenance-notice" role="status">{{ $notice }}</div>@endif
<main class="platform-shell">
    @if(session('status'))<div class="alert success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert" role="alert">{{ $errors->first() }}</div>@endif
    @yield('content')
</main>
</body></html>
