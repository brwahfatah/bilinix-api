<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(): array
    {
        $user  = User::factory()->create(['whmcs_client_id' => 1]);
        $token = $user->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_invoices_list_requires_auth(): void
    {
        $this->getJson('/api/billing/invoices')->assertStatus(401);
    }

    public function test_invoices_list_returns_invoices(): void
    {
        $this->withHeaders($this->authHeaders())
             ->getJson('/api/billing/invoices')
             ->assertStatus(200)
             ->assertJsonStructure(['data', 'message', 'errors'])
             ->assertJsonPath('errors', null);
    }

    public function test_invoice_show_returns_detail(): void
    {
        $this->withHeaders($this->authHeaders())
             ->getJson('/api/billing/invoices/1001')
             ->assertStatus(200)
             ->assertJsonStructure([
                 'data' => ['id', 'status', 'total', 'items'],
             ]);
    }

    public function test_invoice_pay_returns_payment_url(): void
    {
        $this->withHeaders($this->authHeaders())
             ->postJson('/api/billing/invoices/1001/pay')
             ->assertStatus(200)
             ->assertJsonStructure([
                 'data' => ['payment_url'],
             ]);
    }
}
