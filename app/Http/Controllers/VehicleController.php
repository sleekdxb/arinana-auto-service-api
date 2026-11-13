<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use App\Helpers\VehicleHelper;

class VehicleController extends Controller
{

    public function getVehicle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return VehicleHelper::getVehicle($request);
    }



    public function addVehicle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|string',
            'make' => 'required|string',
            'model' => 'required|string',
            'year' => 'required|integer|min:1900|max:' . date('Y'),
            'color' => 'required|string',
            'plate_number' => 'required|string|unique:vehicles,plate_number',
            'vin' => 'nullable|string|max:64|unique:vehicles,vin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return VehicleHelper::addVehicle($request);
    }


    public function updateVehicle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'veh_id' => 'required|string',
            'make' => 'required|string',
            'model' => 'required|string',
            'year' => 'required|integer|min:1900|max:' . date('Y'),
            'color' => 'required|string',
            'plate_number' => 'required|string',
            'vin' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('vehicles', 'vin')->ignore($request->input('veh_id'), 'veh_id'),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return VehicleHelper::updateVehicle($request);
    }



    public function deleteVehicle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'veh_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return VehicleHelper::deleteVehicle($request);
    }


    public function getClientBookings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return VehicleHelper::getClientBookings($request);
    }
}



