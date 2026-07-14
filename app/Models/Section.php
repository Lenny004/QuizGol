<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sección = banco de preguntas de un maestro.
 *
 * Pertenece a una materia, opcionalmente a un grado, y al usuario que la creó.
 * Desde aquí se lanzan salas (quiz o partido).
 */
class Section extends Model
{
    protected $fillable = [
        'subject_id',
        'grade_id',
        'user_id',
        'title',
    ];

    /** Materia de la sección. */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** Grado escolar (puede ser null). */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /** Maestro dueño de la sección. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Preguntas del banco. */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /** Salas que se crearon a partir de esta sección. */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /** Filtra por materia (si $subjectId es null, no filtra). */
    public function scopeForSubject(Builder $query, ?int $subjectId): Builder
    {
        return $subjectId
            ? $query->where('subject_id', $subjectId)
            : $query;
    }

    /** Filtra por grado (si $gradeId es null, no filtra). */
    public function scopeForGrade(Builder $query, ?int $gradeId): Builder
    {
        return $gradeId
            ? $query->where('grade_id', $gradeId)
            : $query;
    }

    /** Nombre legible del grado (o null si no tiene). */
    public function gradeLabel(): ?string
    {
        return $this->grade?->name;
    }
}
