<?php

namespace App\Helpers;


use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Booking;
use Carbon\Carbon;
class TeamStatisticHelper
{
    public static function getDashboard(Request $request): JsonResponse
    {
        $bookings = Booking::with([
            'vehicle.files.state',
            'vehicle.state',
            'customer',
        ])
            ->latest()
            ->get();

        /*
         * Normalize bookings into individual vehicle models.
         * Handles both hasOne/belongsTo and hasMany relationships.
         */
        $vehicles = collect();

        foreach ($bookings as $booking) {
            $relatedVehicles = $booking->vehicle;

            if ($relatedVehicles instanceof Model) {
                $vehicles->push($relatedVehicles);
            } elseif ($relatedVehicles instanceof Collection) {
                foreach ($relatedVehicles->flatten() as $vehicle) {
                    if ($vehicle instanceof Model) {
                        $vehicles->push($vehicle);
                    }
                }
            }
        }

        $vehicles = $vehicles
            ->unique(fn($vehicle) => $vehicle->getKey())
            ->values();

        /*
         * Collect files from each individual vehicle model.
         */
        $files = collect();

        foreach ($vehicles as $vehicle) {
            $relatedFiles = $vehicle->files;

            if ($relatedFiles instanceof Collection) {
                $files = $files->concat($relatedFiles->flatten());
            } elseif ($relatedFiles instanceof Model) {
                $files->push($relatedFiles);
            }
        }

        /*
         * Dashboard figures.
         */
        $figCards = [
            // Total bookings across all teams.
            'today_bookings' => $bookings->count(),

            // Unique vehicles associated with bookings.
            'total_vehicles_in_service' => $vehicles->count(),

            // Documents marked pending.
            'total_documents_to_review' => $files
                ->filter(
                    fn($file) =>
                        strtoupper((string) ($file->file_type ?? '')) === 'PENDING'
                )
                ->count(),

            // Vehicles whose state name is COMPLETED.
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
                    $vehicleFiles = $vehicleFiles->flatten();
                } elseif ($vehicleFiles instanceof Model) {
                    $vehicleFiles = collect([$vehicleFiles]);
                } else {
                    $vehicleFiles = collect();
                }

                $docs = $vehicleFiles
                    ->filter(
                        fn($file) =>
                            strtoupper((string) ($file->file_type ?? '')) === 'PENDING'
                    )
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
            ->flatMap(function ($booking) {
                $relatedVehicles = $booking->vehicle;

                if ($relatedVehicles instanceof Model) {
                    $relatedVehicles = collect([$relatedVehicles]);
                } elseif (!($relatedVehicles instanceof Collection)) {
                    $relatedVehicles = collect();
                } else {
                    $relatedVehicles = $relatedVehicles->flatten()
                        ->filter(fn($item) => $item instanceof Model);
                }

                $customer = $booking->customer;

                return $relatedVehicles
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
            ->map(function ($booking) {
                $customer = $booking->customer;
                $relatedVehicles = $booking->vehicle;

                if ($relatedVehicles instanceof Collection) {
                    $vehicle = $relatedVehicles
                        ->flatten()
                        ->first(fn($item) => $item instanceof Model);
                } else {
                    $vehicle = $relatedVehicles instanceof Model
                        ? $relatedVehicles
                        : null;
                }

                $appointmentDate = null;

                if (!empty($booking->date)) {
                    try {
                        $appointmentDate = Carbon::parse(
                            $booking->date . ' ' .
                            ($booking->time ?? '00:00:00')
                        )->format('D, M j; g:i a');
                    } catch (\Throwable $e) {
                        $appointmentDate = null;
                    }
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

        return response()->json([
            'success' => true,
            'data' => [
                'figCards' => $figCards,
                'document_awaititng_approval' => $documentAwaitingApproval,
                'active_service_jobs' => $activeServiceJobs,
                'recent_bookings' => $recentBookings,
            ],
        ], 200);
    }

    private static function vehicleName($vehicle): ?string
    {
        if (!$vehicle instanceof Model) {
            return null;
        }

        return trim(implode(' ', array_filter([
            $vehicle->year ?? null,
            $vehicle->make ?? null,
            $vehicle->model ?? null,
        ], fn($value) => $value !== null && $value !== '')));
    }



}