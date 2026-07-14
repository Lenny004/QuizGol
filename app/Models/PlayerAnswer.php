<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Respuesta de un jugador a una pregunta concreta.
 *
 * Hay máximo una por jugador+pregunta (unique en la migración).
 * Guarda si acertó y los puntos otorgados en ese momento.
 */
class PlayerAnswer extends Model
{
    protected $fillable = [
        'room_player_id',
        'question_id',
        'answer_id',
        'is_correct',
        'points_awarded',
        'answered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'points_awarded' => 'integer',
            'answered_at' => 'datetime',
        ];
    }

    /** Jugador que respondió. */
    public function roomPlayer(): BelongsTo
    {
        return $this->belongsTo(RoomPlayer::class);
    }

    /** Pregunta respondida. */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /** Opción elegida (puede ser null si se borró la answer). */
    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class);
    }
}
