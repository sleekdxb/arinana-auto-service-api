<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Booking;
use App\Models\BookingState;
use App\Mail\BookingUpdateMail;
use App\Models\Client;
use App\Models\ClientEmail;
use App\Models\ClientNotification;
use App\Models\ClientPreference;
use App\Models\ClientSession;
use App\Models\Service;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Mail;


class NotificationHelper
{

    /** Notifications already sent in this process, so a delivery never runs twice. */
    private static array $delivered = [];

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
        $notification = self::notify(
            $booking->client_id,
            'booking_placed',
            'Booking Confirmed!',
            trim(($booking->service_type ?: 'Your service') . ' booked for ' . self::scheduleLabel($booking)),
            $booking->book_id,
            ['booking_ref' => $booking->booking_ref]
        );

        self::deliver($booking, $notification, 'booking_updates', 'booking_confirmed', [
            'subject' => 'Booking confirmed – ARN-' . $booking->booking_ref,
            'heading' => 'Your booking is confirmed',
            'message' => 'Thanks for booking with us. Please bring your vehicle at the time below.',
            'status' => BookingState::LABELS[BookingState::PLACED],
        ]);
    }


    public static function bookingStateChanged(Booking $booking, string $state, ?string $note = null): void
    {
        $name = BookingState::normalizeName($state);
        $label = self::stateLabel($name);
        $message = self::statusMessage($booking, $name);

        $notification = self::notify(
            $booking->client_id,
            'booking_status',
            $label,
            $note ?: $message,
            $booking->book_id,
            ['booking_ref' => $booking->booking_ref, 'state' => $name]
        );

        $finished = in_array($name, [BookingState::READY_FOR_PICKUP, BookingState::COMPLETED, 'CERTIFICATE_ISSUED'], true);
        $event = match ($name) {
            BookingState::VEHICLE_RECEIVED => 'vehicle_received',
            BookingState::COMPLETED => 'service_completed',
            'CERTIFICATE_ISSUED' => 'certificate_available',
            default => 'status_changes',
        };

        self::deliver($booking, $notification, $finished ? 'service_completion' : 'service_status_updates', $event, [
            'subject' => $label . ' – ARN-' . $booking->booking_ref,
            'heading' => $label,
            'message' => $message,
            'note' => $note,
            'status' => $label,
        ]);
    }


    public static function bookingFileStateChanged(Booking $booking, string $fileName, string $state, ?string $note = null): void
    {
        $document = pathinfo($fileName, PATHINFO_FILENAME);
        $document = str_replace('_', ' ', $document);
        $label = ucfirst(strtolower($state));
        $message = $document . ' for booking ARN-' . $booking->booking_ref . ' was ' . strtolower($state) . '.';

        $notification = self::notify(
            $booking->client_id,
            'booking_document',
            'Document ' . $label,
            $note ?: $message,
            $booking->book_id,
            ['booking_ref' => $booking->booking_ref, 'state' => strtoupper($state), 'file_name' => $fileName]
        );

        $key = strtolower($state);
        $rejected = str_contains($key, 'reject');
        $event = match (true) {
            str_contains($key, 'upload') => 'reupload_requested',
            $rejected => 'document_rejected',
            default => 'status_changes',
        };

        self::deliver($booking, $notification, 'document_review', $event, [
            'subject' => 'Document ' . strtolower($label) . ' – ARN-' . $booking->booking_ref,
            'heading' => 'Document ' . strtolower($label),
            'message' => $message . ($rejected ? ' Please upload a corrected copy from the booking in the app.' : ''),
            'note' => $note,
            'status' => null,
        ]);
    }


    public static function etaUpdated(Booking $booking, ?string $note = null): void
    {
        $when = $booking->estimated_completion_at
            ?->timezone(config('booking.timezone'))
            ->format('D, M j, Y, g:i A');
        $message = $when
            ? 'Your vehicle is now expected to be ready by ' . $when . '.'
            : 'The estimated completion time for your booking has been removed.';

        $notification = self::notify(
            $booking->client_id,
            'booking_eta',
            'Estimated completion updated',
            $note ?: $message,
            $booking->book_id,
            ['booking_ref' => $booking->booking_ref, 'estimated_completion_at' => $booking->estimated_completion_at?->toIso8601String()]
        );

        self::deliver($booking, $notification, 'service_status_updates', 'status_changes', [
            'subject' => 'Estimated completion updated – ARN-' . $booking->booking_ref,
            'heading' => 'Estimated completion updated',
            'message' => $message,
            'note' => $note,
            'status' => null,
        ]);
    }


    public static function certificateIssued(Booking $booking): void
    {
        $message = 'Your structural safety certificate for booking ARN-' . $booking->booking_ref . ' is ready. You can view and download it from the booking in the app.';

        $notification = self::notify(
            $booking->client_id,
            'booking_certificate',
            'Certificate available',
            $message,
            $booking->book_id,
            ['booking_ref' => $booking->booking_ref]
        );

        self::deliver($booking, $notification, 'service_completion', 'certificate_available', [
            'subject' => 'Your certificate is ready – ARN-' . $booking->booking_ref,
            'heading' => 'Your certificate is ready',
            'message' => $message,
            'status' => null,
        ]);
    }


    public static function appointmentReminder(Booking $booking): void
    {
        $message = 'Reminder: your ' . ($booking->service_type ?: 'service') . ' is booked for ' . self::scheduleLabel($booking) . '.';

        $notification = self::notify(
            $booking->client_id,
            'booking_reminder',
            'Appointment reminder',
            $message,
            $booking->book_id,
            ['booking_ref' => $booking->booking_ref]
        );

        self::deliver($booking, $notification, 'booking_updates', 'appointment_reminder', [
            'subject' => 'Appointment reminder – ARN-' . $booking->booking_ref,
            'heading' => 'See you soon',
            'message' => $message,
            'status' => null,
        ]);
    }


    /**
     * Email and push go out after the response is sent. A channel is used only
     * if the business sends this event on it (admin Notification Settings) and
     * the client allows that channel and this kind of update.
     */
    private static function deliver(Booking $booking, ?ClientNotification $notification, string $category, string $event, array $email): void
    {
        if (!$notification) {
            return;
        }

        dispatch(function () use ($booking, $notification, $category, $event, $email) {
            if (isset(self::$delivered[$notification->notification_id])) {
                return;
            }

            self::$delivered[$notification->notification_id] = true;
            $preference = ClientPreference::firstWhere('client_id', $booking->client_id);
            $allows = fn(string $toggle) => (bool) ($preference?->{$toggle} ?? ClientPreference::DEFAULTS[$toggle]);

            if (!$allows($category)) {
                return;
            }

            if ($allows('email_enabled') && SettingsHelper::allows($event, 'email')) {
                self::sendEmail($booking, $email);
            }

            if ($allows('push_enabled') && SettingsHelper::allows($event, 'push')) {
                try {
                    PushHelper::sendToClient($booking->client_id, $notification->subject, $notification->data['body'] ?? null, [
                        'notif_id' => $notification->notification_id,
                        'type' => $notification->type,
                        'ref_id' => $notification->notifiable_id,
                    ]);
                } catch (\Throwable $e) {
                    \Log::error('Push Delivery Error: ' . $e->getMessage());
                }
            }
        })->afterResponse();
    }


    private static function sendEmail(Booking $booking, array $email): void
    {
        $client = Client::where('client_id', $booking->client_id)->first();

        if (!$client || !$client->email) {
            return;
        }

        $record = null;

        try {
            $record = ClientEmail::create([
                'receiver_id' => $client->client_id,
                'mail_id' => 'MAIL' . strtoupper(Str::random(12)),
                'subject' => $email['subject'],
                'message' => $email['message'] ?? '',
                'is_sent' => false,
            ]);

            Mail::to($client->email)->send(new BookingUpdateMail($email['subject'], [
                'name' => trim($client->first_name) ?: 'there',
                'heading' => $email['heading'],
                'message' => $email['message'] ?? null,
                'note' => $email['note'] ?? null,
                'status' => $email['status'] ?? null,
                'rows' => self::bookingRows($booking),
            ]));

            $record->update(['is_sent' => true]);
        } catch (\Throwable $e) {
            \Log::error('Booking Email Error: ' . $e->getMessage());
        }
    }


    private static function bookingRows(Booking $booking): array
    {
        $service = Service::where('ser_id', $booking->service)->value('name');
        $vehicle = Vehicle::whereIn('veh_id', (array) ($booking->vehicle_ids ?? []))->first();

        return array_values(array_filter([
            ['Booking reference', 'ARN-' . $booking->booking_ref],
            ['Service', trim(implode(' – ', array_unique(array_filter([$service, $booking->service_type]))))],
            $vehicle ? ['Vehicle', self::vehicleLabel($vehicle) . ($vehicle->plate_number ? ' (' . $vehicle->plate_number . ')' : '')] : null,
            ['Date & time', self::scheduleLabel($booking)],
        ], fn($row) => $row && $row[1] !== ''));
    }


    private static function scheduleLabel(Booking $booking): string
    {
        $date = $booking->date?->format('D, M j, Y');
        $time = substr((string) $booking->getRawOriginal('time'), 0, 5);

        if ($time) {
            try {
                $time = \Carbon\Carbon::createFromFormat('H:i', $time)->format('g:i A');
            } catch (\Throwable $e) {
            }
        }

        return trim($date . ($time ? ', ' . $time : ''));
    }


    private static function statusMessage(Booking $booking, string $name): string
    {
        $inspection = str_contains(strtolower((string) $booking->service_type), 'structural');

        return match ($name) {
            BookingState::PLACED => 'Your booking is confirmed.',
            BookingState::VEHICLE_RECEIVED => 'We have received your vehicle and will start work soon.',
            BookingState::IN_PROGRESS => $inspection ? 'Your vehicle is currently being inspected.' : 'Your vehicle is currently being serviced.',
            BookingState::READY_FOR_PICKUP => 'Your vehicle is ready for pickup.',
            BookingState::COMPLETED => 'Your booking is complete. Thank you for choosing Ariana Auto Service.',
            'DOCUMENTS_APPROVED' => 'Your documents have been approved.',
            'CERTIFICATE_ISSUED' => 'Your structural safety certificate has been issued.',
            default => 'Your booking ARN-' . $booking->booking_ref . ' is now ' . strtolower(self::stateLabel($name)) . '.',
        };
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


    /**
     * A token moves to the latest session that reports it, so a shared
     * device never gets another account's pushes.
     */
    public static function updatePushToken(Request $request): JsonResponse
    {
        $session = ClientSession::where('client_id', $request->client_id)
            ->where('session_id', $request->session_id)
            ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found.',
            ], 404);
        }

        $token = $request->filled('fcm_token') ? $request->fcm_token : null;

        if ($token) {
            ClientSession::where('fcm_token', $token)
                ->where('session_id', '!=', $session->session_id)
                ->update(['fcm_token' => null]);
        }

        ClientSession::where('session_id', $session->session_id)->update(['fcm_token' => $token]);

        return response()->json([
            'success' => true,
            'message' => 'Push token updated.',
        ], 200);
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
