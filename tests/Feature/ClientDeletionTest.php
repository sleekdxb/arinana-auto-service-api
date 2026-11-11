<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingFile;
use App\Models\BookingFileState;
use App\Models\BookingState;
use App\Models\Client;
use App\Models\ClientFile;
use App\Models\ClientNotification;
use App\Models\ClientSession;
use App\Models\Otp;
use App\Models\Vehicle;
use App\Models\VehicleFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ClientDeletionTest extends TestCase
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

    private function booking(Client $client, string $id, string $status, array $vehicleIds): Booking
    {
        BookingState::create([
            'book_id' => $id,
            'state_id' => 'BS-' . $id,
            'name' => $status,
            'code' => $status,
        ]);

        return Booking::create([
            'client_id' => $client->client_id,
            'book_id' => $id,
            'vehicle_ids' => $vehicleIds,
            'booking_ref' => 'REF-' . $id,
            'service' => 'SERVICE_1',
            'service_type' => 'Structural Safety',
            'date' => '2026-11-05',
            'time' => '08:00',
            'note' => 'Call me on my cell',
            'state_id' => 'BS-' . $id,
        ]);
    }

    private function file(string $path): string
    {
        Storage::disk('hostinger')->put($path, 'x');

        return $path;
    }

    public function test_requires_a_token(): void
    {
        $client = $this->makeClient('CLIENTA');

        $this->deleteJson('/api/client/deleteClient', ['client_id' => $client->client_id])
            ->assertUnauthorized();

        $this->assertDatabaseHas('clients', ['client_id' => 'CLIENTA']);
    }

    public function test_cannot_delete_someone_else(): void
    {
        $me = $this->makeClient('CLIENTA');
        $other = $this->makeClient('CLIENTB');

        $this->withToken(JWTAuth::fromUser($me))
            ->deleteJson('/api/client/deleteClient?client_id=' . $other->client_id)
            ->assertForbidden();

        $this->assertDatabaseHas('clients', ['client_id' => 'CLIENTB']);
    }

    public function test_deletes_personal_data_and_anonymises_completed_bookings(): void
    {
        Storage::fake('hostinger');

        $client = $this->makeClient('CLIENTA');
        $other = $this->makeClient('CLIENTB');

        $vehicle = Vehicle::create([
            'veh_id' => 'VEH1', 'client_id' => $client->client_id, 'make' => 'Toyota', 'model' => 'Corolla',
            'year' => 2021, 'vin' => 'VIN1', 'plate_number' => 'ABC123', 'mileage' => 1, 'color' => 'Black',
        ]);
        $vehiclePhoto = $this->file('vehicle/Img/VEH1/photo.jpg');
        VehicleFile::create(['veh_id' => 'VEH1', 'file_name' => 'Vehicle_img.jpg', 'file_size' => 1, 'file_type' => 'image/jpeg', 'file_url' => $vehiclePhoto]);

        $avatar = $this->file('account/Profiles/Img/avatar.jpg');
        ClientFile::create(['file_id' => 'CF1', 'client_id' => 'CLIENTA', 'file_name' => 'a.jpg', 'file_size' => 1, 'file_type' => 'image/jpeg', 'file_url' => $avatar]);

        $this->booking($client, 'BOOKOPEN', BookingState::PLACED, ['VEH1']);
        $ownership = $this->file('booking/Docs/BOOKOPEN/ownership.pdf');
        $openDoc = BookingFile::create(['book_id' => 'BOOKOPEN', 'client_id' => 'CLIENTA', 'file_name' => 'Proof_of_ownership.pdf', 'file_size' => 1, 'file_type' => 'application/pdf', 'file_url' => $ownership]);
        BookingFileState::create(['file_id' => (string) $openDoc->id, 'state' => 'PENDING']);

        $this->booking($client, 'BOOKDONE', BookingState::COMPLETED, ['VEH1']);
        $history = $this->file('booking/Docs/BOOKDONE/history.pdf');
        BookingFile::create(['book_id' => 'BOOKDONE', 'client_id' => 'CLIENTA', 'file_name' => 'Repair_history.pdf', 'file_size' => 1, 'file_type' => 'application/pdf', 'file_url' => $history]);
        $certificate = $this->file('booking/Certificates/BOOKDONE/cert.pdf');
        BookingFile::create(['book_id' => 'BOOKDONE', 'client_id' => 'CLIENTA', 'file_name' => 'Structural_Safety_Certificate.pdf', 'file_size' => 1, 'file_type' => 'application/pdf', 'file_url' => $certificate]);

        ClientSession::create(['id' => 'SESSION1', 'client_id' => 'CLIENTA', 'session_id' => 'S1', 'payload' => '{}', 'last_activity' => time(), 'fcm_token' => 'push']);
        ClientNotification::create(['notification_id' => 'N1', 'client_id' => 'CLIENTA', 'type' => 'booking', 'subject' => 'Hi']);
        Otp::create(['acc_id' => 'CLIENTA', 'otp_id' => 'O1', 'otp' => '1234', 'target' => 'login', 'is_used' => false, 'expires_at' => now()]);

        $this->booking($other, 'BOOKOTHER', BookingState::PLACED, []);

        $this->withToken(JWTAuth::fromUser($client))
            ->deleteJson('/api/client/deleteClient?client_id=CLIENTA')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('clients', ['client_id' => 'CLIENTA']);
        $this->assertDatabaseMissing('vehicles', ['veh_id' => $vehicle->veh_id]);
        $this->assertDatabaseMissing('vehicle_files', ['veh_id' => 'VEH1']);
        $this->assertDatabaseMissing('client_files', ['client_id' => 'CLIENTA']);
        $this->assertDatabaseMissing('client_sessions', ['client_id' => 'CLIENTA']);
        $this->assertDatabaseMissing('client_notifications', ['client_id' => 'CLIENTA']);
        $this->assertDatabaseMissing('otps', ['acc_id' => 'CLIENTA']);
        $this->assertDatabaseMissing('bookings', ['book_id' => 'BOOKOPEN']);
        $this->assertDatabaseMissing('booking_statuses', ['book_id' => 'BOOKOPEN']);
        $this->assertDatabaseMissing('booking_file_states', ['file_id' => (string) $openDoc->id]);
        $this->assertDatabaseMissing('booking_files', ['client_id' => 'CLIENTA']);

        $done = Booking::where('book_id', 'BOOKDONE')->firstOrFail();
        $this->assertSame('DELETED', $done->client_id);
        $this->assertNull($done->note);
        $this->assertEmpty($done->vehicle_ids);
        $this->assertDatabaseHas('booking_statuses', ['book_id' => 'BOOKDONE']);
        $this->assertSame(['booking/Certificates/BOOKDONE/cert.pdf'], BookingFile::where('book_id', 'BOOKDONE')->pluck('file_url')->all());

        foreach ([$vehiclePhoto, $avatar, $ownership, $history] as $path) {
            Storage::disk('hostinger')->assertMissing($path);
        }
        Storage::disk('hostinger')->assertExists($certificate);

        $this->assertDatabaseHas('clients', ['client_id' => 'CLIENTB']);
        $this->assertDatabaseHas('bookings', ['book_id' => 'BOOKOTHER', 'client_id' => 'CLIENTB']);
    }
}
