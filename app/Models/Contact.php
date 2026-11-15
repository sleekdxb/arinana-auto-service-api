<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'con_id',
        'name',
        'email',
        'interested',
        'state_id',
        'message',
    ];

    public function currentState()
    {
        return $this->belongsTo(ContactsState::class, 'state_id', 'state_id');
    }

    public function states()
    {
        return $this->hasMany(ContactsState::class, 'con_id', 'con_id')->orderBy('id');
    }
}
