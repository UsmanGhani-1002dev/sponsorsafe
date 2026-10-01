<?php

return [
    // Secret path for the super-admin area, e.g. "ops-7f3k2x". Never link to it.
    'ops_path' => env('OPS_PATH', 'ops-change-me'),

    // Comma-separated IPs allowed to reach the super-admin area. Empty = allow all (not recommended in production).
    'ops_allowed_ips' => array_filter(array_map('trim', explode(',', (string) env('OPS_ALLOWED_IPS', '')))),

    // Where website enquiries are emailed (the log file locally).
    'support_email' => env('SUPPORT_EMAIL', 'support@sponsorsafe.example'),

    // Who runs the service: shown in the privacy policy, terms and employee privacy notice. Anything left
    // blank is simply not shown (set the real values in the live .env).
    'company' => [
        'name' => env('COMPANY_NAME', 'Enovtec'),                    // legal name, e.g. "Enovtec Ltd"
        'number' => env('COMPANY_NUMBER'),                           // Companies House number
        'address' => env('COMPANY_ADDRESS', 'Southampton, United Kingdom'),
        'ico' => env('COMPANY_ICO_NUMBER'),                          // ICO data protection registration
        'legal_updated' => '1 October 2026',                         // date of the current privacy policy and terms
    ],

    // Defaults for new businesses and the public plan (super admin can change them).
    'plan' => [
        // Plans by size. More than the largest limit is the Corporate package: a price agreed with
        // the super admin and set on that business (Businesses → Set plan).
        'tiers' => [
            'starter' => ['price_pence' => 2000, 'employee_limit' => 5],
            'standard' => ['price_pence' => 3500, 'employee_limit' => 10],
        ],
        'training_price_pence' => 4900,
        // Days a business keeps access after a failed payment, then it is suspended (data kept).
        'grace_days' => 7,
    ],

    // Sign-ups that never finished paying are removed after this many days.
    'abandoned_signup_days' => 7,

    // Compliance rule defaults, copied into each business's settings on creation.
    'rules' => [
        'unpaid_limit_weeks' => 4,
        'unpaid_leave_year' => 'calendar',      // calendar (resets 1 January) | rolling (12 months)
        'unauthorised_trigger_days' => 10,
        'worker_report_deadline_days' => 10,
        'company_report_deadline_days' => 20,
        // Leave on reduced pay that is not a reportable salary change (compliance-rules §3).
        'exempt_absence_types' => ['sick_self', 'sick_fit', 'family', 'jury'],
        'self_cert_max_days' => 7,               // calendar days; longer sickness needs a fit note
        'expiry_alert_days' => [90, 60, 30],
        // Reminders (compliance-rules §12): follow-up right-to-work check, passport expiry (calendar days
        // before), and Home Office task deadlines (working days before; overdue is always reminded).
        'follow_up_alert_days' => 30,
        'passport_alert_days' => 90,
        'task_alert_working_days' => 5,
        // Unexplained absences (compliance-rules §11): off until the business has clock-in data.
        'clock_in_check' => false,
        'unexplained_red_after_days' => 2, // working days unclassified before the alert turns red
        'payslip_freshness_days' => 35,
        'retention_years' => 1,
        'rtw_retention_years' => 2,
        'annual_leave_weeks' => 5.6,             // pro rata to working days per week
    ],

    // Private document uploads.
    'documents' => [
        'max_kb' => 10240,
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
    ],
];
