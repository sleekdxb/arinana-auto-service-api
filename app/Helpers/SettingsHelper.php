<?php

namespace App\Helpers;

use App\Models\AppSetting;
use App\Models\Booking;
use App\Models\BookingState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsHelper
{
    public const AVAILABILITY = 'availability';
    public const DOCUMENT_REQUIREMENTS = 'document_requirements';
    public const STATUS_PIPELINES = 'status_pipelines';
    public const NOTIFICATIONS = 'notification_settings';

    public const EVENTS = [
        'booking_confirmed' => 'Booking Confirmed',
        'appointment_reminder' => 'Appointment Reminder',
        'vehicle_received' => 'Vehicle Received',
        'status_changes' => 'Status Changes',
        'document_rejected' => 'Document Rejected',
        'reupload_requested' => 'Re-upload Requested',
        'certificate_available' => 'Structural Safety Certificate Available',
        'service_completed' => 'Service Completed',
    ];

    public const CHANNELS = ['push', 'email', 'sms'];


    /* ------------------------------------------------------------------
     | Values used by the rest of the API
     * ------------------------------------------------------------------ */

    public static function availability(): array
    {
        return array_merge(self::defaultAvailability(), AppSetting::read(self::AVAILABILITY, []));
    }

    public static function documentRequirements(bool $enabledOnly = false): array
    {
        $requirements = array_map(
            fn(array $r) => $r + ['type' => 'document', 'count' => 1],
            AppSetting::read(self::DOCUMENT_REQUIREMENTS, self::defaultDocumentRequirements())
        );

        return $enabledOnly
            ? array_values(array_filter($requirements, fn($r) => $r['enabled'] ?? true))
            : $requirements;
    }

    /**
     * File names (without extension, lower case) a requirement's uploads use:
     * "proof_of_ownership" for one file, "vehicle_photos_1".."_4" for several.
     */
    public static function requirementFileKeys(array $requirement): array
    {
        $key = strtolower($requirement['key']);
        $count = max(1, (int) ($requirement['count'] ?? 1));

        return $count === 1 ? [$key] : array_map(fn(int $i) => $key . '_' . $i, range(1, $count));
    }

    public static function statusPipelines(): array
    {
        return AppSetting::read(self::STATUS_PIPELINES, self::defaultStatusPipelines());
    }

    /** How [state] reads for this booking, from its saved status pipeline. */
    public static function stepLabel(Booking $booking, string $state): string
    {
        $name = BookingState::normalizeName($state);
        $pipeline = self::statusPipelines()[$booking->isStructuralSafety() ? 'structural_safety' : 'service'] ?? [];

        foreach ($pipeline as $step) {
            if (BookingState::normalizeName($step['name'] ?? $step['label'] ?? '') === $name && !empty($step['label'])) {
                return $step['label'];
            }
        }

        return BookingState::LABELS[$name] ?? ucfirst(strtolower(str_replace('_', ' ', $name)));
    }

    public static function notificationSettings(): array
    {
        $saved = collect(AppSetting::read(self::NOTIFICATIONS, []))->keyBy('key');

        return collect(self::EVENTS)->map(fn($label, $key) => [
            'key' => $key,
            'label' => $label,
            'push' => (bool) ($saved[$key]['push'] ?? true),
            'email' => (bool) ($saved[$key]['email'] ?? true),
            'sms' => (bool) ($saved[$key]['sms'] ?? false),
        ])->values()->all();
    }

    /** Whether the business sends this event on this channel at all. */
    public static function allows(string $event, string $channel): bool
    {
        foreach (self::notificationSettings() as $setting) {
            if ($setting['key'] === $event) {
                return (bool) ($setting[$channel] ?? false);
            }
        }

        return $channel !== 'sms';
    }


    /* ------------------------------------------------------------------
     | Endpoints
     * ------------------------------------------------------------------ */

    public static function getAvailability(): JsonResponse
    {
        return self::ok('Availability retrieved successfully.', self::availability());
    }

    public static function updateAvailability(Request $request): JsonResponse
    {
        $current = self::availability();
        $data = array_intersect_key($request->all(), self::defaultAvailability());

        if (isset($data['operating_days'])) {
            $data['operating_days'] = array_values(array_unique(array_map('intval', $data['operating_days'])));
            sort($data['operating_days']);
        }

        $merged = array_merge($current, $data);

        if (strcmp($merged['opens_at'], $merged['closes_at']) >= 0) {
            return self::invalid('closes_at', 'Closing time must be after opening time.');
        }

        return self::ok(
            'Availability updated successfully.',
            AppSetting::write(self::AVAILABILITY, $merged, $request->team_id)
        );
    }

    public static function getDocumentRequirements(bool $enabledOnly = false): JsonResponse
    {
        return self::ok('Document requirements retrieved successfully.', [
            'requirements' => self::documentRequirements($enabledOnly),
        ]);
    }

    public static function updateDocumentRequirements(Request $request): JsonResponse
    {
        $existing = collect(self::documentRequirements())->keyBy('key');
        $keys = [];
        $requirements = [];

        foreach ($request->input('requirements', []) as $item) {
            $key = $item['key'] ?? null;

            if (!$key || !$existing->has($key)) {
                $key = self::requirementKey($item['name']);
            }

            if (in_array($key, $keys, true)) {
                return self::invalid('requirements', 'Each document requirement needs a different name.');
            }

            $keys[] = $key;
            $requirements[] = [
                'key' => $key,
                'name' => trim($item['name']),
                'description' => trim((string) ($item['description'] ?? '')),
                'required' => (bool) ($item['required'] ?? false),
                'enabled' => (bool) ($item['enabled'] ?? true),
                'type' => ($item['type'] ?? 'document') === 'image' ? 'image' : 'document',
                'count' => max(1, min(10, (int) ($item['count'] ?? 1))),
            ];
        }

        return self::ok('Document requirements updated successfully.', [
            'requirements' => AppSetting::write(self::DOCUMENT_REQUIREMENTS, $requirements, $request->team_id),
        ]);
    }

    public static function getStatusPipelines(): JsonResponse
    {
        return self::ok('Status pipelines retrieved successfully.', self::statusPipelines());
    }

    public static function updateStatusPipelines(Request $request): JsonResponse
    {
        $pipelines = self::statusPipelines();

        foreach (['service', 'structural_safety'] as $pipeline) {
            if (!$request->has($pipeline)) {
                continue;
            }

            $steps = [];

            foreach ($request->input($pipeline) as $step) {
                $name = BookingState::normalizeName($step['name'] ?? $step['label']);
                $steps[$name] = ['name' => $name, 'label' => trim($step['label'])];
            }

            $steps = array_values($steps);

            if (count($steps) < 2
                || $steps[0]['name'] !== BookingState::PLACED
                || end($steps)['name'] !== BookingState::COMPLETED) {
                return self::invalid($pipeline, 'A pipeline must start with Booked (PLACED) and end with Completed (COMPLETED).');
            }

            $pipelines[$pipeline] = $steps;
        }

        return self::ok(
            'Status pipelines updated successfully.',
            AppSetting::write(self::STATUS_PIPELINES, $pipelines, $request->team_id)
        );
    }

    public static function getNotificationSettings(): JsonResponse
    {
        return self::ok('Notification settings retrieved successfully.', [
            'events' => self::notificationSettings(),
        ]);
    }

    public static function updateNotificationSettings(Request $request): JsonResponse
    {
        $current = collect(self::notificationSettings())->keyBy('key');

        foreach ($request->input('events', []) as $event) {
            $key = $event['key'] ?? null;

            if (!$current->has($key)) {
                continue;
            }

            $row = $current[$key];

            foreach (self::CHANNELS as $channel) {
                if (array_key_exists($channel, $event)) {
                    $row[$channel] = (bool) $event[$channel];
                }
            }

            $current[$key] = $row;
        }

        $saved = $current->map(fn($row) => array_intersect_key($row, array_flip(['key', 'push', 'email', 'sms'])))
            ->values()
            ->all();

        AppSetting::write(self::NOTIFICATIONS, $saved, $request->team_id);

        return self::getNotificationSettings();
    }


    /* ------------------------------------------------------------------
     | Defaults
     * ------------------------------------------------------------------ */

    public static function defaultAvailability(): array
    {
        return [
            'operating_days' => config('booking.operating_days'),
            'opens_at' => config('booking.opens_at'),
            'closes_at' => config('booking.closes_at'),
            'slot_minutes' => config('booking.slot_minutes'),
            'slot_capacity' => config('booking.slot_capacity'),
            'lead_time_minutes' => 0,
            'change_cutoff_hours' => 0,
        ];
    }

    public static function defaultDocumentRequirements(): array
    {
        return [
            [
                'key' => 'Proof_of_ownership',
                'name' => 'Proof of Ownership',
                'description' => "The official title, registration card, or bill of sale verifying the vehicle's identity and legal owner.",
                'required' => true,
                'enabled' => true,
                'type' => 'document',
                'count' => 1,
            ],
            [
                'key' => 'Repair_history',
                'name' => 'Repair and Collision History',
                'description' => 'Insurance claim files, frame-straightening specs, or autobody shop receipts showing past structural work or accident damage.',
                'required' => false,
                'enabled' => true,
                'type' => 'document',
                'count' => 1,
            ],
        ];
    }

    public static function defaultStatusPipelines(): array
    {
        return [
            'service' => [
                ['name' => BookingState::PLACED, 'label' => 'Booked'],
                ['name' => BookingState::VEHICLE_RECEIVED, 'label' => 'Vehicle Received'],
                ['name' => BookingState::IN_PROGRESS, 'label' => 'Service in Progress'],
                ['name' => BookingState::READY_FOR_PICKUP, 'label' => 'Ready for Pickup'],
                ['name' => BookingState::COMPLETED, 'label' => 'Completed'],
            ],
            'structural_safety' => [
                ['name' => BookingState::PLACED, 'label' => 'Booked'],
                ['name' => 'DOCUMENTS_APPROVED', 'label' => 'Documents Approved'],
                ['name' => BookingState::VEHICLE_RECEIVED, 'label' => 'Vehicle Received'],
                ['name' => BookingState::IN_PROGRESS, 'label' => 'Inspection in Progress'],
                ['name' => 'CERTIFICATE_ISSUED', 'label' => 'Certificate Issued'],
                ['name' => BookingState::COMPLETED, 'label' => 'Completed'],
            ],
        ];
    }

    /** "Repair and Collision History" → "Repair_and_collision_history", the app's file-name style. */
    private static function requirementKey(string $name): string
    {
        return ucfirst(trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($name))), '_'));
    }

    private static function ok(string $message, array $data): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], 200);
    }

    private static function invalid(string $field, string $message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'errors' => [$field => [$message]]], 422);
    }
}
