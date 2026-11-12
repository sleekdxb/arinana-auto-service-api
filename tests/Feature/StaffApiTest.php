<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TeamSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class StaffApiTest extends TestCase
{
    use RefreshDatabase;

    private Team $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->admin = $this->member('TEAMADMIN', 'admin@example.com', Team::SUPER_ADMIN);
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

    private function as(Team $team)
    {
        return $this->withToken(JWTAuth::fromUser($team));
    }

    private function add(array $overrides = [])
    {
        return $this->as($this->admin)->postJson('/api/team/addStaff', $overrides + [
            'full_name' => 'Linda Cheng',
            'email' => 'Linda@Example.com',
            'role' => 'General Technician',
            'access_level' => Team::STAFF,
            'employee_id' => 'EMP-002',
        ]);
    }

    public function test_staff_routes_need_a_team_token(): void
    {
        $this->getJson('/api/team/getStaff')->assertUnauthorized();
    }

    public function test_add_and_list_staff(): void
    {
        $response = $this->add()->assertCreated();

        $password = $response->json('data.temporary_password');
        $this->assertSame(12, strlen($password));
        $this->assertTrue($response->json('data.emailed'));
        Mail::assertSent(\App\Mail\BookingUpdateMail::class, fn ($mail) => $mail->hasTo('linda@example.com'));

        $this->postJson('/api/teamAuth/login', ['email' => 'linda@example.com', 'password' => $password])->assertOk();

        TeamSession::create([
            'team_id' => 'TEAMADMIN', 'session_id' => 'S1', 'payload' => '{}', 'last_activity' => 1762851600,
        ]);

        $staff = collect($this->as($this->admin)->getJson('/api/team/getStaff')->assertOk()->json('data'))->keyBy('email');

        $this->assertSame('General Technician', $staff['linda@example.com']['role']);
        $this->assertSame('STAFF', $staff['linda@example.com']['access_level']);
        $this->assertSame('EMP-002', $staff['linda@example.com']['employee_id']);
        $this->assertSame('Active', $staff['linda@example.com']['status']);
        $this->assertNotNull($staff['admin@example.com']['last_active_at']);
    }

    public function test_only_super_admins_can_manage_staff(): void
    {
        $staff = $this->member('TEAMSTAFF', 'staff@example.com', Team::STAFF);

        $this->as($staff)->getJson('/api/team/getStaff')->assertOk();
        $this->as($staff)->postJson('/api/team/addStaff', [
            'full_name' => 'X', 'email' => 'x@example.com', 'role' => 'CEO', 'access_level' => Team::SUPER_ADMIN,
        ])->assertForbidden();
    }

    public function test_update_role_and_details(): void
    {
        $id = $this->add()->json('data.staff.team_id');

        $this->as($this->admin)->putJson('/api/team/updateStaff', [
            'staff_id' => $id,
            'full_name' => 'Linda C.',
            'phone' => '+1 555 010 0102',
            'role' => 'Account Officer',
            'access_level' => Team::MANAGEMENT,
        ])->assertOk()->assertJsonPath('data.role', 'Account Officer')->assertJsonPath('data.access_level', 'MANAGEMENT');

        $this->as($this->admin)->putJson('/api/team/updateStaff', [
            'staff_id' => $id,
            'email' => 'admin@example.com',
        ])->assertStatus(422);
    }

    public function test_suspended_staff_cannot_log_in_or_use_the_api_until_reactivated(): void
    {
        $member = $this->member('TEAMSTAFF', 'staff@example.com', Team::STAFF);
        $token = JWTAuth::fromUser($member);

        $this->as($this->admin)->putJson('/api/team/suspendStaff', ['staff_id' => 'TEAMSTAFF'])
            ->assertOk()->assertJsonPath('data.status', 'Suspended');

        $this->postJson('/api/teamAuth/login', ['email' => 'staff@example.com', 'password' => 'secret123'])->assertForbidden();
        $this->withToken($token)->getJson('/api/team/getStaff')->assertForbidden();

        $this->as($this->admin)->putJson('/api/team/reactivateStaff', ['staff_id' => 'TEAMSTAFF'])
            ->assertOk()->assertJsonPath('data.status', 'Active');

        $this->postJson('/api/teamAuth/login', ['email' => 'staff@example.com', 'password' => 'secret123'])->assertOk();
    }

    public function test_cannot_suspend_or_delete_yourself_or_the_last_super_admin(): void
    {
        $this->as($this->admin)->putJson('/api/team/suspendStaff', ['staff_id' => 'TEAMADMIN'])->assertStatus(422);
        $this->as($this->admin)->deleteJson('/api/team/deleteStaff', ['staff_id' => 'TEAMADMIN'])->assertStatus(422);
        $this->as($this->admin)->putJson('/api/team/updateStaff', [
            'staff_id' => 'TEAMADMIN', 'access_level' => Team::STAFF,
        ])->assertStatus(422);
    }

    public function test_reset_password_and_delete(): void
    {
        $this->member('TEAMSTAFF', 'staff@example.com', Team::STAFF);

        $password = $this->as($this->admin)->putJson('/api/team/resetStaffPassword', ['staff_id' => 'TEAMSTAFF'])
            ->assertOk()->json('data.temporary_password');

        $this->postJson('/api/teamAuth/login', ['email' => 'staff@example.com', 'password' => 'secret123'])->assertUnauthorized();
        $this->postJson('/api/teamAuth/login', ['email' => 'staff@example.com', 'password' => $password])->assertOk();

        $this->as($this->admin)->deleteJson('/api/team/deleteStaff', ['staff_id' => 'TEAMSTAFF'])->assertOk();
        $this->assertDatabaseMissing('teams', ['team_id' => 'TEAMSTAFF']);
    }
}
