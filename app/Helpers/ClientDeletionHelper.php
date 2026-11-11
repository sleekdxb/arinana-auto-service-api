<?php

namespace App\Helpers;

use App\Models\Booking;
use App\Models\BookingFile;
use App\Models\BookingFileState;
use App\Models\BookingState;
use App\Models\Client;
use App\Models\ClientEmail;
use App\Models\ClientFile;
use App\Models\ClientNotification;
use App\Models\ClientPreference;
use App\Models\ClientSession;
use App\Models\ClientStatus;
use App\Models\Otp;
use App\Models\Vehicle;
use App\Models\VehicleFile;
use App\Models\VehicleFileState;
use App\Models\VehicleState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ClientDeletionHelper
{
    public const ANONYMOUS_CLIENT_ID = 'DELETED';

    public static function delete(Client $client): void
    {
        $paths = DB::transaction(function () use ($client) {
            $clientId = $client->client_id;
            $paths = [];

            $vehicleIds = Vehicle::where('client_id', $clientId)->pluck('veh_id');
            $vehicleFiles = VehicleFile::whereIn('veh_id', $vehicleIds)->get();
            $paths = array_merge($paths, $vehicleFiles->pluck('file_url')->all());
            VehicleFileState::whereIn('file_id', $vehicleFiles->pluck('id')->map(fn ($id) => (string) $id))->delete();
            VehicleFile::whereIn('veh_id', $vehicleIds)->delete();
            VehicleState::whereIn('veh_id', $vehicleIds)->delete();
            Vehicle::whereIn('veh_id', $vehicleIds)->delete();

            $bookings = Booking::with('state')->where('client_id', $clientId)->get();
            [$kept, $removed] = $bookings->partition(
                fn (Booking $booking) => BookingState::normalizeName($booking->state?->name) === BookingState::COMPLETED
            );

            $bookingFiles = BookingFile::where('client_id', $clientId)
                ->orWhereIn('book_id', $bookings->pluck('book_id'))
                ->get();
            [$certificates, $documents] = $bookingFiles->partition(
                fn (BookingFile $file) => $kept->contains('book_id', $file->book_id) && self::isCertificate($file)
            );

            $paths = array_merge($paths, $documents->pluck('file_url')->all());
            BookingFileState::whereIn('file_id', $documents->pluck('id')->map(fn ($id) => (string) $id))->delete();
            BookingFile::whereIn('id', $documents->pluck('id'))->delete();
            BookingFile::whereIn('id', $certificates->pluck('id'))->update(['client_id' => self::ANONYMOUS_CLIENT_ID]);

            BookingState::whereIn('book_id', $removed->pluck('book_id'))->delete();
            Booking::whereIn('book_id', $removed->pluck('book_id'))->delete();
            Booking::whereIn('book_id', $kept->pluck('book_id'))->update([
                'client_id' => self::ANONYMOUS_CLIENT_ID,
                'vehicle_ids' => null,
                'note' => null,
            ]);

            $clientFiles = ClientFile::where('client_id', $clientId)->get();
            $paths = array_merge($paths, $clientFiles->pluck('file_url')->all());
            ClientFile::where('client_id', $clientId)->delete();

            ClientSession::where('client_id', $clientId)->delete();
            ClientNotification::where('client_id', $clientId)->delete();
            ClientPreference::where('client_id', $clientId)->delete();
            ClientStatus::where('client_id', $clientId)->delete();
            Otp::where('acc_id', $clientId)->delete();
            ClientEmail::where('receiver_id', $clientId)->orWhere('sender_id', $clientId)->delete();

            $client->delete();

            return array_values(array_unique(array_filter($paths)));
        });

        foreach ($paths as $path) {
            try {
                Storage::disk('hostinger')->delete($path);
            } catch (\Throwable $e) {
                Log::warning('Delete Client file cleanup: ' . $e->getMessage(), ['path' => $path]);
            }
        }
    }

    private static function isCertificate(BookingFile $file): bool
    {
        return str_starts_with((string) $file->file_url, 'booking/Certificates/');
    }
}
