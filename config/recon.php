<?php

return [
    'company_name' => env('RECON_COMPANY_NAME', 'DNS Recon Security'),
    'timeout_seconds' => (int) env('RECON_TIMEOUT_SECONDS', 10),
    'cache_minutes' => (int) env('RECON_CACHE_MINUTES', 20),
    'subdomain_cache_minutes' => (int) env('RECON_SUBDOMAIN_CACHE_MINUTES', 30),
    'max_subdomains' => (int) env('RECON_MAX_SUBDOMAINS', 100),
    'http_response_max_bytes' => (int) env('RECON_HTTP_RESPONSE_MAX_BYTES', 1024 * 1024),
    'crt_url' => env('RECON_CRT_URL', 'https://crt.sh/'),
    'dkim_selectors' => ['default', 'google', 'selector1'],
    'nmap' => [
        'enabled' => filter_var(env('NMAP_ENABLED', false), FILTER_VALIDATE_BOOL),
        'binary' => env('NMAP_BINARY', 'nmap'),
        'timeout_seconds' => (int) env('NMAP_TIMEOUT_SECONDS', 30),
        'top_ports' => (int) env('NMAP_TOP_PORTS', 100),
        'max_output_bytes' => (int) env('NMAP_MAX_OUTPUT_BYTES', 2 * 1024 * 1024),
        'cache_minutes' => (int) env('NMAP_CACHE_MINUTES', 15),
    ],
];
