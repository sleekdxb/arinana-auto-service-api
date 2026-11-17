<?php

namespace Tests\Feature;

use App\Mail\BookingUpdateMail;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ContactMessagesTest extends TestCase
{
    use RefreshDatabase;

    private function team(): string
    {
        return JWTAuth::fromUser(Team::create([
            'team_id' => 'TEAMINBOX',
            'email' => 'inbox@example.com',
            'hashed_email' => hash('sha256', 'inbox@example.com'),
            'full_name' => 'Inbox Admin',
            'account_type' => 'admin',
            'access_level' => Team::STAFF,
            'state_id' => 'STATE-INBOX',
            'password' => 'secret123',
        ]));
    }

    public function test_messages_are_acknowledged_listed_read_replied_and_closed(): void
    {
        Mail::fake();

        $this->postJson('/api/public/submitMessage', [
            'name' => 'Jane Visitor',
            'email' => 'Jane@Example.com',
            'interested' => 'Structural Safety',
            'message' => 'Do you inspect trucks?',
        ])->assertCreated();
        Mail::assertSent(BookingUpdateMail::class, fn ($mail) => $mail->hasTo('jane@example.com')
            && $mail->subjectLine === 'We received your message');

        $this->getJson('/api/team/getContactMessages')->assertUnauthorized();

        $token = $this->team();
        $message = $this->withToken($token)->getJson('/api/team/getContactMessages')
            ->assertOk()->assertJsonPath('data.0.status', 'NEW')->json('data.0');

        $this->withToken($token)->putJson('/api/team/setContactMessageState', ['con_id' => $message['con_id'], 'state' => 'READ'])
            ->assertOk()->assertJsonPath('data.status', 'READ');

        $this->withToken($token)->postJson('/api/team/replyContactMessage', [
            'con_id' => $message['con_id'],
            'message' => 'Yes, we inspect light trucks too.',
        ])->assertOk()->assertJsonPath('data.status', 'REPLIED')
            ->assertJsonPath('data.history.2.note', 'Yes, we inspect light trucks too.')
            ->assertJsonPath('data.history.2.by', 'Inbox Admin');
        Mail::assertSent(BookingUpdateMail::class, fn ($mail) => $mail->subjectLine === 'Re: Your message to Ariana Auto Service'
            && $mail->details['message'] === 'Yes, we inspect light trucks too.');

        $this->withToken($token)->putJson('/api/team/setContactMessageState', ['con_id' => $message['con_id'], 'state' => 'REPLIED'])
            ->assertStatus(422);
        $this->withToken($token)->putJson('/api/team/setContactMessageState', ['con_id' => $message['con_id'], 'state' => 'CLOSED'])
            ->assertOk()->assertJsonPath('data.status', 'CLOSED');
    }
}
