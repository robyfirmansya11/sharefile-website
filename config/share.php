<?php

return [
    // Keep local development separate from IIS Windows Authentication.
    'driver' => env('SHARE_DRIVER', 'local'),
    'root' => env('SHARE_LOCAL_ROOT', storage_path('app/share-demo')),
    'max_upload_kb' => 25600,
    'windows' => [
        'domain' => env('AD_DOMAIN', 'artabumisentraindustri.com'),
        'netbios_domain' => env('AD_NETBIOS_DOMAIN', ''),
        'root' => env('SMB_UNC_ROOT', '\\\\192.168.3.10\\SMB'),
        'writes_enabled' => env('SMB_WRITES_ENABLED', false),
    ],
];
