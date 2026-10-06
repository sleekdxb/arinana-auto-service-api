<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookingFile extends Model
{
    use HasFactory;

    protected $table = 'booking_files';

    protected $fillable = [
        'book_id',
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
     * Booking relationship
     */
    public function booking()
    {
        return $this->belongsTo(
            Booking::class,
            'book_id',
            'book_id'
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

