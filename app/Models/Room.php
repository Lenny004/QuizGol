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
 * Fase de pregunta (question_phase): asking → reveal
 *
 * El código corto (code) es lo que escriben los alumnos al unirse.
 */
class Room extends Model
{
    public const STATUS_LOBBY = 'lobby';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FINISHED = 'finished';

    public const MODE_QUIZ = 'quiz';

    public const MODE_MATCH = 'match';

    public const PHASE_ASKING = 'asking';

    public const PHASE_REVEAL = 'reveal';

    /** Segundos que dura la revelación colectiva antes del auto-avance. */
    public const REVEAL_DURATION_SECONDS = 8;

    protected $fillable = [
        'code',
        'mode',
        'status',
        'host_id',
        'section_id',
        'current_question_id',
        'question_phase',
        'question_started_at',
        'reveal_started_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'question_started_at' => 'datetime',
            'reveal_started_at' => 'datetime',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function currentQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'current_question_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(RoomPlayer::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

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

    public function isAsking(): bool
    {
        return $this->question_phase === self::PHASE_ASKING;
    }

    public function isRevealing(): bool
    {
        return $this->question_phase === self::PHASE_REVEAL;
    }

    /**
     * Segundos transcurridos desde que arrancó la pregunta actual.
     */
    public function questionElapsedSeconds(): int
    {
        if (! $this->question_started_at) {
            return 0;
        }

        return max(0, (int) $this->question_started_at->diffInSeconds(now()));
    }

    /**
     * True si el tiempo de la pregunta actual ya se agotó.
     */
    public function isQuestionTimedOut(): bool
    {
        if (! $this->isAsking() || ! $this->currentQuestion) {
            return false;
        }

        $limit = max(1, (int) ($this->currentQuestion->time_limit ?: 30));

        return $this->questionElapsedSeconds() >= $limit;
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
