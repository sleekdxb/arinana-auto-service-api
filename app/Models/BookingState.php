<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookingState extends Model
{
    use HasFactory;

    protected $table = 'booking_statuses';

    public const PLACED = 'PLACED';
    public const VEHICLE_RECEIVED = 'VEHICLE_RECEIVED';
    public const IN_PROGRESS = 'IN_PROGRESS';
    public const READY_FOR_PICKUP = 'READY_FOR_PICKUP';
    public const COMPLETED = 'COMPLETED';

    public const LABELS = [
        self::PLACED => 'Booked',
        self::VEHICLE_RECEIVED => 'Vehicle Received',
        self::IN_PROGRESS => 'Service in Progress',
        self::READY_FOR_PICKUP => 'Ready for Pickup',
        self::COMPLETED => 'Completed',
    ];

    private const ALIASES = [
        'BOOKED' => self::PLACED,
        'SERVICE_IN_PROGRESS' => self::IN_PROGRESS,
        'INSPECTION_IN_PROGRESS' => self::IN_PROGRESS,
    ];

    /**
     * "Vehicle Received", "vehicle-received" or "VEHICLE_RECEIVED" → "VEHICLE_RECEIVED";
     * a label from LABELS ("Booked", "Service in Progress") → its name.
     */
    public static function normalizeName(?string $name): string
    {
        $name = trim((string) $name);

        foreach (self::LABELS as $code => $label) {
            if (strcasecmp($label, $name) === 0) {
                return $code;
            }
        }

        $code = trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($name)), '_');

        return self::ALIASES[$code] ?? $code;
    }

    protected $fillable = [
        'book_id',
        'team_id',
        'state_id',
        'name',
        'code',
        'note',
    ];




}

