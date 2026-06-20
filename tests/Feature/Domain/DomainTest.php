<?php

namespace Tests\Feature\Domain;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(): array
    {
        $user  = User::factory()->create(['whmcs_client_id' => 1]);
        $token = $user->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_domains_list_requires_auth(): void
    {
        $this->getJson('/api/domains')->assertStatus(401);
    }

    public function test_domains_list_returns_domains(): void
    {
        $this->withHeaders($this->authHeaders())
             ->getJson('/api/domains')
             ->assertStatus(200)
             ->assertJsonStructure(['data', 'message', 'errors'])
             ->assertJsonPath('errors', null);
    }

    public function test_domain_show_returns_domain(): void
    {
        $this->withHeaders($this->authHeaders())
             ->getJson('/api/domains/1')
             ->assertStatus(200)
             ->assertJsonStructure([
                 'data' => ['id', 'domain', 'status'],
             ]);
    }

    public function test_domain_toggle_auto_renew(): void
    {
        $this->withHeaders($this->authHeaders())
             ->postJson('/api/domains/1/auto-renew')
             ->assertStatus(200)
             ->assertJsonPath('errors', null);
    }

    public function test_domain_update_nameservers(): void
    {
        $this->withHeaders($this->authHeaders())
             ->patchJson('/api/domains/1/nameservers', [
                 'nameservers' => ['ns1.example.com', 'ns2.example.com'],
             ])
             ->assertStatus(200)
             ->assertJsonPath('errors', null);
    }
}
