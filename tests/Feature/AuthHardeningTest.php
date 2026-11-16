<?php

namespace Tests\Feature;

use App\Mail\BookingUpdateMail;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Team;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Client $other;

    private Team $admin;

    private Team $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->client = $this->client('CLIENTA', 'a@example.com');
        $this->other = $this->client('CLIENTB', 'b@example.com');
        $this->admin = $this->member('TEAMADMIN', 'admin@example.com', Team::SUPER_ADMIN);
        $this->staff = $this->member('TEAMSTAFF', 'staff@example.com', Team::STAFF);
    }

    private function client(string $id, string $email): Client
    {
        return Client::create([
            'client_id' => $id,
            'email' => $email,
            'hashed_email' => hash('sha256', $email),
            'first_name' => 'Pat',
            'last_name' => 'Lee',
            'account_type' => 'individual',
            'phone' => '+1555' . $id,
            'state_id' => 'STATE-' . $id,
            'password' => 'secret123',
        ]);
    }

    private function member(string $id, string $email, string $access): Team
    {
        return Team::create([
            'team_id' => $id,
            'email' => $email,
            'hashed_email' => hash('sha256', $email),
            'full_name' => ucfirst(strtolower($id)),
            'account_type' => 'admin',
            'access_level' => $access,
            'state_id' => 'STATE-' . $id,
            'password' => 'secret123',
        ]);
    }

    private function profile(Client $client): array
    {
        return [
            'client_id' => $client->client_id,
            'email' => $client->email,
            'account_type' => 'individual',
            'first_name' => 'Changed',
            'last_name' => 'Name',
            'phone' => '+15550000',
        ];
    }

    public function test_client_account_routes_need_the_owners_token(): void
    {
        $id = $this->client->client_id;

        $this->getJson('/api/client/getProfile?client_id=' . $id)->assertUnauthorized();
        $this->getJson('/api/client/getPreferences?client_id=' . $id)->assertUnauthorized();
        $this->putJson('/api/client/updateProfile', $this->profile($this->client))->assertUnauthorized();
        $this->putJson('/api/client/changePassword', [
            'client_id' => $id, 'current_password' => 'secret123', 'password' => 'hijacked1', 'confirm_password' => 'hijacked1',
        ])->assertUnauthorized();
        $this->deleteJson('/api/client/removeProfilePhoto', ['client_id' => $id])->assertUnauthorized();
        $this->putJson('/api/client/updatePushToken', ['client_id' => $id, 'session_id' => 'x', 'fcm_token' => 'x'])->assertUnauthorized();

        $other = $this->other->freshAccessToken();
        $this->withToken($other)->getJson('/api/client/getProfile?client_id=' . $id)->assertForbidden();
        $this->withToken($other)->putJson('/api/client/updateProfile', $this->profile($this->client))->assertForbidden();

        $this->withToken($this->client->freshAccessToken())->getJson('/api/client/getProfile?client_id=' . $id)->assertOk();
        $this->assertSame('Pat', $this->client->fresh()->first_name);
    }

    public function test_staff_can_update_a_client_profile_but_not_use_client_only_routes(): void
    {
        $token = $this->staff->freshAccessToken();

        $this->withToken($token)->putJson('/api/client/updateProfile', $this->profile($this->client))->assertOk();
        $this->assertSame('Changed', $this->client->fresh()->first_name);

        $this->withToken($token)->getJson('/api/client/getPreferences?client_id=' . $this->client->client_id)->assertUnauthorized();
    }

    public function test_changing_password_signs_out_old_tokens_but_keeps_this_device(): void
    {
        $old = $this->client->freshAccessToken();
        $this->travel(1)->minutes();

        $fresh = $this->withToken($old)->putJson('/api/client/changePassword', [
            'client_id' => $this->client->client_id,
            'current_password' => 'secret123',
            'password' => 'newsecret123',
            'confirm_password' => 'newsecret123',
        ])->assertOk()->json('data.access_token');

        $this->assertNotEmpty($fresh);
        $this->withToken($old)->getJson('/api/client/getProfile?client_id=' . $this->client->client_id)
            ->assertUnauthorized()->assertJsonPath('message', 'Your password was changed. Please log in again.');
        $this->withToken($fresh)->getJson('/api/client/getProfile?client_id=' . $this->client->client_id)->assertOk();
    }

    public function test_any_password_change_invalidates_earlier_client_tokens(): void
    {
        $old = $this->client->freshAccessToken();
        $this->travel(1)->minutes();

        $this->client->update(['password' => 'reset123456']);
        $this->assertNotNull($this->client->fresh()->password_changed_at);

        $this->withToken($old)->getJson('/api/client/getPreferences?client_id=' . $this->client->client_id)->assertUnauthorized();
        $this->assertTrue(Hash::check('reset123456', $this->client->fresh()->password));
    }

    public function test_team_self_service_routes_need_a_token_and_only_touch_your_own_account(): void
    {
        $this->postJson('/api/teamAuth/reset_password', [
            'team_id' => 'TEAMADMIN', 'password' => 'hijacked1', 'confirm_password' => 'hijacked1',
        ])->assertUnauthorized();
        $this->getJson('/api/teamAuth/getProfile?team_id=TEAMADMIN')->assertUnauthorized();

        $staff = $this->staff->freshAccessToken();
        $this->withToken($staff)->postJson('/api/teamAuth/reset_password', [
            'team_id' => 'TEAMADMIN', 'current_password' => 'secret123', 'password' => 'hijacked1', 'confirm_password' => 'hijacked1',
        ])->assertForbidden();
        $this->withToken($staff)->getJson('/api/teamAuth/getProfile?team_id=TEAMADMIN')->assertForbidden();
        $this->withToken($staff)->putJson('/api/teamAuth/updateProfile', [
            'team_id' => 'TEAMADMIN', 'email' => 'evil@example.com', 'full_name' => 'Evil', 'phone' => '1',
        ])->assertForbidden();

        $this->assertTrue(Hash::check('secret123', $this->admin->fresh()->password));
        $this->withToken($staff)->getJson('/api/teamAuth/getProfile?team_id=TEAMSTAFF')
            ->assertOk()->assertJsonPath('data.team_id', 'TEAMSTAFF');
    }

    public function test_team_password_change_needs_the_current_password_and_signs_out_old_tokens(): void
    {
        $old = $this->staff->freshAccessToken();
        $this->travel(1)->minutes();
        $body = ['team_id' => 'TEAMSTAFF', 'password' => 'newsecret123', 'confirm_password' => 'newsecret123'];

        $this->withToken($old)->postJson('/api/teamAuth/reset_password', $body)->assertStatus(422);
        $this->withToken($old)->postJson('/api/teamAuth/reset_password', ['current_password' => 'wrong-one'] + $body)
            ->assertStatus(422)->assertJsonValidationErrors('current_password');

        $fresh = $this->withToken($old)->postJson('/api/teamAuth/reset_password', ['current_password' => 'secret123'] + $body)
            ->assertOk()->assertJsonMissingPath('data.access_token')
            ->getCookie(config('admin_auth.cookie'), false)->getValue();

        $this->withToken($old)->getJson('/api/teamAuth/getProfile')->assertUnauthorized();
        $this->withToken($fresh)->getJson('/api/teamAuth/getProfile')->assertOk();
        Mail::assertSent(BookingUpdateMail::class, fn($mail) => $mail->hasTo('staff@example.com')
            && $mail->details['footer'] === 'staff');
    }

    public function test_admin_reset_of_a_staff_password_signs_that_member_out(): void
    {
        $old = $this->staff->freshAccessToken();
        $this->travel(1)->minutes();

        $this->withToken($this->admin->freshAccessToken())
            ->putJson('/api/team/resetStaffPassword', ['staff_id' => 'TEAMSTAFF'])->assertOk();

        $this->withToken($old)->getJson('/api/teamAuth/getProfile')->assertUnauthorized();
    }

    public function test_every_private_route_group_refuses_anonymous_calls(): void
    {
        $routes = [
            ['get', '/api/vehicles/getVehicle'], ['post', '/api/vehicles/addVehicle'],
            ['put', '/api/vehicles/updateVehicle'], ['delete', '/api/vehicles/deleteVehicle'],
            ['post', '/api/media/uploadFiles'],
            ['post', '/api/booking/addBooking'], ['put', '/api/booking/updateBooking'],
            ['delete', '/api/booking/deleteBooking'], ['get', '/api/booking/getClientBookings'],
            ['get', '/api/booking/getAvailableSlots'],
            ['get', '/api/notifications/getNotifications'], ['put', '/api/notifications/markRead'],
            ['post', '/api/team/getDashboard'], ['post', '/api/team/getUsers'], ['put', '/api/team/suspendAccount'],
            ['post', '/api/team/setBookingState'], ['put', '/api/team/updateClient'], ['post', '/api/team/assignTechnician'],
            ['put', '/api/team/updateAvailability'], ['put', '/api/team/updateNotificationSettings'],
            ['post', '/api/team/addServices'], ['delete', '/api/team/deleteServices'],
        ];

        foreach ($routes as [$method, $uri]) {
            $this->json($method, $uri, ['client_id' => 'CLIENTA', 'team_id' => 'TEAMADMIN'])
                ->assertUnauthorized();
        }
    }

    public function test_client_tokens_cannot_use_staff_routes(): void
    {
        $this->asClient($this->client)->postJson('/api/team/getUsers', ['team_id' => 'TEAMADMIN'])->assertUnauthorized();
    }

    public function test_staff_cannot_act_in_another_members_name(): void
    {
        $this->asStaff($this->staff)->postJson('/api/team/getUsers', ['team_id' => 'TEAMADMIN'])->assertForbidden();
        $this->asStaff($this->staff)->postJson('/api/team/getUsers', [])->assertOk();
    }

    public function test_clients_cannot_touch_each_others_vehicles_bookings_or_files(): void
    {
        Storage::fake('hostinger');
        Vehicle::create([
            'veh_id' => 'VEHA', 'client_id' => 'CLIENTA', 'make' => 'Toyota', 'model' => 'RAV4',
            'year' => 2021, 'color' => 'Red', 'plate_number' => 'AAA111', 'state_id' => 'STATE-VEHA',
        ]);
        Booking::create([
            'client_id' => 'CLIENTA', 'book_id' => 'BOOKA', 'vehicle_ids' => ['VEHA'], 'booking_ref' => '100001',
            'service' => 'SERVICE_1', 'date' => '2026-12-01', 'time' => '10:00', 'state_id' => 'STATE-BOOKA',
        ]);
        $intruder = $this->asClient($this->other);

        $intruder->getJson('/api/vehicles/getVehicle?client_id=CLIENTA')->assertForbidden();
        $intruder->putJson('/api/vehicles/updateVehicle', [
            'veh_id' => 'VEHA', 'make' => 'X', 'model' => 'Y', 'year' => 2020, 'color' => 'Blue', 'plate_number' => 'ZZZ999',
        ])->assertNotFound();
        $intruder->deleteJson('/api/vehicles/deleteVehicle', ['veh_id' => 'VEHA'])->assertNotFound();
        $intruder->deleteJson('/api/booking/deleteBooking', ['book_id' => 'BOOKA'])->assertNotFound();
        $intruder->putJson('/api/booking/updateBooking', ['book_id' => 'BOOKA', 'note' => 'mine now'])->assertNotFound();
        $intruder->postJson('/api/booking/addBooking', [
            'client_id' => 'CLIENTB', 'vehicle_ids' => ['VEHA'], 'service' => 'SERVICE_1',
            'service_type' => 'Inspection', 'date' => '2026-12-02', 'time' => '10:00',
        ])->assertNotFound();
        $intruder->getJson('/api/booking/getClientBookings?client_id=CLIENTA')->assertForbidden();
        $intruder->getJson('/api/notifications/getNotifications?client_id=CLIENTA')->assertForbidden();

        foreach ([['vehicle', 'VEHA', 'vehicle_doc'], ['booking', 'BOOKA', 'booking_doc'], ['account', 'CLIENTA', 'account_doc']] as [$target, $ref, $field]) {
            $intruder->post('/api/media/uploadFiles', [
                'uploadProtocol' => json_encode(['target' => $target, 'ref_id' => $ref, 'upload_at' => '2026-11-16 10:00:00']),
                $field => [UploadedFile::fake()->create('Doc.pdf', 10, 'application/pdf')],
            ])->assertNotFound();
        }

        $this->assertSame('Toyota', Vehicle::where('veh_id', 'VEHA')->value('make'));
        $this->assertTrue(Booking::where('book_id', 'BOOKA')->exists());
        $this->asClient($this->client)->deleteJson('/api/booking/deleteBooking', ['book_id' => 'BOOKA'])->assertOk();
    }

    public function test_staff_login_sets_an_http_only_cookie_and_hides_the_token(): void
    {
        $response = $this->postJson('/api/teamAuth/login', ['email' => 'staff@example.com', 'password' => 'secret123'])
            ->assertOk()->assertJsonMissingPath('data.auth.access_token');

        $cookie = $response->getCookie(config('admin_auth.cookie'), false);
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertNotEmpty($cookie->getValue());
    }

    public function test_cookie_auth_requires_the_csrf_header_and_logout_clears_it(): void
    {
        $login = $this->postJson('/api/teamAuth/login', ['email' => 'staff@example.com', 'password' => 'secret123']);
        $token = $login->getCookie(config('admin_auth.cookie'), false)->getValue();
        $sessionId = $login->json('data.auth.session_id');
        $name = config('admin_auth.cookie');

        $this->withCredentials()->withUnencryptedCookie($name, $token)->getJson('/api/teamAuth/getProfile')->assertForbidden();
        $this->withCredentials()->withUnencryptedCookie($name, $token)->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson('/api/teamAuth/getProfile')->assertOk()->assertJsonPath('data.team_id', 'TEAMSTAFF');
        $this->withCredentials()->withUnencryptedCookie($name, $token)->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->putJson('/api/client/updateProfile', $this->profile($this->client))->assertOk();

        $logout = $this->withCredentials()->withUnencryptedCookie($name, $token)->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->postJson('/api/teamAuth/logout', ['session_id' => $sessionId])->assertOk();
        $cleared = $logout->getCookie($name, false);
        $this->assertSame('', (string) $cleared->getValue());
        $this->assertTrue($cleared->isCleared());

        $this->withCredentials()->withUnencryptedCookie($name, $token)->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson('/api/teamAuth/getProfile')->assertUnauthorized();
    }

    public function test_client_routes_ignore_the_staff_cookie(): void
    {
        $token = $this->postJson('/api/teamAuth/login', ['email' => 'staff@example.com', 'password' => 'secret123'])
            ->getCookie(config('admin_auth.cookie'), false)->getValue();

        $this->withCredentials()->withUnencryptedCookie(config('admin_auth.cookie'), $token)->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson('/api/vehicles/getVehicle?client_id=CLIENTA')->assertUnauthorized();
    }
}
