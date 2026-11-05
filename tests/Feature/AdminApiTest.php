<?php

namespace Tests\Feature;

use App\Mail\BookingUpdateMail;
use App\Models\Booking;
use App\Models\BookingFile;
use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\ClientStatus;
use App\Models\Team;
use App\Models\Vehicle;
use App\Models\VehicleFile;
use App\Models\VehicleFileState;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private const TEAM = 'TEAMADMIN1';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-04 10:30', 'America/Toronto'));
        Storage::fake('hostinger');
        Mail::fake();

        $this->client = Client::create([
            'client_id' => 'CLIENTADMINTEST',
            'email' => 'owner@example.com',
            'hashed_email' => hash('sha256', 'owner@example.com'),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'account_type' => 'individual',
            'phone' => '+15550001',
            'state_id' => 'STATE-ACTIVE1',
            'password' => 'secret123',
        ]);

        foreach ([[self::TEAM, 'Admin User', 'admin'], ['TEAMTECH1', 'Tech One', 'technician']] as [$id, $name, $type]) {
            Team::create([
                'team_id' => $id,
                'email' => strtolower($id) . '@example.com',
                'hashed_email' => hash('sha256', strtolower($id) . '@example.com'),
                'full_name' => $name,
                'account_type' => $type,
                'phone' => '+1555' . $id,
                'state_id' => 'STATE-' . $id,
                'password' => 'secret123',
            ]);
        }
    }

    private function vehicle(): Vehicle
    {
        $this->postJson('/api/vehicles/addVehicle', [
            'client_id' => $this->client->client_id,
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2021,
            'color' => 'Black',
            'plate_number' => 'ABC123',
            'vin' => 'VINABC123',
        ])->assertCreated();

        return Vehicle::firstOrFail();
    }

    private function book(string $date = '2026-11-05', string $time = '08:00', ?Vehicle $vehicle = null)
    {
        $vehicle ??= Vehicle::first() ?? $this->vehicle();

        return $this->postJson('/api/booking/addBooking', [
            'client_id' => $this->client->client_id,
            'vehicle_ids' => [$vehicle->veh_id],
            'service' => 'SERVICE_1',
            'service_type' => 'Structural Safety Inspection',
            'date' => $date,
            'time' => $time,
        ]);
    }

    private function team(array $data): array
    {
        return ['team_id' => self::TEAM] + $data;
    }

    public function test_availability_settings_drive_the_slots(): void
    {
        $this->getJson('/api/team/getAvailability?team_id=' . self::TEAM)
            ->assertOk()
            ->assertJsonPath('data.opens_at', '08:00')
            ->assertJsonPath('data.slot_capacity', 3);

        $this->putJson('/api/team/updateAvailability', $this->team([
            'operating_days' => [1, 2, 3, 4, 5],
            'opens_at' => '09:00',
            'closes_at' => '12:00',
            'slot_minutes' => 30,
            'slot_capacity' => 1,
            'lead_time_minutes' => 120,
        ]))->assertOk();

        $days = collect($this->getJson('/api/booking/getAvailableSlots?from=2026-11-04&days=4')->json('data.days'))->keyBy('date');

        $this->assertSame(['09:00', '09:30', '10:00', '10:30', '11:00', '11:30'], array_column($days['2026-11-04']['slots'], 'time'));
        $this->assertFalse(collect($days['2026-11-04']['slots'])->firstWhere('time', '12:00') !== null);
        $this->assertFalse(collect($days['2026-11-04']['slots'])->firstWhere('time', '11:30')['available']);
        $this->assertFalse($days['2026-11-07']['is_open']);

        $this->book('2026-11-04', '11:30')->assertStatus(422)->assertJsonPath('errors.time.0', 'This time is too soon to book. Please choose a later time.');
        $this->book('2026-11-05', '09:00')->assertCreated();
        $this->book('2026-11-05', '09:00')->assertStatus(422);

        $this->putJson('/api/team/updateAvailability', $this->team(['opens_at' => '13:00', 'closes_at' => '12:00']))
            ->assertStatus(422);
    }

    public function test_change_cutoff_blocks_late_reschedules_and_cancellations(): void
    {
        $this->putJson('/api/team/updateAvailability', $this->team(['change_cutoff_hours' => 24]))->assertOk();
        $soon = $this->book('2026-11-05', '08:00')->json('data.book_id');
        $later = $this->book('2026-11-09', '08:00')->json('data.book_id');

        $this->putJson('/api/booking/updateBooking', ['book_id' => $soon, 'date' => '2026-11-10', 'time' => '09:00'])
            ->assertStatus(422);
        $this->deleteJson('/api/booking/deleteBooking', ['book_id' => $soon])->assertStatus(422);

        $this->putJson('/api/booking/updateBooking', ['book_id' => $later, 'date' => '2026-11-10', 'time' => '09:00'])
            ->assertOk();
        $this->deleteJson('/api/booking/deleteBooking', ['book_id' => $later])->assertOk();
    }

    public function test_document_requirements_and_pipelines_are_configurable(): void
    {
        $this->getJson('/api/team/getDocumentRequirements?team_id=' . self::TEAM)
            ->assertOk()
            ->assertJsonPath('data.requirements.0.key', 'Proof_of_ownership');

        $this->putJson('/api/team/updateDocumentRequirements', $this->team([
            'requirements' => [
                ['key' => 'Proof_of_ownership', 'name' => 'Proof of Ownership', 'required' => true],
                ['key' => 'Repair_history', 'name' => 'Repair and Collision History', 'enabled' => false],
                ['name' => 'Insurance Letter', 'description' => 'From your insurer'],
            ],
        ]))->assertOk()->assertJsonPath('data.requirements.2.key', 'Insurance_letter');

        $this->assertSame(
            ['Proof_of_ownership', 'Insurance_letter'],
            array_column($this->getJson('/api/booking/getDocumentRequirements')->json('data.requirements'), 'key')
        );

        $this->putJson('/api/team/updateStatusPipelines', $this->team([
            'service' => [
                ['label' => 'Booked'],
                ['label' => 'Vehicle Received'],
                ['label' => 'Waiting for Parts'],
                ['label' => 'Completed'],
            ],
        ]))->assertOk()->assertJsonPath('data.service.2.name', 'WAITING_FOR_PARTS');

        $this->putJson('/api/team/updateStatusPipelines', $this->team([
            'service' => [['label' => 'Vehicle Received'], ['label' => 'Completed']],
        ]))->assertStatus(422);

        $this->getJson('/api/booking/getStatusPipelines')
            ->assertOk()
            ->assertJsonPath('data.structural_safety.3.label', 'Inspection in Progress');
    }

    public function test_admin_notification_settings_gate_channels(): void
    {
        $this->putJson('/api/team/updateNotificationSettings', $this->team([
            'events' => [['key' => 'booking_confirmed', 'email' => false]],
        ]))->assertOk()->assertJsonPath('data.events.0.email', false)->assertJsonPath('data.events.0.push', true);

        $bookId = $this->book()->assertCreated()->json('data.book_id');
        Mail::assertNothingSent();

        $this->postJson('/api/team/setBookingState', $this->team(['book_id' => $bookId, 'name' => 'VEHICLE_RECEIVED']))->assertOk();
        Mail::assertSent(BookingUpdateMail::class, 1);
        $this->assertSame(2, ClientNotification::where('notifiable_id', $bookId)->count());
    }

    public function test_eta_technician_and_certificate(): void
    {
        $bookId = $this->book()->json('data.book_id');

        $this->postJson('/api/team/setBookingEta', $this->team([
            'book_id' => $bookId,
            'estimated_completion_at' => '2026-11-07 15:00',
        ]))->assertOk();
        $this->assertSame('2026-11-07 20:00', Booking::first()->estimated_completion_at->utc()->format('Y-m-d H:i'));
        $this->assertSame('Estimated completion updated', ClientNotification::where('type', 'booking_eta')->value('subject'));

        $this->getJson('/api/team/getTechnicians?team_id=' . self::TEAM)->assertOk()->assertJsonCount(2, 'data');
        $this->postJson('/api/team/assignTechnician', $this->team(['book_id' => $bookId, 'technician_id' => 'TEAMTECH1']))
            ->assertOk()
            ->assertJsonPath('data.technician.full_name', 'Tech One');
        $this->postJson('/api/team/assignTechnician', $this->team(['book_id' => $bookId, 'technician_id' => 'NOPE']))
            ->assertStatus(422);

        $this->post('/api/team/uploadBookingCertificate', $this->team([
            'book_id' => $bookId,
            'certificate' => UploadedFile::fake()->create('cert.pdf', 50, 'application/pdf'),
        ]))->assertCreated()->assertJsonPath('data.latest_state.state', 'APPROVED');

        $file = BookingFile::where('book_id', $bookId)->firstOrFail();
        $this->assertSame('Structural_Safety_Certificate.pdf', $file->file_name);
        Storage::disk('hostinger')->assertExists($file->file_url);
        $this->assertSame(1, ClientNotification::where('type', 'booking_certificate')->count());

        $booking = collect($this->postJson('/api/team/getBookings', $this->team([]))->json('data'))->firstWhere('book_id', $bookId);
        $this->assertSame('TEAMTECH1', $booking['technician']['team_id']);
        $this->assertSame('APPROVED', $booking['files'][0]['latest_state']['state']);
        $this->assertNotNull($booking['estimated_completion_at']);

        $client = $this->getJson('/api/booking/getClientBookings?client_id=' . $this->client->client_id)->json('data.0');
        $this->assertNotNull($client['estimated_completion_at']);
    }

    public function test_edit_suspend_and_reactivate_a_client(): void
    {
        $this->putJson('/api/team/updateClient', $this->team([
            'client_id' => $this->client->client_id,
            'first_name' => 'Johnny',
            'email' => 'Johnny@Example.com',
            'account_type' => 'Dealer',
            'business_name' => 'Johnny Motors',
        ]))->assertOk()
            ->assertJsonPath('data.first_name', 'Johnny')
            ->assertJsonPath('data.account_type', 'dealer');
        $this->assertSame(hash('sha256', 'johnny@example.com'), $this->client->fresh()->hashed_email);

        $this->putJson('/api/team/suspendAccount', $this->team(['client_id' => $this->client->client_id]))->assertOk();
        $this->postJson('/api/client/login', ['email' => 'johnny@example.com', 'password' => 'secret123'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Your account has been suspended. Please contact support.');

        $this->putJson('/api/team/reactivateAccount', $this->team(['client_id' => $this->client->client_id]))->assertOk();
        $this->assertSame('ACTIVE', ClientStatus::where('state_id', $this->client->fresh()->state_id)->value('code'));
    }

    public function test_user_search_and_type_filter(): void
    {
        $this->postJson('/api/team/getUsers', $this->team(['key_word' => 'John Doe']))
            ->assertOk()->assertJsonCount(1, 'data.data');
        $this->postJson('/api/team/getUsers', $this->team(['key_word' => 'nobody']))
            ->assertOk()->assertJsonCount(0, 'data.data');
        $this->postJson('/api/team/getUsers', $this->team(['customer_type' => 'individual']))
            ->assertOk()->assertJsonCount(1, 'data.data');

        $this->book();
        $this->postJson('/api/team/getBookings', $this->team(['keyword' => 'John']))
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_vehicle_state_and_vehicle_file_review(): void
    {
        $vehicle = $this->vehicle();

        $this->postJson('/api/team/setVehicleState', $this->team(['veh_id' => $vehicle->veh_id, 'name' => 'In Service']))->assertOk();
        $this->assertNotNull($vehicle->fresh()->state_id);

        $file = VehicleFile::create([
            'veh_id' => $vehicle->veh_id,
            'file_name' => 'Vehicle_doc.pdf',
            'file_size' => 10,
            'file_type' => 'application/pdf',
            'file_url' => 'vehicle/Documents/x.pdf',
        ]);

        $this->putJson('/api/team/setVehicleFileState', $this->team(['file_id' => (string) $file->id, 'name' => 'approved']))
            ->assertCreated();
        $this->assertSame('APPROVED', VehicleFileState::where('file_id', $file->id)->value('state'));
    }

    public function test_report_analytics_use_real_numbers(): void
    {
        $first = $this->book('2026-11-05', '08:00')->json('data.book_id');
        $this->book('2026-11-05', '09:00');

        Carbon::setTestNow(Carbon::parse('2026-11-05 09:00', 'America/Toronto'));
        $this->postJson('/api/team/setBookingState', $this->team(['book_id' => $first, 'name' => 'VEHICLE_RECEIVED']))->assertOk();
        Carbon::setTestNow(Carbon::parse('2026-11-07 09:00', 'America/Toronto'));
        $this->postJson('/api/team/setBookingState', $this->team(['book_id' => $first, 'name' => 'COMPLETED']))->assertOk();

        $data = $this->getJson('/api/team/getReportAnalytics?team_id=' . self::TEAM)->assertOk()->json('data');

        $this->assertSame(2, $data['figCard']['total_bookings']);
        $this->assertSame(1, $data['figCard']['active_bookings']);
        $this->assertSame(1, $data['figCard']['completed_jobs']);
        $this->assertSame('2 days', $data['figCard']['average_turnaround_time']);
        $this->assertSame('100%', $data['bookings_by_client_type']['car_owner']);
        $this->assertSame(2, $data['booking_volum_over_time']['Nov']);
        $this->assertSame('SERVICE_1', $data['turnaround_time_distribution_by_service_type'][0]['service_type']);
    }

    public function test_appointment_reminders_go_out_once(): void
    {
        $tomorrow = $this->book('2026-11-05', '08:00')->json('data.book_id');
        $this->book('2026-11-09', '08:00');

        $this->artisan('bookings:send-reminders')->expectsOutput('Sent 1 reminder(s).')->assertSuccessful();
        $this->artisan('bookings:send-reminders')->expectsOutput('Sent 0 reminder(s).')->assertSuccessful();

        $this->assertSame(1, ClientNotification::where('type', 'booking_reminder')->count());
        $this->assertSame($tomorrow, ClientNotification::where('type', 'booking_reminder')->value('notifiable_id'));
    }
}
