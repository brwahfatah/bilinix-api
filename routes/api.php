<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Cart\CartController;
use App\Http\Controllers\Domain\DomainController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Order\OrderController;
use App\Http\Controllers\Payment\PaymentController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\Ticket\TicketController;
use App\Http\Controllers\VPS\VpsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Beeliin Hosting Control Layer
|--------------------------------------------------------------------------
|
| All routes return { data, message, errors } via ApiResponse trait.
| No raw WHMCS responses are ever sent to the frontend.
|
*/

// ── Health check (no auth — used by load balancers and uptime monitors) ──────
Route::get('/health', [HealthController::class, 'check']);

// ── Payment webhooks (no auth — signature verified inside controller) ─────────
// Must be registered BEFORE the auth:sanctum group so Stripe can POST without a token.
// success/cancel are also public because Stripe redirects without a Bearer token.
Route::post('/payments/webhook/stripe', [PaymentController::class, 'stripeWebhook']);
Route::get('/payments/success',         [PaymentController::class, 'success']);
Route::get('/payments/cancel',          [PaymentController::class, 'cancel']);

// ── Public cart endpoints (guest + auth) ──────────────────────────────────────
// Cart is resolved via X-Cart-Token header for guests; auth users auto-adopt their cart.
Route::get('/cart',               [CartController::class, 'show']);
Route::post('/cart/items',        [CartController::class, 'addItem']);
Route::patch('/cart/items/{id}',  [CartController::class, 'updateItem']);
Route::delete('/cart/items/{id}', [CartController::class, 'removeItem']);
Route::delete('/cart',            [CartController::class, 'clear']);

// ── Public auth endpoints ─────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register',        [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login',           [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
});

// ── Public product catalog (no auth required) ─────────────────────────────────
// 'featured' and 'grouped' must come before '{id}' to avoid wildcard capture.
Route::get('/products/featured', [ProductController::class, 'featured']);
Route::get('/products/grouped',  [ProductController::class, 'grouped']);
Route::get('/products/{id}',     [ProductController::class, 'show']);
Route::get('/products',          [ProductController::class, 'index']);

// ── Public domain search (no auth — used on marketing / checkout pages) ───────
// Must be declared before the auth group's /domains/{id} wildcard.
Route::post('/domains/search', [DomainController::class, 'search']);

// ── Authenticated endpoints ───────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::get('/me',               [AuthController::class, 'me']);
        Route::post('/logout',          [AuthController::class, 'logout']);
        Route::post('/logout-all',      [AuthController::class, 'logoutAll']);
        Route::patch('/profile',        [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });

    // Dashboard
    // Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    // VPS
    Route::get('/vps',              [VpsController::class, 'index']);
    Route::post('/vps',             [VpsController::class, 'store']);
    Route::get('/vps/{id}',         [VpsController::class, 'show']);
    Route::post('/vps/{id}/start',  [VpsController::class, 'start']);
    Route::post('/vps/{id}/stop',   [VpsController::class, 'stop']);
    Route::post('/vps/{id}/reboot', [VpsController::class, 'reboot']);
    Route::delete('/vps/{id}',      [VpsController::class, 'destroy']);

    // Domains
    Route::get('/domains',                        [DomainController::class, 'index']);
    Route::get('/domains/{id}',                   [DomainController::class, 'show']);
    Route::post('/domains/{id}/renew',            [DomainController::class, 'renew']);
    Route::post('/domains/{id}/auto-renew',       [DomainController::class, 'toggleAutoRenew']);
    Route::post('/domains/{id}/lock',             [DomainController::class, 'lock']);
    Route::post('/domains/{id}/unlock',           [DomainController::class, 'unlock']);
    Route::patch('/domains/{id}/nameservers',     [DomainController::class, 'updateNameservers']);

    // Billing
    Route::prefix('billing')->group(function () {
        Route::get('/invoices',             [BillingController::class, 'index']);
        Route::get('/invoices/{id}',        [BillingController::class, 'show']);
        Route::post('/invoices/{id}/pay',   [BillingController::class, 'pay']);
    });

    // Support Tickets
    Route::get('/tickets',             [TicketController::class, 'index']);
    Route::post('/tickets',            [TicketController::class, 'store']);
    Route::get('/tickets/{id}',        [TicketController::class, 'show']);
    Route::post('/tickets/{id}/reply', [TicketController::class, 'reply']);
    Route::post('/tickets/{id}/close', [TicketController::class, 'close']);

    // Orders (checkout requires auth; cart is adopted on first auth request)
    Route::post('/orders/checkout', [OrderController::class, 'checkout']);
    Route::get('/orders',           [OrderController::class, 'index']);
    Route::get('/orders/{id}',      [OrderController::class, 'show']);

    // Payments — checkout requires auth (invoice ownership enforced in service)
    Route::post('/payments/checkout', [PaymentController::class, 'checkout']);

});
