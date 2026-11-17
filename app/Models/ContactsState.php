<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactsState extends Model
{
    protected $table = 'contacts_states';

    public const NEW = 'NEW';
    public const READ = 'READ';
    public const REPLIED = 'REPLIED';
    public const CLOSED = 'CLOSED';

    public const LABELS = [
        self::NEW => 'New',
        self::READ => 'Read',
        self::REPLIED => 'Replied',
        self::CLOSED => 'Closed',
    ];

    protected $fillable = [
        'state_id',
        'con_id',
        'name',
        'code',
        'team_id',
        'note',
    ];
}