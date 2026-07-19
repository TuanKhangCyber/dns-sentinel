@extends('layouts.app')
@section('title', $page->translated('title'))
@section('content')
<section class="platform-hero"><p class="eyebrow">DNS RECON &amp; SECURITY INVESTIGATION</p><h1>{{ $page->translated('title') }}</h1></section>
<article class="platform-card prose-text">{{ $page->translated('content') }}</article>
<section class="platform-grid"><article class="platform-card"><h2>{{ __('platform.legal_use') }}</h2><p>{{ __('platform.legal_use_text') }}</p></article><article class="platform-card"><h2>{{ __('platform.acknowledgements') }}</h2><p>Laravel · Nmap · RDAP · Leaflet · Dompdf</p></article></section>
@endsection
