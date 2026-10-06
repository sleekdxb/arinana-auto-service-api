<?php

namespace App\Helpers;


use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use App\Models\Vehicle;



class VehicleHelper
{
    public static function getVehicle(Request $request): JsonResponse
    {
        $vehicles = Vehicle::with('files')->where('client_id', $request->client_id)
            ->orderBy('id', 'desc')
            ->get();

        if ($vehicles->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No vehicles found for this client.',
                'data' => [],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vehicles retrieved successfully.',
            'data' => $vehicles,
        ], 200);
    }


    public static function addVehicle(Request $request): JsonResponse
    {
        // Generate unique vehicle ID
        do {
            $vehId = 'VEHICLE-' . strtoupper(Str::random(8));
        } while (Vehicle::where('veh_id', $vehId)->exists());

        $vehicle = Vehicle::create([
            'veh_id' => $vehId,
            'client_id' => $request->client_id,
            'make' => $request->make,
            'model' => $request->model,
            'year' => $request->year,
            'vin' => strtoupper($request->vin),
            'plate_number' => strtoupper($request->plate_number),
            'mileage' => $request->mileage ?? 0,
            'color' => $request->color,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle added successfully.',
            'data' => $vehicle,
        ], 201);
    }

    public static function updateVehicle(Request $request): JsonResponse
    {
        $vehicle = Vehicle::where('veh_id', $request->veh_id)->first();

        if (!$vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle not found.',
            ], 404);
        }

        $allowedFields = [
            'make',
            'model',
            'year',
            'color',
            'plate_number',
            'vin',
            'mileage',
        ];

        // Get only allowed fields from request
        $data = $request->only($allowedFields);

        // Remove null, empty string and whitespace-only values
        $data = array_filter($data, function ($value) {
            return $value !== null && trim((string) $value) !== '';
        });

        // Update only fields that exist and are not empty
        if (!empty($data)) {
            $vehicle->update($data);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vehicle updated successfully.',
            'data' => $vehicle->fresh(),
        ], 200);
    }
}