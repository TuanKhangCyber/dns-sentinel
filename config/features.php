<?php

return [
    'default_plan' => env('MEMBERSHIP_DEFAULT_PLAN', 'free'),
    'signup_credits' => (int) env('MEMBERSHIP_SIGNUP_CREDITS', 10),
    'registry' => [
        'dns_lookup' => ['name' => 'DNS lookup', 'category' => 'recon', 'plans' => ['free', 'plus'], 'enabled' => true, 'visible' => true, 'cost' => 1],
        'rdap_lookup' => ['name' => 'RDAP lookup', 'category' => 'recon', 'plans' => ['free', 'plus'], 'enabled' => true, 'visible' => true, 'cost' => 0],
        'geo_lookup' => ['name' => 'IP geolocation', 'category' => 'recon', 'plans' => ['free', 'plus'], 'enabled' => true, 'visible' => true, 'cost' => 0],
        'recon_history' => ['name' => 'Recon history', 'category' => 'history', 'plans' => ['free', 'plus'], 'enabled' => true, 'visible' => true, 'cost' => 0],
        'security_headers' => ['name' => 'Security headers', 'category' => 'security', 'plans' => ['plus'], 'enabled' => true, 'visible' => true, 'cost' => 1],
        'email_security' => ['name' => 'Email security', 'category' => 'security', 'plans' => ['plus'], 'enabled' => true, 'visible' => true, 'cost' => 1],
        'ssl_analysis' => ['name' => 'SSL/TLS analysis', 'category' => 'security', 'plans' => ['plus'], 'enabled' => true, 'visible' => true, 'cost' => 1],
        'subdomain_scan' => ['name' => 'Subdomain discovery', 'category' => 'recon', 'plans' => ['plus'], 'enabled' => true, 'visible' => true, 'cost' => 2],
        'technology_fingerprint' => ['name' => 'Technology fingerprint', 'category' => 'recon', 'plans' => ['plus'], 'enabled' => true, 'visible' => true, 'cost' => 1],
        'nmap_scan' => ['name' => 'Nmap scanner', 'category' => 'scanner', 'plans' => ['plus'], 'enabled' => true, 'visible' => true, 'cost' => 5, 'dependency' => 'nmap'],
        'vulnerability_scan' => ['name' => 'Vulnerability scanner', 'category' => 'scanner', 'plans' => ['plus'], 'enabled' => true, 'visible' => true, 'cost' => 10, 'dependency' => 'nessus'],
        'scan_history' => ['name' => 'Scan history', 'category' => 'history', 'plans' => ['plus'], 'enabled' => true, 'visible' => true, 'cost' => 0],
        'scan_compare' => ['name' => 'Scan comparison', 'category' => 'history', 'plans' => ['plus'], 'enabled' => true, 'visible' => true, 'cost' => 0],
        'export_json' => ['name' => 'JSON export', 'category' => 'export', 'plans' => ['plus'], 'enabled' => true, 'visible' => true, 'cost' => 0],
        'export_csv' => ['name' => 'CSV export', 'category' => 'export', 'plans' => ['plus'], 'enabled' => true, 'visible' => true, 'cost' => 0],
        'export_pdf' => ['name' => 'PDF export', 'category' => 'export', 'plans' => ['plus'], 'enabled' => true, 'visible' => true, 'cost' => 1],
    ],
];
