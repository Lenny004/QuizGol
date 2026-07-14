<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    public const DIFFICULTIES = [
        'easy' => 'Fácil',
        'medium' => 'Media',
        'hard' => 'Difícil',
    ];

    protected $fillable = [
        'section_id',
        'prompt',
        'image_path',
        'type',
        'difficulty',
        'time_limit',
        'points',
        'sort_order',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function difficultyLabel(): ?string
    {
        if (! $this->difficulty) {
            return null;
        }

        return self::DIFFICULTIES[$this->difficulty] ?? $this->difficulty;
    }
}
