<?php

namespace App\Providers;

use App\Services\Illuminate\ChallengeApi;
use App\Services\Illuminate\RemoteDatabase;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ChallengeApi::class, fn () => new ChallengeApi(
            (string) config('services.illuminate.api_url'),
            config('services.illuminate.token'),
        ));

        $this->app->bind(RemoteDatabase::class, fn ($app) => new RemoteDatabase(
            $app->make(ChallengeApi::class),
            (int) config('services.illuminate.tunnel_port'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
