<?php

namespace App\Helpers;


use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Carbon\Carbon;
class TeamStatisticHelper
{
    public static function getDashboard(Request $request): JsonResponse
    {
        /*
         * Get all bookings without filtering by team_id.
         * vehicle_ids is expected to contain a JSON array of veh_id values.
         */
        $bookings = Booking::with('customer')
            ->latest()
            ->get();

        /*
         * Decode vehicle_ids if it has not already been cast to an array.
         * Remove duplicate IDs within each booking.
         */
        foreach ($bookings as $booking) {
            $vehicleIds = $booking->vehicle_ids;

            if (is_string($vehicleIds)) {
                $vehicleIds = json_decode($vehicleIds, true);
            }

            if (!is_array($vehicleIds)) {
                $vehicleIds = [];
            }

            $booking->dashboard_vehicle_ids = array_values(
                array_unique(array_filter(
                    $vehicleIds,
                    fn($id) => is_string($id) || is_numeric($id)
                ))
            );
        }

        /*
         * Gather all unique vehicle IDs from every booking.
         */
        $allVehicleIds = $bookings
            ->flatMap(fn($booking) => $booking->dashboard_vehicle_ids)
            ->unique()
            ->values();

        /*
         * Fetch vehicles and their relationships directly.
         * veh_id is the vehicle identifier stored in vehicle_ids.
         */
        $vehiclesById = Vehicle::with([
            'files',
            'state',
        ])
            ->whereIn('veh_id', $allVehicleIds)
            ->get()
            ->keyBy('veh_id');

        /*
         * Associate the correct vehicle models with each booking.
         * This is a collection, not an Eloquent relationship.
         */
        $vehiclesByBooking = [];

        foreach ($bookings as $booking) {
            $vehiclesByBooking[$booking->getKey()] = collect(
                $booking->dashboard_vehicle_ids
            )
                ->map(fn($id) => $vehiclesById->get($id))
                ->filter(fn($vehicle) => $vehicle instanceof Vehicle)
                ->unique(fn($vehicle) => $vehicle->veh_id)
                ->values();
        }

        /*
         * All unique vehicles associated with bookings.
         */
        $vehicles = collect($vehiclesByBooking)
            ->flatten(1)
            ->unique(fn($vehicle) => $vehicle->veh_id)
            ->values();

        /*
         * Collect all files safely.
         */
        $files = $vehicles->flatMap(function ($vehicle) {
            $relatedFiles = $vehicle->files;

            if ($relatedFiles instanceof Collection) {
                return $relatedFiles;
            }

            if ($relatedFiles instanceof Model) {
                return collect([$relatedFiles]);
            }

            return collect();
        });

        /*
         * Dashboard figures.
         *
         * Note: today_bookings currently counts all bookings,
         * matching your original implementation.
         */
        $figCards = [
            'today_bookings' => $bookings->count(),

            'total_vehicles_in_service' => $vehicles->count(),

            'total_documents_to_review' => $files
                ->filter(
                    fn($file) =>
                        strtoupper((string) ($file->file_type ?? '')) === 'PENDING'
                )
                ->count(),

            'awaiting_pickup' => $vehicles
                ->filter(
                    fn($vehicle) =>
                        strtoupper((string) data_get(
                            $vehicle->state,
                            'name',
                            ''
                        )) === 'COMPLETED'
                )
                ->count(),
        ];

        /*
         * Documents awaiting approval.
         */
        $documentAwaitingApproval = $vehicles
            ->map(function ($vehicle) {
                $vehicleFiles = $vehicle->files;

                if ($vehicleFiles instanceof Collection) {
                    $vehicleFiles = $vehicleFiles;
                } elseif ($vehicleFiles instanceof Model) {
                    $vehicleFiles = collect([$vehicleFiles]);
                } else {
                    $vehicleFiles = collect();
                }

                $docs = $vehicleFiles
                    ->filter(function ($file) {
                        // Exclude files whose state is Approved.
                        $stateName = strtoupper(trim(
                            (string) data_get($file->state, 'name', '')
                        ));

                        if ($stateName === 'APPROVED') {
                            return false;
                        }

                        // Include documents only, excluding images and other files.
                        $fileType = strtolower(trim(
                            (string) ($file->file_type ?? '')
                        ));

                        return in_array($fileType, [
                            'application/pdf',
                            'application/doc',
                            'application/docx',
                            'application/xls',
                            'application/xlsx',
                            'application/ppt',
                            'pptx',
                            'application/txt',
                            'application/csv',
                        ], true);
                    })
                    ->pluck('file_name')
                    ->filter()
                    ->unique()
                    ->values();

                if ($docs->isEmpty()) {
                    return null;
                }

                return [
                    'vehicle' => self::vehicleName($vehicle),
                    'docs' => $docs->implode(', '),
                ];
            })
            ->filter()
            ->values();

        /*
         * Active service jobs.
         */
        $activeServiceJobs = $bookings
            ->flatMap(function ($booking) use ($vehiclesByBooking) {
                $customer = $booking->customer;

                return ($vehiclesByBooking[$booking->getKey()] ?? collect())
                    ->filter(
                        fn($vehicle) =>
                            strtoupper((string) data_get(
                                $vehicle->state,
                                'name',
                                ''
                            )) !== 'COMPLETED'
                    )
                    ->map(fn($vehicle) => [
                        'customer_name' => trim(
                            ($customer->first_name ?? '') . ' ' .
                            ($customer->last_name ?? '')
                        ),
                        'vehicle' => self::vehicleName($vehicle),
                        'service' => $booking->service ?? null,
                        'status' => data_get($vehicle->state, 'name'),
                    ]);
            })
            ->values();

        /*
         * Recent bookings.
         */

        $recentBookings = $bookings
            ->take(10)
            ->map(function ($booking) use ($vehiclesByBooking) {
                $customer = $booking->customer;

                $vehicle = ($vehiclesByBooking[$booking->getKey()] ?? collect())
                    ->first();

                /*
                 * Resolve appointment date from available booking fields.
                 * Replace these candidate fields with your actual column names
                 * once confirmed.
                 */
                $appointmentDate = null;

                $dateValue = $booking->date
                    ?? $booking->appointment_date
                    ?? $booking->booking_date
                    ?? null;

                $timeValue = $booking->time
                    ?? $booking->appointment_time
                    ?? $booking->booking_time
                    ?? null;

                try {
                    if ($dateValue instanceof \Carbon\CarbonInterface) {
                        $dateTime = $dateValue->copy();

                        if (!empty($timeValue)) {
                            $dateTime->setTimeFromTimeString((string) $timeValue);
                        }

                        $appointmentDate = $dateTime->format('D, M j; g:i a');
                    } elseif (!empty($dateValue)) {
                        $dateTimeString = trim(
                            (string) $dateValue . ' ' .
                            (string) ($timeValue ?? '')
                        );

                        $appointmentDate = Carbon::parse($dateTimeString)
                            ->format('D, M j; g:i a');
                    }
                } catch (\Throwable $e) {
                    $appointmentDate = null;
                }

                return [
                    'booking_id' => $booking->booking_ref ?? null,

                    'customer' => trim(
                        ($customer->first_name ?? '') . ' ' .
                        ($customer->last_name ?? '')
                    ),

                    'customer_type' => $customer->account_type ?? null,

                    'vehicle' => self::vehicleName($vehicle),

                    'service' => $booking->service ?? null,

                    'appointment_date' => $appointmentDate,
                ];
            })
            ->values();

        /*
         * JSON response.
         */
        return response()->json([
            'success' => true,
            'data' => [
                'figCards' => $figCards,
                'document_awaiting_approval' => $documentAwaitingApproval,
                'active_service_jobs' => $activeServiceJobs,
                'recent_bookings' => $recentBookings,
            ],
        ], 200);
    }

    /**
     * Build a vehicle name from year, make, and model.
     */
    private static function vehicleName($vehicle): ?string
    {
        if (!$vehicle instanceof Vehicle) {
            return null;
        }

        return trim(implode(' ', array_filter([
            $vehicle->year ?? null,
            $vehicle->make ?? null,
            $vehicle->model ?? null,
        ], fn($value) => $value !== null && $value !== '')));
    }


}