<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientNotification extends Model
{
    protected $table = 'client_notifications';

    protected $fillable = [
        'notification_id',
        'client_id',
        'notifiable_id',
        'type',
        'subject',
        'data',
        'read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * The shape the apps read: the body lives in data, the booking or vehicle in notifiable_id.
     */
    public function toApi(): array
    {
        $data = $this->data ?? [];

        return [
            'notif_id' => $this->notification_id,
            'type' => $this->type,
            'title' => $this->subject,
            'body' => $data['body'] ?? null,
            'ref_id' => $this->notifiable_id,
            'data' => array_diff_key($data, ['body' => true]) ?: null,
            'read' => $this->read,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
