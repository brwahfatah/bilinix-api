<?php

namespace App\Providers;

use App\Integrations\FakeWhmcsService;
use App\Integrations\Payments\FakeStripePaymentProvider;
use App\Integrations\Payments\StripePaymentProvider;
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
        $useFakeWhmcs = config('services.whmcs.driver') === 'fake'
            || env('ENABLE_DEV_MOCKS', false)
            || empty(config('services.whmcs.url'));

        if ($useFakeWhmcs) {
            $this->app->bind(WhmcsService::class, FakeWhmcsService::class);
        }

        // Use FakeStripePaymentProvider only when no real Stripe key is configured.
        // This is independent of WHMCS mode so WHMCS can remain fake while Stripe
        // uses real test/live credentials.
        if (empty(config('services.stripe.key'))) {
            $this->app->bind(StripePaymentProvider::class, FakeStripePaymentProvider::class);
        }
    }

    public function boot(): void
    {
        //
    }
}
