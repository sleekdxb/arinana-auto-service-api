<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VehicleFileState extends Model
{
    use HasFactory;

    protected $table = 'vehicle_file_states';

    public $timestamps = false;

    protected $fillable = [
        'file_id',
        'team_id',
        'state',
        'code',
        'create_at',
        'update_at',
    ];

    protected $casts = [
        'create_at' => 'datetime',
        'update_at' => 'datetime',
    ];

    /**
     * Vehicle file relationship
     */
    public function file()
    {
        return $this->belongsTo(
            VehicleFile::class,
            'file_id',
            'id'
        );
    }
}

