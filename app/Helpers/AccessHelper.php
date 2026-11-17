<?php

namespace App\Helpers;

use App\Models\Booking;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ownership checks for records addressed by their own id. Staff (a team
 * token, see verify.client:team) may act on any client's records.
 */
class AccessHelper
{
    public static function isStaff(Request $request): bool
    {
        return $request->attributes->get('team') !== null;
    }

    public static function clientId(Request $request): ?string
    {
        return $request->attributes->get('client')?->client_id;
    }

    public static function ownsVehicles(Request $request, array $vehicleIds): bool
    {
        if (self::isStaff($request)) {
            return true;
        }

        $ids = array_values(array_unique(array_map('strval', $vehicleIds)));

        return Vehicle::whereIn('veh_id', $ids)->where('client_id', self::clientId($request))->count() === count($ids);
    }

    public static function ownsBooking(Request $request, ?string $bookId): bool
    {
        return self::isStaff($request)
            || ($bookId && Booking::where('book_id', $bookId)->where('client_id', self::clientId($request))->exists());
    }

    /** Whether the caller may attach files to [ref_id] for this upload [target]. */
    public static function canUploadTo(Request $request, string $target, string $refId): bool
    {
        $team = $request->attributes->get('team');

        return match (str_replace('_update', '', $target)) {
            'account' => $team ? $refId === $team->team_id : $refId === self::clientId($request),
            'vehicle' => self::ownsVehicles($request, [$refId]),
            'booking' => self::ownsBooking($request, $refId),
            default => false,
        };
    }

    public static function notFound(string $what = 'Record'): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $what . ' not found.'], 404);
    }
}
