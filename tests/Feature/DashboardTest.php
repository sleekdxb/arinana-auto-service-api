<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_recent_bookings_keep_their_own_appointment_date(): void
    {
        Carbon::setTestNow('2026-11-13 08:00:00');

        Booking::create([
            'client_id' => 'CLIENTA',
            'book_id' => 'BOOK1',
            'vehicle_ids' => [],
            'booking_ref' => '126338',
            'service' => 'SERVICE_1',
            'date' => '2026-10-15',
            'time' => '10:30',
            'state_id' => 'STATE1',
        ]);

        $admin = Team::create([
            'team_id' => 'TEAMADMIN',
            'email' => 'admin@example.com',
            'hashed_email' => hash('sha256', 'admin@example.com'),
            'full_name' => 'Admin',
            'account_type' => 'admin',
            'access_level' => Team::SUPER_ADMIN,
            'state_id' => 'STATE-TEAMADMIN',
            'password' => 'secret123',
        ]);

        $this->asStaff($admin)->postJson('/api/team/getDashboard', ['team_id' => 'TEAMADMIN'])
            ->assertOk()
            ->assertJsonPath('data.recent_bookings.0.booking_id', '126338')
            ->assertJsonPath('data.recent_bookings.0.appointment_date', 'Thu, Oct 15; 10:30 am');
    }
}
