<?php

namespace App\Helpers;


use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Client;
use App\Models\ClientStatus;


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


}