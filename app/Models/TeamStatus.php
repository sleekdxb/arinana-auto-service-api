<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamStatus extends Model
{
    protected $table = 'teams_statuses';

    public const ACTIVE = 'Active';
    public const SUSPENDED = 'Suspended';

    protected $fillable = [
        'team_id',
        'team_setter_id',
        'state_id',
        'name',
        'code',
        'note',
    ];
}
