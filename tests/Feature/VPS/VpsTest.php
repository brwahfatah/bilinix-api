<?php

namespace Tests\Feature\VPS;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VpsTest extends TestCase
{
    use RefreshDatabase;

    private function authUser(): array
    {
        $user  = User::factory()->create(['whmcs_client_id' => 1]);
        $token = $user->createToken('test')->plainTextToken;
        return [$user, $token];
    }

    public function test_vps_list_requires_auth(): void
    {
        $this->getJson('/api/vps')->assertStatus(401);
    }

    public function test_vps_list_returns_servers(): void
    {
        [, $token] = $this->authUser();

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
             ->getJson('/api/vps')
             ->assertStatus(200)
             ->assertJsonStructure(['data', 'message', 'errors'])
             ->assertJsonPath('errors', null);
    }

    public function test_vps_show_returns_single_server(): void
    {
        [, $token] = $this->authUser();

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
             ->getJson('/api/vps/1')
             ->assertStatus(200)
             ->assertJsonStructure([
                 'data' => ['id', 'status'],
             ]);
    }

    public function test_vps_power_actions_return_success(): void
    {
        [, $token] = $this->authUser();
        $headers   = ['Authorization' => "Bearer {$token}"];

        foreach (['/api/vps/1/start', '/api/vps/1/stop', '/api/vps/1/reboot'] as $endpoint) {
            $this->withHeaders($headers)
                 ->postJson($endpoint)
                 ->assertStatus(200)
                 ->assertJsonPath('errors', null);
        }
    }
}
