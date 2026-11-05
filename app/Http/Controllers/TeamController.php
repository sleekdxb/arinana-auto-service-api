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
            'note' => 'nullable|string',
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
            'file_id' => 'required|string|exists:vehicle_files,id',
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



    public function getReportAnalytics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team_id' => 'required|string|exists:teams,team_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }
        return TeamHelper::getReportAnalytics($request);
    }


    public function getBookings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team_id' => 'required|string|exists:teams,team_id',
            'service' => 'nullable|string',
            'status' => 'nullable|string',
            'keyword' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return TeamHelper::getBookings($request);
    }





    public function setBookingState(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team_id' => 'required|string|exists:teams,team_id',
            'name' => 'required|string',
            'book_id' => 'required|string',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return TeamHelper::setBookingState($request);
    }

    public function setVehicleState(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team_id' => 'required|string|exists:teams,team_id',
            'veh_id' => 'required|string',
            'name' => 'required|string',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return TeamHelper::setVehicleState($request);
    }


    public function setBookingEta(Request $request): JsonResponse
    {
        return $this->validated($request, [
            'book_id' => 'required|string',
            'estimated_completion_at' => 'nullable|date',
            'note' => 'nullable|string|max:1000',
        ], fn() => TeamHelper::setBookingEta($request));
    }

    public function getTechnicians(Request $request): JsonResponse
    {
        return $this->validated($request, [], fn() => TeamHelper::getTechnicians($request));
    }

    public function assignTechnician(Request $request): JsonResponse
    {
        return $this->validated($request, [
            'book_id' => 'required|string',
            'technician_id' => 'nullable|string|exists:teams,team_id',
        ], fn() => TeamHelper::assignTechnician($request));
    }

    public function uploadBookingCertificate(Request $request): JsonResponse
    {
        return $this->validated($request, [
            'book_id' => 'required|string',
            'certificate' => 'required|file|mimes:pdf,jpg,jpeg,png|max:20480',
        ], fn() => TeamHelper::uploadBookingCertificate($request));
    }

    public function updateClient(Request $request): JsonResponse
    {
        return $this->validated($request, [
            'client_id' => 'required|string|exists:clients,client_id',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email',
            'account_type' => 'nullable|string|in:individual,dealer,Individual,Dealer',
            'business_name' => 'nullable|string|max:150',
            'registration_number' => 'nullable|string|max:100',
        ], fn() => TeamHelper::updateClient($request));
    }

    public function reactivateAccount(Request $request): JsonResponse
    {
        return $this->validated($request, [
            'client_id' => 'required|string|exists:clients,client_id',
            'note' => 'nullable|string|max:500',
        ], fn() => TeamHelper::reactivateAccount($request));
    }

    private function validated(Request $request, array $rules, callable $next): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team_id' => 'required|string|exists:teams,team_id',
        ] + $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return $next();
    }
}
