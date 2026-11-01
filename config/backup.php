<?php

return [
    // base64 key from `php artisan crm:backup-key`. Keep a copy OFF the server (password manager + paper).
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),

    // Who gets an email when a backup or restore test fails.
    'alert_email' => env('BACKUP_ALERT_EMAIL'),

    // local | google_drive
    'destination' => env('BACKUP_DESTINATION', 'local'),

    'keep' => ['daily' => 7, 'weekly' => 4, 'monthly' => 3],

    'google' => [
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'folder_name' => env('GOOGLE_DRIVE_FOLDER', 'CareerMentors CRM backups'),
    ],

    // Separate, empty database used only for the monthly restore test.
    'restore_connection' => env('BACKUP_RESTORE_CONNECTION', 'restore_test'),
];
