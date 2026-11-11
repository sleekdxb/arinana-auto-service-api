<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ClientLogoutTest extends TestCase
{
    use RefreshDatabase;

    private function makeClient(string $id): Client
    {
        return Client::create([
            'client_id' => $id,
            'email' => strtolower($id) . '@example.com',
            'hashed_email' => hash('sha256', strtolower($id) . '@example.com'),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'account_type' => 'individual',
            'phone' => '+1555' . $id,
            'state_id' => 'STATE-' . $id,
            'password' => 'secret123',
        ]);
    }

    private function addSession(Client $client, string $id): void
    {
        ClientSession::create([
            'id' => 'ID_' . $id,
            'client_id' => $client->client_id,
            'session_id' => $id,
            'payload' => '{}',
            'last_activity' => time(),
        ]);
    }

    public function test_logout_ends_the_session(): void
    {
        $client = $this->makeClient('CLIENTA');
        $this->addSession($client, 'S1');

        $this->withToken(JWTAuth::fromUser($client))
            ->postJson('/api/client/logout', ['session_id' => 'S1'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('client_sessions', ['session_id' => 'S1']);
    }

    public function test_logout_requires_a_token(): void
    {
        $client = $this->makeClient('CLIENTA');
        $this->addSession($client, 'S1');

        $this->postJson('/api/client/logout', ['session_id' => 'S1'])->assertUnauthorized();

        $this->assertDatabaseHas('client_sessions', ['session_id' => 'S1']);
    }

    public function test_cannot_end_someone_elses_session(): void
    {
        $me = $this->makeClient('CLIENTA');
        $other = $this->makeClient('CLIENTB');
        $this->addSession($other, 'S2');

        $this->withToken(JWTAuth::fromUser($me))
            ->postJson('/api/client/logout', ['session_id' => 'S2'])
            ->assertNotFound();

        $this->assertDatabaseHas('client_sessions', ['session_id' => 'S2']);
    }
}
