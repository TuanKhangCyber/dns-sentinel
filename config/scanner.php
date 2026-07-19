<?php

$allowlist = array_values(array_filter(array_map('trim', explode(',', (string) env('SCANNER_ALLOWLIST', '')))));

return [
    'company_name' => env('SCANNER_COMPANY_NAME', env('RECON_COMPANY_NAME', 'DNS Recon Security')),
    'allowlist' => $allowlist,
    'max_hosts_per_scan' => (int) env('SCANNER_MAX_HOSTS', 256),
    'min_ipv4_prefix' => (int) env('SCANNER_MIN_IPV4_PREFIX', 24),
    'min_ipv6_prefix' => (int) env('SCANNER_MIN_IPV6_PREFIX', 120),
    'rate_limit_per_hour' => (int) env('SCANNER_RATE_LIMIT_PER_HOUR', 10),
    'submission_window_seconds' => (int) env('SCANNER_SUBMISSION_WINDOW_SECONDS', 10),
    'output_max_bytes' => (int) env('SCANNER_OUTPUT_MAX_BYTES', 5 * 1024 * 1024),
    'temporary_disk' => env('SCANNER_TEMPORARY_DISK', 'local'),
    'nmap_binary' => env('SCANNER_NMAP_BINARY', 'nmap'),
    'allow_privileged_profiles' => filter_var(env('SCANNER_ALLOW_PRIVILEGED_PROFILES', false), FILTER_VALIDATE_BOOL),
    'speeds' => [
        'slow' => ['timing' => 'T2', 'max_rate' => 50],
        'normal' => ['timing' => 'T3', 'max_rate' => 100],
    ],
    'profiles' => [
        'host_discovery' => [
            'label' => 'scanner.profiles.host_discovery', 'scanner_type' => 'nmap',
            'arguments' => ['-sn', '-n'], 'timeout' => 120, 'enabled' => true,
        ],
        'quick_tcp' => [
            'label' => 'scanner.profiles.quick_tcp', 'scanner_type' => 'nmap',
            'arguments' => ['-sT', '-Pn', '-n', '--top-ports', '100'], 'timeout' => 120, 'enabled' => true,
        ],
        'full_tcp' => [
            'label' => 'scanner.profiles.full_tcp', 'scanner_type' => 'nmap',
            'arguments' => ['-sT', '-Pn', '-n', '-p', '1-65535'], 'timeout' => 900, 'enabled' => true,
        ],
        'service_detection' => [
            'label' => 'scanner.profiles.service_detection', 'scanner_type' => 'nmap',
            'arguments' => ['-sT', '-Pn', '-n', '-sV', '--version-light', '--top-ports', '1000'],
            'timeout' => 300, 'enabled' => true,
        ],
        'os_detection' => [
            'label' => 'scanner.profiles.os_detection', 'scanner_type' => 'nmap',
            'arguments' => ['-O', '--osscan-limit', '-Pn', '-n', '--top-ports', '1000'],
            'timeout' => 300, 'enabled' => true, 'privileged' => true,
        ],
        'common_udp' => [
            'label' => 'scanner.profiles.common_udp', 'scanner_type' => 'nmap',
            'arguments' => ['-sU', '-Pn', '-n', '--top-ports', '20'],
            'timeout' => 300, 'enabled' => true, 'privileged' => true,
        ],
        'web_security' => [
            'label' => 'scanner.profiles.web_security', 'scanner_type' => 'nmap',
            'arguments' => ['-sT', '-Pn', '-n', '-sV', '--version-light', '-p', '80,443,8080,8443'],
            'timeout' => 180, 'enabled' => true,
        ],
        'vulnerability_assessment' => [
            'label' => 'scanner.profiles.vulnerability_assessment', 'scanner_type' => 'vulnerability',
            'arguments' => [], 'timeout' => 3600, 'template_config' => 'scanner.nessus.template_uuid',
            'enabled' => filter_var(env('SCANNER_VULNERABILITY_ENABLED', false), FILTER_VALIDATE_BOOL),
        ],
        'compliance' => [
            'label' => 'scanner.profiles.compliance', 'scanner_type' => 'vulnerability',
            'arguments' => [], 'timeout' => 3600, 'template_config' => 'scanner.nessus.compliance_template_uuid',
            'enabled' => filter_var(env('SCANNER_COMPLIANCE_ENABLED', false), FILTER_VALIDATE_BOOL),
        ],
        'custom_safe' => [
            'label' => 'scanner.profiles.custom_safe', 'scanner_type' => 'nmap',
            'arguments' => ['-sT', '-Pn', '-n'], 'timeout' => 300, 'enabled' => true,
        ],
    ],
    'nessus' => [
        'enabled' => filter_var(env('NESSUS_ENABLED', false), FILTER_VALIDATE_BOOL),
        'url' => env('NESSUS_URL'),
        'access_key' => env('NESSUS_ACCESS_KEY'),
        'secret_key' => env('NESSUS_SECRET_KEY'),
        'template_uuid' => env('NESSUS_TEMPLATE_UUID'),
        'compliance_template_uuid' => env('NESSUS_COMPLIANCE_TEMPLATE_UUID'),
        'scanner_id' => env('NESSUS_SCANNER_ID'),
        'folder_id' => env('NESSUS_FOLDER_ID'),
        'timeout_seconds' => (int) env('NESSUS_TIMEOUT_SECONDS', 30),
        'poll_interval_seconds' => (int) env('NESSUS_POLL_INTERVAL_SECONDS', 5),
        'response_max_bytes' => (int) env('NESSUS_RESPONSE_MAX_BYTES', 5 * 1024 * 1024),
        'verify_tls' => filter_var(env('NESSUS_VERIFY_TLS', true), FILTER_VALIDATE_BOOL),
    ],
];
