<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Domain (Control Plane)
    |--------------------------------------------------------------------------
    |
    | In produzione: ADMIN_DOMAIN=admin.tuogestionale.it
    | In dev puoi lasciarlo vuoto e usare:
    | - http://127.0.0.1:8001
    | - http://localhost:8001
    | - http://admin.127.0.0.1.nip.io:8001
    |
    */

    'domain' => env('ADMIN_DOMAIN', ''),

    // Host sempre consentiti come "admin context" (dev/local)
    'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', env(
        'ADMIN_ALLOWED_HOSTS',
        'localhost,127.0.0.1,::1'
    ))))),

    // In dev/testing consenti host che iniziano con "admin." (es. admin.127.0.0.1.nip.io)
    'allow_dev_admin_subdomain' => (bool) env('ADMIN_ALLOW_DEV_SUBDOMAIN', true),
];
