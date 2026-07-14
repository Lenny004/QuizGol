<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Datos del partido asociados a una Room en mode=match.
 *
 * La tabla se llama "matches" (palabra común en fútbol).
 * El modelo se llama MatchGame para no chocar con la palabra reservada match de PHP.
 *
 * turn_team_id queda preparado para turnos futuros; el MVP no lo usa aún.
 */
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

    /** Sala del partido. */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** Equipo local. */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /** Equipo visitante. */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    /** Equipo al que le toca el turno (reservado para futuras reglas). */
    public function turnTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'turn_team_id');
    }
}
