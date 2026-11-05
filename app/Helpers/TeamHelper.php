<?php

namespace App\Helpers;


use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Client;
use App\Models\ClientStatus;
use App\Models\Booking;
use App\Models\Vehicle;
use App\Models\BookingState;
use App\Models\VehicleState;
use Carbon\Carbon;
use App\Models\BookingFileState;
use App\Models\BookingFile;
use App\Models\Team;



class TeamHelper
{
    public static function getUsers(Request $request): JsonResponse
    {
        $query = Client::with([
            'status',
            'vehicles.state',
            'vehicles.files',
            'bookings.state',
        ]);

        // Keyword filter
        if ($request->filled('key_word')) {
            $keyword = $request->key_word;

            $query->where(function ($q) use ($keyword) {
                $q->where('first_name', 'LIKE', "%{$keyword}%")
                    ->orWhere('last_name', 'LIKE', "%{$keyword}%")
                    ->orWhere(function ($name) use ($keyword) {
                        [$first, $last] = array_pad(preg_split('/\s+/', trim($keyword), 2), 2, '');
                        $name->where('first_name', 'LIKE', "%{$first}%")
                            ->where('last_name', 'LIKE', "%{$last}%");
                    })
                    ->orWhere('business_name', 'LIKE', "%{$keyword}%")
                    ->orWhere('email', 'LIKE', "%{$keyword}%")
                    ->orWhere('phone', 'LIKE', "%{$keyword}%");
            });
        }

        // Customer type filter
        if ($request->filled('customer_type')) {
            $query->where('account_type', $request->customer_type);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->whereHas('status', function ($q) use ($request) {
                $q->where('name', $request->status)
                    ->orWhere('code', $request->status);
            });
        }

        // Pagination: default 10 per page
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $customers = $query->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'message' => 'Customers retrieved successfully.',
            'data' => $customers,
        ], 200);
    }


    public static function suspendAccount(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {

            // Find client
            $client = Client::where('client_id', $request->client_id)->first();

            // Generate unique state ID
            do {
                $stateId = 'STATE-' . strtoupper(Str::random(8));
            } while (ClientStatus::where('state_id', $stateId)->exists());

            // Create suspended state
            $status = ClientStatus::create([
                'client_id' => $client->client_id,
                'team_id' => $request->team_id,
                'state_id' => $stateId,
                'name' => 'Suspended',
                'code' => 'SUSPENDED',
                'note' => 'Account suspended',
            ]);

            // Update client's current state
            $client->state_id = $stateId;
            $client->save();

            return response()->json([
                'success' => true,
                'message' => 'Client account suspended successfully.',
                'data' => [
                    'client' => $client->fresh(),
                    'status' => $status,
                ],
            ], 200);
        });
    }


    public static function getReportAnalytics(Request $request): JsonResponse
    {
        $bookings = Booking::with(['state', 'states', 'customer:id,client_id,account_type'])->get();
        $total = $bookings->count();
        $inactive = ['COMPLETED', 'CANCELLED', 'CANCELED', 'REJECTED'];
        $stateOf = fn($booking) => strtoupper((string) $booking->state?->name);
        $percent = fn(int $count) => ($total > 0 ? round($count / $total * 100, 2) : 0) . '%';

        $completed = $bookings->filter(fn($b) => $stateOf($b) === BookingState::COMPLETED);
        $turnaround = fn($group) => self::averageTurnaround($group);

        $certificates = BookingFile::where('file_name', 'like', 'Structural_Safety_Certificate%')
            ->distinct()
            ->pluck('book_id')
            ->merge($bookings->filter(fn($b) => $b->states->contains('name', 'CERTIFICATE_ISSUED'))->pluck('book_id'))
            ->unique()
            ->count();

        $byType = $bookings->groupBy(fn($b) => strtolower((string) $b->customer?->account_type));
        $byService = $bookings->filter(fn($b) => filled($b->service))->groupBy('service')->sortKeys();

        $months = [];
        foreach (range(11, 0) as $ago) {
            $months[now()->startOfMonth()->subMonths($ago)->format('Y-m')] = 0;
        }
        foreach ($bookings as $booking) {
            $key = $booking->created_at?->format('Y-m');
            if ($key && array_key_exists($key, $months)) {
                $months[$key]++;
            }
        }
        $months = collect($months);

        return response()->json([
            'success' => true,
            'data' => [
                'figCard' => [
                    'total_bookings' => $total,
                    'active_bookings' => $bookings->reject(fn($b) => in_array($stateOf($b), $inactive, true))->count(),
                    'completed_jobs' => $completed->count(),
                    'average_turnaround_time' => $turnaround($completed),
                    'total_structural_safety' => $certificates,
                    'total_users' => Client::count(),
                ],
                'bookings_by_client_type' => [
                    'car_owner' => $percent(($byType['individual'] ?? collect())->count() + ($byType['owner'] ?? collect())->count()),
                    'dealer' => $percent(($byType['dealer'] ?? collect())->count()),
                ],
                'bookings_by_service_type' => $byService->map(fn($group, $service) => [
                    'service' => $service,
                    'percentage' => $total > 0 ? round($group->count() / $total * 100, 2) : 0,
                ])->values(),
                'booking_volum_over_time' => $months->mapWithKeys(
                    fn($count, $key) => [\Carbon\Carbon::createFromFormat('Y-m', $key)->format('M') => $count]
                ),
                'turnaround_time_distribution_by_service_type' => $byService->map(function ($group, $service) use ($stateOf, $turnaround) {
                    $done = $group->filter(fn($b) => $stateOf($b) === BookingState::COMPLETED);

                    return [
                        'service_type' => $service,
                        'total_bookings' => $group->count(),
                        'completed_bookings' => $done->count(),
                        'average_turnaround_time' => $turnaround($done),
                    ];
                })->values(),
            ],
        ]);
    }


    /**
     * From when the vehicle was received (or booked, if never marked received)
     * to when the booking was completed.
     */
    private static function averageTurnaround($bookings): string
    {
        $hours = $bookings->map(function ($booking) {
            $done = $booking->states->where('name', BookingState::COMPLETED)->last()?->created_at;
            $start = $booking->states->firstWhere('name', BookingState::VEHICLE_RECEIVED)?->created_at ?? $booking->created_at;

            return $done && $start ? max(0, $start->diffInMinutes($done)) / 60 : null;
        })->filter(fn($value) => $value !== null);

        if ($hours->isEmpty()) {
            return 'N/A';
        }

        $average = $hours->avg();

        return $average < 24
            ? round($average, 1) . ' hours'
            : round($average / 24, 1) . ' days';
    }


    public static function getBookings(Request $request): JsonResponse
    {
        try {
            $query = Booking::with([
                'files.latestState',
                'states',
                'state',
                'customer',
                'technician:id,team_id,full_name,email,phone,account_type',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Filter by service
            |--------------------------------------------------------------------------
            */
            if ($request->filled('service')) {
                $query->where(
                    'service',
                    'like',
                    '%' . $request->input('service') . '%'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Filter by status (related state name)
            |--------------------------------------------------------------------------
            */
            if ($request->filled('status')) {
                $status = $request->input('status');

                $query->whereHas('state', function ($stateQuery) use ($status) {
                    $stateQuery->where(
                        'name',
                        'like',
                        '%' . $status . '%'
                    );
                });
            }

            /*
            |--------------------------------------------------------------------------
            | Search keyword
            |--------------------------------------------------------------------------
            */
            if ($request->filled('keyword')) {
                $keyword = $request->input('keyword');
                $search = '%' . $keyword . '%';

                $query->where(function ($bookingQuery) use ($search) {
                    // Search booking fields.
                    $bookingQuery
                        ->where('booking_ref', 'like', $search)
                        ->orWhere('book_id', 'like', $search)
                        ->orWhere('service', 'like', $search)
                        ->orWhere('service_type', 'like', $search);

                    // Search related state.
                    $bookingQuery->orWhereHas(
                        'state',
                        function ($stateQuery) use ($search) {
                            $stateQuery->where('name', 'like', $search);
                        }
                    );

                    // Search customer fields.
                    $bookingQuery->orWhereHas(
                        'customer',
                        function ($customerQuery) use ($search) {
                            $customerQuery
                                ->where('first_name', 'like', $search)
                                ->orWhere('last_name', 'like', $search)
                                ->orWhere('business_name', 'like', $search)
                                ->orWhere('email', 'like', $search)
                                ->orWhere('phone', 'like', $search);
                        }
                    );
                });
            }

            /*
            |--------------------------------------------------------------------------
            | Retrieve bookings
            |--------------------------------------------------------------------------
            */
            $bookings = $query->get();

            /*
            |--------------------------------------------------------------------------
            | Load vehicles and their files
            |--------------------------------------------------------------------------
            | vehicle_ids is stored as an array/JSON field, not a standard
            | Eloquent relationship.
            */
            $allVehicleIds = $bookings
                ->flatMap(function ($booking) {
                    return $booking->vehicle_ids ?? [];
                })
                ->filter()
                ->unique()
                ->values();

            $vehicles = collect();

            if ($allVehicleIds->isNotEmpty()) {
                $vehicles = Vehicle::with('files')
                    ->whereIn('veh_id', $allVehicleIds)
                    ->get()
                    ->keyBy('veh_id');
            }

            /*
            |--------------------------------------------------------------------------
            | Attach matching vehicles to each booking
            |--------------------------------------------------------------------------
            */
            $bookings->each(function ($booking) use ($vehicles) {
                $vehicleIds = $booking->vehicle_ids ?? [];

                $booking->setRelation(
                    'vehicle',
                    collect($vehicleIds)
                        ->map(fn($vehicleId) => $vehicles->get($vehicleId))
                        ->filter()
                        ->values()
                );
            });

            return response()->json([
                'success' => true,
                'message' => 'Bookings retrieved successfully.',
                'data' => $bookings,
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Get Bookings Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bookings.',
            ], 500);
        }
    }



    public static function setBookingState(Request $request): JsonResponse
    {
        try {
            $result = DB::transaction(function () use ($request) {

                // Find the booking.
                $booking = Booking::where(
                    'book_id',
                    $request->input('book_id')
                )->first();

                if (!$booking) {
                    return null;
                }

                // Generate a unique state ID.
                $stateId = 'STATE_' . strtoupper(Str::random(16));

                // Create the booking state record.
                $state = BookingState::create([
                    'book_id' => $booking->book_id,
                    'team_id' => $request->input('team_id'),
                    'state_id' => $stateId,
                    'name' => BookingState::normalizeName($request->input('name')),
                    'code' => 'TEAM_4512',
                    'note' => $request->input('note'),
                ]);

                // Update the booking with the new state ID.
                $booking->update([
                    'state_id' => $stateId,
                ]);

                return [
                    'booking' => $booking->fresh(),
                    'state' => $state,
                ];
            });

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found.',
                ], 404);
            }

            NotificationHelper::bookingStateChanged(
                $result['booking'],
                $result['state']->name,
                $request->input('note')
            );

            return response()->json([
                'success' => true,
                'message' => 'Booking state updated successfully.',
                'data' => [
                    'booking' => $result['booking'],
                    'state' => $result['state'],
                ],
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Set Booking State Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update booking state.',
            ], 500);
        }
    }


    public static function setVehicleState(Request $request): JsonResponse
    {
        try {
            $result = DB::transaction(function () use ($request) {

                // Find the vehicle.
                $vehicle = Vehicle::where(
                    'veh_id',
                    $request->input('veh_id')
                )->first();

                if (!$vehicle) {
                    return null;
                }

                // Generate a unique state ID.
                $stateId = 'STATE_' . strtoupper(Str::random(16));

                // Create the vehicle state record.
                $state = VehicleState::create([
                    'veh_id' => $vehicle->veh_id,
                    'team_id' => $request->input('team_id'),
                    'state_id' => $stateId,
                    'name' => BookingState::normalizeName($request->input('name')),
                    'code' => 'TEAM_4512',
                    'note' => $request->input('note'),
                ]);

                // Update the vehicle's state_id.
                $vehicle->update([
                    'state_id' => $stateId,
                ]);

                return [
                    'vehicle' => $vehicle->fresh(),
                    'state' => $state,
                ];
            });

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Vehicle state updated successfully.',
                'data' => [
                    'vehicle' => $result['vehicle'],
                    'state' => $result['state'],
                ],
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Set Vehicle State Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update vehicle state.',
            ], 500);
        }
    }


    public static function setBookingEta(Request $request): JsonResponse
    {
        $booking = Booking::where('book_id', $request->book_id)->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found.',
            ], 404);
        }

        $eta = $request->filled('estimated_completion_at')
            ? Carbon::parse($request->estimated_completion_at, config('booking.timezone'))->utc()
            : null;

        $booking->update(['estimated_completion_at' => $eta]);
        NotificationHelper::etaUpdated($booking->fresh(), $request->input('note'));

        return response()->json([
            'success' => true,
            'message' => 'Estimated completion updated successfully.',
            'data' => $booking->fresh(),
        ], 200);
    }


    public static function getTechnicians(Request $request): JsonResponse
    {
        $technicians = Team::select('team_id', 'full_name', 'email', 'phone', 'account_type')
            ->orderBy('full_name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Technicians retrieved successfully.',
            'data' => $technicians,
        ], 200);
    }


    public static function assignTechnician(Request $request): JsonResponse
    {
        $booking = Booking::where('book_id', $request->book_id)->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found.',
            ], 404);
        }

        $booking->update(['technician_id' => $request->input('technician_id') ?: null]);

        return response()->json([
            'success' => true,
            'message' => $booking->technician_id ? 'Technician assigned successfully.' : 'Technician removed.',
            'data' => $booking->fresh()->load('technician:id,team_id,full_name,email,phone,account_type'),
        ], 200);
    }


    /**
     * Stored as a booking file named Structural_Safety_Certificate.<ext>, so
     * the app lists it with the booking's documents.
     */
    public static function uploadBookingCertificate(Request $request): JsonResponse
    {
        $booking = Booking::where('book_id', $request->book_id)->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found.',
            ], 404);
        }

        $file = $request->file('certificate');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $path = 'booking/Certificates/' . $booking->book_id . '/' . Str::uuid() . '.' . $extension;

        Storage::disk('hostinger')->put($path, fopen($file->getRealPath(), 'r'));

        $record = DB::transaction(function () use ($booking, $file, $extension, $path, $request) {
            $record = BookingFile::create([
                'book_id' => $booking->book_id,
                'client_id' => $booking->client_id,
                'file_name' => 'Structural_Safety_Certificate.' . $extension,
                'file_size' => $file->getSize(),
                'file_type' => $file->getMimeType(),
                'file_url' => $path,
            ]);

            BookingFileState::create([
                'file_id' => $record->id,
                'team_id' => $request->team_id,
                'state' => 'APPROVED',
                'code' => 'CERTIFICATE',
                'note' => 'Certificate issued',
                'create_at' => now(),
                'update_at' => now(),
            ]);

            return $record;
        });

        NotificationHelper::certificateIssued($booking);

        return response()->json([
            'success' => true,
            'message' => 'Certificate uploaded successfully.',
            'data' => $record->load('latestState'),
        ], 201);
    }


    public static function updateClient(Request $request): JsonResponse
    {
        $client = Client::where('client_id', $request->client_id)->first();

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        $data = array_filter(
            $request->only(['first_name', 'last_name', 'phone', 'email', 'account_type', 'business_name', 'registration_number']),
            fn($value) => $value !== null && $value !== ''
        );

        if (isset($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
            $data['hashed_email'] = hash('sha256', $data['email']);

            if (Client::where('hashed_email', $data['hashed_email'])->where('client_id', '!=', $client->client_id)->exists()) {
                return self::fieldError('email', 'This email is already in use.');
            }
        }

        if (isset($data['phone']) && Client::where('phone', $data['phone'])->where('client_id', '!=', $client->client_id)->exists()) {
            return self::fieldError('phone', 'This phone number is already in use.');
        }

        if (isset($data['account_type'])) {
            $data['account_type'] = strtolower($data['account_type']);
        }

        $client->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Client updated successfully.',
            'data' => $client->fresh(),
        ], 200);
    }


    public static function reactivateAccount(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $client = Client::where('client_id', $request->client_id)->first();

            do {
                $stateId = 'STATE-' . strtoupper(Str::random(8));
            } while (ClientStatus::where('state_id', $stateId)->exists());

            $status = ClientStatus::create([
                'client_id' => $client->client_id,
                'team_id' => $request->team_id,
                'state_id' => $stateId,
                'name' => 'Active',
                'code' => 'ACTIVE',
                'note' => $request->input('note') ?: 'Account reactivated',
            ]);

            $client->state_id = $stateId;
            $client->save();

            return response()->json([
                'success' => true,
                'message' => 'Client account reactivated successfully.',
                'data' => [
                    'client' => $client->fresh(),
                    'status' => $status,
                ],
            ], 200);
        });
    }


    private static function fieldError(string $field, string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => [$field => [$message]],
        ], 422);
    }
}
