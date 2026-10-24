<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactsState extends Model
{
    protected $table = 'contacts_states';

    protected $fillable = [
        'state_id',
        'con_id',
        'name',
        'code',
        'team_id',
    ];
}