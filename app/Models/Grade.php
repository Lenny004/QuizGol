<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Grado escolar (ej. 3° Primaria).
 *
 * Se usa para filtrar secciones y validar que una materia
 * esté disponible en ese grado (tabla pivote subject_grade).
 */
class Grade extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'level_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'level_order' => 'integer',
        ];
    }

    /** Materias disponibles para este grado. */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_grade')->withTimestamps();
    }

    /** Secciones creadas para este grado. */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /** Solo grados activos (visibles en formularios). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Ordena por nivel escolar (1° → 9°) y luego por nombre. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('level_order')->orderBy('name');
    }
}
