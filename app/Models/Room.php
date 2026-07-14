<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Sala de juego en vivo.
 *
 * Estados (status): lobby → active → finished
 * Modos (mode): quiz (individual) | match (2 equipos)
 *
 * El código corto (code) es lo que escriben los alumnos al unirse.
 */
class Room extends Model
{
    /** Estados posibles de la sala. */
    public const STATUS_LOBBY = 'lobby';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FINISHED = 'finished';

    /** Modos de juego. */
    public const MODE_QUIZ = 'quiz';

    public const MODE_MATCH = 'match';

    protected $fillable = [
        'code',
        'mode',
        'status',
        'host_id',
        'section_id',
        'current_question_id',
        'question_started_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'question_started_at' => 'datetime',
        ];
    }

    /** Maestro anfitrión (proyecta la pantalla host). */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    /** Sección (banco de preguntas) de esta sala. */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** Pregunta que se está mostrando ahora (null en lobby/finished). */
    public function currentQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'current_question_id');
    }

    /** Jugadores unidos a la sala. */
    public function players(): HasMany
    {
        return $this->hasMany(RoomPlayer::class);
    }

    /** Equipos (solo en mode=match: Local y Visitante). */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * Datos del partido (marcador, equipos).
     * Se llama matchGame (no match) para no chocar con la palabra reservada SQL.
     */
    public function matchGame(): HasOne
    {
        return $this->hasOne(MatchGame::class);
    }

    public function isLobby(): bool
    {
        return $this->status === self::STATUS_LOBBY;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_FINISHED;
    }

    public function isMatchMode(): bool
    {
        return $this->mode === self::MODE_MATCH;
    }

    /**
     * Genera un código corto único para unirse a la sala (ej. "A3K9").
     * Omite caracteres confusos: I, O, 0, 1.
     */
    public static function generateUniqueCode(int $length = 4): string
    {
        $allowedCharacters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($index = 0; $index < $length; $index++) {
                $code .= $allowedCharacters[random_int(0, strlen($allowedCharacters) - 1)];
            }
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }
}
