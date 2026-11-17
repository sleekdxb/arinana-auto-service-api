<?php

namespace App\Helpers;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Booking;
use App\Models\BookingState;
use App\Models\BookingFile;
use App\Models\Vehicle;
use App\Models\VehicleState;
use App\Models\Service;
use App\Models\BookingFileState;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;



class BookingHelper
{

    public static function addBooking(Request $request): JsonResponse
    {
        $slotError = self::slotError($request->date, $request->time);

        if ($slotError) {
            return $slotError;
        }

        DB::beginTransaction();

        try {
            do {
                $bookId = 'BOOK' . strtoupper(Str::random(10));
            } while (Booking::where('book_id', $bookId)->exists());

            do {
                $bookingRef = str_pad(
                    (string) random_int(0, 999999),
                    6,
                    '0',
                    STR_PAD_LEFT
                );
            } while (Booking::where('booking_ref', $bookingRef)->exists());

            do {
                $stateId = 'STATE' . strtoupper(Str::random(10));
            } while (BookingState::where('state_id', $stateId)->exists());

            $booking = Booking::create([
                'book_id' => $bookId,
                'booking_ref' => $bookingRef,
                'client_id' => $request->client_id,
                'vehicle_ids' => array_values($request->vehicle_ids),
                'service' => $request->service,
                'service_type' => $request->service_type,
                'date' => $request->date,
                'time' => self::normalizeTime($request->time),
                'note' => $request->note,
                'state_id' => $stateId,
            ]);



            BookingState::create([
                'book_id' => $booking->book_id,
                'team_id' => 'SYSTEM01',
                'state_id' => $stateId,
                'name' => BookingState::PLACED,
                'code' => 'PLACED01',
                'note' => 'Booking created',
            ]);

            DB::commit();

            NotificationHelper::bookingPlaced($booking);

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully.',
                'data' => $booking,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create booking.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public static function updateBooking(Request $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $booking = Booking::where('book_id', $request->book_id)->first();

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found.'
                ], 404);
            }

            $data = [];

            if ($request->filled('service')) {
                $data['service'] = $request->service;
            }

            if ($request->filled('service_type')) {
                $data['service_type'] = $request->service_type;
            }

            if ($request->filled('date')) {
                $data['date'] = $request->date;
            }

            if ($request->filled('time')) {
                $data['time'] = self::normalizeTime($request->time);
            }

            if ($request->has('note') && $request->note !== '') {
                $data['note'] = $request->note;
            }

            if ($request->has('vehicle_ids') && !empty($request->vehicle_ids)) {
                $data['vehicle_ids'] = array_values($request->vehicle_ids);
            }

            if (isset($data['date']) || isset($data['time'])) {
                $cutoffError = self::cutoffError($booking);

                if ($cutoffError) {
                    DB::rollBack();

                    return $cutoffError;
                }

                $slotError = self::slotError(
                    $data['date'] ?? $booking->date->toDateString(),
                    $data['time'] ?? substr((string) $booking->getRawOriginal('time'), 0, 5),
                    $booking->book_id
                );

                if ($slotError) {
                    DB::rollBack();

                    return $slotError;
                }
            }

