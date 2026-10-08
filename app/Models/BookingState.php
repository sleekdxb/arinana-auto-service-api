<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookingState extends Model
{
    use HasFactory;

    protected $table = 'booking_statuses';

    protected $fillable = [
        'book_id',
        'team_id',
        'state_id',
        'name',
        'code',
        'note',
    ];




}

