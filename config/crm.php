<?php

return [
    // Super Admin must use an authenticator app. Only switched off in automated tests.
    'require_admin_2fa' => env('CRM_REQUIRE_ADMIN_2FA', true),

    // Rows per page in lists (kept small for shared hosting).
    'per_page' => 25,

    // Menu style: 'light' (white with green accents) or 'dark' (dark green).
    'sidebar' => env('CRM_SIDEBAR', 'light'),
];
