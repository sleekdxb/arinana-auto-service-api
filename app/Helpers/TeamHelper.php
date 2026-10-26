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

}