<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientPreference extends Model
{
    protected $table = 'client_preferences';

    public const TOGGLES = [
        'push_enabled',
        'email_enabled',
        'sms_enabled',
        'booking_updates',
        'service_status_updates',
        'service_completion',
        'document_review',
    ];

    public const DEFAULTS = [
        'push_enabled' => true,
        'email_enabled' => true,
        'sms_enabled' => false,
        'booking_updates' => true,
        'service_status_updates' => true,
        'service_completion' => true,
        'document_review' => true,
    ];

    protected $fillable = [
        'client_id',
        ...self::TOGGLES,
    ];

    protected $casts = [
        'push_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'booking_updates' => 'boolean',
        'service_status_updates' => 'boolean',
        'service_completion' => 'boolean',
        'document_review' => 'boolean',
    ];
}
