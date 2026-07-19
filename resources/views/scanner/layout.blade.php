<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('scanner.title'))</title>
    @include('partials.theme-bootstrap')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="scanner-page">
    @include('partials.app-navigation', ['placement' => 'scanner'])
    <main class="scanner-shell">@yield('content')</main>
    @yield('page-data')
</body>
</html>
