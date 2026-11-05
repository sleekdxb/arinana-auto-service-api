<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingFile;
use App\Models\BookingState;
use App\Models\Client;
use App\Models\ClientFile;
use App\Models\Team;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientApiTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-04 10:30', 'America/Toronto'));
        Storage::fake('hostinger');

        $this->client = Client::create([
            'client_id' => 'CLIENTTEST1',
            'email' => 'owner@example.com',
            'hashed_email' => hash('sha256', 'owner@example.com'),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'account_type' => 'dealer',
            'phone' => '+15550001',
            'state_id' => 'STATE-CLIENT1',
            'password' => 'secret123',
        ]);

        Team::create([
            'team_id' => 'TEAMTEST1',
            'email' => 'admin@example.com',
            'hashed_email' => hash('sha256', 'admin@example.com'),
            'full_name' => 'Admin',
            'account_type' => 'admin',
            'phone' => '+15559999',
            'state_id' => 'STATE-TEAM1',
            'password' => 'secret123',
        ]);
    }

    private function addVehicle(string $plate = 'ABC123'): Vehicle
    {
        $this->postJson('/api/vehicles/addVehicle', [
            'client_id' => $this->client->client_id,
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2021,
            'color' => 'Black',
            'plate_number' => $plate,
            'vin' => 'VIN' . $plate,
            'mileage' => 42100,
        ])->assertCreated();

        return Vehicle::where('plate_number', $plate)->firstOrFail();
    }

    private function book(Vehicle $vehicle, string $date = '2026-11-05', string $time = '08:00')
    {
        return $this->postJson('/api/booking/addBooking', [
            'client_id' => $this->client->client_id,
            'vehicle_ids' => [$vehicle->veh_id],
            'service' => 'SERVICE_1',
            'service_type' => 'Oil Change',
            'date' => $date,
            'time' => $time,
        ]);
    }

    public function test_booking_documents_attach_and_bookings_return_timeline(): void
    {
        $vehicle = $this->addVehicle();
        $bookId = $this->book($vehicle)->assertCreated()->json('data.book_id');

        $this->post('/api/media/uploadFiles', [
            'uploadProtocol' => json_encode([
                'target' => 'booking',
                'ref_id' => $bookId,
                'upload_at' => '2026-11-04 10:31:00',
            ]),
            'booking_doc' => [
                UploadedFile::fake()->create('Proof_of_ownership.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('Repair_history.pdf', 100, 'application/pdf'),
            ],
        ])->assertOk();

        $this->assertSame(2, BookingFile::where('book_id', $bookId)->where('client_id', $this->client->client_id)->count());

        $file = BookingFile::where('book_id', $bookId)->where('file_name', 'Repair_history.pdf')->firstOrFail();

        $this->putJson('/api/team/setBookingFileState', [
            'file_id' => (string) $file->id,
            'team_id' => 'TEAMTEST1',
            'name' => 'rejected',
            'note' => 'Document is blurry',
        ])->assertCreated();

        $this->postJson('/api/team/setBookingState', [
            'book_id' => $bookId,
            'team_id' => 'TEAMTEST1',
            'name' => 'Vehicle Received',
        ])->assertOk();

        $this->postJson('/api/team/setBookingState', [
            'book_id' => $bookId,
            'team_id' => 'TEAMTEST1',
            'name' => 'IN_PROGRESS',
        ])->assertOk();

        $booking = $this->getJson('/api/booking/getClientBookings?client_id=' . $this->client->client_id)
            ->assertOk()
            ->json('data.0');

        $this->assertSame([$vehicle->veh_id], $booking['vehicle_ids']);
        $this->assertSame(['PLACED', 'VEHICLE_RECEIVED', 'IN_PROGRESS'], array_column($booking['states'], 'name'));
        $this->assertSame('IN_PROGRESS', $booking['state']['name']);
        $this->assertSame(
            'Service in Progress',
            \App\Models\ClientNotification::where('type', 'booking_status')->latest('id')->first()->subject
        );
        $this->assertSame('READY_FOR_PICKUP', BookingState::normalizeName('Ready for Pickup'));
        $this->assertSame('PLACED', BookingState::normalizeName('Booked'));
        $this->assertSame('COMPLETED', BookingState::normalizeName('completed'));
        $this->assertSame('IN_PROGRESS', BookingState::normalizeName('INSPECTION IN PROGRESS'));
        $this->assertSame('IN_PROGRESS', BookingState::normalizeName('SERVICE IN PROGRESS'));
        $this->assertSame($vehicle->veh_id, $booking['vehicles'][0]['veh_id']);
        $this->assertArrayHasKey('files', $booking['vehicles'][0]);

        $files = collect($booking['files'])->keyBy('file_name');
        $this->assertSame('uploaded', $files['Proof_of_ownership.pdf']['latest_state']['state']);
        $this->assertSame('rejected', $files['Repair_history.pdf']['latest_state']['state']);
        $this->assertSame('Document is blurry', $files['Repair_history.pdf']['latest_state']['note']);
    }

    public function test_upload_to_unknown_booking_is_rejected(): void
    {
        $this->post('/api/media/uploadFiles', [
            'uploadProtocol' => json_encode([
                'target' => 'booking',
                'ref_id' => 'BOOKMISSING',
                'upload_at' => '2026-11-04 10:31:00',
            ]),
            'booking_doc' => [UploadedFile::fake()->create('Proof_of_ownership.pdf', 10, 'application/pdf')],
        ])->assertNotFound();
    }

    public function test_available_slots_reflect_hours_and_bookings(): void
    {
        config(['booking.slot_capacity' => 1]);
        $vehicle = $this->addVehicle();
        $this->book($vehicle, '2026-11-05', '09:00')->assertCreated();

        $days = $this->getJson('/api/booking/getAvailableSlots?from=2026-11-04&days=5')
            ->assertOk()
            ->json('data.days');

        $this->assertCount(5, $days);

        $today = collect($days[0]['slots'])->keyBy('time');
        $this->assertFalse($today['10:00']['available']);
        $this->assertTrue($today['11:00']['available']);
        $this->assertSame('15:00', $today->keys()->last());

        $thursday = collect($days[1]['slots'])->keyBy('time');
        $this->assertFalse($thursday['09:00']['available']);
        $this->assertTrue($thursday['08:00']['available']);

        $sunday = collect($days)->firstWhere('date', '2026-11-08');
        $this->assertFalse($sunday['is_open']);
        $this->assertSame([], $sunday['slots']);

        $this->book($vehicle, '2026-11-05', '09:00')->assertStatus(422)->assertJsonPath('errors.time.0', 'This time slot is fully booked. Please choose another time.');
        $this->book($vehicle, '2026-11-08', '09:00')->assertStatus(422);
        $this->book($vehicle, '2026-11-04', '08:00')->assertStatus(422);
        $this->book($vehicle, '2026-11-05', '18:00')->assertStatus(422);
    }

    public function test_rescheduling_keeps_own_slot(): void
    {
        config(['booking.slot_capacity' => 1]);
        $vehicle = $this->addVehicle();
        $bookId = $this->book($vehicle, '2026-11-05', '09:00')->json('data.book_id');

        $this->putJson('/api/booking/updateBooking', ['book_id' => $bookId, 'time' => '09:00', 'note' => 'same'])
            ->assertOk();
        $this->putJson('/api/booking/updateBooking', ['book_id' => $bookId, 'date' => '2026-11-06', 'time' => '10:00'])
            ->assertOk();

        $this->assertSame('10:00', substr(Booking::where('book_id', $bookId)->first()->getRawOriginal('time'), 0, 5));
    }

    public function test_notifications_feed_and_mark_read(): void
    {
        $vehicle = $this->addVehicle();
        $this->book($vehicle)->assertCreated();

        $response = $this->getJson('/api/notifications/getNotifications?client_id=' . $this->client->client_id)
            ->assertOk()
            ->assertJsonPath('unread_count', 2);

        $this->assertSame(['booking_placed', 'vehicle_added'], array_column($response->json('data.data'), 'type'));
        $this->assertSame('New Vehicle Added!', $response->json('data.data.1.title'));
        $this->assertSame('2021 Toyota Corolla - Black has been added to your vehicles.', $response->json('data.data.1.body'));
        $this->assertSame(Vehicle::first()->veh_id, $response->json('data.data.1.ref_id'));
        $this->assertFalse($response->json('data.data.1.read'));

        $firstId = $response->json('data.data.0.notif_id');

        $this->putJson('/api/notifications/markRead', [
            'client_id' => $this->client->client_id,
            'notif_ids' => [$firstId],
        ])->assertOk()->assertJsonPath('data.unread_count', 1);

        $this->putJson('/api/notifications/markRead', ['client_id' => $this->client->client_id])
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_preferences_default_and_update(): void
    {
        $this->getJson('/api/client/getPreferences?client_id=' . $this->client->client_id)
            ->assertOk()
            ->assertJsonPath('data.push_enabled', true)
            ->assertJsonPath('data.sms_enabled', false);

        $this->putJson('/api/client/updatePreferences', [
            'client_id' => $this->client->client_id,
            'sms_enabled' => true,
            'booking_updates' => false,
        ])->assertOk()
            ->assertJsonPath('data.sms_enabled', true)
            ->assertJsonPath('data.booking_updates', false)
            ->assertJsonPath('data.email_enabled', true);
    }

    public function test_update_profile_business_fields_and_email(): void
    {
        $this->putJson('/api/client/updateProfile', [
            'client_id' => $this->client->client_id,
            'email' => 'New@Example.com',
            'account_type' => 'dealer',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+15550001',
            'business_name' => 'BelforTech Group',
            'registration_number' => 'CAC-123',
        ])->assertOk()
            ->assertJsonPath('data.business_name', 'BelforTech Group')
            ->assertJsonPath('data.registration_number', 'CAC-123')
            ->assertJsonPath('data.email', 'new@example.com');

        $this->assertSame(hash('sha256', 'new@example.com'), $this->client->fresh()->hashed_email);
    }

    public function test_change_password_checks_current_password(): void
    {
        $payload = [
            'client_id' => $this->client->client_id,
            'current_password' => 'wrong-pass',
            'password' => 'newsecret123',
            'confirm_password' => 'newsecret123',
        ];

        $this->putJson('/api/client/changePassword', $payload)->assertStatus(422);

        $this->putJson('/api/client/changePassword', ['current_password' => 'secret123'] + $payload)->assertOk();

        $this->assertTrue(Hash::check('newsecret123', $this->client->fresh()->password));
    }

    public function test_profile_photo_upload_and_remove(): void
    {
        $this->post('/api/media/uploadFiles', [
            'uploadProtocol' => json_encode([
                'target' => 'account',
                'ref_id' => $this->client->client_id,
                'upload_at' => '2026-11-04 10:31:00',
            ]),
            'account_img' => [UploadedFile::fake()->image('avatar.jpg')],
        ])->assertOk();

        $this->assertStringStartsWith('https://arianaautoservice.com/Files/account/Profiles/Img/', $this->client->fresh()->profile_img);
        $this->assertSame(1, ClientFile::where('client_id', $this->client->client_id)->count());

        $this->deleteJson('/api/client/removeProfilePhoto', ['client_id' => $this->client->client_id])
            ->assertOk()
            ->assertJsonPath('data.profile_img', null);

        $this->assertSame(0, ClientFile::where('client_id', $this->client->client_id)->count());
    }

    private function signIn(string $id, ?string $token = null): void
    {
        \App\Models\ClientSession::create([
            'id' => 'ID_' . $id,
            'client_id' => $this->client->client_id,
            'session_id' => $id,
            'payload' => '{}',
            'last_activity' => time(),
            'fcm_token' => $token,
        ]);
    }

    private function fakeFirebase(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $path = sys_get_temp_dir() . '/fcm-test-' . uniqid() . '.json';
        file_put_contents($path, json_encode([
            'project_id' => 'ariana-test',
            'client_email' => 'push@ariana-test.iam.gserviceaccount.com',
            'private_key' => $pem,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
        config(['services.fcm.credentials' => $path]);
        \Illuminate\Support\Facades\Cache::forget('fcm_access_token');
    }

    public function test_booking_emails_follow_preferences(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $vehicle = $this->addVehicle();
        $bookId = $this->book($vehicle)->assertCreated()->json('data.book_id');

        \Illuminate\Support\Facades\Mail::assertSent(
            \App\Mail\BookingUpdateMail::class,
            fn($mail) => $mail->hasTo('owner@example.com')
                && str_starts_with($mail->subjectLine, 'Booking confirmed – ARN-')
                && in_array(['Date & time', 'Thu, Nov 5, 2026, 8:00 AM'], $mail->details['rows'], true)
        );

        $this->postJson('/api/team/setBookingState', [
            'book_id' => $bookId,
            'team_id' => 'TEAMTEST1',
            'name' => 'READY_FOR_PICKUP',
            'note' => 'Bay 3, keys at the front desk',
        ])->assertOk();

        \Illuminate\Support\Facades\Mail::assertSent(
            \App\Mail\BookingUpdateMail::class,
            fn($mail) => $mail->details['heading'] === 'Ready for Pickup'
                && $mail->details['note'] === 'Bay 3, keys at the front desk'
        );
        $this->assertSame(2, \App\Models\ClientEmail::where('is_sent', true)->count());

        $this->putJson('/api/client/updatePreferences', [
            'client_id' => $this->client->client_id,
            'service_status_updates' => false,
        ])->assertOk();

        $this->postJson('/api/team/setBookingState', [
            'book_id' => $bookId,
            'team_id' => 'TEAMTEST1',
            'name' => 'IN_PROGRESS',
        ])->assertOk();

        \Illuminate\Support\Facades\Mail::assertSentCount(2);

        $this->putJson('/api/client/updatePreferences', [
            'client_id' => $this->client->client_id,
            'service_status_updates' => true,
            'email_enabled' => false,
        ])->assertOk();

        $this->postJson('/api/team/setBookingState', [
            'book_id' => $bookId,
            'team_id' => 'TEAMTEST1',
            'name' => 'COMPLETED',
        ])->assertOk();

        \Illuminate\Support\Facades\Mail::assertSentCount(2);
    }

    public function test_push_goes_to_signed_in_devices_and_drops_dead_tokens(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->fakeFirebase();
        $this->signIn('SESS_A', 'token-live');
        $this->signIn('SESS_B', 'token-dead');
        \Illuminate\Support\Facades\Http::fake([
            'oauth2.googleapis.com/*' => \Illuminate\Support\Facades\Http::response(['access_token' => 'ya29.test']),
            'fcm.googleapis.com/*' => function ($request) {
                return $request['message']['token'] === 'token-dead'
                    ? \Illuminate\Support\Facades\Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404)
                    : \Illuminate\Support\Facades\Http::response(['name' => 'projects/ariana-test/messages/1']);
            },
        ]);

        $this->book($this->addVehicle())->assertCreated();

        \Illuminate\Support\Facades\Http::assertSent(fn($request) => str_contains($request->url(), 'projects/ariana-test/messages:send')
            && $request['message']['token'] === 'token-live'
            && $request['message']['notification']['title'] === 'Booking Confirmed!'
            && $request['message']['data']['type'] === 'booking_placed'
            && $request->hasHeader('Authorization', 'Bearer ya29.test'));
        $this->assertNull(\App\Models\ClientSession::where('session_id', 'SESS_B')->value('fcm_token'));
        $this->assertSame('token-live', \App\Models\ClientSession::where('session_id', 'SESS_A')->value('fcm_token'));
    }

    public function test_no_push_without_firebase_credentials(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        config(['services.fcm.credentials' => '/nonexistent.json']);
        $this->signIn('SESS_A', 'token-live');
        \Illuminate\Support\Facades\Http::fake();

        $this->book($this->addVehicle())->assertCreated();

        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_push_token_moves_to_the_latest_session(): void
    {
        $this->signIn('SESS_OLD', 'shared-device');
        $this->signIn('SESS_NEW');

        $this->putJson('/api/client/updatePushToken', [
            'client_id' => $this->client->client_id,
            'session_id' => 'SESS_NEW',
            'fcm_token' => 'shared-device',
        ])->assertOk();

        $this->assertNull(\App\Models\ClientSession::where('session_id', 'SESS_OLD')->value('fcm_token'));
        $this->assertSame('shared-device', \App\Models\ClientSession::where('session_id', 'SESS_NEW')->value('fcm_token'));

        $this->putJson('/api/client/updatePushToken', [
            'client_id' => $this->client->client_id,
            'session_id' => 'SESS_MISSING',
            'fcm_token' => 'x',
        ])->assertNotFound();
    }
}
