<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientFile extends Model
{
    use HasFactory;

    protected $table = 'client_files';

    protected $fillable = [
        'file_id',
        'client_id',
        'file_name',
        'file_size',
        'file_type',
        'file_url',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];
}