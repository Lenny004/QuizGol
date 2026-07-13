<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'answered_at' => 'datetime',
        ];
    }

    public function roomPlayer(): BelongsTo
    {
        return $this->belongsTo(RoomPlayer::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class);
    }
}

