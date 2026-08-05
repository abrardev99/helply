<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class FeatureServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->defineFeatures();
    }

    /**
     * Register the application's feature flags.
     */
    private function defineFeatures(): void
    {
        // Authentication (login, registration and the rest of the account flows)
        // is closed to the public for now. It stays available only in local
        // development, while the marketing site and waitlist remain public.
        Feature::define('auth', fn (): bool => $this->app->environment('local', 'testing'));
    }
}
