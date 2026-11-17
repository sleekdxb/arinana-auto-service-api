<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $table = 'services';

    protected $fillable = [
        'ser_id',
        'name',
        'type'
    ];


    protected function casts(): array
    {
        return [
            'type' => 'array',
        ];
    }
}
