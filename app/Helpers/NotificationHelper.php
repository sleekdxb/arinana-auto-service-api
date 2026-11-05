<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Booking;
use App\Models\BookingState;
use App\Models\ClientNotification;
use App\Models\ClientPreference;
use App\Models\Vehicle;


class NotificationHelper
{

    public static function notify(
        string $clientId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $refId = null,
        array $data = []
    ): ?ClientNotification {
        try {
            do {
                $notifId = 'NOTIF' . strtoupper(Str::random(12));
            } while (ClientNotification::where('notification_id', $notifId)->exists());

            return ClientNotification::create([
                'notification_id' => $notifId,
                'client_id' => $clientId,
                'notifiable_id' => $refId,
                'type' => $type,
                'subject' => $title,
                'data' => ['body' => $body] + $data,
                'read' => false,
            ]);

        } catch (\Throwable $e) {
            \Log::error('Notify Error: ' . $e->getMessage());

            return null;
        }
    }


    public static function vehicleAdded(Vehicle $vehicle): void
    {
        self::notify(
            $vehicle->client_id,
            'vehicle_added',
            'New Vehicle Added!',
            self::vehicleLabel($vehicle) . ' has been added to your vehicles.',
            $vehicle->veh_id
        );
    }


    public static function bookingPlaced(Booking $booking): void
    {
        $when = $booking->date?->format('M j, Y');
        $time = substr((string) $booking->getRawOriginal('time'), 0, 5);

        self::notify(
            $booking->client_id,
            'booking_placed',
            'Booking Confirmed!',
            trim(($booking->service_type ?: 'Your service') . ' booked for ' . $when . ($time ? ', ' . $time : '')),
            $booking->book_id,
            ['booking_ref' => $booking->booking_ref]
        );
    }


    public static function bookingStateChanged(Booking $booking, string $state, ?string $note = null): void
    {
        $label = self::stateLabel($state);

        self::notify(
            $booking->client_id,
            'booking_status',
            $label,
            $note ?: 'Your booking ARN-' . $booking->booking_ref . ' is now ' . strtolower($label) . '.',
            $booking->book_id,
            ['booking_ref' => $booking->booking_ref, 'state' => strtoupper($state)]
        );
    }


    public static function bookingFileStateChanged(Booking $booking, string $fileName, string $state, ?string $note = null): void
    {
        $document = pathinfo($fileName, PATHINFO_FILENAME);
        $document = str_replace('_', ' ', $document);
        $label = ucfirst(strtolower($state));

        self::notify(
            $booking->client_id,
            'booking_document',
            'Document ' . $label,
            $note ?: $document . ' for booking ARN-' . $booking->booking_ref . ' was ' . strtolower($state) . '.',
            $booking->book_id,
            ['booking_ref' => $booking->booking_ref, 'state' => strtoupper($state), 'file_name' => $fileName]
        );
    }


    public static function getNotifications(Request $request): JsonResponse
    {
        try {
            $query = ClientNotification::where('client_id', $request->client_id);

            $unreadCount = (clone $query)->where('read', false)->count();

            $notifications = $query
                ->orderByDesc('id')
                ->paginate(
                    $request->input('per_page', 20),
                    ['*'],
                    'page',
                    $request->input('page', 1)
                )
                ->through(fn(ClientNotification $notification) => $notification->toApi());

            return response()->json([
                'success' => true,
                'message' => 'Notifications retrieved successfully.',
                'unread_count' => $unreadCount,
                'data' => $notifications,
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Get Notifications Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve notifications.',
            ], 500);
        }
    }


    public static function markRead(Request $request): JsonResponse
    {
        try {
            $query = ClientNotification::where('client_id', $request->client_id)
                ->where('read', false);

            if ($request->filled('notif_ids')) {
                $query->whereIn('notification_id', $request->notif_ids);
            }

            $updated = $query->update(['read' => true, 'read_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Notifications marked as read.',
                'data' => [
                    'updated' => $updated,
                    'unread_count' => ClientNotification::where('client_id', $request->client_id)
                        ->where('read', false)
                        ->count(),
                ],
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Mark Notifications Read Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update notifications.',
            ], 500);
        }
    }


    public static function getPreferences(Request $request): JsonResponse
    {
        $preference = ClientPreference::firstOrCreate(
            ['client_id' => $request->client_id],
            ClientPreference::DEFAULTS
        );

        return response()->json([
            'success' => true,
            'message' => 'Preferences retrieved successfully.',
            'data' => $preference->fresh(),
        ], 200);
    }


    public static function updatePreferences(Request $request): JsonResponse
    {
        $preference = ClientPreference::firstOrCreate(
            ['client_id' => $request->client_id],
            ClientPreference::DEFAULTS
        );

        $data = [];

        foreach (ClientPreference::TOGGLES as $toggle) {
            if ($request->has($toggle)) {
                $data[$toggle] = $request->boolean($toggle);
            }
        }

        if (!empty($data)) {
            $preference->update($data);
        }

        return response()->json([
            'success' => true,
            'message' => 'Preferences updated successfully.',
            'data' => $preference->fresh(),
        ], 200);
    }


    public static function stateLabel(string $state): string
    {
        $key = BookingState::normalizeName($state);

        return BookingState::LABELS[$key] ?? ucfirst(strtolower(str_replace('_', ' ', $key)));
    }


    private static function vehicleLabel(Vehicle $vehicle): string
    {
        return trim($vehicle->year . ' ' . $vehicle->make . ' ' . $vehicle->model)
            . ($vehicle->color ? ' - ' . $vehicle->color : '');
    }
}
