<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Jugador dentro de una sala (no es un User autenticado).
 *
 * Se identifica con session_token (cookie quizgol_player).
 * En modo partido tiene team_id; en modo quiz queda null.
 */
class RoomPlayer extends Model
{
    protected $fillable = [
        'room_id',
        'nickname',
        'score',
        'team_id',
        'session_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
        ];
    }

    /** Sala donde está jugando. */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** Equipo (solo mode=match). */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** Historial de respuestas de este jugador en la sala. */
    public function playerAnswers(): HasMany
    {
        return $this->hasMany(PlayerAnswer::class);
    }
}
