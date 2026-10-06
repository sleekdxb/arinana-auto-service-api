<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
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


    public static function createEmail(array $data)
    {
        return self::create([
            'mail_id' => self::generateMailId(),
            'sender_id' => $data['sender_id'] ?? null,
            'receiver_id' => $data['receiver_id'] ?? null,
            'subject' => $data['subject'] ?? '',
            'message' => $data['message'] ?? '',
            'is_sent' => $data['is_sent'] ?? false,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Generate Mail ID
    |--------------------------------------------------------------------------
    */

    private static function generateMailId()
    {
        return 'EMAIL_' . strtoupper(Str::random(15));
    }


    protected function casts(): array
    {
        return [
            'is_sent' => 'boolean',
        ];
    }
}
