<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $fillable = [
        'subject_id',
        'grade_id',
        'user_id',
        'title',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function scopeForSubject(Builder $query, ?int $subjectId): Builder
    {
        return $subjectId
            ? $query->where('subject_id', $subjectId)
            : $query;
    }

    public function scopeForGrade(Builder $query, ?int $gradeId): Builder
    {
        return $gradeId
            ? $query->where('grade_id', $gradeId)
            : $query;
    }

    public function gradeLabel(): ?string
    {
        return $this->grade?->name;
    }
}
