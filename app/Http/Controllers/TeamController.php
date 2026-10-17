<?php

namespace App\Http\Controllers;

use App\Helpers\VehicleHelper;
use App\Helpers\TeamHelper;
use App\Helpers\BookingHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class TeamController extends Controller
{
    public function setBookingFileState(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_id' => 'required|string|exists:booking_files,id',
            'team_id' => 'required|string|exists:teams,team_id',
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        return BookingHelper::setBookingFileState($request);
    }

    public function setVehicleFileState(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_id' => 'required|string|exists:booking_files,id',
            'team_id' => 'required|string|exists:teams,team_id',
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        return VehicleHelper::setVehicleFileState($request);
    }



    public function getUsers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'key_word' => 'nullable|string',
            'customer_type' => 'nullable|string',
            'status' => 'nullable|string',
            'team_id' => 'required|string|exists:teams,team_id',
            'per_page' => 'nullable|integer',
            'page' => 'nullable|integer',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        return TeamHelper::getUsers($request);
    }


    public function suspendAccount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|string|exists:clients,client_id',
            'team_id' => 'required|string|exists:teams,team_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        return TeamHelper::suspendAccount($request);
    }
}
