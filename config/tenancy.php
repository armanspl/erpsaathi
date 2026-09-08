<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base domain (production subdomains)
    |--------------------------------------------------------------------------
    |
    | School tenants resolve as {slug}.{base_domain}, e.g. childrenschool.erpsaathi.com
    |
    */
    'base_domain' => env('TENANCY_BASE_DOMAIN', 'erpsaathi.com'),

    /*
    |--------------------------------------------------------------------------
    | Central / Super Admin hosts
    |--------------------------------------------------------------------------
    |
    | Requests on these hosts skip tenant switching and serve Super Admin only.
    | Comma-separated list in env.
    |
    */
    'central_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'TENANCY_CENTRAL_DOMAINS',
            'admin.erpsaathi.com,admin.localhost,127.0.0.1,localhost'
        ))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Local fallback identification
    |--------------------------------------------------------------------------
    */
    'query_parameter' => 'school',
    'header_name' => 'X-Tenant',
    // Sticky tenant on 127.0.0.1 when ?school= is dropped on POST (login, etc.)
    'cookie_name' => 'tenant',

    /*
    |--------------------------------------------------------------------------
    | First school (existing ERP database)
    |--------------------------------------------------------------------------
    */
    'first_school_slug' => env('FIRST_SCHOOL_SLUG', 'demo'),
    'first_school_name' => env('FIRST_SCHOOL_NAME', 'Demo School'),

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Tenant files live under storage/app/schools/{slug}/.
    | The first school may keep using storage/app/private for backward compatibility
    | when "legacy_storage_for_first_school" is true.
    |
    */
    'storage_root' => storage_path('app/schools'),
    'legacy_storage_for_first_school' => env('TENANCY_LEGACY_STORAGE_FIRST', true),

    /*
    |--------------------------------------------------------------------------
    | Production subdomain routing
    |--------------------------------------------------------------------------
    |
    | DNS: point *.erpsaathi.com (and admin.erpsaathi.com) at the app server.
    | Web server: serve the same vhost for all subdomains (wildcard).
    | Local: use ?school={slug} or X-Tenant: {slug} on 127.0.0.1 / localhost.
    |
    */
    'production_notes' => 'Wildcard DNS *.erpsaathi.com → app; Super Admin on admin.erpsaathi.com',

    /*
    |--------------------------------------------------------------------------
    | Public Try Demo school
    |--------------------------------------------------------------------------
    |
    | Marketing "Try Demo" provisions/opens this tenant with sample data.
    |
    */
    'demo' => [
        'slug' => env('DEMO_SCHOOL_SLUG', 'demo'),
        'name' => env('DEMO_SCHOOL_NAME', 'Demo School'),
        'admin_email' => env('DEMO_ADMIN_EMAIL', 'demo@erpsaathi.com'),
        'admin_name' => env('DEMO_ADMIN_NAME', 'Demo Admin'),
        'admin_password' => env('DEMO_ADMIN_PASSWORD', 'Demo@12345'),
    ],

];
