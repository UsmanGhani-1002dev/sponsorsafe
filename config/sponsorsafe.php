<?php

return [
    // Secret path for the super-admin area, e.g. "ops-7f3k2x". Never link to it.
    'ops_path' => env('OPS_PATH', 'ops-change-me'),

    // Comma-separated IPs allowed to reach the super-admin area. Empty = allow all (not recommended in production).
    'ops_allowed_ips' => array_filter(array_map('trim', explode(',', (string) env('OPS_ALLOWED_IPS', '')))),

    // Defaults for new businesses and the public plan (super admin can change them).
    'plan' => [
        'price_pence' => 2000,
        'employee_limit' => 15,
        'training_price_pence' => 4900,
    ],

    // Compliance rule defaults, copied into each business's settings on creation.
    'rules' => [
        'unpaid_limit_weeks' => 4,
        'unauthorised_trigger_days' => 10,
        'worker_report_deadline_days' => 10,
        'company_report_deadline_days' => 20,
        'expiry_alert_days' => [90, 60, 30],
        'payslip_freshness_days' => 35,
        'retention_years' => 1,
        'rtw_retention_years' => 2,
    ],
];
