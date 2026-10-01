<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientEmail extends Model
{
    protected $table = 'client_emails';

    protected $fillable = [
        'receiver_id',
        'sender_id',
        'mail_id',
        'subject',
        'message',
        'is_sent',
    ];

    protected function casts(): array
    {
        return [
            'is_sent' => 'boolean',
        ];
    }
}
