<?php

return [
    'sms_from' => env('VONAGE_SMS_FROM'),

    'api_key' => env('VONAGE_KEY'),

    'api_secret' => env('VONAGE_SECRET'),

    'application_id' => env('VONAGE_APPLICATION_ID'),

    'signature_secret' => env('VONAGE_SIGNATURE_SECRET'),

    'private_key' => env('VONAGE_PRIVATE_KEY'),

    'app' => [
        'name' => env('VONAGE_APP_NAME', 'Laravel'),
        'version' => env('VONAGE_APP_VERSION', '1.1.2'),
    ],
];
