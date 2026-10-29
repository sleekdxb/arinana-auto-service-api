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
                $q->where('name', 'LIKE', "%{$keyword}%")
                    ->orWhere('email', 'LIKE', "%{$keyword}%")
                    ->orWhere('phone', 'LIKE', "%{$keyword}%");
            });
        }

        // Customer type filter
        if ($request->filled('customer_type')) {
            $query->where('customer_type', $request->customer_type);
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
        // Fetch all bookings. No team_id filtering.
        $bookings = Booking::query();

        $totalBookings = (clone $bookings)->count();

        $totalCustomers = Client::count();

        // Booking counts by vehicle state.
        // Change these state names to match your database.
        $activeBookings = 42;

        //(clone $bookings)
        // ->whereHas('vehicle.state', function ($query) {
        //     $query->where('name', 'Active');
        //  })
        //  ->count();

        $completedJobs = 20;

        //  (clone $bookings)
        //  ->whereHas('vehicle.state', function ($query) {
        //      $query->where('name', 'Completed');
        ///   })
        //  ->count();

        // Structural Safety bookings.
        $totalStructuralSafety = (clone $bookings)
            ->where('service', 'Structural Safety')
            ->count();

        // Customer percentages by type.
        $ownerCount = Client::where('account_type', 'Owner')->count();
        $dealerCount = Client::where('account_type', 'Dealer')->count();

        $clientPercentage = function (int $count) use ($totalCustomers) {
            return $totalCustomers > 0
                ? round(($count / $totalCustomers) * 100, 2)
                : 0;
        };

        // Dynamic booking counts and percentages by service.
        $serviceCounts = (clone $bookings)
            ->select('service', DB::raw('COUNT(*) as total_bookings'))
            ->whereNotNull('service')
            ->where('service', '!=', '')
            ->groupBy('service')
            ->orderBy('service')
            ->get();

        $bookingsByServiceType = $serviceCounts->map(function ($item) use ($totalBookings) {
            return [
                'service' => $item->service,
                'percentage' => $totalBookings > 0
                    ? round(($item->total_bookings / $totalBookings) * 100, 2)
                    : 0,
            ];
        })->values();

        // Booking volume grouped by month using created_at.
        $monthlyCounts = (clone $bookings)
            ->selectRaw('MONTH(created_at) as month_number, COUNT(*) as total_bookings')
            ->whereNotNull('created_at')
            ->groupByRaw('MONTH(created_at)')
            ->orderByRaw('MONTH(created_at)')
            ->get();

        $bookingsByMonth = $monthlyCounts->mapWithKeys(function ($item) {
            return [
                date('M', mktime(0, 0, 0, (int) $item->month_number, 1))
                => (int) $item->total_bookings,
            ];
        });

        // Turnaround distribution by dynamic service type.
        $turnaroundDistribution = $serviceCounts->map(function ($item) {
            return [
                'service_type' => $item->service,
                'total_bookings' => (int) $item->total_bookings,
                'completed_bookings' => 24, // Dummy value for now.
                'average_turnaround_time' => '4 days', // Dummy value.
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'figCard' => [
                    'total_bookings' => $totalBookings,
                    'active_bookings' => $activeBookings,
                    'completed_jobs' => $completedJobs,
                    'average_turnaround_time' => '4 days',
                    'total_structural_safety' => $totalStructuralSafety,
                    'total_users' => $totalCustomers,
                ],

                'bookings_by_client_type' => [
                    'car_owner' => $clientPercentage($ownerCount) . '%',
                    'dealer' => $clientPercentage($dealerCount) . '%',
                ],

                'bookings_by_service_type' => $bookingsByServiceType,

                'booking_volum_over_time' => $bookingsByMonth,

                'turnaround_time_distribution_by_service_type' =>
                    $turnaroundDistribution,
            ],
        ]);
    }


    public static function getBookings(Request $request): JsonResponse
    {
        try {
            $query = Booking::with([
                'files',
                'states',
                'state',
                'customer',
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
                                ->where('name', 'like', $search)
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
                    'name' => strtoupper($request->input('name')),
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
                    'name' => strtoupper($request->input('name')),
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
}