<?php

namespace Tests\Feature\Ticket;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(): array
    {
        $user  = User::factory()->create(['whmcs_client_id' => 1]);
        $token = $user->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_tickets_list_requires_auth(): void
    {
        $this->getJson('/api/tickets')->assertStatus(401);
    }

    public function test_tickets_list_returns_tickets(): void
    {
        $this->withHeaders($this->authHeaders())
             ->getJson('/api/tickets')
             ->assertStatus(200)
             ->assertJsonStructure(['data', 'message', 'errors'])
             ->assertJsonPath('errors', null);
    }

    public function test_ticket_show_returns_ticket(): void
    {
        $this->withHeaders($this->authHeaders())
             ->getJson('/api/tickets/1001')
             ->assertStatus(200)
             ->assertJsonStructure([
                 'data' => ['id', 'subject', 'status', 'priority'],
             ]);
    }

    public function test_create_ticket_returns_ticket(): void
    {
        $this->withHeaders($this->authHeaders())
             ->postJson('/api/tickets', [
                 'subject'       => 'Test ticket from smoke test',
                 'department_id' => 4,
                 'priority'      => 'medium',
                 'message'       => 'This is a test message with enough characters.',
             ])
             ->assertStatus(201)
             ->assertJsonStructure([
                 'data' => ['id', 'subject'],
             ]);
    }

    public function test_reply_to_ticket(): void
    {
        $this->withHeaders($this->authHeaders())
             ->postJson('/api/tickets/1001/reply', [
                 'message' => 'This is a test reply message.',
             ])
             ->assertStatus(200)
             ->assertJsonPath('errors', null);
    }
}
