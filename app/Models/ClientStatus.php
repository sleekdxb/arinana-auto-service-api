<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ClientStatus extends Model
{
    use HasFactory;

    protected $table = 'clients_statuses';

    protected $fillable = [
        'client_id',
        'team_id',
        'state_id',
        'name',
        'code',
        'note',
    ];




}

