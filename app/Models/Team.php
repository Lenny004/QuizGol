<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Equipo dentro de una sala en modo partido.
 *
 * side: "home" (Local) | "away" (Visitante)
 * goals: goles acumulados por aciertos del equipo.
 */
class Team extends Model
{
    public const SIDE_HOME = 'home';

    public const SIDE_AWAY = 'away';

    protected $fillable = [
        'room_id',
        'name',
        'side',
        'goals',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'goals' => 'integer',
        ];
    }

    /** Sala a la que pertenece el equipo. */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** Jugadores asignados a este equipo. */
    public function players(): HasMany
    {
        return $this->hasMany(RoomPlayer::class);
    }
}
