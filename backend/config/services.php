<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'vonage' => [
        'key' => env('VONAGE_KEY'),
        'secret' => env('VONAGE_SECRET'),
        'sms_from' => env('VONAGE_SMS_FROM'),
        'local_override_phone' => env('VONAGE_LOCAL_OVERRIDE_PHONE'),
    ],

    'smstools' => [
        'base_url' => env('SMSTOOLS_BASE_URL', 'https://api.smstools.sk'),
        'api_key' => env('SMSTOOLS_API_KEY'),
        'sender_text' => env('SMSTOOLS_SENDER_TEXT', 'BKP'),
        'sender_phone' => env('SMSTOOLS_SENDER_PHONE'),
        'simple_text' => env('SMSTOOLS_SIMPLE_TEXT', false),
        'connect_timeout' => env('SMSTOOLS_CONNECT_TIMEOUT', 10),
        'timeout' => env('SMSTOOLS_TIMEOUT', 20),
        'local_override_phone' => env('SMSTOOLS_LOCAL_OVERRIDE_PHONE'),
    ],

    'amaterky' => [
        'base_url' => env('AMATERKY_BASE_URL', 'https://amaterky.sk'),
        'user_agent' => env('AMATERKY_SCRAPER_USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0 Safari/537.36'),
        'enforce_phone_match' => env('AMATERKY_ENFORCE_PHONE_MATCH', true),
    ],

    'flat_gallery' => [
        'enforce_escort_invitation_access' => env('FLAT_GALLERY_ENFORCE_ESCORT_INVITATION_ACCESS', true),
    ],

    'webshare' => [
        'proxy' => env('WEBSHARE_PROXY'),
        'host' => env('WEBSHARE_PROXY_HOST'),
        'port' => env('WEBSHARE_PROXY_PORT'),
        'username' => env('WEBSHARE_PROXY_USERNAME'),
        'password' => env('WEBSHARE_PROXY_PASSWORD'),
        'connect_timeout' => env('WEBSHARE_CONNECT_TIMEOUT', 10),
        'timeout' => env('WEBSHARE_TIMEOUT', 20),
    ],

    'hardware_signature' => [
        'ttl_seconds' => env('HARDWARE_SIGNATURE_TTL_SECONDS', 300),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
