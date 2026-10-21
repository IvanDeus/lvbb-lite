<?php

$databaseUrl = env('DB_URL') ?: env('DATABASE_URL');

if (! $databaseUrl && ! env('DB_HOST')) {
    throw new RuntimeException(
        'Postgres is the only supported database. Set DATABASE_URL (Neon connection string) in .env.'
    );
}

return [

    'default' => 'pgsql',

    'connections' => [

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => $databaseUrl,
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'neondb'),
            'username' => env('DB_USERNAME', ''),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => env('DB_SEARCH_PATH', 'public'),
            'sslmode' => env('DB_SSLMODE', 'require'),
            'channel_binding' => env('DB_CHANNEL_BINDING', 'require'),
            // Neon's pooled endpoint runs PgBouncer in transaction mode, which does not keep
            // PDO's server-side prepared statements on the same backend across a transaction:
            // the first statement reports success and the next one fails with
            // "current transaction is aborted". Emulating prepares client-side sends plain SQL.
            'options' => [PDO::ATTR_EMULATE_PREPARES => true],
            // Where djbb-lite shares a database, its tables sit alongside these. migrate:fresh
            // resolves its table list against search_path, which defaults to public, so name the
            // Django tables here rather than rely on nobody typing the wrong command.
            'dont_drop' => [
                'auth_group',
                'auth_group_permissions',
                'auth_permission',
                'auth_user',
                'auth_user_groups',
                'auth_user_user_permissions',
                'board_comment',
                'board_post',
                'django_admin_log',
                'django_content_type',
                'django_migrations',
                'django_session',
            ],
        ],

    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

];
