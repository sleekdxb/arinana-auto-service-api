<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Vehicle extends Model
{
    use HasFactory;

    protected $table = 'vehicles';

    protected $fillable = [
        'veh_id',
        'client_id',
        'make',
        'model',
        'year',
        'vin',
        'plate_number',
        'mileage',
        'color',
    ];

    protected $casts = [
        'year' => 'integer',
        'mileage' => 'integer',
    ];

    public function files()
    {
        return $this->hasMany(VehicleFile::class, 'veh_id', 'veh_id'); // veh_id is the foreign key
    }
    public function state()
    {
        return $this->belongsTo(VehicleState::class, 'state_id', 'state_id'); // state_id is the foreign key
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}

