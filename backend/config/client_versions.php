<?php

return [
    'platforms' => [
        'android' => [
            'min_supported_version' => env('CLIENT_VERSIONS_ANDROID_MIN_SUPPORTED_VERSION', '1.0.0'),
            'latest_version' => env('CLIENT_VERSIONS_ANDROID_LATEST_VERSION', '1.0.0'),
            'download_url' => env('CLIENT_VERSIONS_ANDROID_DOWNLOAD_URL', ''),
            'force_update' => env('CLIENT_VERSIONS_ANDROID_FORCE_UPDATE', true),
        ],
        'ios' => [
            'min_supported_version' => env('CLIENT_VERSIONS_IOS_MIN_SUPPORTED_VERSION', '1.0.0'),
            'latest_version' => env('CLIENT_VERSIONS_IOS_LATEST_VERSION', '1.0.0'),
            'download_url' => env('CLIENT_VERSIONS_IOS_DOWNLOAD_URL', ''),
            'force_update' => env('CLIENT_VERSIONS_IOS_FORCE_UPDATE', true),
        ],
    ],
];
