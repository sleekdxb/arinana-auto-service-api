<?php

namespace App\Helpers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServicesHelper
{
    public static function addServices(Request $request): JsonResponse
    {
        try {
            $service = Service::create([
                'ser_id' => 'SERVICE_' . strtoupper(Str::random(16)),
                'name' => $request->input('name'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Service added successfully.',
                'data' => $service,
            ], 201);

        } catch (\Throwable $e) {
            \Log::error('Add Service Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to add service.',
            ], 500);
        }
    }



    public static function updateServices(Request $request): JsonResponse
    {
        try {
            $service = Service::where('ser_id', $request->input('ser_id'))
                ->first();

            if (!$service) {
                return response()->json([
                    'success' => false,
                    'message' => 'Service not found.',
                ], 404);
            }

            $service->update([
                'name' => $request->input('name'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Service updated successfully.',
                'data' => $service->fresh(),
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Update Service Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update service.',
            ], 500);
        }
    }

    public static function deleteServices(Request $request): JsonResponse
    {
        try {
            $service = Service::where('ser_id', $request->input('ser_id'))
                ->first();

            if (!$service) {
                return response()->json([
                    'success' => false,
                    'message' => 'Service not found.',
                ], 404);
            }

            $service->delete();

            return response()->json([
                'success' => true,
                'message' => 'Service deleted successfully.',
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Delete Service Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete service.',
            ], 500);
        }
    }


    public static function getServices(Request $request): JsonResponse
    {
        try {
            $services = Service::all();

            return response()->json([
                'success' => true,
                'message' => 'Services retrieved successfully.',
                'data' => $services,
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Get Services Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve services.',
            ], 500);
        }
    }

}