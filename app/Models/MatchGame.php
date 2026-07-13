<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchGame extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'room_id',
        'home_team_id',
        'away_team_id',
        'turn_team_id',
        'status',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function turnTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'turn_team_id');
    }
}

