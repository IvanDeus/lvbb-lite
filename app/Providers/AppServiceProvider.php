<?php

namespace App\Providers;

use App\Database\Connectors\PostgresConnector;
use App\Hashing\DjangoHasher;
use Illuminate\Hashing\HashManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bound under the name ConnectionFactory::createConnector() looks up, so the stock
        // "pgsql" driver name keeps working while DSNs gain channel_binding support.
        $this->app->singleton('db.connector.pgsql', fn () => new PostgresConnector);

        $this->app->extend('hash', fn (HashManager $manager) => $manager->extend(
            'django',
            fn ($app) => new DjangoHasher($app['config']->get('hashing.bcrypt') ?? [])
        ));
    }

    public function boot(): void
    {
        //
    }
}
