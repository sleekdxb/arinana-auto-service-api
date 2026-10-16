<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleState extends Model
{
    protected $table = 'vehicle_statuses';

    protected $fillable = [
        'veh_id',
        'team_id',
        'state_id',
        'name',
        'code',
        'note',
    ];
}
