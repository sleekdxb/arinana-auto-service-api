<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Booking extends Model
{
    use HasFactory;
    protected $table = 'bookings';
    protected $fillable = [
        'client_id',
        'book_id',
        'vehicle_ids',
        'booking_ref',
        'service',
        'service_type',
        'date',
        'time',
        'note',
        'state_id',
    ];
    protected $casts = [
        'date' => 'date',
        'time' => 'datetime:H:i',
    ];


    public function files()
    {
        return $this->hasMany(BookingFile::class, 'book_id', 'book_id'); // book_id is the foreign key
    }
    public function state()
    {
        return $this->belongsTo(BookingState::class, 'state_id', 'state_id'); // state_id is the foreign key
    }


    public function vehicle()
    {
        return $this->hasMany(Vehicle::class, 'veh_id', 'veh_id'); // veh_id is the foreign key
    }
}
