<?php

return [
    'default' => env('QUEUE_CONNECTION', 'redis'),

    'connections' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            // Must stay above the longest job timeout on this connection
            // (ProcessServiceRequest: 150s), or a slow job is handed to a
            // second worker while the first is still running it.
            'retry_after' => 180,
            'block_for' => null,
        ],
        // Document extraction and embedding can take minutes for a large
        // PDF, so those jobs use their own connection with a longer
        // retry_after instead of stretching it for every job.
        'redis-long' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'documents',
            'retry_after' => 960,
            'block_for' => null,
        ],
        'sync' => ['driver' => 'sync'],
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'failed_jobs',
    ],
];
