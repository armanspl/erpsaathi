<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Meta WhatsApp Cloud API — webhook
    |--------------------------------------------------------------------------
    |
    | verify_token: any long random string you choose. Paste the SAME value into
    | Meta App Dashboard → WhatsApp → Configuration → Webhook → "Verify token".
    |
    | app_secret: Meta App Dashboard → App settings → Basic → "App secret".
    | Every POST from Meta is signed with it (X-Hub-Signature-256); unsigned or
    | wrongly signed requests are rejected. Never commit either value.
    |
    */

    'webhook' => [
        'verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        // Meta payloads are a few KB; anything far bigger is not a genuine notification.
        'max_payload_bytes' => (int) env('WHATSAPP_WEBHOOK_MAX_BYTES', 1048576),
    ],

    /*
    |--------------------------------------------------------------------------
    | Graph API + Embedded Signup (Tech Provider onboarding)
    |--------------------------------------------------------------------------
    |
    | app_id: Meta App Dashboard → App settings → Basic → App ID.
    | graph_version: the API version shown on the App Dashboard (e.g. v23.0).
    | embedded_signup.config_id: Facebook Login for Business → Configurations →
    |   the "WhatsApp Embedded Signup" configuration's ID.
    | embedded_signup.url: the ONE page Meta's JavaScript SDK runs on. Every school's
    |   ERP opens this page in a popup, so only this host needs to be allowed in Meta.
    |
    */

    'app_id' => env('WHATSAPP_APP_ID'),
    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v23.0'),

    'embedded_signup' => [
        'config_id' => env('WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID'),
        'url' => rtrim((string) env('WHATSAPP_SIGNUP_URL', 'https://'.env('TENANCY_BASE_DOMAIN', 'erpsaathi.com')), '/').'/whatsapp/embedded-signup',
        // Minutes a "Connect WhatsApp" popup link stays valid (single use).
        'ticket_ttl' => (int) env('WHATSAPP_SIGNUP_TICKET_TTL', 15),
    ],

    /*
    | Single-school installs (no master school registry): comma-separated Phone
    | Number IDs whose events this install accepts. Multi-school installs map
    | numbers to schools in the master `whatsapp_accounts` table instead
    | (php artisan whatsapp:link-account).
    */
    'phone_number_ids' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('WHATSAPP_PHONE_NUMBER_ID', ''))
    ))),

];
