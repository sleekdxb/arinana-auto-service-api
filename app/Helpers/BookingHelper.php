<?php

namespace App\Helpers;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Booking;
use App\Models\BookingState;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;



class BookingHelper
{

    public static function addBooking(Request $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            do {
                $bookId = 'BOOK' . strtoupper(Str::random(10));
            } while (Booking::where('book_id', $bookId)->exists());

            do {
                $bookingRef = str_pad(
                    (string) random_int(0, 999999),
                    6,
                    '0',
                    STR_PAD_LEFT
                );
            } while (Booking::where('booking_ref', $bookingRef)->exists());

            do {
                $stateId = 'STATE' . strtoupper(Str::random(10));
            } while (BookingState::where('state_id', $stateId)->exists());

            $booking = Booking::create([
                'book_id' => $bookId,
                'booking_ref' => $bookingRef,
                'client_id' => $request->client_id,
                'vehicle_ids' => json_encode($request->vehicle_ids),
                'service' => $request->service,
                'service_type' => $request->service_type,
                'date' => $request->date,
                'time' => $request->time,
                'note' => $request->note,
                'state_id' => $stateId,
            ]);



            BookingState::create([
                'book_id' => $booking->book_id,
                'team_id' => 'SYSTEM01',
                'state_id' => $stateId,
                'name' => 'PLACED',
                'code' => 'PLACED01',
                'note' => 'Booking created',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully.',
                'data' => $booking,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create booking.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public static function updateBooking(Request $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $booking = Booking::where('book_id', $request->book_id)->first();

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found.'
                ], 404);
            }

            $data = [];

            if ($request->filled('service')) {
                $data['service'] = $request->service;
            }

            if ($request->filled('service_type')) {
                $data['service_type'] = $request->service_type;
            }

            if ($request->filled('date')) {
                $data['date'] = $request->date;
            }

            if ($request->filled('time')) {
                $data['time'] = $request->time;
            }

            if ($request->has('note') && $request->note !== '') {
                $data['note'] = $request->note;
            }

            if ($request->has('vehicle_ids') && !empty($request->vehicle_ids)) {
                $data['vehicle_ids'] = json_encode($request->vehicle_ids);
            }

            $booking->update($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking updated successfully.',
                'data' => $booking->fresh(),
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update booking.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}