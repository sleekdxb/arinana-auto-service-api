<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientStatus extends Model
{
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