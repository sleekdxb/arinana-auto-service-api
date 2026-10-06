<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VehicleFile extends Model
{
    use HasFactory;

    protected $table = 'vehicle_files';

    protected $fillable = [
        'veh_id',
        'client_id',
        'file_name',
        'file_size',
        'file_type',
        'file_url',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    /**
     * Vehicle relationship
     */
    public function vehicle()
    {
        return $this->belongsTo(
            Vehicle::class,
            'veh_id',
            'veh_id'
        );
    }

    /**
     * Client relationship
     */
    public function client()
    {
        return $this->belongsTo(
            Client::class,
            'client_id',
            'id'
        );
    }

}

