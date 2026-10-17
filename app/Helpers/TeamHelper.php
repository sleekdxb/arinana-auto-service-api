<?php

namespace App\Helpers;


use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Client;


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
}