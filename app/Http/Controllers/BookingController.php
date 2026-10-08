<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Helpers\BookingHelper;

class BookingController extends Controller
{
    public function addBooking(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|string|exists:clients,client_id',
            'vehicle_ids' => 'required|array',
            'vehicle_ids.*' => 'exists:vehicles,veh_id',
            'service' => 'required|string',
            'service_type' => 'required|string',
            'date' => 'required|date',
            'time' => 'required|string',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return BookingHelper::addBooking($request);
    }


    public function updateBooking(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'book_id' => 'required|exists:bookings,book_id',
            'vehicle_ids' => 'nullable|array',
            'vehicle_ids.*' => 'exists:vehicles,veh_id',
            'service' => 'nullable|string',
            'service_type' => 'nullable|string',
            'date' => 'nullable|date',
            'time' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return BookingHelper::updateBooking($request);
    }


    public function deleteBooking(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'book_id' => 'required|exists:bookings,book_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return BookingHelper::deleteBooking($request);
    }


    public function getClientBookings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|email|exists:clients,client_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return BookingHelper::getClientBookings($request);
    }
}
