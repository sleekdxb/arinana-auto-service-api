<?php

namespace App\Helpers;


use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Booking;

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





    public static function deleteVehicle($request)
    {
        try {
            DB::beginTransaction();

            $vehId = $request->veh_id;

            $vehicle = Vehicle::where('veh_id', $vehId)->first();

            if (!$vehicle) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle not found.',
                ], 404);
            }

            // Get all files linked to this vehicle
            $vehicleFiles = $vehicle->files;

            foreach ($vehicleFiles as $vehicleFile) {

                // Delete physical file
                if (!empty($vehicleFile->file_url)) {
                    self::deleteFileFromStorage($vehicleFile->file_url);
                }

                // Delete vehicle_files record
                $vehicleFile->delete();
            }

            // Delete vehicle record
            $vehicle->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vehicle and linked files deleted successfully.',
            ], 200);

        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete vehicle.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    private static function deleteFileFromStorage(string $fileUrl): void
    {
        if (str_contains($fileUrl, '/storage/')) {
            $filePath = substr($fileUrl, strpos($fileUrl, '/storage/') + 9);
        } else {
            $filePath = ltrim($fileUrl, '/');
        }
        // Delete from the public disk
        if (Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }
    }



}