            $booking->update($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking updated successfully.',
                'data' => $booking->fresh(),
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update booking.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public static function deleteBooking(Request $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $booking = Booking::where('book_id', $request->book_id)->first();

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found.'
                ], 404);
            }

            $cutoffError = self::cutoffError($booking);

            if ($cutoffError) {
                DB::rollBack();

                return $cutoffError;
            }

            // Get all files belonging to the booking
            $files = BookingFile::where('book_id', $booking->book_id)->get();

            foreach ($files as $file) {
                if (!empty($file->file_url)) {
                    $filePath = public_path($file->file_url);

                    if (File::exists($filePath)) {
                        File::delete($filePath);
                    }
                }
            }

            // Delete booking files from database
            BookingFile::where('book_id', $booking->book_id)->delete();



            // Delete booking
            $booking->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking deleted successfully.'
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete booking.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public static function getClientBookings(Request $request): JsonResponse
    {
        try {
            $bookings = Booking::where('client_id', $request->client_id)
                ->with([
                    'files.latestState',
                    'state',
                    'states',
                ])
                ->latest()
                ->get();

            $vehicles = Vehicle::with('files')
                ->whereIn('veh_id', $bookings->flatMap(fn($booking) => self::vehicleIdsOf($booking))->unique()->values())
                ->get()
                ->keyBy('veh_id');

            foreach ($bookings as $booking) {
                $booking->vehicles = collect(self::vehicleIdsOf($booking))
                    ->map(fn($vehicleId) => $vehicles->get($vehicleId))
                    ->filter()
                    ->values();
            }

            return response()->json([
                'success' => true,
                'data' => $bookings,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get client bookings.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public static function setBookingFileState(Request $request): JsonResponse
    {
        $result = DB::transaction(function () use ($request) {

            // Find the booking file
            $file = BookingFile::findOrFail($request->file_id);

            // Generate a unique state ID
            do {
                $stateId = 'STATE-' . strtoupper(Str::random(8));
            } while (BookingFileState::where('code', $stateId)->exists());

            // Create the file state
            $state = BookingFileState::create([
                'file_id' => $file->id,
                'team_id' => $request->team_id,
                'state' => BookingState::normalizeName($request->name),
                'code' => $stateId,
                'note' => $request->note,
                'create_at' => now(),
                'update_at' => now(),
            ]);

            return ['file' => $file, 'state' => $state];
        });

        $booking = Booking::where('book_id', $result['file']->book_id)->first();

        if ($booking) {
            NotificationHelper::bookingFileStateChanged(
                $booking,
                $result['file']->file_name,
                $request->name,
                $request->note
            );

            self::approveDocumentsIfComplete($booking);
        }

        return response()->json([
            'success' => true,
            'message' => 'Booking file state created successfully.',
            'data' => $result['state'],
        ], 201);
    }


    private static function approveDocumentsIfComplete(Booking $booking): void
    {
        if (!$booking->isStructuralSafety()) {
            return;
        }

        $current = BookingState::where('state_id', $booking->state_id)->value('name');
        if (BookingState::normalizeName($current ?? BookingState::PLACED) !== BookingState::PLACED) {
            return;
        }

        $latest = BookingFile::with('latestState')
            ->where('book_id', $booking->book_id)
            ->where('file_url', 'not like', 'booking/Certificates/%')
            ->orderBy('id')
            ->get()
            ->keyBy(fn (BookingFile $file) => strtolower(pathinfo((string) $file->file_name, PATHINFO_FILENAME)));

        if ($latest->isEmpty()) {
            return;
        }

        $missing = collect(SettingsHelper::documentRequirements(true))
            ->filter(fn (array $requirement) => $requirement['required'] ?? false)
            ->flatMap(fn (array $requirement) => SettingsHelper::requirementFileKeys($requirement))
            ->diff($latest->keys());

        $allApproved = $latest->every(
            fn (BookingFile $file) => BookingState::normalizeName($file->latestState?->state) === 'APPROVED'
        );

        if ($missing->isNotEmpty() || !$allApproved) {
            return;
        }

        $state = DB::transaction(function () use ($booking) {
            $stateId = 'STATE_' . strtoupper(Str::random(16));

            $state = BookingState::create([
                'book_id' => $booking->book_id,
                'team_id' => 'SYSTEM01',
                'state_id' => $stateId,
                'name' => BookingState::DOCUMENTS_APPROVED,
                'code' => 'DOCS_APPROVED',
                'note' => null,
            ]);
            $booking->update(['state_id' => $stateId]);

            foreach ($booking->vehicle()->get() as $vehicle) {
                $vehicleStateId = 'STATE_' . strtoupper(Str::random(16));
                VehicleState::create([
                    'veh_id' => $vehicle->veh_id,
                    'team_id' => 'SYSTEM01',
                    'state_id' => $vehicleStateId,
                    'name' => BookingState::DOCUMENTS_APPROVED,
                    'code' => 'DOCS_APPROVED',
                ]);
                $vehicle->update(['state_id' => $vehicleStateId]);
            }

            return $state;
        });

        NotificationHelper::bookingStateChanged($booking->fresh(), $state->name);
    }


    public static function getAvailableSlots(Request $request): JsonResponse
    {
        $timezone = config('booking.timezone');
        $from = $request->filled('from')
            ? Carbon::parse($request->from, $timezone)->startOfDay()
            : Carbon::now($timezone)->startOfDay();
        $days = min((int) $request->input('days', 7), config('booking.max_days'));
        $to = $from->copy()->addDays($days - 1);

        $taken = self::takenSlots($from->toDateString(), $to->toDateString(), $request->exclude_book_id);
        $settings = SettingsHelper::availability();
        $capacity = (int) $settings['slot_capacity'];
        $earliest = self::earliestBookable($settings);
        $result = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $date = $day->toDateString();
            $isOpen = in_array($day->dayOfWeekIso, $settings['operating_days'], true);
            $slots = [];

            if ($isOpen) {
                foreach (self::slotTimes($settings) as $time) {
                    $remaining = max(0, $capacity - ($taken[$date][$time] ?? 0));
                    $isPast = Carbon::parse($date . ' ' . $time, $timezone)->lt($earliest);

                    $slots[] = [
                        'time' => $time,
                        'available' => !$isPast && $remaining > 0,
                        'remaining' => $isPast ? 0 : $remaining,
                    ];
                }
            }

            $result[] = [
                'date' => $date,
                'day' => $day->format('D'),
                'is_open' => $isOpen,
                'slots' => $slots,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Available slots retrieved successfully.',
            'data' => [
                'timezone' => $timezone,
                'slot_minutes' => (int) $settings['slot_minutes'],
                'change_cutoff_hours' => (int) $settings['change_cutoff_hours'],
                'days' => $result,
            ],
        ], 200);
    }


    private static function slotError(?string $date, ?string $time, ?string $excludeBookId = null): ?JsonResponse
    {
        $timezone = config('booking.timezone');
        $settings = SettingsHelper::availability();
        $time = self::normalizeTime($time);
        $day = Carbon::parse($date, $timezone)->startOfDay();
        $at = Carbon::parse($day->toDateString() . ' ' . $time, $timezone);
        $message = null;

        if (!in_array($day->dayOfWeekIso, $settings['operating_days'], true)) {
            $message = 'We are closed on the selected day.';
        } elseif (!in_array($time, self::slotTimes($settings), true)) {
            $message = 'The selected time is outside working hours.';
        } elseif ($at->lte(Carbon::now($timezone))) {
            $message = 'The selected time has already passed.';
        } elseif ($at->lt(self::earliestBookable($settings))) {
            $message = 'This time is too soon to book. Please choose a later time.';
        } else {
            $taken = self::takenSlots($day->toDateString(), $day->toDateString(), $excludeBookId);

            if (($taken[$day->toDateString()][$time] ?? 0) >= (int) $settings['slot_capacity']) {
                $message = 'This time slot is fully booked. Please choose another time.';
            }
        }

        if (!$message) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => ['time' => [$message]],
        ], 422);
    }


    private static function earliestBookable(array $settings): Carbon
    {
        return Carbon::now(config('booking.timezone'))->addMinutes((int) $settings['lead_time_minutes']);
    }


    /**
     * Customers can't move or cancel a booking within the cutoff the team set.
     */
    private static function cutoffError(Booking $booking): ?JsonResponse
    {
        $hours = (int) SettingsHelper::availability()['change_cutoff_hours'];

        if ($hours <= 0) {
            return null;
        }

        $timezone = config('booking.timezone');
        $at = Carbon::parse(
            $booking->date->toDateString() . ' ' . substr((string) $booking->getRawOriginal('time'), 0, 5),
            $timezone
        );

        $now = Carbon::now($timezone);

        if ($at->lte($now) || $now->copy()->addHours($hours)->lt($at)) {
            return null;
        }

        $message = 'Bookings can only be changed or cancelled at least ' . $hours . ' hours before the appointment. Please contact us.';

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => ['date' => [$message]],
        ], 422);
    }


    private static function slotTimes(array $settings): array
    {
        $opens = Carbon::createFromFormat('H:i', $settings['opens_at']);
        $closes = Carbon::createFromFormat('H:i', $settings['closes_at']);
        $times = [];

        for ($slot = $opens->copy(); $slot->lt($closes); $slot->addMinutes((int) $settings['slot_minutes'])) {
            $times[] = $slot->format('H:i');
        }

        return $times;
    }


    private static function takenSlots(string $from, string $to, ?string $excludeBookId = null): array
    {
        $bookings = Booking::with('state')
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->when($excludeBookId, fn($query) => $query->where('book_id', '!=', $excludeBookId))
            ->get();

        $taken = [];

        foreach ($bookings as $booking) {
            if (in_array(strtoupper((string) $booking->state?->name), config('booking.inactive_states'), true)) {
                continue;
            }

            $date = Carbon::parse($booking->getRawOriginal('date'))->toDateString();
            $time = self::normalizeTime($booking->getRawOriginal('time'));

            $taken[$date][$time] = ($taken[$date][$time] ?? 0) + 1;
        }

        return $taken;
    }


    private static function normalizeTime(?string $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        try {
            return Carbon::parse($time)->format('H:i');
        } catch (\Throwable $e) {
            return $time;
        }
    }


    private static function vehicleIdsOf(Booking $booking): array
    {
        $vehicleIds = $booking->vehicle_ids;

        while (is_string($vehicleIds)) {
            $vehicleIds = json_decode($vehicleIds, true);
        }

        return array_values(array_filter((array) $vehicleIds));
    }


    public static function getServices(Request $request): JsonResponse
    {
        try {
            $services = Service::select('ser_id', 'name', 'type')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Services retrieved successfully.',
                'data' => $services,
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve services.',
            ], 500);
        }
    }
}