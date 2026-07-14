<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Materia escolar (ej. Matemáticas, Lenguaje).
 *
 * Cada materia puede estar ligada a varios grados mediante subject_grade.
 */
class Subject extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** Secciones (bancos de preguntas) de esta materia. */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /** Grados en los que se imparte esta materia. */
    public function grades(): BelongsToMany
    {
        return $this->belongsToMany(Grade::class, 'subject_grade')->withTimestamps();
    }

    /** Solo materias activas (visibles en formularios). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Indica si la materia está disponible para el grado dado.
     * Se usa al crear/editar secciones para validar la combinación.
     */
    public function offersGrade(int $gradeId): bool
    {
        return $this->grades()->where('grades.id', $gradeId)->exists();
    }
}
