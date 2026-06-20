<?php

namespace App\Providers;

use App\Integrations\FakeWhmcsService;
use App\Integrations\WhmcsService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Use FakeWhmcsService when:
        //   a) WHMCS_DRIVER=fake       (explicit driver flag), OR
        //   b) ENABLE_DEV_MOCKS=true   (legacy local dev flag), OR
        //   c) WHMCS_API_URL is blank  (nothing configured at all)
        $useFake = env('WHMCS_DRIVER') === 'fake'
            || env('ENABLE_DEV_MOCKS', false)
            || empty(config('services.whmcs.url'));

        if ($useFake) {
            $this->app->bind(WhmcsService::class, FakeWhmcsService::class);
        }
    }

    public function boot(): void
    {
        //
    }
}
