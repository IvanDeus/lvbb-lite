<?php

namespace App\Database\Connectors;

use Illuminate\Database\Connectors\PostgresConnector as BaseConnector;

class PostgresConnector extends BaseConnector
{
    /**
     * libpq accepts channel_binding in the DSN but Laravel's connector does not emit it,
     * so the value parsed off DATABASE_URL would be silently dropped.
     */
    protected function addSslOptions($dsn, array $config)
    {
        $dsn = parent::addSslOptions($dsn, $config);

        if (! empty($config['channel_binding'])) {
            $dsn .= ';channel_binding='.$config['channel_binding'];
        }

        return $dsn;
    }
}
